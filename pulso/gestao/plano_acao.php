<?php
// pulso/gestao/plano_acao.php — Gestão dos planos de ação
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('../../conexao.php');
require_once '../_guard.php';

$msg_ok  = '';
$msg_err = '';

// ── AÇÕES POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // SALVAR NOVO PLANO (vindo de respostas.php)
    if ($acao === 'salvar_novo') {
        $ciclo_id       = filter_input(INPUT_POST, 'ciclo_id',       FILTER_VALIDATE_INT);
        $titulo         = trim($_POST['titulo']         ?? '');
        $problema       = trim($_POST['problema']       ?? '');
        $objetivo       = trim($_POST['objetivo']       ?? '');
        $acoes_json     = $_POST['acoes']               ?? '[]';
        $tipo_resp      = $_POST['tipo_responsavel']    ?? 'gestao';
        $visivel        = isset($_POST['visivel_equipe']) && $_POST['visivel_equipe'] == '1' ? 1 : 0;
        $gerado_ia      = isset($_POST['gerado_por_ia']) ? 1 : 0;

        // Valida JSON das ações
        $acoes_arr = json_decode($acoes_json, true);
        if (!is_array($acoes_arr)) $acoes_arr = [];

        if (!$ciclo_id || !$titulo || !$objetivo) {
            $msg_err = 'Dados incompletos.';
        } else {
            $pdo->prepare("
                INSERT INTO pulso_planos_acao
                    (ciclo_id, titulo, problema, objetivo, acoes,
                     tipo_responsavel, status, visivel_equipe, gerado_por_ia, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, 'ativo', ?, ?, ?)
            ")->execute([
                $ciclo_id, $titulo, $problema, $objetivo,
                json_encode($acoes_arr, JSON_UNESCAPED_UNICODE),
                $tipo_resp, $visivel, $gerado_ia,
                $g_user_id ?? null
            ]);
            $msg_ok = 'Plano de ação salvo com sucesso!';
        }
    }

    // ATUALIZAR STATUS DO PLANO
    if ($acao === 'atualizar_status') {
        $plano_id  = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);
        $status    = $_POST['status'] ?? '';
        $status_ok = ['rascunho','ativo','concluido','cancelado'];
        if ($plano_id && in_array($status, $status_ok)) {
            // Verifica que o plano é desta unidade
            $chk = $pdo->prepare("
                SELECT p.id, p.status FROM pulso_planos_acao p
                INNER JOIN pulso_ciclos c ON c.id = p.ciclo_id
                WHERE p.id = ? AND c.unidade_id = ?
            ");
            $chk->execute([$plano_id, $g_unidade_id]);
            $pl = $chk->fetch(PDO::FETCH_ASSOC);
            if ($pl) {
                $pdo->prepare("UPDATE pulso_planos_acao SET status=? WHERE id=?")
                    ->execute([$status, $plano_id]);
                $pdo->prepare("
                    INSERT INTO pulso_planos_historico (plano_id, campo, valor_ant, valor_novo, autor_id)
                    VALUES (?, 'status', ?, ?, ?)
                ")->execute([$plano_id, $pl['status'], $status, $g_user_id ?? null]);
                $msg_ok = 'Status atualizado.';
            }
        }
    }

    // ATUALIZAR AÇÃO ESPECÍFICA (status de uma ação dentro do plano)
    if ($acao === 'atualizar_acao') {
        $plano_id  = filter_input(INPUT_POST, 'plano_id',  FILTER_VALIDATE_INT);
        $acao_idx  = filter_input(INPUT_POST, 'acao_idx',  FILTER_VALIDATE_INT);
        $novo_st   = $_POST['acao_status'] ?? '';

        $q = $pdo->prepare("
            SELECT p.id, p.acoes FROM pulso_planos_acao p
            INNER JOIN pulso_ciclos c ON c.id = p.ciclo_id
            WHERE p.id = ? AND c.unidade_id = ?
        ");
        $q->execute([$plano_id, $g_unidade_id]);
        $pl = $q->fetch(PDO::FETCH_ASSOC);

        if ($pl) {
            $acoes_arr = json_decode($pl['acoes'], true) ?: [];
            if (isset($acoes_arr[$acao_idx])) {
                $acoes_arr[$acao_idx]['status'] = $novo_st;
                $pdo->prepare("UPDATE pulso_planos_acao SET acoes=? WHERE id=?")
                    ->execute([json_encode($acoes_arr, JSON_UNESCAPED_UNICODE), $plano_id]);
                $msg_ok = 'Ação atualizada.';
            }
        }
    }

    // ALTERNAR VISIBILIDADE
    if ($acao === 'toggle_visivel' && $is_gerente) {
        $plano_id = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);
        $pdo->prepare("
            UPDATE pulso_planos_acao SET visivel_equipe = NOT visivel_equipe
            WHERE id = ? AND ciclo_id IN
                (SELECT id FROM pulso_ciclos WHERE unidade_id = ?)
        ")->execute([$plano_id, $g_unidade_id]);
        $msg_ok = 'Visibilidade alterada.';
    }

    // EXCLUIR
    if ($acao === 'excluir' && $is_gerente) {
        $plano_id = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);
        $pdo->prepare("DELETE FROM pulso_planos_historico WHERE plano_id=?")->execute([$plano_id]);
        $pdo->prepare("DELETE FROM pulso_planos_acao WHERE id=?")->execute([$plano_id]);
        $msg_ok = 'Plano excluído.';
    }
}

