<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../conexao.php');
session_start();

if (!isset($_SESSION['sLogin'])) { header('location:../index.php'); exit; }

$logado = $_SESSION['user'];

date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d');
$data = isset($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data']) ? $_GET['data'] : $hoje;

$mensagem = '';
$erro = '';

/* ── Bitmask dias da semana: Seg=1 Ter=2 Qua=4 Qui=8 Sex=16 Sab=32 ── */
const DIAS_BIT = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16, 6 => 32, 0 => 0]; // date('w'): 0=Dom
function bitDoDia(string $data): int {
    return DIAS_BIT[(int) date('w', strtotime($data))] ?? 0;
}

// Condições que NÃO contam na turma (não entram na chamada, não somam no total)
const CONDICOES_FORA_CONTAGEM = ['evadido', 'cancelado'];

// ---------------------------------------------------------
// Salvar chamada (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turmaId = $_POST['turma_id'] ?? '';
    $dataChamada = $_POST['data'] ?? $hoje;
    $presentes = $_POST['presente'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataChamada)) {
        $erro = 'Data inválida.';
    } elseif (empty($turmaId)) {
        $erro = 'Turma inválida.';
    } else {
        try {
            // Só entram na chamada alunos ativos e que não estejam evadidos/cancelados
            $stmtAlunos = $pdo->prepare("
                SELECT pessoa_id, documento
                FROM turma_alunos
                WHERE turma_id = :turma_id
                  AND status_matricula = 1
                  AND condicao NOT IN ('evadido','cancelado')
            ");
            $stmtAlunos->execute([':turma_id' => $turmaId]);
            $alunosDaTurma = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);

            $stmtUpsert = $pdo->prepare("
                INSERT INTO chamada (turma_id, pessoa_id, documento, data, presente, lancado_por)
                VALUES (:turma_id, :pessoa_id, :documento, :data, :presente, :lancado_por)
                ON DUPLICATE KEY UPDATE
                    presente = VALUES(presente),
                    lancado_por = VALUES(lancado_por)
            ");

            $pdo->beginTransaction();
            foreach ($alunosDaTurma as $aluno) {
                $estaPresente = in_array($aluno['pessoa_id'], $presentes, true) ? 1 : 0;
                $stmtUpsert->execute([
                    ':turma_id'    => $turmaId,
                    ':pessoa_id'   => $aluno['pessoa_id'],
                    ':documento'   => $aluno['documento'],
                    ':data'        => $dataChamada,
                    ':presente'    => $estaPresente,
                    ':lancado_por' => $logado,
                ]);
            }
            $pdo->commit();

            $mensagem = 'Chamada salva com sucesso (' . count($alunosDaTurma) . ' alunos).';
            $data = $dataChamada;
            $_GET['turma_id'] = $turmaId;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar chamada: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------
// Turmas vigentes na data selecionada
// ---------------------------------------------------------
$stmtTurmas = $pdo->prepare("
    SELECT id, nome, codigo
    FROM turmas
    WHERE data_inicio <= :data AND data_fim >= :data
    ORDER BY codigo
");
$stmtTurmas->execute([':data' => $data]);
$turmasVigentes = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// Local de cada turma na data (reserva > sala fixa > sem sala)
// ---------------------------------------------------------
$bitHoje = bitDoDia($data);

$stmtRes = $pdo->prepare("
    SELECT r.turma, r.aprovado, l.nome AS nomeLocal
    FROM reservas r
    JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
    WHERE r.data = :data
");
$stmtRes->execute([':data' => $data]);
$reservaPorTurma = [];
foreach ($stmtRes->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (!isset($reservaPorTurma[$r['turma']]) || (int)$r['aprovado'] === 1) {
        $reservaPorTurma[$r['turma']] = $r;
    }
}

$salaFixaPorTurma = [];
$stmtSala = $pdo->query("
    SELECT ts.codigoTurma, ts.turno, ts.diasSemana, l.nome AS nomeLocal
    FROM turma_sala ts
    JOIN laboratorios l ON l.idLaboratorio = ts.idSala
");
foreach ($stmtSala->fetchAll(PDO::FETCH_ASSOC) as $s) {
    if (((int)$s['diasSemana'] & $bitHoje) > 0) {
        $salaFixaPorTurma[$s['codigoTurma']][$s['turno']] = $s['nomeLocal'];
    }
}

$stmtSalaExt = $pdo->query("
    SELECT tse.codigoTurma, tse.turno, tse.diasSemana, se.nome AS nomeSala, p.nome AS nomePredio
    FROM turma_sala_externa tse
    JOIN salas_externas se ON se.id = tse.idSala
    JOIN predios p ON p.id = se.idPredio
");
foreach ($stmtSalaExt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    if (((int)$s['diasSemana'] & $bitHoje) > 0) {
        $salaFixaPorTurma[$s['codigoTurma']][$s['turno']] = $s['nomeSala'] . ' — ' . $s['nomePredio'];
    }
}

function localDaTurma(string $codigo, array $reservaPorTurma, array $salaFixaPorTurma): array {
    if (isset($reservaPorTurma[$codigo])) {
        $r = $reservaPorTurma[$codigo];
        return [
            'texto'  => $r['nomeLocal'],
            'status' => (int)$r['aprovado'] === 1 ? 'reservado' : 'aguardando',
        ];
    }
    if (isset($salaFixaPorTurma[$codigo])) {
        $partes = [];
        foreach ($salaFixaPorTurma[$codigo] as $turno => $nomeLocal) {
            $partes[] = $nomeLocal . ' (' . ucfirst($turno) . ')';
        }
        return ['texto' => implode(' / ', array_unique($partes)), 'status' => 'sala-fixa'];
    }
    return ['texto' => 'Sem sala definida', 'status' => 'sem-sala'];
}

$locais = [];
foreach ($turmasVigentes as $t) {
    $locais[$t['id']] = localDaTurma($t['codigo'], $reservaPorTurma, $salaFixaPorTurma);
}

$badgeClasse = [
    'reservado'  => 'badge-danger',
    'aguardando' => 'badge-warning',
    'sala-fixa'  => 'badge-success',
    'sem-sala'   => 'badge-secondary',
];

$condicaoLabel = [
    'normal'    => 'Normal',
    'atestado'  => 'Atestado',
    'evadido'   => 'Evadido',
    'cancelado' => 'Cancelado',
];
$condicaoBadge = [
    'normal'    => '',                 // sem badge — é o padrão
    'atestado'  => 'badge-info',
    'evadido'   => 'badge-secondary',
    'cancelado' => 'badge-dark',
];

// ---------------------------------------------------------
// Alunos ATIVOS da turma selecionada
// ---------------------------------------------------------
$turmaSelecionada = $_GET['turma_id'] ?? '';
$alunos = [];
$turmaInfo = null;
$localSelecionado = null;

if (!empty($turmaSelecionada)) {
    $stmtTurmaInfo = $pdo->prepare("SELECT id, nome, codigo FROM turmas WHERE id = :id");
    $stmtTurmaInfo->execute([':id' => $turmaSelecionada]);
    $turmaInfo = $stmtTurmaInfo->fetch(PDO::FETCH_ASSOC);

    if ($turmaInfo) {
        $localSelecionado = $locais[$turmaSelecionada] ?? localDaTurma($turmaInfo['codigo'], $reservaPorTurma, $salaFixaPorTurma);
    }

    // status_matricula = 1 -> ativo (vindo da catraca; inativos nem chegam a ser sincronizados).
    // Ordena: normal/atestado primeiro (por nome), evadido/cancelado por último (por nome).
    $stmtAlunos = $pdo->prepare("
        SELECT ta.pessoa_id, ta.nome, ta.documento, ta.condicao, c.presente AS presente_lancado
        FROM turma_alunos ta
        LEFT JOIN chamada c
            ON c.turma_id = ta.turma_id AND c.pessoa_id = ta.pessoa_id AND c.data = :data
        WHERE ta.turma_id = :turma_id AND ta.status_matricula = 1
        ORDER BY
            CASE WHEN ta.condicao IN ('evadido','cancelado') THEN 1 ELSE 0 END,
            ta.nome
    ");
    $stmtAlunos->execute([':data' => $data, ':turma_id' => $turmaSelecionada]);
    $alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);
}

// Separa em dois grupos pra exibição: quem conta na turma e quem não conta
$alunosContam = array_filter($alunos, fn($a) => !in_array($a['condicao'], CONDICOES_FORA_CONTAGEM, true));
$alunosForaContagem = array_filter($alunos, fn($a) => in_array($a['condicao'], CONDICOES_FORA_CONTAGEM, true));
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chamada diária</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.card-chamada{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:16px;margin-bottom:16px;}

.lista-alunos{display:flex;flex-direction:column;gap:8px;margin-bottom:90px;} /* espaço p/ barra fixa */
.aluno-item{
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
    padding:10px 12px;border:1px solid #e9ecef;border-radius:8px;
    background:#fff;transition:background .15s,border-color .15s;
}
.aluno-item.ausente{background:#fdecea;border-color:#f5c6cb;}
.aluno-item.fora-contagem{background:#f1f2f4;border-color:#dee2e6;opacity:.8;}
.aluno-info{flex:1;min-width:160px;}
.aluno-nome{font-weight:600;font-size:.92rem;line-height:1.2;}
.aluno-doc{font-size:.76rem;color:#6c757d;}
.aluno-condicao-badge{font-size:.68rem;margin-left:6px;vertical-align:middle;}

.chk-presente-wrap{flex-shrink:0;}
.chk-presente-wrap input{width:26px;height:26px;cursor:pointer;}

.select-condicao{font-size:.76rem;padding:3px 6px;border-radius:4px;border:1px solid #ced4da;background:#fff;}

.divisor-fora-contagem{
    display:flex;align-items:center;gap:10px;margin:18px 0 10px;color:#6c757d;font-size:.8rem;font-weight:600;
    text-transform:uppercase;letter-spacing:.03em;
}
.divisor-fora-contagem::after{content:'';flex:1;height:1px;background:#dee2e6;}

/* Barra de ação fixa embaixo, botão à direita */
.barra-acao{
    position:fixed;left:0;right:0;bottom:0;z-index:1030;
    background:#fff;border-top:1px solid #dee2e6;
    padding:10px 16px;
    display:flex;align-items:center;justify-content:flex-end;gap:12px;
    box-shadow:0 -2px 8px rgba(0,0,0,.06);
}
.barra-acao .btn-salvar{min-width:160px;}
.barra-acao .contador{font-size:.82rem;color:#6c757d;}

.toolbar-topo{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-bottom:12px;}
.toolbar-topo .btns-marcar button{margin-right:6px;}

@media (max-width: 576px){
    .toolbar-topo{flex-direction:column;align-items:stretch;}
    .toolbar-topo .btns-marcar{display:flex;gap:6px;}
    .toolbar-topo .btns-marcar button{flex:1;margin-right:0;}
    .barra-acao{justify-content:flex-end;}
}
</style>
</head>
<body>
<div class="wrapper">
<div class="header">
    <div class="header-menu">
        <div class="title"><img src="../img/logo_white.svg"></div>
        <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
        <ul>
            <li><a href="#" class="user"><?php echo htmlspecialchars($logado); ?></a></li>
            <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
        </ul>
    </div>
</div>
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container">

    <h4 class="mb-3"><i class="fas fa-clipboard-check mr-2"></i>Chamada diária</h4>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    
    <div class="card-chamada">
        <form method="get" id="formFiltro">
            <div class="form-row">
                <div class="form-group col-12 col-md-3">
                    <label class="font-weight-bold" style="font-size:.82rem">Data</label>
                    <input type="date" name="data" id="data" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($data) ?>" max="<?= htmlspecialchars($hoje) ?>">
                </div>
                <div class="form-group col-12 col-md-7">
                    <label class="font-weight-bold" style="font-size:.82rem">Turma</label>
                    <select name="turma_id" id="turma_id" class="form-control form-control-sm">
                        <option value="">Selecione a turma...</option>
                        <?php foreach ($turmasVigentes as $t):
                            $loc = $locais[$t['id']];
                        ?>
                            <option value="<?= htmlspecialchars($t['id']) ?>"
                                    <?= $t['id'] === $turmaSelecionada ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['codigo']) ?> — <?= htmlspecialchars($loc['texto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-12 col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-secondary btn-sm btn-block">
                        <i class="fas fa-sync-alt mr-1"></i>Carregar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($turmasVigentes)): ?>
        <div class="alert alert-warning">Nenhuma turma vigente para esta data.</div>
    <?php endif; ?>

    <?php if ($turmaInfo && !empty($alunos)): ?>
        <div class="card-chamada">
            <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap:8px">
                <h5 class="mb-0"><?= htmlspecialchars($turmaInfo['codigo']) ?></h5>
                <span class="badge <?= $badgeClasse[$localSelecionado['status']] ?> p-2">
                    <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($localSelecionado['texto']) ?>
                </span>
            </div>

            <form method="post" id="formChamada">
                <input type="hidden" name="turma_id" value="<?= htmlspecialchars($turmaInfo['id']) ?>">
                <input type="hidden" name="data" value="<?= htmlspecialchars($data) ?>">

                <div class="toolbar-topo">
                    <span class="contador text-muted" style="font-size:.85rem">
                        <?= count($alunosContam) ?> alunos na turma — <span id="contadorPresentes"></span> presentes
                        <?php if (!empty($alunosForaContagem)): ?>
                            <span class="text-muted">(+<?= count($alunosForaContagem) ?> fora da contagem)</span>
                        <?php endif; ?>
                    </span>
                    <div class="btns-marcar">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="marcarTodos(true)">
                            <i class="fas fa-check mr-1"></i>Todos presentes
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="marcarTodos(false)">
                            <i class="fas fa-times mr-1"></i>Todos ausentes
                        </button>
                    </div>
                </div>

                <div class="lista-alunos">
                    <?php foreach ($alunosContam as $aluno):
                        $presente = $aluno['presente_lancado'] !== null ? (bool)$aluno['presente_lancado'] : true;
                    ?>
                        <div class="aluno-item <?= $presente ? '' : 'ausente' ?>">
                            <div class="aluno-info">
                                <div class="aluno-nome">
                                    <?= htmlspecialchars($aluno['nome']) ?>
                                    <?php if ($aluno['condicao'] !== 'normal'): ?>
                                        <span class="badge <?= $condicaoBadge[$aluno['condicao']] ?> aluno-condicao-badge">
                                            <?= $condicaoLabel[$aluno['condicao']] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="aluno-doc"><?= htmlspecialchars($aluno['documento']) ?></div>
                                <select class="select-condicao mt-1" data-pessoa="<?= htmlspecialchars($aluno['pessoa_id']) ?>" onchange="mudarCondicao(this)">
                                    <?php foreach ($condicaoLabel as $valor => $label): ?>
                                        <option value="<?= $valor ?>" <?= $aluno['condicao'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="chk-presente-wrap">
                                <input type="checkbox" class="chk-presente" name="presente[]"
                                       value="<?= htmlspecialchars($aluno['pessoa_id']) ?>"
                                       <?= $presente ? 'checked' : '' ?>
                                       onchange="atualizarLinha(this)">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($alunosForaContagem)): ?>
                    <div class="divisor-fora-contagem">Não contabilizam nesta turma</div>
                    <div class="lista-alunos">
                        <?php foreach ($alunosForaContagem as $aluno): ?>
                            <div class="aluno-item fora-contagem">
                                <div class="aluno-info">
                                    <div class="aluno-nome">
                                        <?= htmlspecialchars($aluno['nome']) ?>
                                        <span class="badge <?= $condicaoBadge[$aluno['condicao']] ?> aluno-condicao-badge">
                                            <?= $condicaoLabel[$aluno['condicao']] ?>
                                        </span>
                                    </div>
                                    <div class="aluno-doc"><?= htmlspecialchars($aluno['documento']) ?></div>
                                    <select class="select-condicao mt-1" data-pessoa="<?= htmlspecialchars($aluno['pessoa_id']) ?>" onchange="mudarCondicao(this)">
                                        <?php foreach ($condicaoLabel as $valor => $label): ?>
                                            <option value="<?= $valor ?>" <?= $aluno['condicao'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="barra-acao">
                    <span class="contador d-none d-sm-inline">
                        <span id="contadorPresentesBarra"></span> presentes
                    </span>
                    <button type="submit" class="btn btn-success btn-salvar">
                        <i class="fas fa-save mr-1"></i>Salvar chamada
                    </button>
                </div>
            </form>
        </div>
    <?php elseif ($turmaSelecionada): ?>
        <div class="alert alert-warning">Essa turma não tem alunos ativos sincronizados.</div>
    <?php endif; ?>

</div><!-- /main-container -->
</div><!-- /wrapper -->

<script src="../js/menu.js"></script>
<script>
var TURMA_ID = <?= json_encode($turmaSelecionada) ?>;

function atualizarLinha(chk) {
    chk.closest('.aluno-item').classList.toggle('ausente', !chk.checked);
    atualizarContador();
}
function marcarTodos(presente) {
    document.querySelectorAll('.chk-presente').forEach(function (c) { c.checked = presente; atualizarLinha(c); });
}
function atualizarContador() {
    var total = document.querySelectorAll('.chk-presente').length;
    var marcados = document.querySelectorAll('.chk-presente:checked').length;
    var el1 = document.getElementById('contadorPresentes');
    var el2 = document.getElementById('contadorPresentesBarra');
    if (el1) el1.textContent = marcados + '/' + total;
    if (el2) el2.textContent = marcados + '/' + total;
}
document.addEventListener('DOMContentLoaded', atualizarContador);

var selTurma = document.getElementById('turma_id');
var selData = document.getElementById('data');
if (selTurma) selTurma.addEventListener('change', function () { document.getElementById('formFiltro').submit(); });
if (selData) selData.addEventListener('change', function () { document.getElementById('formFiltro').submit(); });

// Muda a condição do aluno via AJAX e recarrega a lista (reordena / move entre grupos)
function mudarCondicao(select) {
    var pessoaId = select.dataset.pessoa;
    var condicao = select.value;
    var fd = new FormData();
    fd.append('turma_id', TURMA_ID);
    fd.append('pessoa_id', pessoaId);
    fd.append('condicao', condicao);

    select.disabled = true;
    fetch('atualizarCondicaoAluno.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.ok) {
                alert(res.msg || 'Erro ao salvar condição.');
                select.disabled = false;
                return;
            }
            // Recarrega mantendo turma/data pra refletir reordenação e recontagem
            var params = new URLSearchParams(window.location.search);
            params.set('turma_id', TURMA_ID);
            window.location.search = params.toString();
        })
        .catch(function () {
            alert('Erro de comunicação ao salvar condição.');
            select.disabled = false;
        });
}
</script>
</body>
</html>