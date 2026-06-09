<?php
// pulso/gestao/ciclos.php — Gestão de ciclos trimestrais
require_once '../_guard.php';
exige_gerente();
include('../../conexao.php');

$msg_ok  = '';
$msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // CRIAR CICLO
    if ($acao === 'criar') {
        $titulo = trim($_POST['titulo'] ?? '');
        if (!$titulo) {
            $msg_err = 'Informe o título do ciclo.';
        } else {
            // Não permite dois ciclos abertos simultâneos na mesma unidade
            $aberto = $pdo->prepare("
                SELECT id FROM pulso_ciclos
                WHERE unidade_id = ? AND status = 'aberto'
            ");
            $aberto->execute([$g_unidade_id]);
            if ($aberto->fetch()) {
                $msg_err = 'Já existe um ciclo aberto. Encerre-o antes de criar outro.';
            } else {
                $pdo->prepare("
                    INSERT INTO pulso_ciclos (unidade_id, titulo, status)
                    VALUES (?, ?, 'rascunho')
                ")->execute([$g_unidade_id, $titulo]);
                $msg_ok = 'Ciclo criado como rascunho. Cadastre as perguntas e depois abra o ciclo.';
            }
        }
    }

    // ABRIR CICLO
    if ($acao === 'abrir') {
        $cid = filter_input(INPUT_POST, 'ciclo_id', FILTER_VALIDATE_INT);
        // Verifica se há pelo menos uma pergunta
        $npergs = $pdo->prepare("SELECT COUNT(*) FROM pulso_perguntas WHERE ciclo_id = ?");
        $npergs->execute([$cid]);
        if ($npergs->fetchColumn() < 1) {
            $msg_err = 'Adicione pelo menos uma pergunta antes de abrir o ciclo.';
        } else {
            $pdo->prepare("
                UPDATE pulso_ciclos SET status='aberto', aberto_em=NOW()
                WHERE id=? AND unidade_id=? AND status='rascunho'
            ")->execute([$cid, $g_unidade_id]);
            // Cria registro de participação
            $pdo->prepare("INSERT IGNORE INTO pulso_participacao (ciclo_id, total) VALUES (?,0)")
                ->execute([$cid]);
            $msg_ok = 'Ciclo aberto. A pesquisa já está disponível para os docentes.';
        }
    }

    // ENCERRAR CICLO
    if ($acao === 'encerrar') {
        $cid = filter_input(INPUT_POST, 'ciclo_id', FILTER_VALIDATE_INT);
        $pdo->prepare("
            UPDATE pulso_ciclos SET status='encerrado', encerrado_em=NOW()
            WHERE id=? AND unidade_id=? AND status='aberto'
        ")->execute([$cid, $g_unidade_id]);
        $msg_ok = 'Ciclo encerrado. As respostas estão disponíveis para análise.';
    }
}

// Lista ciclos
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(p.id) AS total_perguntas,
           pp.total    AS total_participacao
    FROM pulso_ciclos c
    LEFT JOIN pulso_perguntas p ON p.ciclo_id = c.id
    LEFT JOIN pulso_participacao pp ON pp.ciclo_id = c.id
    WHERE c.unidade_id = ?
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmt->execute([$g_unidade_id]);
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Ciclos';
$pagina_ativa  = 'ciclos';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-calendar" style="color:var(--laranja);margin-right:8px;"></i>Ciclos</h1>
            <div class="page-sub">Gerencie os ciclos trimestrais de pesquisa</div>
        </div>
        <button class="btn-pri" onclick="document.getElementById('formNovoCiclo').style.display='block';">
            <i class="fa fa-plus"></i> Novo ciclo
        </button>
    </div>

    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- FORM NOVO CICLO -->
    <div id="formNovoCiclo" style="display:none;margin-bottom:20px;">
        <div class="g-card">
            <div class="g-card-header">
                <h2><i class="fa fa-plus-circle"></i> Novo ciclo</h2>
            </div>
            <div class="g-card-body">
                <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="acao" value="criar">
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label-g">Título do ciclo</label>
                        <input type="text" name="titulo" class="form-input-g"
                               placeholder="Ex: 1º Trimestre 2025" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-pri">
                            <i class="fa fa-save"></i> Criar ciclo
                        </button>
                        <button type="button" class="btn-sec" style="margin-left:8px;"
                                onclick="document.getElementById('formNovoCiclo').style.display='none'">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LISTA DE CICLOS -->
    <div class="g-card">
        <?php if (empty($ciclos)): ?>
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-calendar-times-o" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhum ciclo criado ainda.
        </div>
        <?php else: ?>
        <table class="g-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Perguntas</th>
                    <th>Participação</th>
                    <th>Aberto em</th>
                    <th>Encerrado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ciclos as $c): ?>
            <?php
                $badge = match($c['status']) {
                    'aberto'    => 'badge-escolha',
                    'rascunho'  => 'badge-variavel',
                    'encerrado' => 'badge-fixa',
                    default     => ''
                };
                $label = match($c['status']) {
                    'aberto'    => '🟢 Aberto',
                    'rascunho'  => '✏️ Rascunho',
                    'encerrado' => '🔵 Encerrado',
                    default     => $c['status']
                };
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['titulo']) ?></strong></td>
                <td><span class="badge-tipo <?= $badge ?>"><?= $label ?></span></td>
                <td style="text-align:center;"><?= $c['total_perguntas'] ?></td>
                <td style="text-align:center;">
                    <?= $c['total_participacao'] ?? '—' ?>
                    <?php if ($c['total_participacao']): ?>
                    <span style="color:#aaa;font-size:11px;">respostas</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#888;">
                    <?= $c['aberto_em'] ? date('d/m/Y H:i', strtotime($c['aberto_em'])) : '—' ?>
                </td>
                <td style="font-size:12px;color:#888;">
                    <?= $c['encerrado_em'] ? date('d/m/Y H:i', strtotime($c['encerrado_em'])) : '—' ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="perguntas.php?ciclo=<?= $c['id'] ?>" class="btn-sec btn-sm">
                            <i class="fa fa-question-circle"></i> Perguntas
                        </a>
                        <?php if ($c['status'] === 'rascunho'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="acao"     value="abrir">
                            <input type="hidden" name="ciclo_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-pri btn-sm"
                                    onclick="return confirm('Abrir este ciclo? A pesquisa ficará disponível para os docentes.')">
                                <i class="fa fa-play"></i> Abrir
                            </button>
                        </form>
                        <?php elseif ($c['status'] === 'aberto'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="acao"     value="encerrar">
                            <input type="hidden" name="ciclo_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-danger btn-sm"
                                    onclick="return confirm('Encerrar este ciclo? Os docentes não poderão mais responder.')">
                                <i class="fa fa-stop"></i> Encerrar
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- GUIA DE STATUS -->
    <div style="margin-top:20px;padding:16px 20px;background:#fff;border-radius:10px;
                box-shadow:0 1px 6px rgba(22,65,148,.06);font-size:13px;color:#555;line-height:1.7;">
        <strong style="color:var(--azul);">Fluxo do ciclo:</strong>
        &nbsp; ✏️ Rascunho → Cadastrar perguntas →
        🟢 Aberto (pesquisa disponível para docentes) →
        🔵 Encerrado (resultados disponíveis para análise)
    </div>

</main>
</body>
</html>