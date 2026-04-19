#!/usr/bin/env python3
"""
gerarHorariosCache.py
=====================
1. Conecta ao Gmail via IMAP com login + senha de app.
2. Busca o e-mail mais recente com assunto "Horário SENAI".
3. Se for novo (id diferente do último processado), baixa o anexo
   .xlsm e salva como horarios.xlsm.
4. Roda o parsing e gera horariosCache.json.
5. Se o e-mail já foi processado, encerra sem fazer nada.

Sem dependências externas — usa apenas bibliotecas padrão do Python.

Configuração:
    Edite as constantes GMAIL_EMAIL e GMAIL_SENHA abaixo.
    GMAIL_SENHA deve ser uma "Senha de app" gerada em:
    myaccount.google.com → Segurança → Senhas de app

Cron (a cada 30 minutos):
    */30 * * * * /usr/bin/python3 /var/www/html/scripts/gerarHorariosCache.py \
        >> /var/www/html/logs/horarios_cache.log 2>&1
"""

import argparse, datetime, email, imaplib, json, logging, os, sys, traceback

# ── Configurações ──────────────────────────────────────────────────────────
GMAIL_EMAIL       = 'orion.sspa@gmail.com'
GMAIL_SENHA       = 'apcx yexr fpze yzes'   # 16 chars gerada no Google
ASSUNTO_BUSCA     = 'HorarioSENAI'               # busca parcial, case-insensitive
ARQUIVO_EXCEL     = '/var/www/html/data/horarios.xlsm'
ARQUIVO_CACHE     = '/var/www/html/data/horariosCache.json'
ARQUIVO_LOCK      = '/tmp/gerarHorariosCache.lock'
ARQUIVO_ULTIMO_ID = '/var/www/html/data/gmail_ultimo_id.txt'
ABAS_IGNORAR      = {'PAINEL', 'MODELO2', 'MODELO', 'testesesi'}
# ──────────────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%d/%m/%Y %H:%M:%S',
)
log = logging.getLogger(__name__)


# ═══════════════════════════════════════════════════════════════════════════
# GMAIL VIA IMAP
# ═══════════════════════════════════════════════════════════════════════════

def conectar_imap() -> imaplib.IMAP4_SSL:
    """Conecta e autentica no Gmail via IMAP."""
    log.info("Conectando ao Gmail IMAP (%s)...", GMAIL_EMAIL)
    try:
        imap = imaplib.IMAP4_SSL('imap.gmail.com', 993)
        imap.login(GMAIL_EMAIL, GMAIL_SENHA)
        log.info("Autenticado com sucesso.")
        return imap
    except imaplib.IMAP4.error as e:
        log.error("Falha na autenticação IMAP: %s", e)
        log.error("Verifique se usou uma Senha de App (não a senha normal do Gmail).")
        sys.exit(1)


def buscar_email_mais_recente(imap: imaplib.IMAP4_SSL) -> dict | None:
    """
    Busca na caixa de entrada o e-mail mais recente cujo assunto
    contém ASSUNTO_BUSCA (case-insensitive).
    Retorna {uid, subject, date} ou None.
    """
    imap.select('INBOX', readonly=True)

    # IMAP SEARCH não suporta case-insensitive nativamente,
    # então busca por substring — o servidor Gmail aceita SUBJECT parcial
    assunto_ascii = ASSUNTO_BUSCA.encode('ascii', errors='replace').decode()
    status, data  = imap.uid('search', None, f'SUBJECT "{assunto_ascii}"')

    if status != 'OK' or not data[0]:
        log.info("Nenhum e-mail encontrado com assunto '%s'.", ASSUNTO_BUSCA)
        return None

    uids = data[0].split()
    uid  = uids[-1].decode()  # mais recente = último da lista

    # Busca só os headers para economizar banda
    status, msg_data = imap.uid('fetch', uid, '(BODY.PEEK[HEADER.FIELDS (SUBJECT DATE)])')
    if status != 'OK':
        return None

    raw_header = msg_data[0][1]
    msg        = email.message_from_bytes(raw_header)

    # Decodifica assunto (pode estar em UTF-8 ou latin-1 codificado)
    subject_raw = msg.get('Subject', '')
    subject     = _decodificar_header(subject_raw)
    date_str    = msg.get('Date', '')

    return {'uid': uid, 'subject': subject, 'date': date_str}


def _decodificar_header(valor: str) -> str:
    """Decodifica headers MIME que podem estar em base64/quoted-printable."""
    from email.header import decode_header
    partes = decode_header(valor)
    resultado = []
    for parte, charset in partes:
        if isinstance(parte, bytes):
            resultado.append(parte.decode(charset or 'utf-8', errors='replace'))
        else:
            resultado.append(parte)
    return ''.join(resultado)