// ── LISTAGEM ─────────────────────────────────────────────────
$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;
$ver_plano = filter_input(INPUT_GET, 'ver',   FILTER_VALIDATE_INT) ?: 0;

$ciclos_stmt = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ? ORDER BY id DESC
");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclo_sel && $ciclos_lista) {
    $ciclo_sel = $ciclos_lista[0]['id'];
}

// Planos do ciclo
$planos = [];
if ($ciclo_sel) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome AS criado_por_nome
        FROM pulso_planos_acao p
        LEFT JOIN usuarios u ON u.id = p.criado_por
        WHERE p.ciclo_id = ?
        ORDER BY p.criado_em DESC
    ");
    $stmt->execute([$ciclo_sel]);
    $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Plano em detalhe
$plano_det = null;
if ($ver_plano) {
    foreach ($planos as $pl) {
        if ($pl['id'] == $ver_plano) {
            $plano_det = $pl;
            $plano_det['acoes_arr'] = json_decode($pl['acoes'], true) ?: [];
            break;
        }
    }
}

$status_label = [
    'rascunho'  => ['✏️ Rascunho',  '#888'],
    'ativo'     => ['🟢 Ativo',      '#1a9e4a'],
    'concluido' => ['✔ Concluído',  '#164194'],
    'cancelado' => ['✕ Cancelado',  '#c0392b'],
];

$acao_status_label = [
    'pendente'    => ['⏳ Pendente',    '#888'],
    'em_andamento'=> ['🔄 Em andamento','#f59e0b'],
    'concluida'   => ['✔ Concluída',   '#1a9e4a'],
    'cancelada'   => ['✕ Cancelada',   '#c0392b'],
];

$resp_label = [
    'gestao'       => ['fa-user-secret',    'Gestão'],
    'docente'      => ['fa-graduation-cap', 'Docentes'],
    'compartilhado'=> ['fa-handshake-o',    'Compartilhado'],
];

