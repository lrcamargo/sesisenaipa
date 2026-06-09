<?php
// pulso/responder.php — Formulário anônimo da pesquisa
// SEM login. SEM sessão. SEM identificação do respondente.
include('../conexao.php');

$slug = $_GET['unidade'] ?? '';
if (!$slug) { header('Location: index.php'); exit; }

// Busca unidade e ciclo aberto
$stmt = $pdo->prepare("
    SELECT u.id AS unidade_id, u.nome AS unidade_nome,
           c.id AS ciclo_id, c.titulo AS ciclo_titulo
    FROM pulso_unidades u
    INNER JOIN pulso_ciclos c ON c.unidade_id = u.id AND c.status = 'aberto'
    WHERE u.slug = ? AND u.ativa = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) { header('Location: index.php'); exit; }

// Busca perguntas do ciclo, agrupadas por categoria
$stmt2 = $pdo->prepare("
    SELECT * FROM pulso_perguntas
    WHERE ciclo_id = ?
    ORDER BY categoria, ordem, id
");
$stmt2->execute([$dados['ciclo_id']]);
$perguntas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por categoria
$por_categoria = [];
foreach ($perguntas as $p) {
    $por_categoria[$p['categoria']][] = $p;
}

// Opções para perguntas de escolha
$opcoes_escolha = [
    6 => 'Concordo totalmente',
    5 => 'Concordo em grande parte',
    4 => 'Mais concordo que discordo',
    3 => 'Mais discordo que concordo',
    2 => 'Discordo em grande parte',
    1 => 'Discordo totalmente',
];

// Ícones por categoria
$icones = [
    'Reconhecimento e Carreira'     => 'fa-star',
    'Treinamento e Desenvolvimento'  => 'fa-graduation-cap',
    'Condições Físicas'             => 'fa-building',
    'Liderança'                     => 'fa-users',
    'Qualidade de Vida'             => 'fa-heart',
    'Relacionamento'                => 'fa-handshake-o',
    'Comunicação'                   => 'fa-comments',
    'Geral'                         => 'fa-circle',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa — <?= htmlspecialchars($dados['unidade_nome']) ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <style>
        :root {
            --azul:    #164194;
            --laranja: #E84910;
            --azul-c:  #008BD2;
            --cinza-f: #F4F6FA;
            --texto:   #1a1a2e;
            --verde:   #1a9e4a;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cinza-f);
            color: var(--texto);
        }

        /* ── HEADER ── */
        .pulso-header {
            background: var(--azul);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .pulso-header .marca {
            display: flex;
            align-items: center;
            gap: 10px;
        }
                                .anon-badge {
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            color: rgba(255,255,255,.85);
            font-size: 11px; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
            display: flex; align-items: center; gap: 6px;
        }
        .anon-badge .fa { color: #6ee78d; }

        /* ── BARRA DE PROGRESSO ── */
        .progress-bar-wrap {
            background: rgba(255,255,255,.15);
            height: 3px;
        }
        .progress-bar-fill {
            height: 3px;
            background: var(--laranja);
            transition: width .4s ease;
            width: 0%;
        }

        /* ── INFO DO CICLO ── */
        .ciclo-info {
            background: #fff;
            border-bottom: 1px solid #e0e6f0;
            padding: 12px 24px;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        .ciclo-info strong { color: var(--azul); }

        /* ── CONTAINER ── */
        .form-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        /* ── BLOCO DE CATEGORIA ── */
        .categoria-bloco {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(22,65,148,.07);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .categoria-header {
            background: linear-gradient(90deg, var(--azul) 0%, #1e54c5 100%);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .categoria-header .fa {
            font-size: 18px;
            color: rgba(255,255,255,.75);
        }
        .categoria-header h3 {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            margin: 0;
        }
        .categoria-body { padding: 8px 0; }

        /* ── ITEM DE PERGUNTA ── */
        .pergunta-item {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f3f8;
            transition: background .15s;
        }
        .pergunta-item:last-child { border-bottom: none; }
        .pergunta-item:hover { background: #fafbff; }

        .pergunta-texto {
            font-size: 14px;
            font-weight: 500;
            color: var(--texto);
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .pergunta-num {
            display: inline-block;
            background: var(--azul);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            width: 22px; height: 22px;
            border-radius: 50%;
            text-align: center;
            line-height: 22px;
            margin-right: 8px;
            flex-shrink: 0;
        }

        /* ── OPÇÕES DE ESCOLHA ── */
        .opcoes-escolha {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .opcao-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border: 1.5px solid #dde3ef;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            color: #444;
            transition: all .15s;
            user-select: none;
        }
        .opcao-label:hover {
            border-color: var(--azul-c);
            background: #f0f6ff;
            color: var(--azul);
        }
        .opcao-label input[type="radio"] { display: none; }
        .opcao-label.selecionada {
            border-color: var(--azul);
            background: #eef3fd;
            color: var(--azul);
            font-weight: 600;
        }
        .opcao-label.selecionada::before {
            content: '\f058';
            font-family: FontAwesome;
            color: var(--azul);
            font-size: 16px;
        }
        .opcao-label:not(.selecionada)::before {
            content: '\f10c';
            font-family: FontAwesome;
            color: #ccc;
            font-size: 16px;
        }

        /* ── ESCALA NPS ── */
        .escala-wrap {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .escala-btn {
            width: 44px; height: 44px;
            border: 2px solid #dde3ef;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all .15s;
            display: flex; align-items: center; justify-content: center;
        }
        .escala-btn:hover { border-color: var(--azul-c); color: var(--azul); }
        .escala-btn.ativo {
            background: var(--azul);
            border-color: var(--azul);
            color: #fff;
        }
        .escala-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #999;
            margin-top: 6px;
        }

        /* ── CAMPO ABERTO ── */
        .campo-aberto {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            font-family: inherit;
            border: 1.5px solid #dde3ef;
            border-radius: 8px;
            background: #fafbfd;
            color: var(--texto);
            resize: vertical;
            min-height: 100px;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .campo-aberto:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(22,65,148,.1);
            background: #fff;
        }
        .opcional-tag {
            font-size: 11px;
            color: #aaa;
            margin-top: 6px;
        }

        /* ── BOTÃO ENVIAR ── */
        .enviar-wrap {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(22,65,148,.07);
            padding: 28px 28px 32px;
        }
        .aviso-final {
            background: #f0f6ff;
            border: 1px solid #c8d9f5;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 13px;
            color: #444;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            line-height: 1.5;
        }
        .aviso-final .fa {
            color: var(--verde);
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .btn-enviar {
            width: 100%;
            padding: 16px;
            background: var(--azul);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background .2s, transform .1s, box-shadow .2s;
            letter-spacing: .3px;
        }
        .btn-enviar:hover {
            background: #1a52c4;
            box-shadow: 0 4px 16px rgba(22,65,148,.3);
        }
        .btn-enviar:active { transform: scale(.98); }
        .btn-enviar:disabled {
            background: #9aabcc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── ALERTA CAMPOS OBRIGATÓRIOS ── */
        .alerta-campos {
            display: none;
            background: #fff3f0;
            border: 1px solid #f5c6bb;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #c0392b;
            margin-top: 12px;
            align-items: center;
            gap: 10px;
        }
        .alerta-campos.visivel { display: flex; }

        @media (max-width: 600px) {
            .escala-btn { width: 38px; height: 38px; font-size: 13px; }
            .form-container { padding: 20px 12px 80px; }
            .anon-badge span { display: none; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="pulso-header">
        <div class="marca">
            <img src="pulso.png" alt="PulsoSENAI" style="height:44px;">
        </div>
        <div class="anon-badge">
            <i class="fa fa-lock"></i>
            <span>Resposta anônima</span>
        </div>
    </header>

    <!-- BARRA DE PROGRESSO -->
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar"></div>
    </div>

    <!-- INFO CICLO -->
    <div class="ciclo-info">
        <strong><?= htmlspecialchars($dados['unidade_nome']) ?></strong>
        &nbsp;&bull;&nbsp;
        <?= htmlspecialchars($dados['ciclo_titulo']) ?>
    </div>

    <!-- FORMULÁRIO -->
    <div class="form-container">
        <form action="enviar.php" method="POST" id="formPulso" novalidate>
            <input type="hidden" name="ciclo_id" value="<?= (int)$dados['ciclo_id'] ?>">
            <input type="hidden" name="unidade_slug" value="<?= htmlspecialchars($slug) ?>">

            <?php
            $num_global = 0;
            foreach ($por_categoria as $categoria => $pergs):
                $icone = $icones[$categoria] ?? 'fa-circle';
            ?>
            <div class="categoria-bloco">
                <div class="categoria-header">
                    <i class="fa <?= $icone ?>"></i>
                    <h3><?= htmlspecialchars($categoria) ?></h3>
                </div>
                <div class="categoria-body">
                    <?php foreach ($pergs as $p):
                        $num_global++;
                        $fname = 'p_' . $p['id'];
                    ?>
                    <div class="pergunta-item" data-tipo="<?= $p['tipo'] ?>" data-id="<?= $p['id'] ?>">
                        <div class="pergunta-texto">
                            <span class="pergunta-num"><?= $num_global ?></span>
                            <?= htmlspecialchars($p['texto']) ?>
                            <?php if ($p['tipo'] !== 'aberta'): ?>
                                <span style="color:#c0392b;margin-left:4px;" title="Obrigatória">*</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($p['tipo'] === 'escolha'): ?>
                            <!-- OPÇÕES DE CONCORDÂNCIA -->
                            <div class="opcoes-escolha">
                                <?php foreach ($opcoes_escolha as $val => $label): ?>
                                    <label class="opcao-label">
                                        <input type="radio"
                                               name="<?= $fname ?>"
                                               value="<?= $val ?>"
                                               required
                                               onchange="marcarOpcao(this)">
                                        <?= $label ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($p['tipo'] === 'escala'): ?>
                            <!-- ESCALA 0-10 -->
                            <div class="escala-wrap" id="escala_<?= $p['id'] ?>">
                                <?php for ($i = 0; $i <= 10; $i++): ?>
                                    <button type="button"
                                            class="escala-btn"
                                            data-val="<?= $i ?>"
                                            data-pid="<?= $p['id'] ?>"
                                            onclick="selecionarEscala(this)">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>
                                <input type="hidden" name="<?= $fname ?>" id="hid_<?= $p['id'] ?>" required>
                            </div>
                            <div class="escala-labels">
                                <span>Muito insatisfeito</span>
                                <span>Muito satisfeito</span>
                            </div>

                        <?php else: ?>
                            <!-- CAMPO ABERTO -->
                            <textarea class="campo-aberto"
                                      name="<?= $fname ?>"
                                      placeholder="Escreva sua resposta aqui (opcional)..."
                                      rows="3"></textarea>
                            <p class="opcional-tag">Campo opcional</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ENVIO -->
            <div class="enviar-wrap">
                <div class="aviso-final">
                    <i class="fa fa-shield"></i>
                    <div>
                        <strong>Sua resposta é 100% anônima.</strong><br>
                        Nenhum dado de identificação é coletado ou armazenado.
                        Ao enviar, você contribui para a melhoria real do ambiente de trabalho.
                    </div>
                </div>
                <button type="submit" class="btn-enviar" id="btnEnviar">
                    <i class="fa fa-paper-plane"></i>
                    Enviar minha resposta
                </button>
                <div class="alerta-campos" id="alertaCampos">
                    <i class="fa fa-exclamation-circle"></i>
                    Por favor, responda todas as perguntas obrigatórias antes de enviar.
                </div>
            </div>
        </form>
    </div>

    <script>
    // ── Marca opção de escolha visualmente ──────────────────────
    function marcarOpcao(radio) {
        const grupo = radio.closest('.opcoes-escolha');
        grupo.querySelectorAll('.opcao-label').forEach(l => l.classList.remove('selecionada'));
        radio.closest('.opcao-label').classList.add('selecionada');
        atualizarProgresso();
    }

    // ── Seleciona valor na escala ────────────────────────────────
    function selecionarEscala(btn) {
        const pid = btn.dataset.pid;
        document.querySelectorAll(`#escala_${pid} .escala-btn`)
                .forEach(b => b.classList.remove('ativo'));
        btn.classList.add('ativo');
        document.getElementById(`hid_${pid}`).value = btn.dataset.val;
        atualizarProgresso();
    }

    // ── Barra de progresso ───────────────────────────────────────
    function atualizarProgresso() {
        const obrigatorias = document.querySelectorAll(
            '.pergunta-item:not([data-tipo="aberta"])'
        ).length;

        let respondidas = 0;
        document.querySelectorAll('.opcao-label.selecionada').forEach(() => {
            // conta grupos únicos
        });

        // Conta grupos de radio respondidos
        const gruposRespondidos = new Set();
        document.querySelectorAll('input[type="radio"]:checked').forEach(r => {
            gruposRespondidos.add(r.name);
        });
        // Conta escalas respondidas
        document.querySelectorAll('input[type="hidden"]').forEach(h => {
            if (h.id.startsWith('hid_') && h.value !== '') gruposRespondidos.add(h.name);
        });

        respondidas = gruposRespondidos.size;
        const pct = obrigatorias > 0 ? Math.round((respondidas / obrigatorias) * 100) : 0;
        document.getElementById('progressBar').style.width = pct + '%';
    }

    // ── Validação antes do envio ─────────────────────────────────
    document.getElementById('formPulso').addEventListener('submit', function(e) {
        const campos = this.querySelectorAll('[required]');
        let ok = true;
        campos.forEach(c => { if (!c.value) ok = false; });

        if (!ok) {
            e.preventDefault();
            const alerta = document.getElementById('alertaCampos');
            alerta.classList.add('visivel');
            alerta.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Desabilita botão para evitar duplo envio
        const btn = document.getElementById('btnEnviar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';
    });
    </script>
</body>
</html>