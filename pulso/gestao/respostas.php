<?php
// pulso/gestao/respostas.php — Visualização completa das respostas do ciclo
// Permite selecionar respostas abertas e gerar plano de ação com IA
include('../../conexao.php');
require_once '../_guard.php';

$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;
$cat_filtro = $_GET['categoria'] ?? '';

// Lista de ciclos
$ciclos_stmt = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ? ORDER BY id DESC
");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclo_sel && $ciclos_lista) {
    $ciclo_sel = $ciclos_lista[0]['id'];
}

$ciclo_atual = null;
foreach ($ciclos_lista as $c) {
    if ($c['id'] == $ciclo_sel) { $ciclo_atual = $c; break; }
}

// Participação
$participacao = 0;
if ($ciclo_sel) {
    $q = $pdo->prepare("SELECT total FROM pulso_participacao WHERE ciclo_id = ?");
    $q->execute([$ciclo_sel]);
    $participacao = (int)($q->fetchColumn() ?: 0);
}

// Usuários para atribuição (gestores + docentes)
$usuarios_gestao  = $pdo->query("
    SELECT id, nome, LOWER(REPLACE(perfil,'.','')) AS grp
    FROM usuarios WHERE status = 1
    AND LOWER(REPLACE(perfil,'.','')) IN ('gerencia','sup tecnica','sup pedagogica','administrator')
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$usuarios_docentes = $pdo->query("
    SELECT id, nome FROM usuarios WHERE status = 1
    AND LOWER(REPLACE(perfil,'.','')) IN ('instrutor','docente','professor')
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

// SLA config para esta unidade
$sla_cfg_stmt = $pdo->prepare("
    SELECT categoria, prazo_resposta, prazo_resolucao
    FROM pulso_sla_config WHERE unidade_id = ?
");
$sla_cfg_stmt->execute([$g_unidade_id]);
$sla_cfg = [];
foreach ($sla_cfg_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $sla_cfg[$r['categoria']] = [
        'resposta'  => $r['prazo_resposta'],
        'resolucao' => $r['prazo_resolucao'],
    ];
}
// Fallback padrão se tabela vazia
$sla_default = [
    'Infraestrutura crítica'        => ['resposta'=>1,  'resolucao'=>7],
    'Condições Físicas'             => ['resposta'=>2,  'resolucao'=>30],
    'Reconhecimento e Carreira'     => ['resposta'=>5,  'resolucao'=>10],
    'Treinamento e Desenvolvimento' => ['resposta'=>5,  'resolucao'=>60],
    'Relacionamento'                => ['resposta'=>3,  'resolucao'=>21],
    'Qualidade de Vida'             => ['resposta'=>5,  'resolucao'=>30],
    'Liderança'                     => ['resposta'=>5,  'resolucao'=>21],
    'Comunicação'                   => ['resposta'=>3,  'resolucao'=>15],
    'Geral'                         => ['resposta'=>7,  'resolucao'=>45],
];
if (empty($sla_cfg)) $sla_cfg = $sla_default;

// Perguntas do ciclo agrupadas por categoria
$perguntas = [];
if ($ciclo_sel) {
    $where_cat = $cat_filtro ? 'AND p.categoria = ?' : '';
    $params    = $cat_filtro ? [$ciclo_sel, $cat_filtro] : [$ciclo_sel];
    $stmt = $pdo->prepare("
        SELECT p.* FROM pulso_perguntas p
        WHERE p.ciclo_id = ? $where_cat
        ORDER BY p.categoria, p.ordem, p.id
    ");
    $stmt->execute($params);
    $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Para cada pergunta, busca as respostas
$respostas_por_pergunta = [];
foreach ($perguntas as $p) {
    if ($p['tipo'] === 'aberta') {
        // Respostas abertas — textos individuais (anônimos)
        $q = $pdo->prepare("
            SELECT id, texto, registrado_em
            FROM pulso_respostas_agregadas
            WHERE pergunta_id = ? AND texto IS NOT NULL
            ORDER BY registrado_em DESC
        ");
        $q->execute([$p['id']]);
        $respostas_por_pergunta[$p['id']] = [
            'tipo'  => 'aberta',
            'itens' => $q->fetchAll(PDO::FETCH_ASSOC),
        ];
    } else {
        // Escala/escolha — totalizadores
        $q = $pdo->prepare("
            SELECT valor, quantidade
            FROM pulso_respostas_agregadas
            WHERE pergunta_id = ? AND valor IS NOT NULL
            ORDER BY valor DESC
        ");
        $q->execute([$p['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($rows, 'quantidade'));
        $fav   = 0;
        foreach ($rows as $r) {
            if ($r['valor'] >= 4) $fav += $r['quantidade'];
        }
        $respostas_por_pergunta[$p['id']] = [
            'tipo'       => $p['tipo'],
            'total'      => $total,
            'favoravel'  => $fav,
            'pct'        => $total > 0 ? round(($fav / $total) * 100, 1) : 0,
            'distribuicao' => $rows,
        ];
    }
}

// Categorias disponíveis para filtro
$cats_stmt = $pdo->prepare("
    SELECT DISTINCT categoria FROM pulso_perguntas
    WHERE ciclo_id = ? ORDER BY categoria
");
$cats_stmt->execute([$ciclo_sel]);
$categorias = $cats_stmt->fetchAll(PDO::FETCH_COLUMN);

$opcoes_label = [
    6 => 'Concordo totalmente',
    5 => 'Concordo em grande parte',
    4 => 'Mais concordo que discordo',
    3 => 'Mais discordo que concordo',
    2 => 'Discordo em grande parte',
    1 => 'Discordo totalmente',
];


// ── EXPORTAÇÃO ───────────────────────────────────────────────
$exportar = $_GET['exportar'] ?? '';

if ($exportar && $ciclo_sel && $ciclo_atual) {
    $titulo_ciclo = $ciclo_atual['titulo'];
    $unidade_nome_exp = $g_unidade_nome;

    // Busca TODAS as perguntas e respostas sem filtro de categoria
    $exp_stmt = $pdo->prepare("
        SELECT p.categoria, p.texto AS pergunta, p.tipo,
               ra.valor, ra.quantidade, ra.texto AS resposta_aberta
        FROM pulso_perguntas p
        LEFT JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
        WHERE p.ciclo_id = ?
        ORDER BY p.categoria, p.ordem, p.id, ra.valor DESC
    ");
    $exp_stmt->execute([$ciclo_sel]);
    $exp_rows = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);

    $exp_agrupado = [];
    foreach ($exp_rows as $r) {
        $key = $r['categoria'] . '||' . $r['pergunta'] . '||' . $r['tipo'];
        if (!isset($exp_agrupado[$key])) {
            $exp_agrupado[$key] = [
                'categoria' => $r['categoria'],
                'pergunta'  => $r['pergunta'],
                'tipo'      => $r['tipo'],
                'valores'   => [],
                'abertas'   => [],
            ];
        }
        if ($r['tipo'] !== 'aberta' && $r['valor'] !== null) {
            $exp_agrupado[$key]['valores'][$r['valor']] = (int)$r['quantidade'];
        }
        if ($r['tipo'] === 'aberta' && $r['resposta_aberta'] !== null) {
            $exp_agrupado[$key]['abertas'][] = $r['resposta_aberta'];
        }
    }

    $opcoes_exp = [
        6 => 'Concordo totalmente',
        5 => 'Concordo em grande parte',
        4 => 'Mais concordo que discordo',
        3 => 'Mais discordo que concordo',
        2 => 'Discordo em grande parte',
        1 => 'Discordo totalmente',
    ];

    if ($exportar === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="respostas_' .
               preg_replace('/[^a-z0-9]/i', '_', $titulo_ciclo) . '.csv"');
        echo "\xEF\xBB\xBF";
        echo "Unidade;Ciclo;Categoria;Pergunta;Tipo;Resposta;Quantidade\n";
        foreach ($exp_agrupado as $bloco) {
            if ($bloco['tipo'] === 'aberta') {
                foreach ($bloco['abertas'] as $texto) {
                    echo implode(';', [
                        '"' . str_replace('"','""',$unidade_nome_exp)      . '"',
                        '"' . str_replace('"','""',$titulo_ciclo)          . '"',
                        '"' . str_replace('"','""',$bloco['categoria'])    . '"',
                        '"' . str_replace('"','""',$bloco['pergunta'])     . '"',
                        'Aberta',
                        '"' . str_replace('"','""',$texto)                 . '"',
                        '1',
                    ]) . "\n";
                }
            } else {
                foreach ($bloco['valores'] as $val => $qtd) {
                    $label = $bloco['tipo'] === 'escolha'
                        ? ($opcoes_exp[$val] ?? $val) : "Nota $val";
                    echo implode(';', [
                        '"' . str_replace('"','""',$unidade_nome_exp)      . '"',
                        '"' . str_replace('"','""',$titulo_ciclo)          . '"',
                        '"' . str_replace('"','""',$bloco['categoria'])    . '"',
                        '"' . str_replace('"','""',$bloco['pergunta'])     . '"',
                        ucfirst($bloco['tipo']),
                        '"' . str_replace('"','""',$label)                 . '"',
                        $qtd,
                    ]) . "\n";
                }
            }
        }
        exit;
    }

    if ($exportar === 'txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="respostas_' .
               preg_replace('/[^a-z0-9]/i', '_', $titulo_ciclo) . '.txt"');

        echo "================================================================\n";
        echo "PESQUISA DE CLIMA ORGANIZACIONAL — RESPOSTAS DO CICLO\n";
        echo "================================================================\n";
        echo "Unidade : {$unidade_nome_exp}\n";
        echo "Ciclo   : {$titulo_ciclo}\n";
        echo "Partic. : {$participacao} respondentes\n";
        echo "================================================================\n\n";

        $cat_ant_exp = '';
        foreach ($exp_agrupado as $bloco) {
            if ($bloco['categoria'] !== $cat_ant_exp) {
                $cat_ant_exp = $bloco['categoria'];
                echo "\n━━━ " . strtoupper($bloco['categoria']) . " ━━━\n\n";
            }
            echo "PERGUNTA: " . $bloco['pergunta'] . "\n";
            echo "TIPO    : " . ucfirst($bloco['tipo']) . "\n";
            if ($bloco['tipo'] === 'aberta') {
                if (empty($bloco['abertas'])) {
                    echo "RESPOSTAS: (nenhuma)\n";
                } else {
                    echo "RESPOSTAS (" . count($bloco['abertas']) . "):\n";
                    foreach ($bloco['abertas'] as $i => $texto) {
                        echo "  " . ($i + 1) . ". " . $texto . "\n";
                    }
                }
            } else {
                $total_exp = array_sum($bloco['valores']);
                $fav_exp   = ($bloco['valores'][4] ?? 0)
                           + ($bloco['valores'][5] ?? 0)
                           + ($bloco['valores'][6] ?? 0);
                $pct_exp   = $total_exp > 0 ? round(($fav_exp / $total_exp) * 100, 1) : 0;
                echo "FAVORABILIDADE: {$pct_exp}% ({$fav_exp} de {$total_exp} respostas)\n";
                echo "DISTRIBUICAO:\n";
                arsort($bloco['valores']);
                foreach ($bloco['valores'] as $val => $qtd) {
                    $label = $bloco['tipo'] === 'escolha'
                        ? ($opcoes_exp[$val] ?? "Opcao $val") : "Nota $val";
                    $pct_v = $total_exp > 0 ? round(($qtd / $total_exp) * 100) : 0;
                    echo "  - $label: $qtd resposta(s) ($pct_v%)\n";
                }
            }
            echo "\n";
        }
        exit;
    }
}

$titulo_pagina = 'Respostas';
$pagina_ativa  = 'respostas';
?>
<?php include '../_layout_head.php'; ?>
<style>
.resposta-aberta-item {
    background: #f8faff;
    border: 1px solid #e0e9f8;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 8px;
    font-size: 13.5px;
    color: #444;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    transition: all .15s;
    user-select: none;
}
.resposta-aberta-item:hover {
    border-color: var(--azul-c);
    background: #eef6ff;
}
.resposta-aberta-item.selecionada {
    border-color: var(--azul);
    background: #deeaf8;
    font-weight: 500;
}
.resposta-aberta-item input[type="checkbox"] {
    margin-top: 2px;
    accent-color: var(--azul);
    flex-shrink: 0;
}
.barra-dist {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.barra-dist .label { font-size: 12px; color: #666; width: 200px; flex-shrink: 0; }
.barra-dist .barra { flex: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; }
.barra-dist .fill  { height: 8px; border-radius: 4px; }
.barra-dist .pct   { font-size: 12px; font-weight: 700; width: 40px; text-align: right; }
.barra-dist .qtd   { font-size: 11px; color: #aaa; width: 30px; }

.fab-gerar {
    background: var(--azul);
    color: #fff;
    border: none;
    border-radius: 28px;
    padding: 14px 24px;
    font-size: 14px; font-weight: 700;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 6px 20px rgba(22,65,148,.35);
    cursor: pointer;
    transition: background .2s;
}
.fab-gerar:hover { box-shadow: 0 8px 24px rgba(22,65,148,.45); filter: brightness(1.1); }
.contador-sel {
    background: var(--laranja);
    color: #fff;
    border-radius: 14px;
    padding: 2px 10px;
    font-size: 13px;
    font-weight: 800;
}
</style>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-comments" style="color:var(--laranja);margin-right:8px;"></i>Respostas</h1>
            <div class="page-sub">
                Visualize o que a equipe respondeu e selecione itens para gerar planos de ação
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <div style="position:relative;" id="exportDropWrap">
                <button class="btn-sec" onclick="toggleExport()"
                        style="display:flex;align-items:center;gap:6px;">
                    <i class="fa fa-download"></i> Exportar
                    <i class="fa fa-caret-down"></i>
                </button>
                <div id="exportDrop"
                     style="display:none;position:absolute;top:100%;right:0;margin-top:4px;
                            background:#fff;border:1.5px solid #d0d9e8;border-radius:10px;
                            box-shadow:0 8px 24px rgba(22,65,148,.12);
                            min-width:220px;z-index:200;overflow:hidden;">
                    <a href="?ciclo=<?= $ciclo_sel ?>&exportar=txt"
                       style="display:flex;align-items:center;gap:10px;padding:12px 16px;
                              font-size:13px;color:#444;text-decoration:none;
                              border-bottom:1px solid #f0f3f8;transition:background .1s;"
                       onmouseover="this.style.background='#f4f6fa'"
                       onmouseout="this.style.background=''">
                        <i class="fa fa-file-text-o" style="color:var(--azul);width:16px;"></i>
                        <div>
                            <div style="font-weight:600;">Exportar .TXT</div>
                            <div style="font-size:11px;color:#aaa;">
                                Formato legível para IA — ideal para enviar ao Claude
                            </div>
                        </div>
                    </a>
                    <a href="?ciclo=<?= $ciclo_sel ?>&exportar=csv"
                       style="display:flex;align-items:center;gap:10px;padding:12px 16px;
                              font-size:13px;color:#444;text-decoration:none;transition:background .1s;"
                       onmouseover="this.style.background='#f4f6fa'"
                       onmouseout="this.style.background=''">
                        <i class="fa fa-table" style="color:var(--verde);width:16px;"></i>
                        <div>
                            <div style="font-weight:600;">Exportar .CSV</div>
                            <div style="font-size:11px;color:#aaa;">
                                Planilha com todas as respostas e distribuições
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <a href="plano_acao.php?ciclo=<?= $ciclo_sel ?>" class="btn-sec">
                <i class="fa fa-list-alt"></i> Planos de ação
            </a>
        </div>
    </div>

    <!-- SELETOR + FILTRO -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <select name="ciclo" class="form-input-g" style="max-width:280px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['titulo']) ?> — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="categoria" class="form-input-g" style="max-width:240px;margin:0;"
                        onchange="this.form.submit()">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $cat_filtro === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($participacao): ?>
                <span style="font-size:13px;color:#888;">
                    <i class="fa fa-users" style="margin-right:4px;"></i>
                    <?= $participacao ?> participante<?= $participacao > 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (empty($perguntas)): ?>
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhuma resposta disponível para este ciclo ainda.
        </div>
    </div>
    <?php else: ?>

    <?php
    $cat_ant = '';
    foreach ($perguntas as $p):
        $resp = $respostas_por_pergunta[$p['id']] ?? null;
        if (!$resp) continue;

        // Cabeçalho de categoria
        if ($p['categoria'] !== $cat_ant):
            if ($cat_ant !== '') echo '</div>'; // fecha g-card anterior
            $cat_ant = $p['categoria'];
    ?>
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-header">
            <h2><span class="badge-cat"><?= htmlspecialchars($p['categoria']) ?></span></h2>
        </div>
    <?php endif; ?>

        <!-- PERGUNTA -->
        <div style="padding:20px 24px;border-bottom:1px solid #f0f3f8;">
            <div style="font-size:14px;font-weight:600;color:var(--texto);margin-bottom:14px;line-height:1.5;">
                <?= htmlspecialchars($p['texto']) ?>
                <span class="badge-tipo badge-<?= $p['tipo'] ?>" style="margin-left:8px;vertical-align:middle;">
                    <?= ['escolha'=>'Escolha','escala'=>'Escala','aberta'=>'Aberta'][$p['tipo']] ?>
                </span>
            </div>

            <?php if ($resp['tipo'] === 'aberta'): ?>
            <!-- RESPOSTAS ABERTAS -->
            <?php if (empty($resp['itens'])): ?>
            <p style="font-size:13px;color:#aaa;font-style:italic;">Nenhuma resposta registrada.</p>
            <?php else: ?>
            <div style="margin-bottom:8px;font-size:12px;color:#888;">
                <i class="fa fa-hand-pointer-o"></i>
                Clique para selecionar respostas e gerar um plano de ação com IA
            </div>
            <?php foreach ($resp['itens'] as $item): ?>
            <div class="resposta-aberta-item"
                 data-id="<?= $item['id'] ?>"
                 data-texto="<?= htmlspecialchars($item['texto'], ENT_QUOTES) ?>"
                 data-pergunta="<?= htmlspecialchars($p['texto'], ENT_QUOTES) ?>"
                 data-categoria="<?= htmlspecialchars($p['categoria'], ENT_QUOTES) ?>"
                 onclick="toggleSelecao(this)">
                <input type="checkbox" onclick="event.stopPropagation();"
                       onchange="toggleSelecao(this.parentElement)">
                <span><?= htmlspecialchars($item['texto']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php elseif ($resp['tipo'] === 'escolha'): ?>
            <!-- DISTRIBUIÇÃO ESCOLHA -->
            <?php
            $total_r = $resp['total'];
            $pct_fav = $resp['pct'];
            $cor_fav = $pct_fav >= 70 ? '#1a9e4a' : ($pct_fav >= 50 ? '#f59e0b' : '#c0392b');
            ?>
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:14px;">
                <div style="flex:1;background:#eee;border-radius:5px;height:10px;overflow:hidden;">
                    <div style="height:10px;border-radius:5px;width:<?= $pct_fav ?>%;background:<?= $cor_fav ?>;"></div>
                </div>
                <strong style="font-size:16px;color:<?= $cor_fav ?>;"><?= $pct_fav ?>%</strong>
                <span style="font-size:12px;color:#aaa;">favorável (<?= $total_r ?> resp.)</span>
            </div>
            <?php foreach ($resp['distribuicao'] as $d):
                $p_item = $total_r > 0 ? round(($d['quantidade'] / $total_r) * 100) : 0;
                $cor_b  = $d['valor'] >= 4 ? '#1a9e4a' : ($d['valor'] >= 3 ? '#f59e0b' : '#c0392b');
            ?>
            <div class="barra-dist">
                <span class="label"><?= $opcoes_label[$d['valor']] ?? $d['valor'] ?></span>
                <div class="barra">
                    <div class="fill" style="width:<?= $p_item ?>%;background:<?= $cor_b ?>;"></div>
                </div>
                <span class="pct" style="color:<?= $cor_b ?>;"><?= $p_item ?>%</span>
                <span class="qtd"><?= $d['quantidade'] ?></span>
            </div>
            <?php endforeach; ?>

            <?php elseif ($resp['tipo'] === 'escala'): ?>
            <!-- DISTRIBUIÇÃO ESCALA -->
            <?php
            $total_e = $resp['total'];
            // Média ponderada
            $soma = 0;
            foreach ($resp['distribuicao'] as $d) $soma += $d['valor'] * $d['quantidade'];
            $media = $total_e > 0 ? round($soma / $total_e, 1) : 0;
            $cor_e = $media >= 7 ? '#1a9e4a' : ($media >= 5 ? '#f59e0b' : '#c0392b');
            ?>
            <div style="margin-bottom:14px;display:flex;align-items:center;gap:16px;">
                <div>
                    <span style="font-size:36px;font-weight:800;color:<?= $cor_e ?>;"><?= $media ?></span>
                    <span style="font-size:13px;color:#aaa;"> / 10</span>
                </div>
                <div style="font-size:12px;color:#888;">
                    Média · <?= $total_e ?> resposta<?= $total_e > 1 ? 's' : '' ?>
                </div>
            </div>
            <div style="display:flex;align-items:flex-end;gap:4px;height:60px;">
                <?php for ($v = 0; $v <= 10; $v++):
                    $qtd_v = 0;
                    foreach ($resp['distribuicao'] as $d) {
                        if ($d['valor'] == $v) $qtd_v = $d['quantidade'];
                    }
                    $max_qtd = max(1, max(array_column($resp['distribuicao'], 'quantidade')));
                    $alt = $qtd_v > 0 ? max(4, round(($qtd_v / $max_qtd) * 50)) : 2;
                    $cor_v = $v >= 7 ? '#1a9e4a' : ($v >= 5 ? '#f59e0b' : '#c0392b');
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;">
                    <?php if ($qtd_v > 0): ?>
                    <span style="font-size:9px;color:#aaa;"><?= $qtd_v ?></span>
                    <?php endif; ?>
                    <div style="width:100%;height:<?= $alt ?>px;background:<?= $qtd_v > 0 ? $cor_v : '#eee' ?>;
                                border-radius:3px 3px 0 0;opacity:<?= $qtd_v > 0 ? '.85' : '1' ?>;"></div>
                    <span style="font-size:9px;color:#bbb;"><?= $v ?></span>
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>
    <?php if ($cat_ant !== '') echo '</div>'; ?>

    <?php endif; /* empty perguntas */ ?>

</main>

<!-- FAB: GERAR PLANO DE AÇÃO -->
<div id="fabWrap" style="position:fixed;bottom:28px;right:28px;z-index:300;
     display:flex;flex-direction:column;gap:8px;align-items:flex-end;
     transform:translateY(80px);opacity:0;transition:all .25s;">
    <button class="fab-gerar" style="transform:none;opacity:1;position:static;"
            onclick="abrirModalSLA()">
        <i class="fa fa-tasks"></i>
        Criar item de SLA
        <span class="contador-sel" id="contadorSel">0</span>
    </button>
    <button class="fab-gerar" style="transform:none;opacity:1;position:static;
            background:#1a9e4a;" onclick="abrirModalPlano()">
        <i class="fa fa-magic"></i>
        Gerar plano de ação
    </button>
</div>

<!-- MODAL: GERAR PLANO COM IA -->
<div id="modalPlano" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:680px;
                max-height:90vh;overflow:hidden;display:flex;flex-direction:column;
                box-shadow:0 20px 60px rgba(0,0,0,.25);">

        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;">
                <i class="fa fa-magic" style="margin-right:8px;"></i>
                Gerar plano de ação com IA
            </h3>
            <button onclick="fecharModalPlano()"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:22px;cursor:pointer;line-height:1;">×</button>
        </div>

        <div style="overflow-y:auto;padding:24px;flex:1;">

            <!-- RESPOSTAS SELECIONADAS -->
            <div style="margin-bottom:20px;">
                <label class="form-label-g">Respostas selecionadas como insumo</label>
                <div id="previewSelecionadas"
                     style="background:#f8faff;border:1px solid #d8e4f5;border-radius:8px;
                            padding:14px;font-size:13px;color:#444;line-height:1.7;
                            max-height:140px;overflow-y:auto;"></div>
            </div>

            <!-- CONTEXTO ADICIONAL -->
            <div style="margin-bottom:20px;" class="form-group-g">
                <label class="form-label-g">Contexto adicional (opcional)</label>
                <textarea id="contextoAdicional" class="form-input-g" rows="2"
                          placeholder="Ex: Esta questão tem relação com a falta de equipamentos no laboratório de elétrica..."
                          style="resize:vertical;"></textarea>
            </div>

            <!-- TIPO DE RESPONSABILIDADE -->
            <div style="margin-bottom:20px;" class="form-group-g">
                <label class="form-label-g">Quem estará envolvido no plano?</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ([
                        ['gestao',        'Apenas gestão',        'fa-user-secret'],
                        ['docente',       'Apenas docentes',      'fa-graduation-cap'],
                        ['compartilhado', 'Gestão + docentes',    'fa-handshake-o'],
                    ] as [$val, $label, $ico]): ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                  padding:10px 16px;border:1.5px solid #dde3ef;border-radius:8px;
                                  font-size:13px;color:#444;transition:all .15s;"
                           id="radio_<?= $val ?>">
                        <input type="radio" name="tipo_resp" value="<?= $val ?>"
                               style="accent-color:var(--azul);"
                               <?= $val === 'compartilhado' ? 'checked' : '' ?>
                               onchange="destacarRadio()">
                        <i class="fa <?= $ico ?>"></i> <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RESULTADO DA IA -->
            <div id="resultadoIA" style="display:none;">
                <div style="border-top:1px solid #eef0f5;padding-top:20px;margin-bottom:16px;">
                    <label class="form-label-g">
                        <i class="fa fa-magic" style="color:var(--laranja);margin-right:4px;"></i>
                        Plano gerado pela IA — revise e ajuste antes de salvar
                    </label>

                    <div style="margin-bottom:12px;" class="form-group-g">
                        <label class="form-label-g">Título do plano</label>
                        <input type="text" id="r_titulo" class="form-input-g"
                               placeholder="Título do plano de ação">
                    </div>
                    <div style="margin-bottom:12px;" class="form-group-g">
                        <label class="form-label-g">Objetivo</label>
                        <textarea id="r_objetivo" class="form-input-g" rows="2"
                                  style="resize:vertical;"></textarea>
                    </div>

                    <label class="form-label-g">Ações propostas</label>
                    <div id="r_acoes_container"></div>
                    <button type="button" onclick="addAcao()"
                            class="btn-sec btn-sm" style="margin-top:8px;">
                        <i class="fa fa-plus"></i> Adicionar ação
                    </button>
                </div>

                <!-- VISIBILIDADE -->
                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" id="r_visivel" style="accent-color:var(--azul);width:16px;height:16px;">
                        <span>
                            <strong>Tornar visível para a equipe</strong> — o plano (sem as respostas originais) aparece no painel público
                        </span>
                    </label>
                </div>
            </div>

            <!-- LOADING -->
            <div id="loadingIA" style="display:none;text-align:center;padding:32px;">
                <i class="fa fa-spinner fa-spin" style="font-size:32px;color:var(--azul);"></i>
                <div style="margin-top:12px;font-size:14px;color:#888;">
                    Analisando respostas e gerando plano...
                </div>
            </div>

        </div>

        <!-- RODAPÉ DO MODAL -->
        <div style="padding:16px 24px;border-top:1px solid #eef0f5;flex-shrink:0;
                    display:flex;gap:10px;justify-content:flex-end;background:#fafbff;">
            <button class="btn-sec" onclick="fecharModalPlano()">Cancelar</button>
            <button class="btn-pri" id="btnGerarIA" onclick="gerarComIA()">
                <i class="fa fa-magic"></i> Gerar com IA
            </button>
            <button class="btn-pri" id="btnSalvarPlano" style="display:none;"
                    onclick="salvarPlano()">
                <i class="fa fa-save"></i> Salvar plano
            </button>
        </div>
    </div>
</div>

<!-- FORM OCULTO PARA SALVAR PLANO -->
<form id="formSalvar" method="POST" action="plano_acao.php" style="display:none;">
    <input type="hidden" name="acao"            value="salvar_novo">
    <input type="hidden" name="ciclo_id"        value="<?= $ciclo_sel ?>">
    <input type="hidden" name="titulo"          id="fs_titulo">
    <input type="hidden" name="problema"        id="fs_problema">
    <input type="hidden" name="objetivo"        id="fs_objetivo">
    <input type="hidden" name="acoes"           id="fs_acoes">
    <input type="hidden" name="tipo_responsavel" id="fs_tipo">
    <input type="hidden" name="visivel_equipe"  id="fs_visivel" value="0">
    <input type="hidden" name="gerado_por_ia"   value="1">
</form>

<script>
// ── SELEÇÃO DE RESPOSTAS ─────────────────────────────────────
let selecionadas = {}; // id -> {texto, pergunta}

function toggleSelecao(el) {
    const cb = el.querySelector('input[type="checkbox"]');
    const id  = el.dataset.id;
    if (cb.checked) {
        cb.checked = false;
        el.classList.remove('selecionada');
        delete selecionadas[id];
    } else {
        cb.checked = true;
        el.classList.add('selecionada');
        selecionadas[id] = { texto: el.dataset.texto, pergunta: el.dataset.pergunta, categoria: el.dataset.categoria };
    }
    atualizarFAB();
}

function atualizarFAB() {
    const n   = Object.keys(selecionadas).length;
    const fab = document.getElementById('fabGerar');
    document.getElementById('contadorSel').textContent = n;
    const wrap = document.getElementById('fabWrap');
    if (n > 0) { wrap.style.transform='translateY(0)'; wrap.style.opacity='1'; }
    else        { wrap.style.transform='translateY(80px)'; wrap.style.opacity='0'; }
}

// ── MODAL ────────────────────────────────────────────────────
function abrirModalPlano() {
    const itens = Object.values(selecionadas);
    if (!itens.length) return;

    const preview = document.getElementById('previewSelecionadas');
    preview.innerHTML = itens.map(it =>
        `<div style="margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid #e0e9f8;">
            <span style="font-size:11px;color:#888;">${it.pergunta}</span><br>
            "${it.texto}"
         </div>`
    ).join('');

    document.getElementById('resultadoIA').style.display  = 'none';
    document.getElementById('loadingIA').style.display    = 'none';
    document.getElementById('btnGerarIA').style.display   = 'inline-flex';
    document.getElementById('btnSalvarPlano').style.display = 'none';
    document.getElementById('modalPlano').style.display   = 'flex';
    destacarRadio();
}

function fecharModalPlano() {
    document.getElementById('modalPlano').style.display = 'none';
}

document.getElementById('modalPlano').addEventListener('click', e => {
    if (e.target.id === 'modalPlano') fecharModalPlano();
});

function destacarRadio() {
    document.querySelectorAll('[id^="radio_"]').forEach(el => {
        const cb = el.querySelector('input[type="radio"]');
        el.style.borderColor  = cb.checked ? 'var(--azul)' : '#dde3ef';
        el.style.background   = cb.checked ? '#eef3fd'     : '#fff';
        el.style.color        = cb.checked ? 'var(--azul)' : '#444';
        el.style.fontWeight   = cb.checked ? '600'         : '400';
    });
}

// ── GERAR COM IA ─────────────────────────────────────────────
async function gerarComIA() {
    const itens     = Object.values(selecionadas);
    const contexto  = document.getElementById('contextoAdicional').value.trim();
    const tipo_resp = document.querySelector('input[name="tipo_resp"]:checked')?.value || 'compartilhado';

    document.getElementById('loadingIA').style.display  = 'block';
    document.getElementById('btnGerarIA').disabled      = true;
    document.getElementById('resultadoIA').style.display = 'none';

    const insumo = itens.map(it =>
        `Pergunta: "${it.pergunta}"\nResposta: "${it.texto}"`
    ).join('\n\n');

    const tipo_label = {
        gestao: 'apenas a equipe gestora (gestão, supervisão)',
        docente: 'apenas os docentes',
        compartilhado: 'gestão e docentes em conjunto',
    }[tipo_resp];

    const prompt = `Você é um consultor especialista em gestão escolar e clima organizacional.
Analise as seguintes respostas de uma pesquisa de clima de uma escola SENAI e gere um plano de ação estruturado.

RESPOSTAS SELECIONADAS:
${insumo}

${contexto ? `CONTEXTO ADICIONAL INFORMADO PELO GESTOR:\n${contexto}\n` : ''}

ENVOLVIDOS NA EXECUÇÃO: ${tipo_label}

Responda APENAS em JSON válido, sem texto antes ou depois, sem markdown, sem blocos de código.
O JSON deve ter exatamente esta estrutura:
{
  "titulo": "título conciso do plano de ação (máx 80 caracteres)",
  "objetivo": "o que se quer alcançar com este plano (2-3 frases)",
  "acoes": [
    {
      "descricao": "descrição clara e acionável da ação",
      "responsavel_tipo": "gestao" ou "docente" ou "compartilhado",
      "responsavel_sugerido": "cargo ou papel sugerido (ex: Supervisão Pedagógica, Docentes de TI)",
      "prazo_sugerido": "prazo realista (ex: 30 dias, 60 dias, próximo ciclo)",
      "como": "como executar esta ação de forma prática"
    }
  ]
}

Gere entre 3 e 5 ações práticas e realizáveis. Seja específico para o contexto de uma escola técnica SENAI.`;

    try {
        const resp = await fetch('api_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                model: 'claude-sonnet-4-20250514',
                max_tokens: 1000,
                messages: [{ role: 'user', content: prompt }]
            })
        });

        const data   = await resp.json();
        const texto  = data.content?.[0]?.text || '';
        const clean  = texto.replace(/```json|```/g, '').trim();
        const plano  = JSON.parse(clean);

        document.getElementById('r_titulo').value   = plano.titulo   || '';
        document.getElementById('r_objetivo').value = plano.objetivo || '';

        // Renderiza ações editáveis
        const container = document.getElementById('r_acoes_container');
        container.innerHTML = '';
        (plano.acoes || []).forEach((a, i) => addAcao(a));

        document.getElementById('resultadoIA').style.display   = 'block';
        document.getElementById('btnSalvarPlano').style.display = 'inline-flex';
        document.getElementById('btnGerarIA').textContent      = ' Regenerar';
        document.getElementById('btnGerarIA').innerHTML        = '<i class="fa fa-refresh"></i> Regenerar';

    } catch (err) {
        alert('Erro ao gerar plano com IA. Tente novamente.\n' + err.message);
    } finally {
        document.getElementById('loadingIA').style.display = 'none';
        document.getElementById('btnGerarIA').disabled     = false;
    }
}

// ── AÇÕES EDITÁVEIS ──────────────────────────────────────────
let acaoIdx = 0;
function addAcao(dados = {}) {
    const idx = acaoIdx++;
    const container = document.getElementById('r_acoes_container');
    const div = document.createElement('div');
    div.id = `acao_${idx}`;
    div.style.cssText = 'background:#f8faff;border:1px solid #dde3ef;border-radius:10px;' +
                        'padding:14px;margin-bottom:10px;';
    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;color:var(--azul);">Ação ${idx + 1}</span>
            <button type="button" onclick="document.getElementById('acao_${idx}').remove()"
                    style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:13px;">
                <i class="fa fa-trash"></i>
            </button>
        </div>
        <div class="form-group-g">
            <label class="form-label-g">Descrição</label>
            <textarea class="form-input-g acao-desc" rows="2" style="resize:vertical;"
                      placeholder="O que será feito?">${dados.descricao || ''}</textarea>
        </div>
        <div class="form-group-g">
            <label class="form-label-g">Como executar</label>
            <textarea class="form-input-g acao-como" rows="2" style="resize:vertical;"
                      placeholder="Como será feito na prática?">${dados.como || ''}</textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
            <div>
                <label class="form-label-g">Responsável (tipo)</label>
                <select class="form-input-g acao-tipo">
                    <option value="gestao"        ${dados.responsavel_tipo==='gestao'        ?'selected':''}>Gestão</option>
                    <option value="docente"       ${dados.responsavel_tipo==='docente'       ?'selected':''}>Docente</option>
                    <option value="compartilhado" ${dados.responsavel_tipo==='compartilhado' ?'selected':''}>Compartilhado</option>
                </select>
            </div>
            <div>
                <label class="form-label-g">Papel sugerido</label>
                <input type="text" class="form-input-g acao-papel"
                       placeholder="Ex: Sup. Pedagógica"
                       value="${dados.responsavel_sugerido || ''}">
            </div>
            <div>
                <label class="form-label-g">Prazo sugerido</label>
                <input type="text" class="form-input-g acao-prazo"
                       placeholder="Ex: 30 dias"
                       value="${dados.prazo_sugerido || ''}">
            </div>
        </div>`;
    container.appendChild(div);
}

// ── SALVAR PLANO ─────────────────────────────────────────────
function salvarPlano() {
    const acoes = [];
    document.querySelectorAll('[id^="acao_"]').forEach(div => {
        acoes.push({
            descricao:           div.querySelector('.acao-desc')?.value  || '',
            como:                div.querySelector('.acao-como')?.value  || '',
            responsavel_tipo:    div.querySelector('.acao-tipo')?.value  || 'gestao',
            responsavel_sugerido:div.querySelector('.acao-papel')?.value || '',
            prazo_sugerido:      div.querySelector('.acao-prazo')?.value || '',
            status:              'pendente',
        });
    });

    const insumo = Object.values(selecionadas).map(it => it.texto).join('\n---\n');
    const tipo   = document.querySelector('input[name="tipo_resp"]:checked')?.value || 'compartilhado';
    const vis    = document.getElementById('r_visivel').checked ? '1' : '0';

    document.getElementById('fs_titulo').value   = document.getElementById('r_titulo').value;
    document.getElementById('fs_problema').value = insumo;
    document.getElementById('fs_objetivo').value = document.getElementById('r_objetivo').value;
    document.getElementById('fs_acoes').value    = JSON.stringify(acoes);
    document.getElementById('fs_tipo').value     = tipo;
    document.getElementById('fs_visivel').value  = vis;

    document.getElementById('formSalvar').submit();
}
</script>

<!-- PHP DATA FOR JS -->
<script>
const SLA_CFG = <?= json_encode($sla_cfg, JSON_UNESCAPED_UNICODE) ?>;
const CATEGORIAS_LIST = <?= json_encode(array_keys($sla_cfg), JSON_UNESCAPED_UNICODE) ?>;
const USUARIOS_GESTAO   = <?= json_encode($usuarios_gestao,   JSON_UNESCAPED_UNICODE) ?>;
const USUARIOS_DOCENTES = <?= json_encode($usuarios_docentes, JSON_UNESCAPED_UNICODE) ?>;
const CICLO_ID = <?= (int)$ciclo_sel ?>;
</script>

<!-- MODAL: CRIAR ITEM DE SLA -->
<div id="modalSLA" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:620px;
                max-height:92vh;overflow:hidden;display:flex;flex-direction:column;
                box-shadow:0 20px 60px rgba(0,0,0,.25);">

        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;">
                <i class="fa fa-tasks" style="margin-right:8px;"></i>Criar item de SLA
            </h3>
            <button onclick="fecharModalSLA()"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:22px;cursor:pointer;line-height:1;">×</button>
        </div>

        <div style="overflow-y:auto;padding:24px;flex:1;">

            <!-- TÍTULO -->
            <div class="form-group-g">
                <label class="form-label-g">Título do item *</label>
                <input type="text" id="sla_titulo" class="form-input-g"
                       placeholder="Ex: Falta de equipamentos no laboratório de elétrica"
                       maxlength="200">
                <div style="font-size:11px;color:#aaa;margin-top:4px;">
                    Este título ficará visível no painel público quando marcado como visível.
                </div>
            </div>

            <!-- RESPOSTAS SELECIONADAS -->
            <div class="form-group-g">
                <label class="form-label-g">Respostas selecionadas como base</label>
                <div id="sla_respostas_preview"
                     style="background:#f8faff;border:1px solid #d8e4f5;border-radius:8px;
                            padding:12px 14px;font-size:13px;color:#555;line-height:1.7;
                            max-height:120px;overflow-y:auto;"></div>
            </div>

            <!-- CATEGORIA + SLA AUTOMÁTICO -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group-g">
                    <label class="form-label-g">Categoria *</label>
                    <select id="sla_categoria" class="form-input-g" onchange="calcularPrazos()">
                        <option value="">— Selecione —</option>
                    </select>
                </div>
                <div class="form-group-g">
                    <label class="form-label-g">Modo de exibição público</label>
                    <select id="sla_modo" class="form-input-g">
                        <option value="chamado">Chamado (status simples)</option>
                        <option value="plano">Plano de ação (tabela detalhada)</option>
                    </select>
                </div>
            </div>

            <!-- PRAZOS (pré-preenchidos, editáveis) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group-g">
                    <label class="form-label-g">
                        Prazo de resposta
                        <span id="sla_hint_resp"
                              style="font-size:10px;color:#aaa;font-weight:400;margin-left:4px;"></span>
                    </label>
                    <input type="date" id="sla_prazo_resp" class="form-input-g">
                    <div style="font-size:11px;color:#aaa;margin-top:3px;">
                        Quando o responsável deve dar uma posição inicial.
                    </div>
                </div>
                <div class="form-group-g">
                    <label class="form-label-g">
                        Prazo de resolução
                        <span id="sla_hint_res"
                              style="font-size:10px;color:#aaa;font-weight:400;margin-left:4px;"></span>
                    </label>
                    <input type="date" id="sla_prazo_res" class="form-input-g">
                    <div style="font-size:11px;color:#aaa;margin-top:3px;">
                        Quando o problema deve estar resolvido.
                    </div>
                </div>
            </div>

            <!-- RESPONSÁVEL -->
            <div class="form-group-g">
                <label class="form-label-g">Responsável *</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                    <?php foreach ([
                        ['gestao',      'fa-user-secret',    'Gestão'],
                        ['supervisao',  'fa-sitemap',        'Supervisão'],
                        ['docente',     'fa-graduation-cap', 'Docentes'],
                    ] as [$val,$ico,$lbl]): ?>
                    <label id="sla_radio_<?= $val ?>"
                           style="display:flex;align-items:center;gap:7px;cursor:pointer;
                                  padding:8px 14px;border:1.5px solid #dde3ef;border-radius:8px;
                                  font-size:13px;color:#444;transition:all .15s;">
                        <input type="radio" name="sla_tipo_resp" value="<?= $val ?>"
                               style="accent-color:var(--azul);"
                               <?= $val === 'docente' ? 'checked' : '' ?>
                               onchange="atualizarRadioSLA();atualizarListaResp()">
                        <i class="fa <?= $ico ?>"></i> <?= $lbl ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Específico ou geral -->
                <div id="sla_resp_detalhe">
                    <div style="display:flex;gap:10px;margin-bottom:10px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="radio" name="sla_especifico" value="todos"
                                   checked onchange="atualizarListaResp()"
                                   style="accent-color:var(--azul);">
                            <span id="sla_label_todos">Todos os docentes</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="radio" name="sla_especifico" value="especifico"
                                   onchange="atualizarListaResp()"
                                   style="accent-color:var(--azul);">
                            Pessoa específica
                        </label>
                    </div>
                    <select id="sla_pessoa" class="form-input-g" style="display:none;">
                        <option value="">— Selecione —</option>
                    </select>
                </div>
            </div>

            <!-- VISIBILIDADE -->
            <div class="form-group-g">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                    <input type="checkbox" id="sla_visivel"
                           style="accent-color:var(--azul);width:16px;height:16px;" checked>
                    <span>
                        <strong>Visível no painel público</strong> — 
                        o título e status aparecem para toda a equipe
                        (sem as respostas originais)
                    </span>
                </label>
            </div>

        </div>

        <div style="padding:16px 24px;border-top:1px solid #eef0f5;flex-shrink:0;
                    display:flex;gap:10px;justify-content:flex-end;background:#fafbff;">
            <button class="btn-sec" onclick="fecharModalSLA()">Cancelar</button>
            <button class="btn-pri" onclick="salvarSLA()">
                <i class="fa fa-save"></i> Criar item de SLA
            </button>
        </div>
    </div>
</div>

<!-- FORM OCULTO PARA SALVAR SLA -->
<form id="formSLA" method="POST" action="itens_sla.php" style="display:none;">
    <input type="hidden" name="acao"               value="criar">
    <input type="hidden" name="ciclo_id"           id="sla_f_ciclo"    value="">
    <input type="hidden" name="titulo"             id="sla_f_titulo">
    <input type="hidden" name="categoria"          id="sla_f_categoria">
    <input type="hidden" name="conteudo"           id="sla_f_conteudo">
    <input type="hidden" name="responsavel_tipo"   id="sla_f_tipo">
    <input type="hidden" name="responsavel_id"     id="sla_f_resp_id">
    <input type="hidden" name="responsavel_publico" id="sla_f_resp_pub">
    <input type="hidden" name="prazo"              id="sla_f_prazo_res">
    <input type="hidden" name="prazo_resposta"     id="sla_f_prazo_resp">
    <input type="hidden" name="visivel_equipe"     id="sla_f_visivel"  value="1">
    <input type="hidden" name="modo_exibicao"      id="sla_f_modo">
    <input type="hidden" name="respostas_ids"      id="sla_f_ids">
</form>

<script>
// ── MODAL SLA ────────────────────────────────────────────────
function abrirModalSLA() {
    const itens = Object.values(selecionadas);
    if (!itens.length) return;

    // Preview das respostas
    document.getElementById('sla_respostas_preview').innerHTML =
        itens.map(it =>
            `<div style="margin-bottom:5px;padding-bottom:5px;border-bottom:1px solid #e0e9f8;">
                <span style="font-size:10px;color:#aaa;">${it.pergunta}</span><br>
                "${it.texto}"
             </div>`
        ).join('');

    // Preenche categorias
    const sel = document.getElementById('sla_categoria');
    sel.innerHTML = '<option value="">— Selecione —</option>';

    // Tenta inferir a categoria da primeira resposta selecionada
    const primCategoria = Object.values(selecionadas)[0]?.categoria || '';
    CATEGORIAS_LIST.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat; opt.textContent = cat;
        if (cat === primCategoria) opt.selected = true;
        sel.appendChild(opt);
    });

    calcularPrazos();
    atualizarRadioSLA();
    atualizarListaResp();
    document.getElementById('modalSLA').style.display = 'flex';
}

function fecharModalSLA() {
    document.getElementById('modalSLA').style.display = 'none';
}
document.getElementById('modalSLA').addEventListener('click', e => {
    if (e.target.id === 'modalSLA') fecharModalSLA();
});

// ── CALCULA PRAZOS AUTOMATICAMENTE ───────────────────────────
function calcularPrazos() {
    const cat = document.getElementById('sla_categoria').value;
    if (!cat || !SLA_CFG[cat]) return;

    const cfg  = SLA_CFG[cat];
    const hoje = new Date();

    // Prazo de resposta
    const dr = new Date(hoje);
    dr.setDate(dr.getDate() + cfg.resposta);
    document.getElementById('sla_prazo_resp').value =
        dr.toISOString().split('T')[0];
    document.getElementById('sla_hint_resp').textContent =
        `(padrão: ${cfg.resposta} dia${cfg.resposta > 1 ? 's' : ''})`;

    // Prazo de resolução
    const dres = new Date(hoje);
    dres.setDate(dres.getDate() + cfg.resolucao);
    document.getElementById('sla_prazo_res').value =
        dres.toISOString().split('T')[0];
    document.getElementById('sla_hint_res').textContent =
        `(padrão: ${cfg.resolucao} dias)`;
}

// ── ATUALIZA RADIO RESPONSÁVEL ───────────────────────────────
function atualizarRadioSLA() {
    const tipo = document.querySelector('input[name="sla_tipo_resp"]:checked')?.value;
    ['gestao','supervisao','docente'].forEach(v => {
        const el = document.getElementById(`sla_radio_${v}`);
        const cb = el.querySelector('input[type="radio"]');
        el.style.borderColor = cb.checked ? 'var(--azul)' : '#dde3ef';
        el.style.background  = cb.checked ? '#eef3fd'     : '#fff';
        el.style.color       = cb.checked ? 'var(--azul)' : '#444';
        el.style.fontWeight  = cb.checked ? '600'         : '400';
    });

    const labels = {
        gestao:     'Toda a gestão',
        supervisao: 'Toda a supervisão',
        docente:    'Todos os docentes',
    };
    document.getElementById('sla_label_todos').textContent = labels[tipo] || 'Todos';
}

// ── ATUALIZA LISTA DE PESSOAS ─────────────────────────────────
function atualizarListaResp() {
    const tipo       = document.querySelector('input[name="sla_tipo_resp"]:checked')?.value;
    const especifico = document.querySelector('input[name="sla_especifico"]:checked')?.value;
    const sel        = document.getElementById('sla_pessoa');

    if (especifico === 'especifico') {
        sel.style.display = 'block';
        sel.innerHTML     = '<option value="">— Selecione —</option>';
        const lista = tipo === 'docente' ? USUARIOS_DOCENTES : USUARIOS_GESTAO;
        lista.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id; opt.textContent = u.nome;
            sel.appendChild(opt);
        });
    } else {
        sel.style.display = 'none';
        sel.innerHTML     = '';
    }
}

// ── SALVAR SLA ────────────────────────────────────────────────
function salvarSLA() {
    const titulo    = document.getElementById('sla_titulo').value.trim();
    const categoria = document.getElementById('sla_categoria').value;
    const tipo      = document.querySelector('input[name="sla_tipo_resp"]:checked')?.value;
    const especifico= document.querySelector('input[name="sla_especifico"]:checked')?.value;
    const pessoaId  = document.getElementById('sla_pessoa').value;

    if (!titulo)    { alert('Informe o título do item.'); return; }
    if (!categoria) { alert('Selecione a categoria.'); return; }
    if (!tipo)      { alert('Selecione o responsável.'); return; }
    if (especifico === 'especifico' && !pessoaId) {
        alert('Selecione a pessoa específica.'); return;
    }

    // Label público (nunca expõe nome)
    const pubLabels = {
        gestao: 'Gestão', supervisao: 'Supervisão', docente: 'Docentes'
    };

    const insumo = Object.values(selecionadas).map(it => it.texto).join('\n---\n');
    const ids    = Object.keys(selecionadas).join(',');

    document.getElementById('sla_f_ciclo').value    = CICLO_ID;
    document.getElementById('sla_f_titulo').value   = titulo;
    document.getElementById('sla_f_categoria').value= categoria;
    document.getElementById('sla_f_conteudo').value = insumo;
    document.getElementById('sla_f_tipo').value     = tipo;
    document.getElementById('sla_f_resp_id').value  = especifico === 'especifico' ? pessoaId : '';
    document.getElementById('sla_f_resp_pub').value = pubLabels[tipo] || tipo;
    document.getElementById('sla_f_prazo_resp').value= document.getElementById('sla_prazo_resp').value;
    document.getElementById('sla_f_prazo_res').value = document.getElementById('sla_prazo_res').value;
    document.getElementById('sla_f_visivel').value  = document.getElementById('sla_visivel').checked ? '1' : '0';
    document.getElementById('sla_f_modo').value     = document.getElementById('sla_modo').value;
    document.getElementById('sla_f_ids').value      = ids;

    document.getElementById('formSLA').submit();
}
</script>

<script>
function toggleExport() {
    const d = document.getElementById('exportDrop');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
    const wrap = document.getElementById('exportDropWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('exportDrop').style.display = 'none';
    }
});
</script>
</body>
</html>