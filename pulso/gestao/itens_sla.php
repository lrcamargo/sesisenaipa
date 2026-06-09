<?php
// pulso/gestao/itens_sla.php — Gestão de itens de SLA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('../../conexao.php');
require_once '../_guard.php';

$msg_ok  = '';
$msg_err = '';
$hoje    = date('Y-m-d');

// ── AÇÕES POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // CRIAR ITEM (gerente lança item a partir de resposta aberta)
    if ($acao === 'criar' && $is_gerente) {
        $ciclo_id   = filter_input(INPUT_POST, 'ciclo_id',  FILTER_VALIDATE_INT);
        $categoria  = trim($_POST['categoria']  ?? '');
        $conteudo   = trim($_POST['conteudo']   ?? '');
        $resp_id    = filter_input(INPUT_POST, 'responsavel_id', FILTER_VALIDATE_INT) ?: null;
        $prazo      = $_POST['prazo'] ?? null;

        if (!$ciclo_id || !$categoria || !$conteudo) {
            $msg_err = 'Preencha todos os campos obrigatórios.';
        } else {
            $titulo      = trim($_POST['titulo']             ?? '');
            $resp_tipo   = $_POST['responsavel_tipo']        ?? 'gestao';
            $resp_pub    = trim($_POST['responsavel_publico'] ?? 'Gestão');
            $prazo_resp  = $_POST['prazo_resposta']           ?? null;
            $visivel     = isset($_POST['visivel_equipe']) && $_POST['visivel_equipe']=='1' ? 1 : 0;
            $modo        = in_array($_POST['modo_exibicao']??'',['chamado','plano']) ? $_POST['modo_exibicao'] : 'chamado';
            $res_ids     = trim($_POST['respostas_ids'] ?? '');

            $pdo->prepare("
                INSERT INTO pulso_itens_sla
                    (ciclo_id, titulo, categoria, conteudo,
                     responsavel_tipo, responsavel_publico,
                     status, responsavel_id,
                     prazo_resposta, prazo,
                     visivel_equipe, modo_exibicao, respostas_ids)
                VALUES (?, ?, ?, ?, ?, ?, 'aberto', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $ciclo_id, $titulo ?: null, $categoria, $conteudo,
                $resp_tipo, $resp_pub,
                $resp_id, $prazo_resp ?: null, $prazo ?: null,
                $visivel, $modo, $res_ids ?: null
            ]);

            // Registra no histórico
            $last_id = $pdo->lastInsertId();
            $pdo->prepare("
                INSERT INTO pulso_sla_historico
                    (item_id, status_novo, observacao, autor_id)
                VALUES (?, 'aberto', 'Item criado', ?)
            ")->execute([$last_id, $g_user_id ?? null]);

            $msg_ok = 'Item criado com sucesso.';
        }
    }

    // ATUALIZAR STATUS
    if ($acao === 'atualizar') {
        $item_id    = filter_input(INPUT_POST, 'item_id',    FILTER_VALIDATE_INT);
        $novo_status= $_POST['novo_status'] ?? '';
        $obs        = trim($_POST['observacao'] ?? '');
        $prazo      = $_POST['prazo'] ?? null;
        $resp_id    = filter_input(INPUT_POST, 'responsavel_id', FILTER_VALIDATE_INT) ?: null;

        $status_validos = ['aberto','em_andamento','resolvido','escalado'];
        if (!$item_id || !in_array($novo_status, $status_validos)) {
            $msg_err = 'Dados inválidos.';
        } else {
            // Busca status atual e verifica acesso
            $q = $pdo->prepare("
                SELECT s.status, s.ciclo_id, c.unidade_id
                FROM pulso_itens_sla s
                INNER JOIN pulso_ciclos c ON c.id = s.ciclo_id
                WHERE s.id = ?
            ");
            $q->execute([$item_id]);
            $item = $q->fetch(PDO::FETCH_ASSOC);

            if (!$item || $item['unidade_id'] != $g_unidade_id) {
                $msg_err = 'Item não encontrado.';
            } else {
                $status_ant = $item['status'];

                // Atualiza item
                $upd = "UPDATE pulso_itens_sla SET status=?, atualizado_em=NOW()";
                $params = [$novo_status];
                if ($prazo)   { $upd .= ', prazo=?';           $params[] = $prazo; }
                if ($resp_id) { $upd .= ', responsavel_id=?';  $params[] = $resp_id; }
                $upd .= ' WHERE id=?';
                $params[] = $item_id;
                $pdo->prepare($upd)->execute($params);

                // Registra histórico
                $pdo->prepare("
                    INSERT INTO pulso_sla_historico
                        (item_id, status_anterior, status_novo, observacao, autor_id)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$item_id, $status_ant, $novo_status, $obs ?: null, $g_user_id ?? null]);

                $msg_ok = 'Item atualizado.';
            }
        }
    }

    // EXCLUIR ITEM (só gerente)
    if ($acao === 'excluir' && $is_gerente) {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $pdo->prepare("DELETE FROM pulso_sla_historico WHERE item_id = ?")->execute([$item_id]);
        $pdo->prepare("DELETE FROM pulso_itens_sla WHERE id = ?")->execute([$item_id]);
        $msg_ok = 'Item excluído.';
    }
}

