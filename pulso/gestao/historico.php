<?php
// pulso/gestao/historico.php — Arquivo de ciclos anteriores
include('../../conexao.php');
require_once '../_guard.php';

// Busca todos os ciclos da unidade com dados agregados
$stmt = $pdo->prepare("
    SELECT
        c.id, c.titulo, c.status,
        c.aberto_em, c.encerrado_em,
        COUNT(DISTINCT p.id)   AS total_perguntas,
        pp.total               AS participacao,
        COUNT(DISTINCT s.id)   AS total_itens,
        SUM(s.status = 'resolvido')   AS itens_resolvidos,
        SUM(s.status = 'em_andamento') AS itens_andamento,
        SUM(s.status = 'escalado')    AS itens_escalados,
        SUM(s.status = 'aberto')      AS itens_abertos,
        d.publicada
    FROM pulso_ciclos c
    LEFT JOIN pulso_perguntas p        ON p.ciclo_id  = c.id
    LEFT JOIN pulso_participacao pp    ON pp.ciclo_id = c.id
    LEFT JOIN pulso_itens_sla s        ON s.ciclo_id  = c.id
    LEFT JOIN pulso_devolutivas d      ON d.ciclo_id  = c.id
    WHERE c.unidade_id = ?
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmt->execute([$g_unidade_id]);
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ciclo selecionado para ver detalhes
$ciclo_det = filter_input(INPUT_GET, 'ver', FILTER_VALIDATE_INT) ?: 0;
$detalhe   = null;
$respostas = [];
$devolutiva_txt = '';

if ($ciclo_det) {
    // Dados do ciclo
    $q = $pdo->prepare("
        SELECT c.*, pp.total AS participacao
        FROM pulso_ciclos c
        LEFT JOIN pulso_participacao pp ON pp.ciclo_id = c.id
        WHERE c.id = ? AND c.unidade_id = ?
    ");
    $q->execute([$ciclo_det, $g_unidade_id]);
    $detalhe = $q->fetch(PDO::FETCH_ASSOC);

    if ($detalhe) {
        // Respostas agregadas por pergunta (escolha/escala)
        $q2 = $pdo->prepare("
            SELECT p.categoria, p.texto, p.tipo,
                   ra.valor, SUM(ra.quantidade) AS total
            FROM pulso_perguntas p
            LEFT JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
            WHERE p.ciclo_id = ? AND p.tipo != 'aberta'
            GROUP BY p.id, ra.valor
            ORDER BY p.categoria, p.ordem, p.id, ra.valor DESC
        ");
        $q2->execute([$ciclo_det]);
        foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['categoria'] . '||' . $r['texto'];
            $respostas[$key]['categoria'] = $r['categoria'];
            $respostas[$key]['texto']     = $r['texto'];
            $respostas[$key]['tipo']      = $r['tipo'];
            $respostas[$key]['valores'][$r['valor']] = (int)$r['total'];
        }

        // Devolutiva
        $q3 = $pdo->prepare("SELECT conteudo FROM pulso_devolutivas WHERE ciclo_id = ? AND publicada=1");
        $q3->execute([$ciclo_det]);
        $devolutiva_txt = $q3->fetchColumn() ?: '';
    }
}

$titulo_pagina = 'Histórico';
$pagina_ativa  = 'historico';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-history" style="color:var(--laranja);margin-right:8px;"></i>Histórico de Ciclos</h1>
            <div class="page-sub">Arquivo de todos os ciclos realizados na unidade</div>
        </div>
    </div>

    <!-- LISTA DE CICLOS -->
    <div class="g-card" style="margin-bottom:24px;">
        <?php if (empty($ciclos)): ?>
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhum ciclo realizado ainda.
        </div>
        <?php else: ?>
        <table class="g-table">
            <thead>
                <tr>
                    <th>Ciclo</th>
                    <th>Status</th>
                    <th style="text-align:center;">Partic.</th>
                    <th style="text-align:center;">Perguntas</th>
                    <th style="text-align:center;">Itens SLA</th>
                    <th style="text-align:center;">Resolvidos</th>
                    <th style="text-align:center;">Devolutiva</th>
                    <th>Período</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ciclos as $i => $c):
                $prox = $ciclos[$i + 1] ?? null; // ciclo anterior para comparação
                $badge = match($c['status']) {
                    'aberto'    => ['badge-escolha',  '🟢 Aberto'],
                    'rascunho'  => ['badge-variavel', '✏️ Rascunho'],
                    'encerrado' => ['badge-fixa',     '🔵 Encerrado'],
                    default     => ['', $c['status']]
                };
                $taxa = $c['total_itens'] > 0
                    ? round(($c['itens_resolvidos'] / $c['total_itens']) * 100)
                    : null;
            ?>
            <tr style="<?= $ciclo_det == $c['id'] ? 'background:#eef3fd;' : '' ?>">
                <td style="font-weight:700;"><?= htmlspecialchars($c['titulo']) ?></td>
                <td><span class="badge-tipo <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                <td style="text-align:center;font-weight:600;"><?= $c['participacao'] ?? '—' ?></td>
                <td style="text-align:center;"><?= $c['total_perguntas'] ?></td>
                <td style="text-align:center;"><?= $c['total_itens'] ?></td>
                <td style="text-align:center;">
                    <?php if ($taxa !== null): ?>
                    <span style="font-weight:700;color:<?= $taxa >= 70 ? '#1a9e4a' : ($taxa >= 40 ? '#f59e0b' : '#c0392b') ?>">
                        <?= $taxa ?>%
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?= $c['publicada'] ? '<span style="color:#1a9e4a;font-weight:600;">✔ Publicada</span>' : '<span style="color:#aaa;">—</span>' ?>
                </td>
                <td style="font-size:12px;color:#888;">
                    <?= $c['aberto_em']    ? date('d/m/Y', strtotime($c['aberto_em']))    : '—' ?>
                    <?= $c['encerrado_em'] ? ' → ' . date('d/m/Y', strtotime($c['encerrado_em'])) : '' ?>
                </td>
                <td>
                    <a href="?ver=<?= $c['id'] ?>" class="btn-sec btn-sm">
                        <i class="fa fa-search"></i> Detalhes
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- DETALHES DO CICLO SELECIONADO -->
    <?php if ($detalhe): ?>
    <div style="margin-bottom:8px;">
        <h2 style="font-size:17px;font-weight:700;color:var(--azul);">
            <i class="fa fa-search" style="margin-right:6px;"></i>
            Detalhes: <?= htmlspecialchars($detalhe['titulo']) ?>
        </h2>
    </div>

    <!-- Respostas agregadas -->
    <?php if (!empty($respostas)): ?>
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-header">
            <h2><i class="fa fa-bar-chart"></i> Respostas por pergunta</h2>
        </div>
        <div class="g-card-body" style="padding:0;">
            <?php
            $cat_ant = '';
            foreach ($respostas as $r):
                if ($r['categoria'] !== $cat_ant):
                    $cat_ant = $r['categoria'];
            ?>
            <div style="background:var(--azul);color:#fff;padding:10px 20px;
                        font-size:12px;font-weight:700;letter-spacing:.5px;">
                <?= htmlspecialchars($r['categoria']) ?>
            </div>
            <?php endif; ?>
            <div style="padding:16px 20px;border-bottom:1px solid #f0f3f8;">
                <div style="font-size:13px;font-weight:500;color:var(--texto);margin-bottom:10px;">
                    <?= htmlspecialchars($r['texto']) ?>
                </div>
                <?php
                    $total_resp = array_sum($r['valores'] ?? []);
                    arsort($r['valores']);
                    $opcoes_label = [
                        6=>'Concordo totalmente', 5=>'Concordo em grande parte',
                        4=>'Mais concordo', 3=>'Mais discordo',
                        2=>'Discordo em grande parte', 1=>'Discordo totalmente'
                    ];
                    // Favorabilidade: valores 4,5,6 = favorável
                    $fav = ($r['valores'][6] ?? 0) + ($r['valores'][5] ?? 0) + ($r['valores'][4] ?? 0);
                    $pct_fav = $total_resp > 0 ? round(($fav / $total_resp) * 100) : 0;
                ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                    <div style="flex:1;background:#eee;border-radius:4px;height:8px;overflow:hidden;">
                        <div style="height:8px;border-radius:4px;width:<?= $pct_fav ?>%;
                                    background:<?= $pct_fav >= 70 ? '#1a9e4a' : ($pct_fav >= 50 ? '#f59e0b' : '#c0392b') ?>;
                                    transition:width .4s;">
                        </div>
                    </div>
                    <span style="font-size:14px;font-weight:700;
                                 color:<?= $pct_fav >= 70 ? '#1a9e4a' : ($pct_fav >= 50 ? '#f59e0b' : '#c0392b') ?>;">
                        <?= $pct_fav ?>% favorável
                    </span>
                    <span style="font-size:11px;color:#aaa;"><?= $total_resp ?> respostas</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Devolutiva publicada -->
    <?php if ($devolutiva_txt): ?>
    <div class="g-card">
        <div class="g-card-header">
            <h2><i class="fa fa-bullhorn"></i> Devolutiva publicada</h2>
        </div>
        <div class="g-card-body">
            <p style="font-size:14px;line-height:1.8;color:#444;white-space:pre-line;">
                <?= htmlspecialchars($devolutiva_txt) ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; /* detalhe */ ?>

</main>
</body>
</html>