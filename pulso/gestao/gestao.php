<?php
// pulso/gestao/index.php — Dashboard principal do PulsoSENAI
include('../../conexao.php');
require_once '../_guard.php';

// ── Ciclo ativo da unidade ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.id, c.titulo, c.status, c.aberto_em,
           COUNT(p.id) AS total_perguntas,
           pp.total    AS total_participacao
    FROM pulso_ciclos c
    LEFT JOIN pulso_perguntas p    ON p.ciclo_id  = c.id
    LEFT JOIN pulso_participacao pp ON pp.ciclo_id = c.id
    WHERE c.unidade_id = ?
    GROUP BY c.id
    ORDER BY c.id DESC
    LIMIT 1
");
$stmt->execute([$g_unidade_id]);
$ciclo = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Contadores de SLA (ciclo mais recente) ───────────────────
$sla = ['aberto' => 0, 'em_andamento' => 0, 'resolvido' => 0, 'escalado' => 0];
if ($ciclo) {
    $stmt2 = $pdo->prepare("
        SELECT status, COUNT(*) AS n
        FROM pulso_itens_sla
        WHERE ciclo_id = ?
        GROUP BY status
    ");
    $stmt2->execute([$ciclo['id']]);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sla[$row['status']] = (int)$row['n'];
    }
}