// ── FILTROS ───────────────────────────────────────────────────
$filtro_status = $_GET['filtro'] ?? 'todos';
$ciclo_sel     = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;

// Ciclo mais recente se não selecionado
if (!$ciclo_sel) {
    $q = $pdo->prepare("
        SELECT id FROM pulso_ciclos
        WHERE unidade_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $q->execute([$g_unidade_id]);
    $ciclo_sel = (int)($q->fetchColumn() ?: 0);
}

// Todos os ciclos para o seletor
$ciclos_stmt = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ? ORDER BY id DESC
");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ciclo atual
$ciclo_atual = null;
foreach ($ciclos_lista as $c) {
    if ($c['id'] == $ciclo_sel) { $ciclo_atual = $c; break; }
}

// WHERE de filtro
$where_status = '';
$params_status = [];
if ($filtro_status === 'vencidos') {
    $where_status = "AND s.status NOT IN ('resolvido','escalado') AND s.prazo < CURDATE()";
} elseif (in_array($filtro_status, ['aberto','em_andamento','resolvido','escalado'])) {
    $where_status = "AND s.status = ?";
    $params_status[] = $filtro_status;
}

// Filtro de responsável (supervisor vê só os seus)
$where_resp = $is_gerente ? '' : 'AND s.responsavel_id = ' . (int)($g_user_id ?? 0);

// Busca itens
$itens = [];
if ($ciclo_sel) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome AS responsavel_nome,
               COALESCE(s.responsavel_publico, 'Gestão') AS responsavel_publico
        FROM pulso_itens_sla s
        LEFT JOIN usuarios u ON u.id = s.responsavel_id
        WHERE s.ciclo_id = ? $where_status $where_resp
        ORDER BY
            CASE s.status
                WHEN 'aberto'       THEN 1
                WHEN 'em_andamento' THEN 2
                WHEN 'escalado'     THEN 3
                WHEN 'resolvido'    THEN 4
            END,
            s.prazo ASC
    ");
    $stmt->execute(array_merge([$ciclo_sel], $params_status));
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lista de gestores para atribuição
$gestores = [];
if ($is_gerente) {
    // Busca usuários dos grupos de gestão que existem no sistema
    $gestores = $pdo->query("
        SELECT id, nome FROM usuarios
        WHERE status = 1
          AND LOWER(REPLACE(perfil,'.','')) IN
              ('gerencia','sup tecnica','sup pedagogica','administrator')
        ORDER BY nome
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Contadores para abas
$contadores = ['todos' => 0, 'aberto' => 0, 'em_andamento' => 0,
               'resolvido' => 0, 'escalado' => 0, 'vencidos' => 0];
if ($ciclo_sel) {
    $cnt = $pdo->prepare("
        SELECT status,
               COUNT(*) AS n,
               SUM(CASE WHEN status NOT IN ('resolvido','escalado')
                         AND prazo < CURDATE() THEN 1 ELSE 0 END) AS venc
        FROM pulso_itens_sla
        WHERE ciclo_id = ? $where_resp
        GROUP BY status
    ");
    $cnt->execute([$ciclo_sel]);
    foreach ($cnt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $contadores['todos']         += $r['n'];
        $contadores[$r['status']]     = $r['n'];
        $contadores['vencidos']      += $r['venc'];
    }
}

$status_cfg = [
    'aberto'       => ['badge-aberto',    'Aberto',       'var(--laranja)'],
    'em_andamento' => ['badge-andamento', 'Em andamento', '#f59e0b'],
    'resolvido'    => ['badge-resolvido', 'Resolvido',    'var(--verde)'],
    'escalado'     => ['badge-escalado',  'Escalado',     '#7c3aed'],
];

$titulo_pagina = 'Itens de SLA';
$pagina_ativa  = 'sla';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-tasks" style="color:var(--laranja);margin-right:8px;"></i>Itens de SLA</h1>
            <div class="page-sub">Acompanhe e atualize os compromissos gerados pela pesquisa</div>
        </div>
        <?php if ($is_gerente && $ciclo_sel): ?>
        <button class="btn-pri" onclick="abrirModalCriar()">
            <i class="fa fa-plus"></i> Novo item
        </button>
        <?php endif; ?>
    </div>

    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- SELETOR DE CICLO + FILTROS -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <select name="ciclo" class="form-input-g" style="max-width:260px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['titulo']) ?> — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro_status) ?>">
            </form>
        </div>
    </div>

    <!-- ABAS DE STATUS -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php
        $abas = [
            'todos'       => ['Todos',        $contadores['todos'],       ''],
            'aberto'      => ['Abertos',       $contadores['aberto'],      '#E84910'],
            'em_andamento'=> ['Em andamento',  $contadores['em_andamento'],'#f59e0b'],
            'resolvido'   => ['Resolvidos',    $contadores['resolvido'],   '#1a9e4a'],
            'escalado'    => ['Escalados',     $contadores['escalado'],    '#7c3aed'],
            'vencidos'    => ['⚠️ Vencidos',   $contadores['vencidos'],    '#c0392b'],
        ];
        foreach ($abas as $key => [$label, $cnt, $cor]):
            $ativo = $filtro_status === $key;
        ?>
        <a href="?ciclo=<?= $ciclo_sel ?>&filtro=<?= $key ?>"
           style="display:inline-flex;align-items:center;gap:6px;
                  padding:7px 14px;border-radius:8px;font-size:13px;
                  font-weight:<?= $ativo ? '700' : '500' ?>;
                  text-decoration:none;
                  background:<?= $ativo ? ($cor ?: 'var(--azul)') : '#fff' ?>;
                  color:<?= $ativo ? '#fff' : '#666' ?>;
                  border:1.5px solid <?= $ativo ? ($cor ?: 'var(--azul)') : '#dde3ef' ?>;
                  box-shadow:<?= $ativo ? '0 2px 8px rgba(0,0,0,.15)' : 'none' ?>;">
            <?= $label ?>
            <span style="background:<?= $ativo ? 'rgba(255,255,255,.25)' : '#f0f3f8' ?>;
                         color:<?= $ativo ? '#fff' : '#888' ?>;
                         font-size:11px;font-weight:700;
                         padding:1px 7px;border-radius:10px;">
                <?= $cnt ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- LISTA DE ITENS -->
    <div class="g-card">
        <?php if (empty($itens)): ?>
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhum item encontrado para este filtro.
        </div>
        <?php else: ?>
        <table class="g-table">
            <thead>
                <tr>
                    <th style="width:32px;">
                        <input type="checkbox" id="checkTodos"
                               style="accent-color:var(--azul);width:15px;height:15px;"
                               onchange="toggleTodos(this)">
                    </th>
                    <th style="width:30px;"></th>
                    <th>Título / Conteúdo</th>
                    <th>Status</th>
                    <th>Responsável</th>
                    <th>Prazo resp.</th>
                    <th>Prazo resol.</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($itens as $it):
                $sc   = $status_cfg[$it['status']] ?? ['','','' ];
                $venc = $it['prazo'] && $it['prazo'] < $hoje
                        && !in_array($it['status'], ['resolvido','escalado']);
            ?>
            <tr data-id="<?= $it['id'] ?>"
                data-titulo="<?= htmlspecialchars($it['titulo'] ?? $it['categoria'], ENT_QUOTES) ?>"
                data-categoria="<?= htmlspecialchars($it['categoria'], ENT_QUOTES) ?>"
                data-conteudo="<?= htmlspecialchars($it['conteudo'], ENT_QUOTES) ?>"
                data-status="<?= $it['status'] ?>">
                <td style="text-align:center;">
                    <input type="checkbox" class="check-item"
                           value="<?= $it['id'] ?>"
                           style="accent-color:var(--azul);width:15px;height:15px;"
                           onchange="atualizarSelecionados()">
                </td>
                <td style="text-align:center;">
                    <?php
                    // Semáforo SLA
                    $semaforo = '🟢';
                    if ($it['status'] === 'resolvido' || $it['status'] === 'escalado') {
                        $semaforo = '⚫';
                    } elseif ($venc) {
                        $semaforo = '🔴';
                    } elseif ($it['prazo_resposta'] && $it['prazo_resposta'] < date('Y-m-d', strtotime('+2 days'))) {
                        $semaforo = '🟡';
                    }
                    echo $semaforo;
                    ?>
                </td>
                <td style="max-width:300px;line-height:1.4;">
                    <?php if (!empty($it['titulo'])): ?>
                    <div style="font-size:13px;font-weight:600;color:var(--texto);margin-bottom:3px;">
                        <?= htmlspecialchars($it['titulo']) ?>
                    </div>
                    <?php endif; ?>
                    <span class="badge-cat" style="margin-bottom:4px;display:inline-block;">
                        <?= htmlspecialchars($it['categoria']) ?>
                    </span>
                    <div style="font-size:12px;color:#888;">
                        <?= htmlspecialchars(mb_strimwidth($it['conteudo'], 0, 80, '…')) ?>
                    </div>
                    <?php if (!empty($it['visivel_equipe'])): ?>
                    <span style="font-size:10px;color:#1a9e4a;"><i class="fa fa-eye"></i> Público</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge-status <?= $sc[0] ?>"><?= $sc[1] ?></span></td>
                <td style="font-size:12px;color:#555;">
                    <?php
                    $resp_pub = $it['responsavel_publico'] ?? '';
                    $resp_priv = $it['responsavel_nome'] ?? null;
                    echo htmlspecialchars($resp_pub);
                    if ($resp_priv && $resp_priv !== $resp_pub):
                    ?>
                    <span style="display:block;font-size:11px;color:#aaa;">
                        (<?= htmlspecialchars($resp_priv) ?>)
                    </span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#888;">
                    <?= !empty($it['prazo_resposta']) ? date('d/m/Y', strtotime($it['prazo_resposta'])) : '—' ?>
                </td>
                <td style="font-size:12px;<?= $venc ? 'color:#c0392b;font-weight:600;' : 'color:#888;' ?>">
                    <?= $it['prazo'] ? date('d/m/Y', strtotime($it['prazo'])) : '—' ?>
                    <?php if ($venc): ?>
                    <span style="display:block;font-size:11px;">Vencido</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-sec btn-sm"
                                onclick='abrirModalAtualizar(<?= htmlspecialchars(json_encode($it)) ?>)'>
                            <i class="fa fa-pencil"></i> Atualizar
                        </button>
                        <?php if ($is_gerente): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Excluir este item?')">
                            <input type="hidden" name="acao"    value="excluir">
                            <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                            <button type="submit" class="btn-danger btn-sm">
                                <i class="fa fa-trash"></i>
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

</main>

<!-- MODAL: CRIAR ITEM -->
<?php if ($is_gerente): ?>
<div id="modalCriar" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:540px;
                box-shadow:0 16px 48px rgba(0,0,0,.2);overflow:hidden;">
        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;">Novo item de SLA</h3>
            <button onclick="fecharModal('modalCriar')"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:20px;cursor:pointer;">×</button>
        </div>
        <form method="POST" style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" name="acao"     value="criar">
            <input type="hidden" name="ciclo_id" value="<?= $ciclo_sel ?>">

            <div>
                <label class="form-label-g">Categoria *</label>
                <select name="categoria" class="form-input-g" required>
                    <option value="">— Selecione —</option>
                    <?php foreach (['Reconhecimento e Carreira','Treinamento e Desenvolvimento',
                                    'Condições Físicas','Liderança','Qualidade de Vida',
                                    'Relacionamento','Comunicação','Geral'] as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label-g">Conteúdo do item *</label>
                <textarea name="conteudo" class="form-input-g" rows="3" required
                          placeholder="Descreva o ponto levantado (anonimizado)..."
                          style="resize:vertical;"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="form-label-g">Responsável</label>
                    <select name="responsavel_id" class="form-input-g">
                        <option value="">— A definir —</option>
                        <?php foreach ($gestores as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label-g">Prazo</label>
                    <input type="date" name="prazo" class="form-input-g"
                           min="<?= $hoje ?>">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-sec" onclick="fecharModal('modalCriar')">Cancelar</button>
                <button type="submit" class="btn-pri">
                    <i class="fa fa-save"></i> Criar item
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: ATUALIZAR STATUS -->
<div id="modalAtualizar" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:540px;
                box-shadow:0 16px 48px rgba(0,0,0,.2);overflow:hidden;">
        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;">Atualizar item</h3>
            <button onclick="fecharModal('modalAtualizar')"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:20px;cursor:pointer;">×</button>
        </div>
        <form method="POST" style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" name="acao"    value="atualizar">
            <input type="hidden" name="item_id" id="u_item_id">

            <div id="u_conteudo_preview"
                 style="background:#f8faff;border-radius:8px;padding:12px 14px;
                        font-size:13px;color:#555;line-height:1.5;border:1px solid #e0e9f8;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="form-label-g">Novo status *</label>
                    <select name="novo_status" id="u_status" class="form-input-g" required
                               onchange="toggleObsReq()">
                        <option value="aberto">Aberto</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="resolvido">Resolvido</option>
                        <option value="escalado">Escalado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label-g">Prazo</label>
                    <input type="date" name="prazo" id="u_prazo" class="form-input-g">
                </div>
            </div>

            <?php if ($is_gerente): ?>
            <div>
                <label class="form-label-g">Responsável</label>
                <select name="responsavel_id" id="u_resp" class="form-input-g">
                    <option value="">— A definir —</option>
                    <?php foreach ($gestores as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div id="u_obs_wrap">
                <label class="form-label-g" id="u_obs_label">
                    Observação
                    <span id="u_obs_req" style="color:#c0392b;display:none;">
                        * obrigatória ao resolver — este texto fica visível para a equipe
                    </span>
                </label>
                <textarea name="observacao" id="u_observacao"
                          class="form-input-g" rows="3"
                          placeholder="Registre o que foi feito. Ao marcar como Resolvido, este texto aparece publicamente para a equipe."
                          style="resize:vertical;"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-sec" onclick="fecharModal('modalAtualizar')">Cancelar</button>
                <button type="submit" class="btn-pri">
                    <i class="fa fa-save"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
}
function abrirModalCriar() {
    document.getElementById('modalCriar').style.display = 'flex';
}
function abrirModalAtualizar(it) {
    document.getElementById('u_item_id').value  = it.id;
    document.getElementById('u_status').value   = it.status;
    document.getElementById('u_prazo').value    = it.prazo || '';
    document.getElementById('u_observacao').value = ''; // limpa para nova obs
    document.getElementById('u_conteudo_preview').textContent =
        (it.titulo ? it.titulo + '\n' : '') + it.conteudo;
    const resp = document.getElementById('u_resp');
    if (resp) resp.value = it.responsavel_id || '';
    toggleObsReq();
    document.getElementById('modalAtualizar').style.display = 'flex';
}

function toggleObsReq() {
    const status = document.getElementById('u_status').value;
    const req    = document.getElementById('u_obs_req');
    const obs    = document.getElementById('u_observacao');
    if (status === 'resolvido') {
        req.style.display = 'inline';
        obs.style.borderColor = '#1a9e4a';
        obs.placeholder = 'Descreva o que foi feito para resolver este item. Este texto ficará visível para a equipe no painel público.';
    } else {
        req.style.display = 'none';
        obs.style.borderColor = '';
        obs.placeholder = 'Registre o que foi feito ou o motivo da mudança...';
    }
}
// Fecha clicando fora
['modalCriar','modalAtualizar'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', e => {
        if (e.target.id === id) fecharModal(id);
    });
});
</script>

<!-- FAB: GERAR PLANO DE AÇÃO -->
<div id="fabWrap" style="position:fixed;bottom:28px;right:28px;z-index:300;
     transition:all .25s;transform:translateY(80px);opacity:0;">
    <button onclick="abrirModalPlanoSLA()"
            style="background:var(--azul);color:#fff;border:none;border-radius:28px;
                   padding:14px 24px;font-size:14px;font-weight:700;
                   display:flex;align-items:center;gap:10px;
                   box-shadow:0 6px 20px rgba(22,65,148,.35);cursor:pointer;">
        <i class="fa fa-list-alt"></i>
        Gerar plano de ação
        <span id="contadorSel" style="background:var(--laranja);color:#fff;
              border-radius:14px;padding:2px 10px;font-size:13px;font-weight:800;">0</span>
    </button>
</div>

<!-- MODAL: GERAR PLANO A PARTIR DE SLAs -->
<div id="modalPlanoSLA" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:640px;
                max-height:90vh;overflow:hidden;display:flex;flex-direction:column;
                box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="background:var(--azul);padding:18px 24px;
                    display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;">
                <i class="fa fa-list-alt" style="margin-right:8px;"></i>Gerar plano de ação
            </h3>
            <button onclick="fecharModalPlanoSLA()"
                    style="background:none;border:none;color:rgba(255,255,255,.7);
                           font-size:22px;cursor:pointer;">×</button>
        </div>

        <div style="overflow-y:auto;padding:24px;flex:1;">

            <!-- ITENS SELECIONADOS -->
            <div style="margin-bottom:20px;">
                <label class="form-label-g">Itens de SLA selecionados como base</label>
                <div id="slasPreview"
                     style="background:#f8faff;border:1px solid #d8e4f5;border-radius:8px;
                            padding:14px;font-size:13px;color:#444;line-height:1.8;
                            max-height:160px;overflow-y:auto;"></div>
            </div>

            <!-- CONTEXTO ADICIONAL -->
            <div class="form-group-g">
                <label class="form-label-g">Contexto adicional (opcional)</label>
                <textarea id="plano_contexto" class="form-input-g" rows="2"
                          placeholder="Informações adicionais que a IA deve considerar..."
                          style="resize:vertical;"></textarea>
            </div>

            <!-- RESPONSÁVEL -->
            <div class="form-group-g">
                <label class="form-label-g">Quem estará envolvido?</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ([
                        ['gestao','fa-user-secret','Apenas gestão'],
                        ['docente','fa-graduation-cap','Apenas docentes'],
                        ['compartilhado','fa-handshake-o','Gestão + docentes'],
                    ] as [$val,$ico,$lbl]): ?>
                    <label id="pr_<?= $val ?>"
                           style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                  padding:9px 14px;border:1.5px solid #dde3ef;border-radius:8px;
                                  font-size:13px;transition:all .15s;">
                        <input type="radio" name="plano_tipo_resp" value="<?= $val ?>"
                               style="accent-color:var(--azul);"
                               <?= $val==='compartilhado'?'checked':'' ?>
                               onchange="destacarRadioPlano()">
                        <i class="fa <?= $ico ?>"></i> <?= $lbl ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RESULTADO IA -->
            <div id="planoResultadoIA" style="display:none;">
                <div style="border-top:1px solid #eef0f5;padding-top:20px;margin-bottom:16px;">
                    <label class="form-label-g">
                        <i class="fa fa-magic" style="color:var(--laranja);margin-right:4px;"></i>
                        Plano gerado — revise antes de salvar
                    </label>
                    <div class="form-group-g">
                        <label class="form-label-g">Título</label>
                        <input type="text" id="plano_titulo" class="form-input-g">
                    </div>
                    <div class="form-group-g">
                        <label class="form-label-g">Objetivo</label>
                        <textarea id="plano_objetivo" class="form-input-g" rows="2"
                                  style="resize:vertical;"></textarea>
                    </div>
                    <label class="form-label-g">Ações</label>
                    <div id="plano_acoes_container"></div>
                    <button type="button" onclick="addAcaoPlano()"
                            class="btn-sec btn-sm" style="margin-top:8px;">
                        <i class="fa fa-plus"></i> Adicionar ação
                    </button>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" id="plano_visivel"
                               style="accent-color:var(--azul);width:16px;height:16px;">
                        <span><strong>Tornar visível para a equipe</strong> — aparece no painel público</span>
                    </label>
                </div>
            </div>

            <!-- LOADING IA -->
            <div id="planoLoadingIA" style="display:none;text-align:center;padding:32px;">
                <i class="fa fa-spinner fa-spin" style="font-size:32px;color:var(--azul);"></i>
                <div style="margin-top:12px;font-size:14px;color:#888;">Gerando plano de ação...</div>
            </div>

        </div>

        <div style="padding:16px 24px;border-top:1px solid #eef0f5;flex-shrink:0;
                    display:flex;gap:10px;justify-content:flex-end;background:#fafbff;">
            <button class="btn-sec" onclick="fecharModalPlanoSLA()">Cancelar</button>
            <button class="btn-pri" id="btnGerarPlanoIA" onclick="gerarPlanoComIA()">
                <i class="fa fa-magic"></i> Gerar com IA
            </button>
            <button class="btn-pri" id="btnSalvarPlanoSLA" style="display:none;"
                    onclick="salvarPlanoSLA()">
                <i class="fa fa-save"></i> Salvar plano
            </button>
        </div>
    </div>
</div>

<!-- FORM OCULTO -->
<form id="formSalvarPlanoSLA" method="POST" action="plano_acao.php" style="display:none;">
    <input type="hidden" name="acao"             value="salvar_novo">
    <input type="hidden" name="ciclo_id"         value="<?= $ciclo_sel ?>">
    <input type="hidden" name="titulo"           id="fps_titulo">
    <input type="hidden" name="problema"         id="fps_problema">
    <input type="hidden" name="objetivo"         id="fps_objetivo">
    <input type="hidden" name="acoes"            id="fps_acoes">
    <input type="hidden" name="tipo_responsavel" id="fps_tipo">
    <input type="hidden" name="visivel_equipe"   id="fps_visivel" value="0">
    <input type="hidden" name="gerado_por_ia"    value="1">
</form>

<script>
// ── SELEÇÃO DE ITENS ─────────────────────────────────────────
let itensSelecionados = {};

function toggleTodos(cb) {
    document.querySelectorAll('.check-item').forEach(c => {
        c.checked = cb.checked;
        const tr = c.closest('tr');
        if (cb.checked) {
            itensSelecionados[tr.dataset.id] = {
                titulo:    tr.dataset.titulo,
                categoria: tr.dataset.categoria,
                conteudo:  tr.dataset.conteudo,
                status:    tr.dataset.status,
            };
        } else {
            delete itensSelecionados[tr.dataset.id];
        }
    });
    atualizarSelecionados();
}

function atualizarSelecionados() {
    document.querySelectorAll('.check-item').forEach(c => {
        const tr = c.closest('tr');
        if (c.checked) {
            itensSelecionados[tr.dataset.id] = {
                titulo:    tr.dataset.titulo,
                categoria: tr.dataset.categoria,
                conteudo:  tr.dataset.conteudo,
                status:    tr.dataset.status,
            };
        } else {
            delete itensSelecionados[tr.dataset.id];
        }
    });
    const n   = Object.keys(itensSelecionados).length;
    const fab = document.getElementById('fabWrap');
    document.getElementById('contadorSel').textContent = n;
    if (n > 0) { fab.style.transform='translateY(0)'; fab.style.opacity='1'; }
    else        { fab.style.transform='translateY(80px)'; fab.style.opacity='0'; }
}

// ── MODAL ────────────────────────────────────────────────────
function abrirModalPlanoSLA() {
    const itens = Object.values(itensSelecionados);
    if (!itens.length) return;

    document.getElementById('slasPreview').innerHTML = itens.map(it =>
        `<div style="margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid #e0e9f8;">
            <strong>${it.titulo}</strong>
            <span class="badge-cat" style="margin-left:6px;font-size:10px;">${it.categoria}</span><br>
            <span style="font-size:12px;color:#888;">${it.conteudo.substring(0,100)}...</span>
         </div>`
    ).join('');

    document.getElementById('planoResultadoIA').style.display  = 'none';
    document.getElementById('planoLoadingIA').style.display    = 'none';
    document.getElementById('btnGerarPlanoIA').style.display   = 'inline-flex';
    document.getElementById('btnSalvarPlanoSLA').style.display = 'none';
    document.getElementById('modalPlanoSLA').style.display     = 'flex';
    destacarRadioPlano();
}

function fecharModalPlanoSLA() {
    document.getElementById('modalPlanoSLA').style.display = 'none';
}
document.getElementById('modalPlanoSLA').addEventListener('click', e => {
    if (e.target.id === 'modalPlanoSLA') fecharModalPlanoSLA();
});

function destacarRadioPlano() {
    document.querySelectorAll('[id^="pr_"]').forEach(el => {
        const cb = el.querySelector('input[type="radio"]');
        el.style.borderColor = cb.checked ? 'var(--azul)' : '#dde3ef';
        el.style.background  = cb.checked ? '#eef3fd'     : '#fff';
        el.style.fontWeight  = cb.checked ? '600'         : '400';
    });
}

// ── GERAR COM IA ─────────────────────────────────────────────
async function gerarPlanoComIA() {
    const itens    = Object.values(itensSelecionados);
    const contexto = document.getElementById('plano_contexto').value.trim();
    const tipo     = document.querySelector('input[name="plano_tipo_resp"]:checked')?.value || 'compartilhado';

    document.getElementById('planoLoadingIA').style.display    = 'block';
    document.getElementById('btnGerarPlanoIA').disabled        = true;
    document.getElementById('planoResultadoIA').style.display  = 'none';

    const insumo = itens.map(it =>
        `Título: "${it.titulo}"\nCategoria: ${it.categoria}\nDescrição: "${it.conteudo}"`
    ).join('\n\n');

    const tipoLabel = {
        gestao:'apenas a equipe gestora', docente:'apenas os docentes',
        compartilhado:'gestão e docentes em conjunto'
    }[tipo];

    const prompt = `Você é consultor de gestão escolar. Crie um plano de ação para os seguintes itens de SLA de uma escola SENAI.

ITENS DE SLA:
${insumo}

${contexto ? 'CONTEXTO ADICIONAL: ' + contexto + '\n' : ''}
ENVOLVIDOS: ${tipoLabel}

Responda APENAS em JSON válido, sem markdown:
{"titulo":"título conciso","objetivo":"o que se quer alcançar","acoes":[{"descricao":"ação","como":"como executar","responsavel_tipo":"gestao","responsavel_sugerido":"papel","prazo_sugerido":"prazo"}]}`;

    try {
        const resp = await fetch('api_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: [{ role: 'user', content: prompt }], max_tokens: 800 })
        });
        const raw  = await resp.text();
        const data = JSON.parse(raw);
        if (data.error) throw new Error(data.error);
        const texto = data.content?.[0]?.text || '';
        const plano = JSON.parse(texto.replace(/```json|```/g,'').trim());

        document.getElementById('plano_titulo').value   = plano.titulo   || '';
        document.getElementById('plano_objetivo').value = plano.objetivo || '';

        const container = document.getElementById('plano_acoes_container');
        container.innerHTML = '';
        (plano.acoes || []).forEach(a => addAcaoPlano(a));

        document.getElementById('planoResultadoIA').style.display   = 'block';
        document.getElementById('btnSalvarPlanoSLA').style.display  = 'inline-flex';
        document.getElementById('btnGerarPlanoIA').innerHTML        = '<i class="fa fa-refresh"></i> Regenerar';
    } catch(err) {
        alert('Erro ao gerar plano: ' + err.message);
    } finally {
        document.getElementById('planoLoadingIA').style.display = 'none';
        document.getElementById('btnGerarPlanoIA').disabled     = false;
    }
}

let acaoPlanoIdx = 0;
function addAcaoPlano(d = {}) {
    const i = acaoPlanoIdx++;
    const div = document.createElement('div');
    div.id = `ap_${i}`;
    div.style.cssText = 'background:#f8faff;border:1px solid #dde3ef;border-radius:10px;padding:14px;margin-bottom:10px;';
    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;color:var(--azul);">Ação ${i+1}</span>
            <button type="button" onclick="document.getElementById('ap_${i}').remove()"
                    style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:13px;">
                <i class="fa fa-trash"></i></button>
        </div>
        <div class="form-group-g">
            <label class="form-label-g">Descrição</label>
            <textarea class="form-input-g ap-desc" rows="2" style="resize:vertical;">${d.descricao||''}</textarea>
        </div>
        <div class="form-group-g">
            <label class="form-label-g">Como executar</label>
            <textarea class="form-input-g ap-como" rows="1" style="resize:vertical;">${d.como||''}</textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
            <div>
                <label class="form-label-g">Responsável</label>
                <select class="form-input-g ap-tipo">
                    <option value="gestao" ${d.responsavel_tipo==='gestao'?'selected':''}>Gestão</option>
                    <option value="docente" ${d.responsavel_tipo==='docente'?'selected':''}>Docente</option>
                    <option value="compartilhado" ${d.responsavel_tipo==='compartilhado'?'selected':''}>Compartilhado</option>
                </select>
            </div>
            <div>
                <label class="form-label-g">Papel</label>
                <input type="text" class="form-input-g ap-papel" value="${d.responsavel_sugerido||''}">
            </div>
            <div>
                <label class="form-label-g">Prazo</label>
                <input type="text" class="form-input-g ap-prazo" value="${d.prazo_sugerido||''}">
            </div>
        </div>`;
    document.getElementById('plano_acoes_container').appendChild(div);
}

function salvarPlanoSLA() {
    const acoes = [];
    document.querySelectorAll('[id^="ap_"]').forEach(div => {
        acoes.push({
            descricao:            div.querySelector('.ap-desc')?.value  || '',
            como:                 div.querySelector('.ap-como')?.value  || '',
            responsavel_tipo:     div.querySelector('.ap-tipo')?.value  || 'gestao',
            responsavel_sugerido: div.querySelector('.ap-papel')?.value || '',
            prazo_sugerido:       div.querySelector('.ap-prazo')?.value || '',
            status:               'pendente',
        });
    });

    const itens   = Object.values(itensSelecionados);
    const problema = itens.map(it => it.titulo + ': ' + it.conteudo).join('\n');
    const tipo    = document.querySelector('input[name="plano_tipo_resp"]:checked')?.value || 'compartilhado';

    document.getElementById('fps_titulo').value   = document.getElementById('plano_titulo').value;
    document.getElementById('fps_problema').value = problema;
    document.getElementById('fps_objetivo').value = document.getElementById('plano_objetivo').value;
    document.getElementById('fps_acoes').value    = JSON.stringify(acoes);
    document.getElementById('fps_tipo').value     = tipo;
    document.getElementById('fps_visivel').value  = document.getElementById('plano_visivel').checked ? '1' : '0';

    document.getElementById('formSalvarPlanoSLA').submit();
}
</script>
</body>
</html>