def ler_ultimo_uid() -> str:
    return open(ARQUIVO_ULTIMO_ID).read().strip() if os.path.exists(ARQUIVO_ULTIMO_ID) else ''


def salvar_ultimo_uid(uid: str):
    os.makedirs(os.path.dirname(ARQUIVO_ULTIMO_ID), exist_ok=True)
    with open(ARQUIVO_ULTIMO_ID, 'w') as f:
        f.write(uid)


def baixar_anexo(imap: imaplib.IMAP4_SSL, uid: str) -> bool:
    """
    Baixa o primeiro anexo .xlsm/.xlsx do e-mail e salva como ARQUIVO_EXCEL.
    Retorna True se bem-sucedido.
    """
    status, msg_data = imap.uid('fetch', uid, '(RFC822)')
    if status != 'OK':
        log.error("Falha ao buscar e-mail uid=%s.", uid)
        return False

    raw  = msg_data[0][1]
    msg  = email.message_from_bytes(raw)

    for parte in msg.walk():
        content_disp = parte.get('Content-Disposition', '')
        nome         = parte.get_filename()

        if not nome:
            continue
        # Decodifica nome do arquivo se necessário
        nome = _decodificar_header(nome)
        if not nome.lower().endswith(('.xlsm', '.xlsx')):
            continue

        payload = parte.get_payload(decode=True)
        if not payload:
            log.warning("Anexo '%s' sem conteúdo.", nome)
            continue

        os.makedirs(os.path.dirname(ARQUIVO_EXCEL), exist_ok=True)
        tmp = ARQUIVO_EXCEL + '.tmp'
        with open(tmp, 'wb') as f:
            f.write(payload)
        os.replace(tmp, ARQUIVO_EXCEL)
        log.info("Anexo '%s' salvo em %s (%d bytes)", nome, ARQUIVO_EXCEL, len(payload))
        return True

    log.warning("Nenhum anexo .xlsm/.xlsx encontrado no e-mail uid=%s.", uid)
    return False


# ═══════════════════════════════════════════════════════════════════════════
# PARSING DO EXCEL
# ═══════════════════════════════════════════════════════════════════════════

def limpar(valor):
    if valor is None: return None
    s = str(valor).strip()
    return s if s else None


def linha_vazia(row, cols=(1, 6)):
    return all(row[c] is None for c in range(cols[0], cols[1] + 1))


def extrair_instrutor_padrao_b(texto: str):
    if not texto or ' - ' not in texto:
        return None, None
    partes = texto.rsplit(' - ', 1)
    if len(partes) != 2:
        return None, None
    uc, inst = partes[0].strip(), partes[1].strip()
    if not inst or any(c.isdigit() for c in inst) or len(inst.split()) > 3:
        return None, None
    return uc, inst


def detectar_padrao_b(rows_conteudo):
    import datetime as dt
    for r in rows_conteudo[:3]:
        if isinstance(r[0], dt.time):
            return True
    return False


def processar_aba(ws, nome_aba):
    import datetime as dt
    rows = list(ws.iter_rows(min_row=19, max_col=8, values_only=True))

    nome_turma = None
    codigo     = None
    for r in rows[:4]:
        if r[1] == 'NOME DA TURMA': nome_turma = limpar(r[4])
        if r[1] == 'CÓDIGO':        codigo     = limpar(r[2])

    if not codigo:
        log.warning("Aba '%s': sem código — ignorada.", nome_aba)
        return []

    registros = []
    i = 3

    while i < len(rows):
        r_header = rows[i]
        if not isinstance(r_header[0], (int, float)):
            i += 1
            continue
        if i + 1 >= len(rows):
            break

        r_datas  = rows[i + 1]
        conteudo = []
        j = i + 2
        while j < len(rows):
            r = rows[j]
            if linha_vazia(r): break
            if isinstance(r[0], (int, float)) and r[0] != r_header[0]: break
            conteudo.append(r)
            j += 1

        if not conteudo:
            i = j + 1
            continue

        if detectar_padrao_b(conteudo):
            primeiro_col = None
            for col in range(1, 7):
                if isinstance(r_datas[col], dt.datetime):
                    primeiro_col = col
                    break
            if primeiro_col is None:
                i = j + 1
                continue
            vistos = set()
            for r in conteudo:
                for col in range(primeiro_col, 7):
                    data_val = r_datas[col]
                    celula   = r[col]
                    if not isinstance(data_val, dt.datetime): continue
                    if not celula or not isinstance(celula, str): continue
                    uc, inst = extrair_instrutor_padrao_b(celula)
                    if not inst: continue
                    data_iso = data_val.strftime('%Y-%m-%d')
                    chave = (data_iso, col)
                    if chave in vistos: continue
                    vistos.add(chave)
                    registros.append({
                        'data_iso': data_iso, 'codigo': codigo,
                        'instrutor': inst, 'uc': uc, 'nome_turma': nome_turma,
                    })
        else:
            r_instrutor = conteudo[-1]
            r_uc        = conteudo[0]
            for col in range(1, 7):
                data_val = r_datas[col]
                inst_val = r_instrutor[col]
                uc_val   = r_uc[col]
                if not isinstance(data_val, dt.datetime): continue
                if not inst_val: continue
                registros.append({
                    'data_iso': data_val.strftime('%Y-%m-%d'), 'codigo': codigo,
                    'instrutor': limpar(inst_val), 'uc': limpar(uc_val),
                    'nome_turma': nome_turma,
                })

        i = j + 1
    return registros


