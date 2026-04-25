<?php
// Garante fuso horário correto em todas as operações de data
date_default_timezone_set('America/Sao_Paulo');

// Cache de horários dos instrutores (gerado pelo script Python)
require_once __DIR__ . '/horariosHelper.php';

/*
 * ocupacaoHelper.php
 * Lógica central de ocupação compartilhada entre painel.php e painel_logado.php.
 *
 * Dias da semana — bitmask (TINYINT):
 *   Seg=1  Ter=2  Qua=4  Qui=8  Sex=16  Sáb=32
 *   Exemplos: Seg-Sex=31  SóSáb=32  Ter/Qui=10  Todos=63
 *
 * Verificação: ($diasSemana & $valorDia) > 0
 */

/* ── Mapa dia da semana → bit ── */
const DIAS_BIT = [
    'seg' => 1,
    'ter' => 2,
    'qua' => 4,
    'qui' => 8,
    'sex' => 16,
    'sab' => 32,
];

/* Mapa date('w') → chave do bit (0=Dom, 1=Seg ... 6=Sáb) */
const DOW_MAP = [
    0 => null,   // Domingo — sem bit definido
    1 => 'seg',
    2 => 'ter',
    3 => 'qua',
    4 => 'qui',
    5 => 'sex',
    6 => 'sab',
];

/* ─────────────────────────────────────────────────────
   Retorna o bit do dia para uma data 'Y-m-d'.
   Retorna 0 se for domingo (sem aulas normalmente).
   ───────────────────────────────────────────────────── */
function bitDoDia(string $data): int {
    $dow = (int) date('w', strtotime($data));
    $chave = DOW_MAP[$dow] ?? null;
    if($chave === null) return 0;
    return DIAS_BIT[$chave];
}

/* ─────────────────────────────────────────────────────
   Verifica se a turma ocorre no dia (operação de bit).
   $diasSemana: valor TINYINT do banco
   $bit:        resultado de bitDoDia()
   ───────────────────────────────────────────────────── */
function turmaOcorreNoDia(int $diasSemana, int $bit): bool {
    if($bit === 0) return false;          // Domingo → nunca
    return ($diasSemana & $bit) > 0;     // Teste de bit
}

/* ─────────────────────────────────────────────────────
   Verifica sobreposição de dois intervalos de horário.
   ───────────────────────────────────────────────────── */
function sobreposicao(string $ini1, string $fim1, string $ini2, string $fim2): bool {
    return $ini1 < $fim2 && $fim1 > $ini2;
}

/* ─────────────────────────────────────────────────────
   Calcula a ocupação de todos os ambientes × turnos
   para uma data específica.

   Retorna: [idLaboratorio][turno] => [
       'status'      => 'ocupado'|'aguardando'|'na-sala'|'livre'|'vazio'
       'turma'       => string
       'sub'         => string
       'solicitante' => string
   ]
   ───────────────────────────────────────────────────── */
/* ─────────────────────────────────────────────────────
   Busca o nome completo de um instrutor no banco
   comparando o primeiro nome da planilha com usuarios.
   Ex: planilha="Patrick" → banco="Patrick Souza Lima"
   ───────────────────────────────────────────────────── */