$titulo_pagina = 'Planos de Ação';
$pagina_ativa  = 'planos';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-list-alt" style="color:var(--laranja);margin-right:8px;"></i>Planos de Ação</h1>
            <div class="page-sub">Planos gerados a partir das respostas da pesquisa</div>
        </div>
        <a href="respostas.php?ciclo=<?= $ciclo_sel ?>" class="btn-pri">
            <i class="fa fa-plus"></i> Novo plano (via respostas)
        </a>
    </div>

    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- SELETOR DE CICLO -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
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

    <div style="display:grid;grid-template-columns:<?= $plano_det ? '360px 1fr' : '1fr' ?>;gap:20px;align-items:start;">

        <!-- LISTA DE PLANOS -->
        <div>
            <?php if (empty($planos)): ?>
            <div class="g-card">
                <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
                    <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
                    Nenhum plano criado ainda para este ciclo.<br>
                    <a href="respostas.php?ciclo=<?= $ciclo_sel ?>" style="color:var(--azul-c);margin-top:8px;display:inline-block;">
                        Ir para as respostas e criar o primeiro plano →
                    </a>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($planos as $pl):
                $sl = $status_label[$pl['status']] ?? ['?', '#888'];
                $rl = $resp_label[$pl['tipo_responsavel']] ?? ['fa-circle','?'];
                $acoes_arr = json_decode($pl['acoes'], true) ?: [];
                $concluidas = count(array_filter($acoes_arr, fn($a) => ($a['status'] ?? '') === 'concluida'));
                $total_ac   = count($acoes_arr);
                $pct_ac     = $total_ac > 0 ? round(($concluidas / $total_ac) * 100) : 0;
            ?>
            <div class="g-card" style="margin-bottom:12px;cursor:pointer;
                 <?= $ver_plano == $pl['id'] ? 'border:2px solid var(--azul);' : '' ?>"
                 onclick="window.location='?ciclo=<?= $ciclo_sel ?>&ver=<?= $pl['id'] ?>'">
                <div class="g-card-body" style="padding:16px 18px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;">
                        <div style="font-size:14px;font-weight:700;color:var(--texto);line-height:1.4;">
                            <?= htmlspecialchars($pl['titulo']) ?>
                        </div>
                        <span style="font-size:11px;font-weight:600;white-space:nowrap;color:<?= $sl[1] ?>;">
                            <?= $sl[0] ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                        <span style="font-size:11px;color:#888;">
                            <i class="fa <?= $rl[0] ?>"></i> <?= $rl[1] ?>
                        </span>
                        <?php if ($pl['visivel_equipe']): ?>
                        <span style="font-size:11px;color:#1a9e4a;">
                            <i class="fa fa-eye"></i> Visível p/ equipe
                        </span>
                        <?php endif; ?>
                        <?php if ($pl['gerado_por_ia']): ?>
                        <span style="font-size:11px;color:var(--laranja);">
                            <i class="fa fa-magic"></i> IA
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($total_ac > 0): ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;
                                    font-size:11px;color:#888;margin-bottom:4px;">
                            <span>Progresso das ações</span>
                            <span><?= $concluidas ?>/<?= $total_ac ?></span>
                        </div>
                        <div style="background:#eee;border-radius:4px;height:6px;overflow:hidden;">
                            <div style="height:6px;border-radius:4px;width:<?= $pct_ac ?>%;
                                        background:<?= $pct_ac >= 100 ? '#1a9e4a' : 'var(--azul)' ?>;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- DETALHE DO PLANO -->
        <?php if ($plano_det): ?>
        <div>
            <div class="g-card">
                <div class="g-card-header">
                    <h2><i class="fa fa-file-text-o"></i> <?= htmlspecialchars($plano_det['titulo']) ?></h2>
                    <div style="display:flex;gap:8px;">
                        <!-- Alterar status -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="acao"     value="toggle_visivel">
                            <input type="hidden" name="plano_id" value="<?= $plano_det['id'] ?>">
                            <button type="submit" class="btn-sec btn-sm"
                                    title="<?= $plano_det['visivel_equipe'] ? 'Ocultar da equipe' : 'Tornar visível para a equipe' ?>">
                                <i class="fa fa-<?= $plano_det['visivel_equipe'] ? 'eye-slash' : 'eye' ?>"></i>
                                <?= $plano_det['visivel_equipe'] ? 'Ocultar' : 'Publicar' ?>
                            </button>
                        </form>
                        <?php if ($is_gerente): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Excluir este plano?')">
                            <input type="hidden" name="acao"     value="excluir">
                            <input type="hidden" name="plano_id" value="<?= $plano_det['id'] ?>">
                            <button type="submit" class="btn-danger btn-sm">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card-body">

                    <!-- METADADOS -->
                    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;
                                padding-bottom:16px;border-bottom:1px solid #f0f3f8;">
                        <?php
                        $sl  = $status_label[$plano_det['status']] ?? ['?','#888'];
                        $rl  = $resp_label[$plano_det['tipo_responsavel']] ?? ['fa-circle','?'];
                        ?>
                        <div>
                            <div style="font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;">Status</div>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao"     value="atualizar_status">
                                <input type="hidden" name="plano_id" value="<?= $plano_det['id'] ?>">
                                <select name="status" class="form-input-g"
                                        style="font-size:13px;padding:6px 10px;margin:4px 0 0;"
                                        onchange="this.form.submit()">
                                    <?php foreach ($status_label as $sv => [$slbl, $scor]): ?>
                                    <option value="<?= $sv ?>" <?= $plano_det['status']===$sv?'selected':'' ?>>
                                        <?= $slbl ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div>
                            <div style="font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;">Envolvidos</div>
                            <div style="margin-top:4px;font-size:13px;color:#444;">
                                <i class="fa <?= $rl[0] ?>"></i> <?= $rl[1] ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;">Criado por</div>
                            <div style="margin-top:4px;font-size:13px;color:#444;">
                                <?= htmlspecialchars($plano_det['criado_por_nome'] ?? 'Sistema') ?>
                                <?= $plano_det['gerado_por_ia'] ? ' <span style="color:var(--laranja);font-size:11px;">+ IA</span>' : '' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;">Visibilidade</div>
                            <div style="margin-top:4px;font-size:13px;color:<?= $plano_det['visivel_equipe'] ? '#1a9e4a' : '#888' ?>;">
                                <?= $plano_det['visivel_equipe'] ? '🟢 Visível para a equipe' : '🔒 Apenas gestão' ?>
                            </div>
                        </div>
                    </div>

                    <!-- OBJETIVO -->
                    <div style="margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;color:var(--azul);
                                    text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                            Objetivo
                        </div>
                        <p style="font-size:14px;color:#444;line-height:1.7;margin:0;">
                            <?= nl2br(htmlspecialchars($plano_det['objetivo'])) ?>
                        </p>
                    </div>

                    <!-- INSUMO ORIGINAL -->
                    <?php if ($plano_det['problema']): ?>
                    <div style="margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;color:#888;
                                    text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                            Insumo original (respostas selecionadas)
                        </div>
                        <div style="background:#f8faff;border:1px solid #e0e9f8;border-radius:8px;
                                    padding:12px 14px;font-size:13px;color:#666;line-height:1.7;
                                    max-height:120px;overflow-y:auto;">
                            <?= nl2br(htmlspecialchars($plano_det['problema'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- AÇÕES -->
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--azul);
                                    text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">
                            Ações
                        </div>
                        <?php foreach ($plano_det['acoes_arr'] as $ai => $ac):
                            $ast = $ac['status'] ?? 'pendente';
                            $asl = $acao_status_label[$ast] ?? ['?', '#888'];
                            $arl = $resp_label[$ac['responsavel_tipo'] ?? 'gestao'] ?? ['fa-circle','?'];
                        ?>
                        <div style="background:#f8faff;border:1px solid #e0e9f8;border-radius:10px;
                                    padding:16px;margin-bottom:10px;">
                            <div style="display:flex;align-items:flex-start;
                                        justify-content:space-between;gap:10px;margin-bottom:10px;">
                                <div style="font-size:14px;font-weight:600;color:var(--texto);">
                                    <?= ($ai + 1) ?>. <?= htmlspecialchars($ac['descricao'] ?? '') ?>
                                </div>
                                <form method="POST" style="flex-shrink:0;">
                                    <input type="hidden" name="acao"      value="atualizar_acao">
                                    <input type="hidden" name="plano_id"  value="<?= $plano_det['id'] ?>">
                                    <input type="hidden" name="acao_idx"  value="<?= $ai ?>">
                                    <select name="acao_status" class="form-input-g"
                                            style="font-size:12px;padding:5px 8px;"
                                            onchange="this.form.submit()">
                                        <?php foreach ($acao_status_label as $asv => [$aslbl, $ascor]): ?>
                                        <option value="<?= $asv ?>" <?= $ast===$asv?'selected':'' ?>>
                                            <?= $aslbl ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <?php if (!empty($ac['como'])): ?>
                            <div style="font-size:13px;color:#666;line-height:1.5;margin-bottom:10px;">
                                <strong style="color:#888;font-size:11px;">Como:</strong><br>
                                <?= htmlspecialchars($ac['como']) ?>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#888;">
                                <span><i class="fa <?= $arl[0] ?>"></i> <?= $arl[1] ?></span>
                                <?php if (!empty($ac['responsavel_sugerido'])): ?>
                                <span><i class="fa fa-user"></i> <?= htmlspecialchars($ac['responsavel_sugerido']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ac['prazo_sugerido'])): ?>
                                <span><i class="fa fa-clock-o"></i> <?= htmlspecialchars($ac['prazo_sugerido']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</main>
</body>
</html>