def gerar_cache(arquivo_excel: str) -> bool:
    try:
        from openpyxl import load_workbook
    except ImportError:
        log.error("Instale: pip install openpyxl --break-system-packages")
        return False

    if not os.path.exists(arquivo_excel):
        log.error("Arquivo não encontrado: %s", arquivo_excel)
        return False

    log.info("Lendo Excel: %s", arquivo_excel)
    wb    = load_workbook(arquivo_excel, read_only=True, data_only=True)
    abas  = [s for s in wb.sheetnames if s not in ABAS_IGNORAR]
    log.info("%d abas encontradas.", len(abas))

    dados = {}
    total = 0
    erros = 0

    for nome_aba in abas:
        try:
            regs = processar_aba(wb[nome_aba], nome_aba)
            for r in regs:
                d, c = r['data_iso'], r['codigo']
                if d not in dados: dados[d] = {}
                if c not in dados[d]:
                    dados[d][c] = {
                        'instrutor'  : r['instrutor'],
                        'uc'         : r['uc'],
                        'nome_turma' : r['nome_turma'],
                    }
                    total += 1
        except Exception:
            log.error("Erro na aba '%s':\n%s", nome_aba, traceback.format_exc())
            erros += 1

    wb.close()
    os.makedirs(os.path.dirname(ARQUIVO_CACHE), exist_ok=True)

    cache = {
        'gerado_em'       : datetime.datetime.now().strftime('%Y-%m-%dT%H:%M:%S'),
        'fonte'           : arquivo_excel,
        'total_registros' : total,
        'total_datas'     : len(dados),
        'erros_abas'      : erros,
        'dados'           : dados,
    }

    tmp = ARQUIVO_CACHE + '.tmp'
    with open(tmp, 'w', encoding='utf-8') as f:
        json.dump(cache, f, ensure_ascii=False, indent=2)
    os.replace(tmp, ARQUIVO_CACHE)
    log.info("Cache gerado: %d registros em %d datas (%d erros).", total, len(dados), erros)
    return True


# ═══════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════

def main(forcar=False):
    if os.path.exists(ARQUIVO_LOCK):
        log.warning("Lock encontrado — outra instância em execução. Saindo.")
        return

    try:
        open(ARQUIVO_LOCK, 'w').close()

        # Etapa 1: conecta e busca e-mail
        imap  = conectar_imap()
        email_info = buscar_email_mais_recente(imap)

        if not email_info:
            log.info("Nenhum e-mail com assunto '%s'. Encerrando.", ASSUNTO_BUSCA)
            imap.logout()
            return

        log.info("E-mail encontrado: uid=%s | '%s' | %s",
                 email_info['uid'], email_info['subject'], email_info['date'])

        ultimo_uid = ler_ultimo_uid()
        if not forcar and email_info['uid'] == ultimo_uid:
            log.info("E-mail já processado (uid=%s). Nada a fazer.", email_info['uid'])
            imap.logout()
            return

        # Etapa 2: baixa o anexo
        log.info("Novo e-mail detectado — baixando anexo...")
        sucesso = baixar_anexo(imap, email_info['uid'])
        imap.logout()

        if not sucesso:
            log.error("Falha no download do anexo. Cache não atualizado.")
            return

        # Etapa 3: gera o cache
        log.info("Gerando cache de horários...")
        if gerar_cache(ARQUIVO_EXCEL):
            salvar_ultimo_uid(email_info['uid'])
            log.info("Concluído com sucesso. uid salvo: %s", email_info['uid'])
        else:
            log.error("Falha ao gerar cache.")

    finally:
        if os.path.exists(ARQUIVO_LOCK):
            os.remove(ARQUIVO_LOCK)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='Atualiza cache de horários via Gmail IMAP')
    parser.add_argument('--forcar', action='store_true',
                        help='Reprocessa mesmo que o e-mail já tenha sido lido')
    args = parser.parse_args()
    main(forcar=args.forcar)