// ── Itens de SLA vencidos (prazo < hoje e não resolvidos) ────
$vencidos = 0;
if ($ciclo) {
    $stmt3 = $pdo->prepare("
        SELECT COUNT(*) FROM pulso_itens_sla
        WHERE ciclo_id = ?
          AND status NOT IN ('resolvido','escalado')
          AND prazo < CURDATE()
    ");
    $stmt3->execute([$ciclo['id']]);
    $vencidos = (int)$stmt3->fetchColumn();
}

// ── Itens de SLA do supervisor (só para supervisores) ────────
$meus_itens = [];
if (!$is_gerente && $ciclo) {
    $stmt4 = $pdo->prepare("
        SELECT s.id, s.categoria, s.conteudo, s.status, s.prazo
        FROM pulso_itens_sla s
        WHERE s.ciclo_id = ?
          AND s.responsavel_id = ?
          AND s.status != 'resolvido'
        ORDER BY s.prazo ASC
        LIMIT 10
    ");
    $stmt4->execute([$ciclo['id'], $g_user_id ?? 0]);
    $meus_itens = $stmt4->fetchAll(PDO::FETCH_ASSOC);
}

// ── Histórico rápido de ciclos ───────────────────────────────
$historico = [];
if ($is_gerente) {
    $stmt5 = $pdo->prepare("
        SELECT c.id, c.titulo, c.status, c.encerrado_em,
               pp.total AS participacao,
               COUNT(s.id) AS itens_sla
        FROM pulso_ciclos c
        LEFT JOIN pulso_participacao pp ON pp.ciclo_id = c.id
        LEFT JOIN pulso_itens_sla s     ON s.ciclo_id  = c.id
        WHERE c.unidade_id = ?
        GROUP BY c.id
        ORDER BY c.id DESC
        LIMIT 5
    ");
    $stmt5->execute([$g_unidade_id]);
    $historico = $stmt5->fetchAll(PDO::FETCH_ASSOC);
}

$hoje = date('Y-m-d');
$titulo_pagina = 'Dashboard';
$pagina_ativa  = 'dashboard';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>

<main class="g-main">

    <!-- CABEÇALHO -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fa fa-tachometer" style="color:var(--laranja);margin-right:8px;"></i>
                Dashboard
            </h1>
            <div class="page-sub">
                Olá, <strong><?= htmlspecialchars($g_nome) ?></strong> —
                <?= htmlspecialchars($g_unidade_nome) ?>
            </div>
        </div>
        <?php if ($is_gerente && $ciclo && $ciclo['status'] === 'rascunho'): ?>
            <a href="ciclos.php" class="btn-pri">
                <i class="fa fa-play"></i> Abrir ciclo
            </a>
        <?php elseif ($is_gerente && !$ciclo): ?>
            <a href="ciclos.php" class="btn-pri">
                <i class="fa fa-plus"></i> Criar primeiro ciclo
            </a>
        <?php endif; ?>
    </div>

    <!-- ALERTA: SEM CICLO -->
    <?php if (!$ciclo): ?>
    <div class="alerta-erro">
        <i class="fa fa-exclamation-circle"></i>
        Nenhum ciclo criado ainda.
        <?php if ($is_gerente): ?>
            <a href="ciclos.php" style="color:inherit;font-weight:700;margin-left:4px;">
                Criar o primeiro ciclo →
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ALERTA: ITENS VENCIDOS -->
    <?php if ($vencidos > 0): ?>
    <div class="alerta-erro" style="margin-bottom:20px;">
        <i class="fa fa-clock-o"></i>
        <strong><?= $vencidos ?> item<?= $vencidos > 1 ? 's' : '' ?> de SLA vencido<?= $vencidos > 1 ? 's' : '' ?></strong>
        sem resolução.
        <a href="itens_sla.php?filtro=vencidos" style="color:inherit;font-weight:700;margin-left:4px;">
            Ver itens →
        </a>
    </div>
    <?php endif; ?>

    <!-- CARDS DE STATUS DO CICLO -->
    <?php if ($ciclo): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">

        <!-- Participação -->
        <div class="g-card" style="text-align:center;padding:24px 16px;">
            <div style="font-size:38px;font-weight:800;color:var(--azul);line-height:1;">
                <?= $ciclo['total_participacao'] ?? 0 ?>
            </div>
            <div style="font-size:12px;color:#888;margin-top:6px;">Participantes</div>
            <div style="font-size:11px;color:#bbb;margin-top:2px;"><?= $ciclo['total_perguntas'] ?> perguntas</div>
        </div>

        <!-- SLA: aberto -->
        <div class="g-card" style="text-align:center;padding:24px 16px;border-top:3px solid var(--laranja);">
            <div style="font-size:38px;font-weight:800;color:var(--laranja);line-height:1;">
                <?= $sla['aberto'] ?>
            </div>
            <div style="font-size:12px;color:#888;margin-top:6px;">Em aberto</div>
            <?php if ($vencidos > 0): ?>
            <div style="font-size:11px;color:#c0392b;margin-top:2px;">
                <?= $vencidos ?> vencido<?= $vencidos > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- SLA: em andamento -->
        <div class="g-card" style="text-align:center;padding:24px 16px;border-top:3px solid #f59e0b;">
            <div style="font-size:38px;font-weight:800;color:#f59e0b;line-height:1;">
                <?= $sla['em_andamento'] ?>
            </div>
            <div style="font-size:12px;color:#888;margin-top:6px;">Em andamento</div>
        </div>

        <!-- SLA: resolvido -->
        <div class="g-card" style="text-align:center;padding:24px 16px;border-top:3px solid var(--verde);">
            <div style="font-size:38px;font-weight:800;color:var(--verde);line-height:1;">
                <?= $sla['resolvido'] ?>
            </div>
            <div style="font-size:12px;color:#888;margin-top:6px;">Resolvidos</div>
        </div>

        <!-- SLA: escalado -->
        <div class="g-card" style="text-align:center;padding:24px 16px;border-top:3px solid #7c3aed;">
            <div style="font-size:38px;font-weight:800;color:#7c3aed;line-height:1;">
                <?= $sla['escalado'] ?>
            </div>
            <div style="font-size:12px;color:#888;margin-top:6px;">Escalados</div>
        </div>

    </div>

    <!-- STATUS DO CICLO -->
    <div class="g-card" style="margin-bottom:24px;">
        <div class="g-card-header">
            <h2><i class="fa fa-calendar"></i> Ciclo atual</h2>
            <?php if ($is_gerente): ?>
            <a href="ciclos.php" class="btn-sec btn-sm">Gerenciar ciclos</a>
            <?php endif; ?>
        </div>
        <div class="g-card-body">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:16px;font-weight:700;color:var(--texto);">
                        <?= htmlspecialchars($ciclo['titulo']) ?>
                    </div>
                    <?php if ($ciclo['aberto_em']): ?>
                    <div style="font-size:12px;color:#aaa;margin-top:3px;">
                        Aberto em <?= date('d/m/Y', strtotime($ciclo['aberto_em'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                $badge_status = match($ciclo['status']) {
                    'aberto'    => ['badge-escolha',  '🟢 Aberto — pesquisa disponível para os docentes'],
                    'rascunho'  => ['badge-variavel', '✏️ Rascunho — aguardando abertura'],
                    'encerrado' => ['badge-fixa',     '🔵 Encerrado'],
                    default     => ['', $ciclo['status']]
                };
                ?>
                <span class="badge-tipo <?= $badge_status[0] ?>" style="font-size:13px;padding:5px 14px;">
                    <?= $badge_status[1] ?>
                </span>

                <?php if ($ciclo['status'] === 'aberto'): ?>
                <a href="index.php" target="_blank" class="btn-sec btn-sm">
                    <i class="fa fa-external-link"></i> Ver pesquisa ao vivo
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; /* $ciclo */ ?>

    <div style="display:grid;grid-template-columns:<?= $is_gerente ? '1fr 1fr' : '1fr' ?>;gap:20px;">

        <!-- AÇÕES RÁPIDAS -->
        <div class="g-card">
            <div class="g-card-header">
                <h2><i class="fa fa-bolt"></i> Ações rápidas</h2>
            </div>
            <div class="g-card-body" style="display:flex;flex-direction:column;gap:10px;">

                <a href="itens_sla.php" class="btn-pri" style="justify-content:flex-start;">
                    <i class="fa fa-tasks"></i> Ver itens de SLA
                </a>

                <?php if ($ciclo && $ciclo['status'] === 'aberto'): ?>
                <a href="status.php" target="_blank" class="btn-sec" style="justify-content:flex-start;">
                    <i class="fa fa-eye"></i> Painel público de status
                </a>
                <?php endif; ?>

                <?php if ($is_gerente): ?>
                <a href="perguntas.php<?= $ciclo ? '?ciclo='.$ciclo['id'] : '' ?>"
                   class="btn-sec" style="justify-content:flex-start;">
                    <i class="fa fa-question-circle"></i> Gerenciar perguntas
                </a>
                <a href="devolutiva.php" class="btn-sec" style="justify-content:flex-start;">
                    <i class="fa fa-bullhorn"></i> Publicar devolutiva
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- HISTÓRICO DE CICLOS (só gerente) -->
        <?php if ($is_gerente && !empty($historico)): ?>
        <div class="g-card">
            <div class="g-card-header">
                <h2><i class="fa fa-history"></i> Histórico de ciclos</h2>
                <a href="historico.php" class="btn-sec btn-sm">Ver tudo</a>
            </div>
            <table class="g-table">
                <thead>
                    <tr>
                        <th>Ciclo</th>
                        <th style="text-align:center;">Partic.</th>
                        <th style="text-align:center;">Itens SLA</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historico as $h): ?>
                <?php
                    $hs = match($h['status']) {
                        'aberto'    => ['badge-escolha',  '🟢 Aberto'],
                        'rascunho'  => ['badge-variavel', '✏️ Rascunho'],
                        'encerrado' => ['badge-fixa',     '🔵 Encerrado'],
                        default     => ['', $h['status']]
                    };
                ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($h['titulo']) ?></td>
                    <td style="text-align:center;"><?= $h['participacao'] ?? '—' ?></td>
                    <td style="text-align:center;"><?= $h['itens_sla'] ?></td>
                    <td><span class="badge-tipo <?= $hs[0] ?>"><?= $hs[1] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- MEUS ITENS DE SLA (supervisor) -->
        <?php if (!$is_gerente && !empty($meus_itens)): ?>
        <div class="g-card">
            <div class="g-card-header">
                <h2><i class="fa fa-tasks"></i> Meus itens pendentes</h2>
                <a href="itens_sla.php" class="btn-sec btn-sm">Ver todos</a>
            </div>
            <table class="g-table">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Prazo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($meus_itens as $it): ?>
                <?php
                    $venc = $it['prazo'] && $it['prazo'] < $hoje && $it['status'] !== 'resolvido';
                    $sc   = match($it['status']) {
                        'aberto'       => ['badge-aberto',    'Aberto'],
                        'em_andamento' => ['badge-andamento', 'Em andamento'],
                        'escalado'     => ['badge-escalado',  'Escalado'],
                        default        => ['', $it['status']]
                    };
                ?>
                <tr>
                    <td>
                        <span class="badge-cat"><?= htmlspecialchars($it['categoria']) ?></span>
                        <div style="font-size:12px;color:#888;margin-top:4px;line-height:1.3;">
                            <?= htmlspecialchars(mb_strimwidth($it['conteudo'], 0, 60, '…')) ?>
                        </div>
                    </td>
                    <td><span class="badge-status <?= $sc[0] ?>"><?= $sc[1] ?></span></td>
                    <td style="font-size:12px;<?= $venc ? 'color:#c0392b;font-weight:600;' : 'color:#888;' ?>">
                        <?= $it['prazo'] ? date('d/m/Y', strtotime($it['prazo'])) : '—' ?>
                        <?= $venc ? '<br><span style="font-size:11px;">Vencido</span>' : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

</main>
</body>
</html>