function nomeCompletoInstrutor(PDO $pdo, string $nomeNaPlanilha): string {
    static $cache = [];
    $token = mb_strtolower(trim($nomeNaPlanilha));
    if(isset($cache[$token])) return $cache[$token];

    /*
     * Estratégia de busca (ordem de prioridade):
     *
     * 1. Primeiro nome exato: LOWER(SUBSTRING_INDEX(nome,' ',1)) = 'patrick'
     *    → cobre 'Patrick', 'Patrick Souza Lima'
     *
     * 2. Qualquer token do nome: LOWER(nome) LIKE '% pompeu %' ou
     *    LOWER(nome) LIKE 'pompeu %' ou LOWER(nome) LIKE '% pompeu'
     *    → cobre sobrenome isolado ('POMPEU' → 'José Carlos Pompeu')
     *
     * 3. Fallback: retorna o token em maiúsculas
     *
     * Filtro: perfil = 'Instrutor' em todas as buscas
     * (evita conflito com professores de mesmo nome)
     */

    // Tentativa 1 — primeiro nome exato
    $stmt = $pdo->prepare("
        SELECT nome FROM usuarios
        WHERE perfil = 'Instrutor'
          AND LOWER(SUBSTRING_INDEX(nome, ' ', 1)) = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$row){
        // Tentativa 2 — token em qualquer posição do nome (sobrenome/apelido)
        $like = '%' . $token . '%';
        $stmt2 = $pdo->prepare("
            SELECT nome FROM usuarios
            WHERE perfil = 'Instrutor'
              AND LOWER(nome) LIKE ?
            LIMIT 1
        ");
        $stmt2->execute([$like]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    $resultado = $row ? mb_strtoupper($row['nome']) : mb_strtoupper($nomeNaPlanilha);
    $cache[$token] = $resultado;
    return $resultado;
}

function calcularOcupacao(
    PDO    $pdo,
    string $data,
    array  $ambientes,
    array  $turnos
): array {

    $bit = bitDoDia($data);   // bit do dia da semana desta data

    /* ── Reservas aprovadas/aguardando do dia ── */
    $stmtR = $pdo->prepare("
        SELECT r.laboratorio, r.horarioInicio, r.horarioFim,
               r.turma, r.aprovado, r.solicitante AS solicitanteId,
               u.nome AS solicitante, u.registro AS solicitanteRegistro
        FROM reservas r
        JOIN usuarios u ON u.id = r.solicitante
        WHERE r.data = ? AND r.aprovado IN (0,1)
    ");
    $stmtR->execute([$data]);
    $reservas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Confirmação de presença via IoT — lê do iot_estado.json (sem banco).
     *
     * Fluxo:
     * 1. Lê JSON de estado do mqttMonitor (histórico de acessos por porta)
     * 2. Cruza MAC da porta com laboratorios.macPorta → sabe qual lab é qual porta
     * 3. Para cada reserva aprovada: verifica se o crachá do solicitante
     *    aparece no histórico da porta dentro da janela [início-15min, ∞]
     * 4. Cruza crachá → registro via usuarios.cracha (sem abrir cadastroiot)
     *
     * Nota: usuarios.cracha deve conter o código do crachá — se não existir
     * este campo, o cruzamento retorna vazio e o status fica 'ocupado' normal.
     */
    $acessosIoT = []; // [idLaboratorio] => [{cracha, data_hora}, ...]
    try {
        static $estadoIoT = null;
        if($estadoIoT === null){
            $arq = '/var/www/html/data/iot_estado.json';
            $estadoIoT = file_exists($arq)
                ? (json_decode(file_get_contents($arq), true) ?: [])
                : [];
        }

        // Mapa MAC → idLaboratorio
        $macParaLab = [];
        $stmMac = $pdo->query("
            SELECT idLaboratorio, macPorta FROM laboratorios
            WHERE macPorta IS NOT NULL AND macPorta != ''
        ");
        foreach($stmMac->fetchAll(PDO::FETCH_ASSOC) as $m){
            $macParaLab[strtoupper($m['macPorta'])] = $m['idLaboratorio'];
        }

        // Percorre dispositivos do JSON e coleta histórico de acessos
        foreach($estadoIoT as $disp){
            $mac = strtoupper($disp['mac'] ?? '');
            if(!$mac || !isset($macParaLab[$mac])) continue;
            $idLab    = $macParaLab[$mac];
            $historico = $disp['historico_acessos'] ?? [];
            if($disp['ultimo_acesso'] ?? null){
                // Garante que último acesso está incluído
                array_unshift($historico, $disp['ultimo_acesso']);
            }
            foreach($historico as $ac){
                if(isset($ac['cracha']) && isset($ac['data_hora'])){
                    if(!isset($acessosIoT[$idLab])) $acessosIoT[$idLab] = [];
                    $acessosIoT[$idLab][] = [
                        'cracha'    => $ac['cracha'],
                        'data_hora' => $ac['data_hora'],
                    ];
                }
            }
        }
    } catch(Exception $e){
        $acessosIoT = [];
    }

    // Mapa crachá → registro (campo cracha na tabela usuarios, se existir)
    $crachaParaRegistro = [];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'cracha'")->fetchAll();
        if(!empty($cols)){
            foreach($pdo->query("SELECT cracha, registro FROM usuarios WHERE cracha IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $u){
                if($u['cracha'] && $u['registro'])
                    $crachaParaRegistro[$u['cracha']] = $u['registro'];
            }
        }
    } catch(Exception $e){}

    /* ── Vínculos turma→sala ── */
    $stmtV = $pdo->prepare("SELECT * FROM turma_sala");
    $stmtV->execute();
    $vinculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    /*
     * vincIdxSala[idSala][turno][] = [codigoTurma, diasSemana]
     * Múltiplos vínculos por célula (dias diferentes).
     */
    $vincIdxSala = [];
    foreach($vinculos as $v){
        $vincIdxSala[$v['idSala']][$v['turno']][] = [
            'codigoTurma' => $v['codigoTurma'],
            'diasSemana'  => (int)($v['diasSemana'] ?? 31),
        ];
    }

    /*
     * turmaReservas[codigoTurma] = [ reserva, ... ]
     * Para verificar se a turma está em laboratório.
     */
    $turmaReservas = [];
    foreach($reservas as $r){
        $t = $r['turma'] ?? '';
        if($t === '') continue;
        $turmaReservas[$t][] = $r;
    }

    /* ── Calcula status por ambiente × turno ── */
    $resultado = [];

    foreach($ambientes as $amb){
        $id = $amb['idLaboratorio'];
        $resultado[$id] = [];

        foreach($turnos as $turnoKey => $turno){
            $tIni = $turno['inicio'];
            $tFim = $turno['fim'];

            /* ══════════════════════════════════════════════
               LABORATÓRIO — reserva direta no banco
               Exibe solicitante do banco (nome completo já
               está em usuarios.nome). Sem planilha aqui.
               ══════════════════════════════════════════════ */
            if($amb['temReserva'] && !$amb['temSala']){
                $reservaDireta = null;
                foreach($reservas as $r){
                    if($r['laboratorio'] == $id
                        && sobreposicao($r['horarioInicio'], $r['horarioFim'], $tIni, $tFim)){
                        $reservaDireta = $r;
                        break;
                    }
                }

                if($reservaDireta){
                    $aprovado  = $reservaDireta['aprovado'] == 1;
                    $registro  = $reservaDireta['solicitanteRegistro'] ?? '';

                    // Verifica confirmação IoT:
                    // Crachá do solicitante acessou a porta dentro da janela
                    // [início da reserva - 15min, sem limite de fim]
                    $confirmadoIoT = false;
                    if($aprovado && $registro && isset($acessosIoT[$id])){
                        $inicioReserva = strtotime($data.' '.$reservaDireta['horarioInicio']);
                        $janelaInicio  = $inicioReserva - (15 * 60); // 15min antes
                        foreach($acessosIoT[$id] as $ac){
                            // Cruza crachá → registro
                            $regAcesso = $crachaParaRegistro[$ac['cracha']] ?? null;
                            if($regAcesso !== $registro) continue;
                            // Verifica janela de tempo
                            $tsAcesso = strtotime($ac['data_hora']);
                            if($tsAcesso >= $janelaInicio){
                                $confirmadoIoT = true;
                                break;
                            }
                        }
                    }
                    $resultado[$id][$turnoKey] = [
                        'status'      => $confirmadoIoT ? 'confirmado'
                                       : ($aprovado ? 'ocupado' : 'aguardando'),
                        'turma'       => $reservaDireta['turma'] ?? '',
                        'sub'         => $confirmadoIoT ? 'Confirmado ✓'
                                       : ($aprovado ? 'Reservado' : 'Aguardando'),
                        'solicitante' => mb_strtoupper($reservaDireta['solicitante'] ?? ''),
                    ];
                } else {
                    $resultado[$id][$turnoKey] = [
                        'status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''
                    ];
                }
                continue;
            }

            /* ══════════════════════════════════════════════
               SALA DE AULA — preenche da planilha
               Busca vínculo turma→sala para este dia/turno,
               depois pega o instrutor do cache de horários.
               ══════════════════════════════════════════════ */
            if($amb['temSala'] && isset($vincIdxSala[$id][$turnoKey])){

                // Encontra o vínculo que ocorre neste dia da semana
                $vincAtivo = null;
                foreach($vincIdxSala[$id][$turnoKey] as $v){
                    if(turmaOcorreNoDia($v['diasSemana'], $bit)){
                        $vincAtivo = $v;
                        break;
                    }
                }

                if(!$vincAtivo){
                    $resultado[$id][$turnoKey] = [
                        'status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''
                    ];
                    continue;
                }

                $codTurma = $vincAtivo['codigoTurma'];

                /* Verifica se a turma está no laboratório neste turno */
                $turmaNoLab = false;
                if(isset($turmaReservas[$codTurma])){
                    foreach($turmaReservas[$codTurma] as $r){
                        if(sobreposicao($r['horarioInicio'], $r['horarioFim'], $tIni, $tFim)){
                            $turmaNoLab = true;
                            break;
                        }
                    }
                }

                if($turmaNoLab){
                    $resultado[$id][$turnoKey] = [
                        'status'      => 'livre',
                        'turma'       => 'Livre',
                        'sub'         => "{$codTurma} está no laboratório",
                        'solicitante' => '',
                    ];
                    continue;
                }

                // Turma na sala — busca instrutor na planilha
                $infoHorario = horario_get($codTurma, $data);
                $nomeInstrutor = '';

                if($infoHorario && !empty($infoHorario['instrutor'])){
                    // Compara primeiro nome da planilha com o banco
                    $nomeInstrutor = nomeCompletoInstrutor($pdo, $infoHorario['instrutor']);
                }

                $resultado[$id][$turnoKey] = [
                    'status'      => 'na-sala',
                    'turma'       => $codTurma,
                    'sub'         => 'Na sala',
                    'solicitante' => $nomeInstrutor,
                ];
                continue;
            }

            /* 3. Vazio */
            $resultado[$id][$turnoKey] = [
                'status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''
            ];
        }
    }

    return $resultado;
}
?>