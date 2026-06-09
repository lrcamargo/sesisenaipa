<?php
// pulso/status.php — Painel público de status dos compromissos
// SEM login. Dados apenas AGREGADOS. Nenhuma identificação individual.
include('../conexao.php');

// Filtra por unidade se informado
$slug_filtro = $_GET['unidade'] ?? '';

// Busca unidades com ciclos (abertos ou encerrados com devolutiva)
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.nome, u.slug
    FROM pulso_unidades u
    INNER JOIN pulso_ciclos c ON c.unidade_id = u.id
    WHERE u.ativa = 1
    ORDER BY u.nome
");
$unidades_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca itens de SLA por unidade (filtrada ou todas)
$where_u = $slug_filtro ? "AND u.slug = ?" : "";
$params  = $slug_filtro ? [$slug_filtro]  : [];

$stmt2 = $pdo->prepare("
    SELECT
        u.nome AS unidade_nome,
        u.slug,
        c.titulo AS ciclo_titulo,
        c.status AS ciclo_status,
        s.id, s.titulo, s.categoria,
        s.status AS item_status, s.prazo, s.prazo_resposta,
        s.responsavel_publico, s.modo_exibicao, s.criado_em,
        d.publicada, d.conteudo AS devolutiva,
        hr.observacao AS resposta_resolucao
    FROM pulso_itens_sla s
    INNER JOIN pulso_ciclos c ON c.id = s.ciclo_id
    INNER JOIN pulso_unidades u ON u.id = c.unidade_id
    LEFT JOIN pulso_devolutivas d
           ON d.ciclo_id = c.id AND d.publicada = 1
    LEFT JOIN pulso_sla_historico hr
           ON hr.item_id = s.id
          AND hr.status_novo = 'resolvido'
          AND hr.id = (
              SELECT MAX(h2.id) FROM pulso_sla_historico h2
              WHERE h2.item_id = s.id AND h2.status_novo = 'resolvido'
          )
    WHERE s.visivel_equipe = 1 $where_u
    ORDER BY u.nome, c.id DESC, s.criado_em DESC
");
$stmt2->execute($params);
$itens = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por unidade → ciclo
$por_unidade = [];
foreach ($itens as $it) {
    $key = $it['slug'];
    if (!isset($por_unidade[$key])) {
        $por_unidade[$key] = [
            'nome'         => $it['unidade_nome'],
            'ciclo_titulo' => $it['ciclo_titulo'],
            'ciclo_status' => $it['ciclo_status'],
            'devolutiva'   => $it['devolutiva'],
            'itens'        => [],
        ];
    }
    $por_unidade[$key]['itens'][] = $it;
}

// Contador de participação
$part_stmt = $pdo->prepare("
    SELECT u.slug, pp.total
    FROM pulso_participacao pp
    INNER JOIN pulso_ciclos c ON c.id = pp.ciclo_id
    INNER JOIN pulso_unidades u ON u.id = c.unidade_id
    WHERE c.status = 'aberto'
");
$part_stmt->execute();
$participacoes = [];
foreach ($part_stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $participacoes[$p['slug']] = $p['total'];
}

// Labels e cores de status
$status_cfg = [
    'aberto'        => ['label' => 'Aberto',        'class' => 'badge-aberto'],
    'em_andamento'  => ['label' => 'Em andamento',  'class' => 'badge-andamento'],
    'resolvido'     => ['label' => 'Resolvido',     'class' => 'badge-resolvido'],
    'escalado'      => ['label' => 'Escalado',      'class' => 'badge-escalado'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Compromissos — PulsoSENAI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <style>
        :root {
            --azul:    #164194;
            --laranja: #E84910;
            --azul-c:  #008BD2;
            --cinza-f: #F4F6FA;
            --verde:   #1a9e4a;
            --texto:   #1a1a2e;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--cinza-f); }

        .pulso-header {
            background: var(--azul);
            padding: 14px 24px;
            display: flex; align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }
        .marca { display: flex; align-items: center; gap: 10px; }
                                .btn-responder {
            background: var(--laranja);
            color: #fff;
            font-size: 13px; font-weight: 600;
            padding: 8px 18px; border-radius: 20px;
            text-decoration: none;
            display: flex; align-items: center; gap: 6px;
            transition: background .2s;
        }
        .btn-responder:hover { background: #d03d09; color: #fff; text-decoration: none; }

        /* HERO */
        .hero {
            background: linear-gradient(135deg, var(--azul) 0%, #1e54c5 100%);
            padding: 40px 24px 56px;
            text-align: center;
            color: #fff;
        }
        .hero h1 { font-size: clamp(20px, 4vw, 28px); font-weight: 700; margin-bottom: 8px; }
        .hero p { font-size: 14px; color: rgba(255,255,255,.75); max-width: 480px; margin: 0 auto; }

        /* FILTRO */
        .filtro-bar {
            max-width: 800px;
            margin: -22px auto 24px;
            padding: 0 20px;
            position: relative; z-index: 2;
        }
        .filtro-bar select {
            width: 100%;
            padding: 12px 40px 12px 16px;
            font-size: 14px; font-family: inherit;
            border: 2px solid #d0d9e8; border-radius: 10px;
            background: #fff;
            appearance: none; cursor: pointer; outline: none;
            box-shadow: 0 4px 12px rgba(22,65,148,.1);
        }
        .filtro-bar::after {
            content: '\f0b0';
            font-family: FontAwesome;
            position: absolute; right: 36px; top: 50%;
            transform: translateY(-50%);
            color: var(--azul); pointer-events: none;
        }

        /* CONTAINER */
        .container-status { max-width: 800px; margin: 0 auto; padding: 0 20px 60px; }

        /* CARD DE UNIDADE */
        .unidade-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(22,65,148,.07);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .unidade-header {
            background: var(--azul);
            padding: 16px 20px;
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .unidade-header h2 {
            font-size: 15px; font-weight: 700;
            color: #fff; margin: 0;
        }
        .unidade-header .ciclo-tag {
            font-size: 12px;
            color: rgba(255,255,255,.7);
            background: rgba(255,255,255,.12);
            padding: 3px 10px; border-radius: 12px;
        }

        /* CONTADORES */
        .contadores {
            display: flex; flex-wrap: wrap;
            border-bottom: 1px solid #eef0f5;
        }
        .contador-item {
            flex: 1; min-width: 100px;
            text-align: center;
            padding: 16px 12px;
            border-right: 1px solid #eef0f5;
        }
        .contador-item:last-child { border-right: none; }
        .contador-item .num {
            font-size: 28px; font-weight: 800;
            display: block; line-height: 1;
        }
        .contador-item .label {
            font-size: 11px; color: #888;
            margin-top: 4px; display: block;
        }
        .num-aberto   { color: #E84910; }
        .num-andamento{ color: #f59e0b; }
        .num-resolvido{ color: var(--verde); }
        .num-escalado { color: #7c3aed; }
        .num-participacao { color: var(--azul); }

        /* LISTA DE ITENS */
        .itens-lista { padding: 0; }
        .item-sla {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid #f0f3f8;
        }
        .item-sla:last-child { border-bottom: none; }
        .item-cat {
            font-size: 11px; font-weight: 600;
            color: var(--azul);
            background: #eef3fd;
            padding: 3px 8px; border-radius: 10px;
            white-space: nowrap; flex-shrink: 0;
        }
        .item-body { flex: 1; min-width: 0; }
        .item-body p {
            font-size: 13px; color: #444;
            margin: 0 0 6px; line-height: 1.4;
        }
        .item-meta {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        }

        /* BADGES DE STATUS */
        .badge-status {
            font-size: 11px; font-weight: 600;
            padding: 3px 10px; border-radius: 10px;
        }
        .badge-aberto    { background: #fff0eb; color: #c0392b; }
        .badge-andamento { background: #fef9e7; color: #b7770d; }
        .badge-resolvido { background: #eafaf1; color: var(--verde); }
        .badge-escalado  { background: #f3eeff; color: #7c3aed; }

        .item-prazo {
            font-size: 11px; color: #999;
            display: flex; align-items: center; gap: 4px;
        }
        .item-prazo.vencido { color: #c0392b; }

        /* DEVOLUTIVA */
        .devolutiva-bloco {
            background: #f8faff;
            border-top: 2px solid #e0e9f8;
            padding: 20px;
        }
        .devolutiva-bloco h4 {
            font-size: 13px; font-weight: 700;
            color: var(--azul); margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .devolutiva-bloco p {
            font-size: 13px; color: #444;
            line-height: 1.6; margin: 0;
        }

        /* SEM ITENS */
        .sem-itens {
            padding: 32px;
            text-align: center;
            color: #aaa;
            font-size: 14px;
        }
        .sem-itens .fa { font-size: 32px; display: block; margin-bottom: 10px; color: #ddd; }

        @media (max-width: 600px) {
            .contadores { flex-direction: row; }
            .contador-item { min-width: 80px; }
        }
    </style>
</head>
<body>

    <header class="pulso-header">
        <div class="marca">
            <img src="../img/pulso.png" alt="PulsoSENAI" style="height:44px;">
        </div>
        <a href="index.php" class="btn-responder">
            <i class="fa fa-pencil"></i>
            Responder pesquisa
        </a>
    </header>

    <div class="hero">
        <h1>Painel Público de Compromissos</h1>
        <p>Acompanhe o status de cada item levantado pela equipe. Transparência é o nosso compromisso.</p>
    </div>

    <!-- FILTRO DE UNIDADE -->
    <?php if (count($unidades_lista) > 1): ?>
    <div class="filtro-bar">
        <form method="GET">
            <select name="unidade" onchange="this.form.submit()">
                <option value="">Todas as unidades</option>
                <?php foreach ($unidades_lista as $ul): ?>
                    <option value="<?= htmlspecialchars($ul['slug']) ?>"
                        <?= $slug_filtro === $ul['slug'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ul['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <div class="container-status">

        <?php if (empty($por_unidade)): ?>
            <div class="unidade-card">
                <div class="sem-itens">
                    <i class="fa fa-inbox"></i>
                    Nenhum compromisso registrado ainda.
                    Os itens aparecem aqui após o encerramento de cada ciclo.
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($por_unidade as $slug => $dados): ?>
        <?php
            // Contadores
            $c = ['aberto' => 0, 'em_andamento' => 0, 'resolvido' => 0, 'escalado' => 0];
            foreach ($dados['itens'] as $it) { $c[$it['item_status']] = ($c[$it['item_status']] ?? 0) + 1; }
            $participacao = $participacoes[$slug] ?? 0;
            $hoje = date('Y-m-d');
        ?>
        <div class="unidade-card">
            <div class="unidade-header">
                <div>
                    <h2><?= htmlspecialchars($dados['nome']) ?></h2>
                </div>
                <span class="ciclo-tag">
                    <?= htmlspecialchars($dados['ciclo_titulo']) ?>
                    &bull;
                    <?= $dados['ciclo_status'] === 'aberto' ? '🟢 Aberto' : '🔵 Encerrado' ?>
                </span>
            </div>

            <!-- CONTADORES -->
            <div class="contadores">
                <?php if ($participacao > 0): ?>
                <div class="contador-item">
                    <span class="num num-participacao"><?= $participacao ?></span>
                    <span class="label">Participantes</span>
                </div>
                <?php endif; ?>
                <div class="contador-item">
                    <span class="num num-aberto"><?= $c['aberto'] ?></span>
                    <span class="label">Em aberto</span>
                </div>
                <div class="contador-item">
                    <span class="num num-andamento"><?= $c['em_andamento'] ?></span>
                    <span class="label">Em andamento</span>
                </div>
                <div class="contador-item">
                    <span class="num num-resolvido"><?= $c['resolvido'] ?></span>
                    <span class="label">Resolvidos</span>
                </div>
                <div class="contador-item">
                    <span class="num num-escalado"><?= $c['escalado'] ?></span>
                    <span class="label">Escalados</span>
                </div>
            </div>

            <!-- ITENS DE SLA -->
            <?php if (!empty($dados['itens'])): ?>
            <div class="itens-lista">
                <?php foreach ($dados['itens'] as $it): ?>
                <?php
                    $sc = $status_cfg[$it['item_status']] ?? ['label' => $it['item_status'], 'class' => ''];
                    $vencido = $it['prazo'] && $it['prazo'] < $hoje && $it['item_status'] !== 'resolvido';
                ?>
                <?php if ($it['modo_exibicao'] === 'plano'): ?>
                <!-- MODO PLANO DE AÇÃO -->
                <div class="item-sla" style="flex-direction:column;gap:10px;">
                    <div style="display:flex;align-items:center;
                                justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <div>
                            <?php if (!empty($it['titulo'])): ?>
                            <div style="font-size:15px;font-weight:700;color:var(--texto);margin-bottom:4px;">
                                <?= htmlspecialchars($it['titulo']) ?>
                            </div>
                            <?php endif; ?>
                            <span class="item-cat"><?= htmlspecialchars($it['categoria']) ?></span>
                        </div>
                        <span class="badge-status <?= $sc['class'] ?>"><?= $sc['label'] ?></span>
                            <?php if ($it['item_status'] === 'resolvido' && !empty($it['resposta_resolucao'])): ?>
                            <button onclick="verResposta(<?= $it['id'] ?>, <?= htmlspecialchars(json_encode($it['resposta_resolucao'])) ?>)"
                                    style="background:none;border:1.5px solid #1a9e4a;color:#1a9e4a;
                                           border-radius:6px;padding:3px 10px;font-size:12px;
                                           font-weight:600;cursor:pointer;display:inline-flex;
                                           align-items:center;gap:5px;"
                                    onmouseover="this.style.background='#1a9e4a';this.style.color='#fff'"
                                    onmouseout="this.style.background='none';this.style.color='#1a9e4a'">
                                <i class="fa fa-comment-o"></i> Ver resposta
                            </button>
                            <?php endif; ?>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f4f6fa;">
                                <th style="padding:8px 12px;text-align:left;font-size:11px;
                                           font-weight:700;color:#888;text-transform:uppercase;
                                           letter-spacing:.5px;border-bottom:1px solid #e0e9f8;">
                                    Responsável
                                </th>
                                <th style="padding:8px 12px;text-align:left;font-size:11px;
                                           font-weight:700;color:#888;text-transform:uppercase;
                                           letter-spacing:.5px;border-bottom:1px solid #e0e9f8;">
                                    Prazo de resposta
                                </th>
                                <th style="padding:8px 12px;text-align:left;font-size:11px;
                                           font-weight:700;color:#888;text-transform:uppercase;
                                           letter-spacing:.5px;border-bottom:1px solid #e0e9f8;">
                                    Prazo de resolução
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:8px 12px;border-bottom:1px solid #f0f3f8;">
                                    <?= htmlspecialchars($it['responsavel_publico'] ?? '—') ?>
                                </td>
                                <td style="padding:8px 12px;border-bottom:1px solid #f0f3f8;color:#888;">
                                    <?= !empty($it['prazo_resposta'])
                                        ? date('d/m/Y', strtotime($it['prazo_resposta'])) : '—' ?>
                                </td>
                                <td style="padding:8px 12px;border-bottom:1px solid #f0f3f8;
                                           <?= $vencido ? 'color:#c0392b;font-weight:600;' : 'color:#888;' ?>">
                                    <?= $it['prazo'] ? date('d/m/Y', strtotime($it['prazo'])) : '—' ?>
                                    <?= $vencido ? ' · Vencido' : '' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <!-- MODO CHAMADO (padrão) -->
                <div class="item-sla">
                    <span class="item-cat"><?= htmlspecialchars($it['categoria']) ?></span>
                    <div class="item-body">
                        <?php if (!empty($it['titulo'])): ?>
                        <p style="font-size:14px;font-weight:600;color:#1a1a2e;margin:0 0 8px;">
                            <?= htmlspecialchars($it['titulo']) ?>
                        </p>
                        <?php else: ?>
                        <p style="font-size:13px;color:#888;font-style:italic;margin:0 0 8px;">
                            (sem título)
                        </p>
                        <?php endif; ?>
                        <div class="item-meta">
                            <span class="badge-status <?= $sc['class'] ?>">
                                <?= $sc['label'] ?>
                            </span>
                            <?php if (!empty($it['responsavel_publico'])): ?>
                            <span style="font-size:12px;color:#888;">
                                <i class="fa fa-user"></i>
                                <?= htmlspecialchars($it['responsavel_publico']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($it['prazo']): ?>
                            <span class="item-prazo <?= $vencido ? 'vencido' : '' ?>">
                                <i class="fa fa-calendar<?= $vencido ? '-times-o' : '' ?>"></i>
                                Prazo: <?= date('d/m/Y', strtotime($it['prazo'])) ?>
                                <?= $vencido ? ' · Vencido' : '' ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($it['item_status'] === 'resolvido' && !empty($it['resposta_resolucao'])): ?>
                            <button onclick="verResposta(<?= $it['id'] ?>, <?= htmlspecialchars(json_encode($it['resposta_resolucao'])) ?>)"
                                    style="background:none;border:1.5px solid #1a9e4a;color:#1a9e4a;
                                           border-radius:6px;padding:3px 10px;font-size:12px;
                                           font-weight:600;cursor:pointer;display:inline-flex;
                                           align-items:center;gap:5px;transition:all .15s;"
                                    onmouseover="this.style.background='#1a9e4a';this.style.color='#fff'"
                                    onmouseout="this.style.background='none';this.style.color='#1a9e4a'">
                                <i class="fa fa-comment-o"></i> Ver resposta
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="sem-itens">
                <i class="fa fa-clock-o"></i>
                Os itens aparecerão aqui após o processamento das respostas pela gestão.
            </div>
            <?php endif; ?>

            <!-- DEVOLUTIVA PUBLICADA -->
            <?php if (!empty($dados['devolutiva'])): ?>
            <div class="devolutiva-bloco">
                <h4><i class="fa fa-bullhorn"></i> Devolutiva da Gestão</h4>
                <p><?= nl2br(htmlspecialchars($dados['devolutiva'])) ?></p>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

    </div>


<!-- MODAL: VER RESPOSTA -->
<div id="modalResposta" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:500px;
                box-shadow:0 16px 48px rgba(0,0,0,.2);overflow:hidden;">
        <div style="background:#1a9e4a;padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:15px;font-weight:700;color:#fff;margin:0;
                       display:flex;align-items:center;gap:8px;">
                <i class="fa fa-check-circle"></i> Item resolvido — Resposta da gestão
            </h3>
            <button onclick="fecharModalResposta()"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:22px;cursor:pointer;line-height:1;">×</button>
        </div>
        <div style="padding:24px;">
            <p id="modalRespostaTexto"
               style="font-size:14px;color:#444;line-height:1.8;margin:0;
                      white-space:pre-line;"></p>
        </div>
        <div style="padding:0 24px 20px;text-align:right;">
            <button onclick="fecharModalResposta()"
                    style="background:var(--azul);color:#fff;border:none;
                           border-radius:8px;padding:10px 20px;font-size:13px;
                           font-weight:600;cursor:pointer;">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
function verResposta(id, texto) {
    document.getElementById('modalRespostaTexto').textContent = texto;
    document.getElementById('modalResposta').style.display = 'flex';
}
function fecharModalResposta() {
    document.getElementById('modalResposta').style.display = 'none';
}
document.getElementById('modalResposta').addEventListener('click', e => {
    if (e.target.id === 'modalResposta') fecharModalResposta();
});
</script>
</body>
</html>