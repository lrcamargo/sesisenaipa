<?php
// pulso/gestao/relatorios.php — Relatórios e gráficos de favorabilidade
include('../../conexao.php');
require_once '../_guard.php';
exige_gerente();

$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;

// Lista de ciclos
$ciclos_stmt = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ? ORDER BY id DESC
");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclo_sel && $ciclos_lista) {
    // Prefere encerrado, senão o mais recente
    foreach ($ciclos_lista as $c) {
        if ($c['status'] === 'encerrado') { $ciclo_sel = $c['id']; break; }
    }
    if (!$ciclo_sel) $ciclo_sel = $ciclos_lista[0]['id'];
}

$ciclo_atual = null;
foreach ($ciclos_lista as $c) {
    if ($c['id'] == $ciclo_sel) { $ciclo_atual = $c; break; }
}

// ── Dados do relatório ────────────────────────────────────────
$participacao = 0;
$por_categoria = []; // favorabilidade por categoria
$itens_sla     = [];
$evolucao      = []; // comparativo entre ciclos

if ($ciclo_sel) {
    // Participação
    $q = $pdo->prepare("SELECT total FROM pulso_participacao WHERE ciclo_id = ?");
    $q->execute([$ciclo_sel]);
    $participacao = (int)($q->fetchColumn() ?: 0);

    // Favorabilidade por categoria (perguntas de escolha, valores 4-6 = favorável)
    $q2 = $pdo->prepare("
        SELECT p.categoria,
               SUM(ra.quantidade) AS total_respostas,
               SUM(CASE WHEN ra.valor >= 4 THEN ra.quantidade ELSE 0 END) AS favoraveis
        FROM pulso_perguntas p
        INNER JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
        WHERE p.ciclo_id = ? AND p.tipo = 'escolha'
        GROUP BY p.categoria
        ORDER BY SUM(CASE WHEN ra.valor >= 4 THEN ra.quantidade ELSE 0 END) / SUM(ra.quantidade) DESC
    ");
    $q2->execute([$ciclo_sel]);
    $por_categoria = $q2->fetchAll(PDO::FETCH_ASSOC);

    // Itens de SLA resumo
    $q3 = $pdo->prepare("
        SELECT status, COUNT(*) AS n FROM pulso_itens_sla
        WHERE ciclo_id = ? GROUP BY status
    ");
    $q3->execute([$ciclo_sel]);
    foreach ($q3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $itens_sla[$r['status']] = $r['n'];
    }

    // Evolução: favorabilidade global dos últimos 4 ciclos
    $q4 = $pdo->prepare("
        SELECT c.id, c.titulo,
               SUM(ra.quantidade) AS total,
               SUM(CASE WHEN ra.valor >= 4 THEN ra.quantidade ELSE 0 END) AS fav
        FROM pulso_ciclos c
        INNER JOIN pulso_perguntas p ON p.ciclo_id = c.id AND p.tipo = 'escolha'
        INNER JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
        WHERE c.unidade_id = ?
        GROUP BY c.id
        ORDER BY c.id ASC
        LIMIT 4
    ");
    $q4->execute([$g_unidade_id]);
    $evolucao = $q4->fetchAll(PDO::FETCH_ASSOC);
}

// Favorabilidade global do ciclo
$fav_global = 0;
if (!empty($por_categoria)) {
    $tot = array_sum(array_column($por_categoria, 'total_respostas'));
    $fav = array_sum(array_column($por_categoria, 'favoraveis'));
    $fav_global = $tot > 0 ? round(($fav / $tot) * 100, 1) : 0;
}

$titulo_pagina = 'Relatórios';
$pagina_ativa  = 'relatorios';
?>
<?php include '../_layout_head.php'; ?>
<style>
.barra-fav {
    height: 10px; border-radius: 5px;
    background: #eee; overflow: hidden; flex: 1;
}
.barra-fav-fill {
    height: 10px; border-radius: 5px;
    transition: width .5s ease;
}
.gauge-wrap {
    text-align: center; padding: 24px 16px;
}
.gauge-num {
    font-size: 52px; font-weight: 800; line-height: 1;
}
.gauge-label {
    font-size: 13px; color: #888; margin-top: 6px;
}
.gauge-zona {
    font-size: 12px; font-weight: 600;
    padding: 4px 12px; border-radius: 12px; margin-top: 8px;
    display: inline-block;
}
</style>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-bar-chart" style="color:var(--laranja);margin-right:8px;"></i>Relatórios</h1>
            <div class="page-sub">Favorabilidade por categoria e evolução entre ciclos</div>
        </div>
        <a href="?ciclo=<?= $ciclo_sel ?>&exportar=1" class="btn-sec">
            <i class="fa fa-download"></i> Exportar CSV
        </a>
    </div>

    <!-- EXPORTAÇÃO CSV -->
    <?php
    if (isset($_GET['exportar']) && $ciclo_sel && $por_categoria) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_pulso_ciclo_' . $ciclo_sel . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        echo "Categoria;Total Respostas;Favoráveis;Favorabilidade %\n";
        foreach ($por_categoria as $cat) {
            $pct = $cat['total_respostas'] > 0
                ? round(($cat['favoraveis'] / $cat['total_respostas']) * 100, 1) : 0;
            echo implode(';', [
                '"' . str_replace('"', '""', $cat['categoria']) . '"',
                $cat['total_respostas'],
                $cat['favoraveis'],
                number_format($pct, 1, ',', '.')
            ]) . "\n";
        }
        exit;
    }
    ?>

    <!-- SELETOR DE CICLO -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <label class="form-label-g" style="margin:0;">Ciclo:</label>
                <select name="ciclo" class="form-input-g" style="max-width:300px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['titulo']) ?> — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (!$ciclo_sel || empty($por_categoria)): ?>
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-bar-chart" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhum dado disponível. Os relatórios aparecem após o encerramento de um ciclo com respostas.
        </div>
    </div>
    <?php else: ?>

    <!-- INDICADORES GLOBAIS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">

        <div class="g-card">
            <div class="gauge-wrap">
                <?php
                $cor_global = $fav_global >= 70 ? '#1a9e4a' : ($fav_global >= 50 ? '#f59e0b' : '#c0392b');
                $zona = $fav_global >= 70 ? 'Qualidade' : ($fav_global >= 50 ? 'Em Desenvolvimento' : 'Crítico');
                $zona_bg = $fav_global >= 70 ? '#eafaf1' : ($fav_global >= 50 ? '#fef9e7' : '#fff0eb');
                ?>
                <div class="gauge-num" style="color:<?= $cor_global ?>;"><?= $fav_global ?>%</div>
                <div class="gauge-label">Favorabilidade global</div>
                <span class="gauge-zona" style="background:<?= $zona_bg ?>;color:<?= $cor_global ?>;">
                    <?= $zona ?>
                </span>
            </div>
        </div>

        <div class="g-card">
            <div class="gauge-wrap">
                <div class="gauge-num" style="color:var(--azul);"><?= $participacao ?></div>
                <div class="gauge-label">Participantes</div>
            </div>
        </div>

        <div class="g-card">
            <div class="gauge-wrap">
                <div class="gauge-num" style="color:#1a9e4a;"><?= $itens_sla['resolvido'] ?? 0 ?></div>
                <div class="gauge-label">Itens resolvidos</div>
                <?php $tot_sla = array_sum($itens_sla); ?>
                <?php if ($tot_sla > 0): ?>
                <span class="gauge-zona" style="background:#eafaf1;color:#1a9e4a;">
                    <?= round((($itens_sla['resolvido'] ?? 0) / $tot_sla) * 100) ?>% do total
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="g-card">
            <div class="gauge-wrap">
                <div class="gauge-num" style="color:#c0392b;"><?= $itens_sla['aberto'] ?? 0 ?></div>
                <div class="gauge-label">Itens em aberto</div>
            </div>
        </div>

    </div>

    <!-- FAVORABILIDADE POR CATEGORIA -->
    <div class="g-card" style="margin-bottom:24px;">
        <div class="g-card-header">
            <h2><i class="fa fa-list-ol"></i> Favorabilidade por categoria</h2>
        </div>
        <div class="g-card-body">
            <?php foreach ($por_categoria as $cat):
                $pct = $cat['total_respostas'] > 0
                    ? round(($cat['favoraveis'] / $cat['total_respostas']) * 100, 1) : 0;
                $cor = $pct >= 70 ? '#1a9e4a' : ($pct >= 50 ? '#f59e0b' : '#c0392b');
            ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:14px;font-weight:600;color:var(--texto);">
                        <?= htmlspecialchars($cat['categoria']) ?>
                    </span>
                    <span style="font-size:15px;font-weight:800;color:<?= $cor ?>;">
                        <?= $pct ?>%
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="barra-fav">
                        <div class="barra-fav-fill"
                             style="width:<?= $pct ?>%;background:<?= $cor ?>;"></div>
                    </div>
                    <span style="font-size:11px;color:#aaa;white-space:nowrap;">
                        <?= $cat['favoraveis'] ?>/<?= $cat['total_respostas'] ?> resp.
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- EVOLUÇÃO ENTRE CICLOS -->
    <?php if (count($evolucao) > 1): ?>
    <div class="g-card">
        <div class="g-card-header">
            <h2><i class="fa fa-line-chart"></i> Evolução entre ciclos</h2>
        </div>
        <div class="g-card-body">
            <div style="display:flex;align-items:flex-end;gap:16px;height:160px;padding-bottom:8px;">
                <?php foreach ($evolucao as $ev):
                    $pct_ev = $ev['total'] > 0 ? round(($ev['fav'] / $ev['total']) * 100, 1) : 0;
                    $cor_ev = $pct_ev >= 70 ? '#1a9e4a' : ($pct_ev >= 50 ? '#f59e0b' : '#c0392b');
                    $alt    = max(20, $pct_ev * 1.4); // px height proporcional
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;">
                    <span style="font-size:13px;font-weight:800;color:<?= $cor_ev ?>;">
                        <?= $pct_ev ?>%
                    </span>
                    <div style="width:100%;height:<?= $alt ?>px;border-radius:6px 6px 0 0;
                                background:<?= $ev['id'] == $ciclo_sel ? $cor_ev : 'rgba(22,65,148,.15)' ?>;
                                border:2px solid <?= $cor_ev ?>;
                                transition:height .4s;">
                    </div>
                    <span style="font-size:11px;color:#888;text-align:center;line-height:1.3;">
                        <?= htmlspecialchars($ev['titulo']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:16px;margin-top:12px;font-size:11px;color:#aaa;flex-wrap:wrap;">
                <span style="display:flex;align-items:center;gap:4px;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#1a9e4a;"></span>
                    Qualidade ≥ 70%
                </span>
                <span style="display:flex;align-items:center;gap:4px;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#f59e0b;"></span>
                    Em Desenvolvimento 50–70%
                </span>
                <span style="display:flex;align-items:center;gap:4px;">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#c0392b;"></span>
                    Crítico &lt; 50%
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; /* dados disponíveis */ ?>

</main>
</body>
</html>