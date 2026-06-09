<?php
// pulso/gestao/perguntas.php — Cadastro e gestão de perguntas
require_once '../_guard.php';
exige_gerente(); // Apenas gerentes cadastram perguntas
include('../../conexao.php');

$msg_ok  = '';
$msg_err = '';

// ── AÇÕES POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // CRIAR PERGUNTA
    if ($acao === 'criar') {
        $ciclo_id  = filter_input(INPUT_POST, 'ciclo_id',  FILTER_VALIDATE_INT);
        $categoria = trim($_POST['categoria'] ?? '');
        $texto     = trim($_POST['texto']     ?? '');
        $tipo      = $_POST['tipo']  ?? 'escolha';
        $fixa      = isset($_POST['fixa']) ? 1 : 0;
        $ordem     = filter_input(INPUT_POST, 'ordem', FILTER_VALIDATE_INT) ?: 0;

        $tipos_validos = ['escolha','escala','aberta'];
        if (!$ciclo_id || !$categoria || !$texto || !in_array($tipo, $tipos_validos)) {
            $msg_err = 'Preencha todos os campos obrigatórios.';
        } else {
            // Verifica que o ciclo é desta unidade e está em rascunho ou aberto
            $chk = $pdo->prepare("
                SELECT id FROM pulso_ciclos
                WHERE id = ? AND unidade_id = ? AND status IN ('rascunho','aberto')
            ");
            $chk->execute([$ciclo_id, $g_unidade_id]);
            if (!$chk->fetch()) {
                $msg_err = 'Ciclo inválido ou encerrado.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO pulso_perguntas
                        (ciclo_id, categoria, texto, tipo, fixa, ordem)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$ciclo_id, $categoria, $texto, $tipo, $fixa, $ordem]);
                $msg_ok = 'Pergunta cadastrada com sucesso.';
            }
        }
    }

    // EXCLUIR PERGUNTA
    if ($acao === 'excluir') {
        $pid = filter_input(INPUT_POST, 'pergunta_id', FILTER_VALIDATE_INT);
        if ($pid) {
            // Verifica que a pergunta é desta unidade e ciclo não encerrado
            $chk = $pdo->prepare("
                SELECT p.id FROM pulso_perguntas p
                INNER JOIN pulso_ciclos c ON c.id = p.ciclo_id
                WHERE p.id = ? AND c.unidade_id = ? AND c.status != 'encerrado'
            ");
            $chk->execute([$pid, $g_unidade_id]);
            if ($chk->fetch()) {
                $pdo->prepare("DELETE FROM pulso_perguntas WHERE id = ?")->execute([$pid]);
                $msg_ok = 'Pergunta excluída.';
            } else {
                $msg_err = 'Não é possível excluir esta pergunta.';
            }
        }
    }

    // REORDENAR (drag-drop via AJAX)
    if ($acao === 'reordenar' && isset($_POST['ids'])) {
        $ids = json_decode($_POST['ids'], true);
        if (is_array($ids)) {
            $upd = $pdo->prepare("UPDATE pulso_perguntas SET ordem = ? WHERE id = ? AND ciclo_id IN (SELECT id FROM pulso_ciclos WHERE unidade_id = ?)");
            foreach ($ids as $ordem => $id) {
                $upd->execute([(int)$ordem, (int)$id, $g_unidade_id]);
            }
            echo json_encode(['ok' => true]); exit;
        }
    }

    // EDITAR PERGUNTA
    if ($acao === 'editar') {
        $pid       = filter_input(INPUT_POST, 'pergunta_id', FILTER_VALIDATE_INT);
        $categoria = trim($_POST['categoria'] ?? '');
        $texto     = trim($_POST['texto']     ?? '');
        $tipo      = $_POST['tipo']  ?? 'escolha';
        $fixa      = isset($_POST['fixa']) ? 1 : 0;
        $ordem     = filter_input(INPUT_POST, 'ordem', FILTER_VALIDATE_INT) ?: 0;

        if ($pid && $categoria && $texto) {
            $chk = $pdo->prepare("
                SELECT p.id FROM pulso_perguntas p
                INNER JOIN pulso_ciclos c ON c.id = p.ciclo_id
                WHERE p.id = ? AND c.unidade_id = ? AND c.status != 'encerrado'
            ");
            $chk->execute([$pid, $g_unidade_id]);
            if ($chk->fetch()) {
                $pdo->prepare("
                    UPDATE pulso_perguntas
                    SET categoria=?, texto=?, tipo=?, fixa=?, ordem=?
                    WHERE id=?
                ")->execute([$categoria, $texto, $tipo, $fixa, $ordem, $pid]);
                $msg_ok = 'Pergunta atualizada.';
            } else {
                $msg_err = 'Não é possível editar esta pergunta.';
            }
        }
    }
}

// ── FILTRO DE CICLO ───────────────────────────────────────────
$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;

// Busca ciclos da unidade
$ciclos = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ?
    ORDER BY id DESC
");
$ciclos->execute([$g_unidade_id]);
$ciclos = $ciclos->fetchAll(PDO::FETCH_ASSOC);

// Se nenhum ciclo selecionado, usa o primeiro
if (!$ciclo_sel && $ciclos) {
    $ciclo_sel = $ciclos[0]['id'];
}

// Ciclo atual
$ciclo_atual = null;
foreach ($ciclos as $c) {
    if ($c['id'] === $ciclo_sel) { $ciclo_atual = $c; break; }
}

// Busca perguntas do ciclo selecionado
$perguntas = [];
if ($ciclo_sel) {
    $stmt = $pdo->prepare("
        SELECT * FROM pulso_perguntas
        WHERE ciclo_id = ?
        ORDER BY categoria, ordem, id
    ");
    $stmt->execute([$ciclo_sel]);
    $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Agrupa por categoria para exibição
$por_cat = [];
foreach ($perguntas as $p) {
    $por_cat[$p['categoria']][] = $p;
}

// Categorias pré-definidas
$categorias = [
    'Reconhecimento e Carreira',
    'Treinamento e Desenvolvimento',
    'Condições Físicas',
    'Liderança',
    'Qualidade de Vida',
    'Relacionamento',
    'Comunicação',
    'Geral',
];

$ciclo_editavel = $ciclo_atual && in_array($ciclo_atual['status'], ['rascunho','aberto']);

$titulo_pagina = 'Perguntas';
$pagina_ativa  = 'perguntas';
?>
<?php include '../_layout_head.php'; ?>
<body>

<?php include '../_nav.php'; ?>

<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-question-circle" style="color:var(--laranja);margin-right:8px;"></i>Perguntas</h1>
            <div class="page-sub">Gerencie as perguntas de cada ciclo da pesquisa</div>
        </div>
        <?php if ($ciclo_editavel): ?>
        <button class="btn-pri" onclick="abrirModal()">
            <i class="fa fa-plus"></i> Nova pergunta
        </button>
        <?php endif; ?>
    </div>

    <!-- ALERTAS -->
    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- SELETOR DE CICLO -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:16px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <label class="form-label-g" style="margin:0;white-space:nowrap;">Ciclo:</label>
                <select name="ciclo" class="form-input-g" style="max-width:320px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['titulo']) ?>
                        — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($ciclos)): ?>
                <span style="font-size:13px;color:#888;">
                    Nenhum ciclo criado ainda.
                    <a href="ciclos.php">Criar ciclo</a>
                </span>
                <?php endif; ?>
                <?php if ($ciclo_atual): ?>
                <span class="badge-tipo badge-<?= $ciclo_atual['status'] === 'aberto' ? 'escolha' : ($ciclo_atual['status'] === 'encerrado' ? 'fixa' : 'variavel') ?>">
                    <?= ucfirst($ciclo_atual['status']) ?>
                </span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!$ciclo_sel): ?>
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>
            Selecione ou crie um ciclo para gerenciar as perguntas.
        </div>
    </div>
    <?php else: ?>

    <!-- PERGUNTAS POR CATEGORIA -->
    <?php if (empty($por_cat)): ?>
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-pencil-square-o" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            <p>Nenhuma pergunta cadastrada neste ciclo ainda.</p>
            <?php if ($ciclo_editavel): ?>
            <button class="btn-pri" style="margin-top:16px;" onclick="abrirModal()">
                <i class="fa fa-plus"></i> Adicionar primeira pergunta
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>

    <?php foreach ($por_cat as $cat => $pergs): ?>
    <div class="g-card" style="margin-bottom:16px;">
        <div class="g-card-header">
            <h2><span class="badge-cat"><?= htmlspecialchars($cat) ?></span>
                <span style="font-size:13px;color:#aaa;font-weight:400;">
                    <?= count($pergs) ?> pergunta<?= count($pergs) != 1 ? 's' : '' ?>
                </span>
            </h2>
        </div>
        <table class="g-table">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th>Texto da pergunta</th>
                    <th style="width:100px;">Tipo</th>
                    <th style="width:80px;">Natureza</th>
                    <?php if ($ciclo_editavel): ?>
                    <th style="width:120px;">Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pergs as $i => $p): ?>
                <tr>
                    <td style="color:#ccc;font-size:12px;"><?= $p['ordem'] ?: ($i+1) ?></td>
                    <td style="line-height:1.4;"><?= htmlspecialchars($p['texto']) ?></td>
                    <td>
                        <span class="badge-tipo badge-<?= $p['tipo'] ?>">
                            <?= ['escolha'=>'Escolha','escala'=>'Escala','aberta'=>'Aberta'][$p['tipo']] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-tipo <?= $p['fixa'] ? 'badge-fixa' : 'badge-variavel' ?>">
                            <?= $p['fixa'] ? 'Fixa' : 'Variável' ?>
                        </span>
                    </td>
                    <?php if ($ciclo_editavel): ?>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn-sec btn-sm"
                                onclick='abrirModalEditar(<?= htmlspecialchars(json_encode($p)) ?>)'>
                                <i class="fa fa-pencil"></i>
                            </button>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Excluir esta pergunta?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="pergunta_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="ciclo_id" value="<?= $ciclo_sel ?>">
                                <button type="submit" class="btn-danger btn-sm">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- RESUMO -->
    <div style="margin-top:8px;font-size:12px;color:#aaa;text-align:right;">
        Total: <?= count($perguntas) ?> pergunta<?= count($perguntas) != 1 ? 's' : '' ?>
        &bull; <?= count(array_filter($perguntas, fn($p) => $p['fixa'])) ?> fixas
        &bull; <?= count(array_filter($perguntas, fn($p) => !$p['fixa'])) ?> variáveis
    </div>
    <?php endif; /* empty por_cat */ ?>
    <?php endif; /* ciclo_sel */ ?>

</main>

<!-- ── MODAL NOVA PERGUNTA ─────────────────────────────────── -->
<div id="modalPergunta" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;
                box-shadow:0 16px 48px rgba(0,0,0,.2);overflow:hidden;">
        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 id="modalTitulo" style="font-size:16px;font-weight:700;color:#fff;margin:0;">
                Nova pergunta
            </h3>
            <button onclick="fecharModal()"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:20px;cursor:pointer;line-height:1;">×</button>
        </div>
        <form method="POST" style="padding:24px;">
            <input type="hidden" name="acao"     id="f_acao"        value="criar">
            <input type="hidden" name="ciclo_id" value="<?= $ciclo_sel ?>">
            <input type="hidden" name="pergunta_id" id="f_pergunta_id" value="">

            <div class="form-group-g">
                <label class="form-label-g">Categoria *</label>
                <select name="categoria" id="f_categoria" class="form-input-g" required>
                    <option value="">— Selecione —</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                    <?php endforeach; ?>
                    <option value="__nova__">+ Nova categoria...</option>
                </select>
                <input type="text" name="categoria_nova" id="f_categoria_nova"
                       class="form-input-g" placeholder="Nome da nova categoria"
                       style="margin-top:8px;display:none;">
            </div>

            <div class="form-group-g">
                <label class="form-label-g">Texto da pergunta *</label>
                <textarea name="texto" id="f_texto" class="form-input-g"
                          rows="3" required
                          placeholder="Ex: Neste trimestre, senti que meu trabalho foi devidamente reconhecido?"
                          style="resize:vertical;"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group-g">
                    <label class="form-label-g">Tipo de resposta *</label>
                    <select name="tipo" id="f_tipo" class="form-input-g" required>
                        <option value="escolha">Escolha (Concordo/Discordo)</option>
                        <option value="escala">Escala (0 a 10)</option>
                        <option value="aberta">Aberta (texto livre)</option>
                    </select>
                    <div id="tipo_hint" style="font-size:11px;color:#888;margin-top:6px;"></div>
                </div>
                <div class="form-group-g">
                    <label class="form-label-g">Ordem de exibição</label>
                    <input type="number" name="ordem" id="f_ordem"
                           class="form-input-g" value="0" min="0" max="99">
                    <div style="font-size:11px;color:#888;margin-top:6px;">
                        Menor número aparece primeiro
                    </div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="fixa" id="f_fixa"
                           style="width:16px;height:16px;accent-color:var(--azul);">
                    <span style="font-size:13px;color:#444;">
                        <strong>Pergunta fixa</strong> —
                        aparece em todos os ciclos e não pode ser removida pelos supervisores
                    </span>
                </label>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-sec" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-pri" id="btnSalvar">
                    <i class="fa fa-save"></i> Salvar pergunta
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Categoria "nova" ─────────────────────────────────────────
document.getElementById('f_categoria').addEventListener('change', function() {
    const nova = document.getElementById('f_categoria_nova');
    nova.style.display = this.value === '__nova__' ? 'block' : 'none';
    if (this.value === '__nova__') {
        nova.required = true;
        nova.name = 'categoria';
        this.name = 'categoria_ignorar';
    } else {
        nova.required = false;
        nova.name = 'categoria_nova';
        this.name = 'categoria';
    }
});

// ── Dica de tipo ─────────────────────────────────────────────
const tipoHints = {
    escolha: 'O respondente escolhe entre: Concordo totalmente → Discordo totalmente (6 opções)',
    escala:  'O respondente escolhe um número de 0 a 10. Bom para NPS e bem-estar.',
    aberta:  'Campo de texto livre. Sempre opcional para o respondente.',
};
document.getElementById('f_tipo').addEventListener('change', function() {
    document.getElementById('tipo_hint').textContent = tipoHints[this.value] || '';
});
// Dispara na carga
document.getElementById('f_tipo').dispatchEvent(new Event('change'));

// ── Abrir/fechar modal ───────────────────────────────────────
function abrirModal() {
    document.getElementById('f_acao').value       = 'criar';
    document.getElementById('f_pergunta_id').value= '';
    document.getElementById('modalTitulo').textContent = 'Nova pergunta';
    document.getElementById('btnSalvar').innerHTML = '<i class="fa fa-save"></i> Salvar pergunta';
    document.querySelector('form[method="POST"]').reset();
    document.getElementById('tipo_hint').textContent = tipoHints['escolha'];
    document.getElementById('modalPergunta').style.display = 'flex';
}

function abrirModalEditar(p) {
    document.getElementById('f_acao').value        = 'editar';
    document.getElementById('f_pergunta_id').value = p.id;
    document.getElementById('f_categoria').value   = p.categoria;
    document.getElementById('f_texto').value       = p.texto;
    document.getElementById('f_tipo').value        = p.tipo;
    document.getElementById('f_ordem').value       = p.ordem;
    document.getElementById('f_fixa').checked      = p.fixa == 1;
    document.getElementById('modalTitulo').textContent = 'Editar pergunta';
    document.getElementById('btnSalvar').innerHTML = '<i class="fa fa-save"></i> Atualizar pergunta';
    document.getElementById('f_tipo').dispatchEvent(new Event('change'));
    document.getElementById('modalPergunta').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modalPergunta').style.display = 'none';
}

// Fecha clicando fora
document.getElementById('modalPergunta').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
</script>

</body>
</html>