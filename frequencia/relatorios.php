<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../conexao.php');
session_start();

if (!isset($_SESSION['sLogin'])) { header('location:../index.php'); exit; }

$logado = $_SESSION['user'];

date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d');

// Período padrão: últimos 30 dias (igual ao pedido original)
$dataFim = isset($_GET['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']) ? $_GET['data_fim'] : $hoje;
$dataIni = isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio'])
    ? $_GET['data_inicio']
    : date('Y-m-d', strtotime($dataFim . ' -29 days'));

$turmaFiltro = $_GET['turma_id'] ?? '';

const LIMIAR_CONSECUTIVAS = 3;   // dias seguidos faltados para entrar no relatório
const LIMIAR_NAO_CONSECUTIVAS = 10; // faltas (não precisam ser seguidas) no período

// ---------------------------------------------------------
// Turmas para o filtro (todas que já têm alguma chamada lançada)
// ---------------------------------------------------------
$stmtTurmasFiltro = $pdo->query("
    SELECT DISTINCT t.id, t.codigo
    FROM turmas t
    JOIN chamada c ON c.turma_id = t.id
    ORDER BY t.codigo
");
$turmasParaFiltro = $stmtTurmasFiltro->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// Dados brutos de chamada no período (só quem conta na turma)
// ---------------------------------------------------------
$sql = "
    SELECT c.turma_id, c.pessoa_id, c.data, c.presente,
           ta.nome, ta.documento, t.codigo AS turmaCodigo
    FROM chamada c
    JOIN turma_alunos ta ON ta.turma_id = c.turma_id AND ta.pessoa_id = c.pessoa_id
    JOIN turmas t ON t.id = c.turma_id
    WHERE c.data BETWEEN :ini AND :fim
      AND ta.status_matricula = 1
      AND ta.condicao NOT IN ('evadido','cancelado')
";
$params = [':ini' => $dataIni, ':fim' => $dataFim];
if (!empty($turmaFiltro)) {
    $sql .= " AND c.turma_id = :turma_id";
    $params[':turma_id'] = $turmaFiltro;
}
$sql .= " ORDER BY c.pessoa_id, c.turma_id, c.data ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// Agrupa por aluno+turma e calcula streaks / totais
// ---------------------------------------------------------
$porAluno = [];
foreach ($registros as $r) {
    $key = $r['pessoa_id'] . '|' . $r['turma_id'];
    if (!isset($porAluno[$key])) {
        $porAluno[$key] = [
            'nome' => $r['nome'],
            'documento' => $r['documento'],
            'turmaCodigo' => $r['turmaCodigo'],
            'registros' => [],
        ];
    }
    $porAluno[$key]['registros'][] = $r;
}

$relatorioConsecutivas = [];
$relatorioNaoConsecutivas = [];
$rankingFaltososGeral = [];

foreach ($porAluno as $dados) {
    $streakAtual = 0;
    $streakMax = 0;
    $totalFaltas = 0;
    $totalRegistros = count($dados['registros']);

    foreach ($dados['registros'] as $reg) {
        if ((int) $reg['presente'] === 0) {
            $streakAtual++;
            $totalFaltas++;
            if ($streakAtual > $streakMax) $streakMax = $streakAtual;
        } else {
            $streakAtual = 0;
        }
    }
    $emAberto = ($streakAtual === $streakMax && $streakAtual > 0);

    $linhaBase = [
        'nome' => $dados['nome'],
        'documento' => $dados['documento'],
        'turma' => $dados['turmaCodigo'],
        'totalFaltas' => $totalFaltas,
        'totalRegistros' => $totalRegistros,
    ];

    if ($streakMax >= LIMIAR_CONSECUTIVAS) {
        $relatorioConsecutivas[] = $linhaBase + ['dias' => $streakMax, 'emAberto' => $emAberto];
    }
    if ($totalFaltas >= LIMIAR_NAO_CONSECUTIVAS) {
        $relatorioNaoConsecutivas[] = $linhaBase;
    }
    if ($totalFaltas > 0) {
        $rankingFaltososGeral[] = $linhaBase;
    }
}

usort($relatorioConsecutivas, fn($a, $b) => $b['dias'] <=> $a['dias']);
usort($relatorioNaoConsecutivas, fn($a, $b) => $b['totalFaltas'] <=> $a['totalFaltas']);
usort($rankingFaltososGeral, fn($a, $b) => $b['totalFaltas'] <=> $a['totalFaltas']);
$top10Faltosos = array_slice($rankingFaltososGeral, 0, 10);

// ---------------------------------------------------------
// Ranking de turmas por % de falta
// ---------------------------------------------------------
$sqlTurma = "
    SELECT t.id, t.codigo,
           COUNT(*) AS totalRegistros,
           SUM(CASE WHEN c.presente = 0 THEN 1 ELSE 0 END) AS totalFaltas
    FROM chamada c
    JOIN turmas t ON t.id = c.turma_id
    JOIN turma_alunos ta ON ta.turma_id = c.turma_id AND ta.pessoa_id = c.pessoa_id
    WHERE c.data BETWEEN :ini AND :fim
      AND ta.status_matricula = 1
      AND ta.condicao NOT IN ('evadido','cancelado')
    GROUP BY t.id, t.codigo
    HAVING totalRegistros > 0
    ORDER BY (SUM(CASE WHEN c.presente = 0 THEN 1 ELSE 0 END) / COUNT(*)) DESC
";
$stmtTurma = $pdo->prepare($sqlTurma);
$stmtTurma->execute([':ini' => $dataIni, ':fim' => $dataFim]);
$rankingTurmas = $stmtTurma->fetchAll(PDO::FETCH_ASSOC);
foreach ($rankingTurmas as &$rt) {
    $rt['percFalta'] = $rt['totalRegistros'] > 0 ? round(($rt['totalFaltas'] / $rt['totalRegistros']) * 100, 1) : 0;
}
unset($rt);

// ---------------------------------------------------------
// Chamadas pendentes hoje (turmas vigentes sem chamada lançada hoje)
// ---------------------------------------------------------
$stmtPend = $pdo->prepare("
    SELECT t.id, t.codigo
    FROM turmas t
    WHERE t.data_inicio <= :hoje1 AND t.data_fim >= :hoje2
      AND NOT EXISTS (SELECT 1 FROM chamada c WHERE c.turma_id = t.id AND c.data = :hoje3)
    ORDER BY t.codigo
");
$stmtPend->execute([':hoje1' => $hoje, ':hoje2' => $hoje, ':hoje3' => $hoje]);
$chamadasPendentes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// KPIs gerais do período
// ---------------------------------------------------------
$totalRegistrosPeriodo = count($registros);
$totalFaltasPeriodo = 0;
foreach ($registros as $r) { if ((int) $r['presente'] === 0) $totalFaltasPeriodo++; }
$percPresencaGeral = $totalRegistrosPeriodo > 0
    ? round((($totalRegistrosPeriodo - $totalFaltasPeriodo) / $totalRegistrosPeriodo) * 100, 1)
    : null;
$turmaMaisCritica = $rankingTurmas[0] ?? null;

function corSeveridade(int $dias): string {
    if ($dias >= 7) return 'badge-danger';
    if ($dias >= 5) return 'badge-warning';
    return 'badge-secondary';
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Relatórios de frequência</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.card-rel{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:16px;margin-bottom:20px;}
.kpi-row{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:20px;}
.kpi-card{flex:1;min-width:180px;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:14px 16px;}
.kpi-card .kpi-valor{font-size:1.6rem;font-weight:700;line-height:1.1;}
.kpi-card .kpi-label{font-size:.76rem;color:#6c757d;text-transform:uppercase;letter-spacing:.03em;margin-top:4px;}
.kpi-card.destaque .kpi-valor{color:#dc3545;}

.secao-titulo{display:flex;align-items:center;gap:8px;margin-bottom:12px;}
.secao-titulo h5{margin:0;}
.secao-titulo .badge{font-size:.72rem;}

table.tabela-rel{width:100%;font-size:.85rem;}
table.tabela-rel th{font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#6c757d;border-top:none;}
.barra-perc{height:8px;border-radius:4px;background:#e9ecef;overflow:hidden;min-width:80px;}
.barra-perc-fill{height:100%;background:#dc3545;}
.sem-dados{color:#6c757d;font-size:.85rem;padding:10px 0;}
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

    <h4 class="mb-3"><i class="fas fa-chart-bar mr-2"></i>Relatórios de frequência</h4>

    <div class="card-rel">
        <form method="get" class="form-row align-items-end">
            <div class="form-group col-6 col-md-2">
                <label class="font-weight-bold" style="font-size:.82rem">De</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($dataIni) ?>" max="<?= htmlspecialchars($hoje) ?>">
            </div>
            <div class="form-group col-6 col-md-2">
                <label class="font-weight-bold" style="font-size:.82rem">Até</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= htmlspecialchars($dataFim) ?>" max="<?= htmlspecialchars($hoje) ?>">
            </div>
            <div class="form-group col-12 col-md-5">
                <label class="font-weight-bold" style="font-size:.82rem">Turma</label>
                <select name="turma_id" class="form-control form-control-sm">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmasParaFiltro as $t): ?>
                        <option value="<?= htmlspecialchars($t['id']) ?>" <?= $t['id'] === $turmaFiltro ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['codigo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-12 col-md-3">
                <button type="submit" class="btn btn-outline-secondary btn-sm btn-block">
                    <i class="fas fa-filter mr-1"></i>Filtrar
                </button>
            </div>
        </form>
    </div>

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-valor"><?= $percPresencaGeral !== null ? $percPresencaGeral . '%' : '—' ?></div>
            <div class="kpi-label">Presença geral no período</div>
        </div>
        <div class="kpi-card <?= count($relatorioConsecutivas) > 0 ? 'destaque' : '' ?>">
            <div class="kpi-valor"><?= count($relatorioConsecutivas) ?></div>
            <div class="kpi-label">Alunos c/ <?= LIMIAR_CONSECUTIVAS ?>+ faltas seguidas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-valor"><?= $turmaMaisCritica ? htmlspecialchars($turmaMaisCritica['codigo']) : '—' ?></div>
            <div class="kpi-label">Turma mais crítica <?= $turmaMaisCritica ? '(' . $turmaMaisCritica['percFalta'] . '% falta)' : '' ?></div>
        </div>
        <div class="kpi-card <?= count($chamadasPendentes) > 0 ? 'destaque' : '' ?>">
            <div class="kpi-valor"><?= count($chamadasPendentes) ?></div>
            <div class="kpi-label">Chamadas pendentes hoje</div>
        </div>
    </div>

    <!-- Faltas consecutivas -->
    <div class="card-rel">
        <div class="secao-titulo">
            <h5><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Faltas consecutivas</h5>
            <span class="badge badge-light border"><?= LIMIAR_CONSECUTIVAS ?>+ dias seguidos no período</span>
        </div>
        <?php if (empty($relatorioConsecutivas)): ?>
            <div class="sem-dados">Nenhum aluno com <?= LIMIAR_CONSECUTIVAS ?>+ faltas seguidas no período selecionado.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm tabela-rel">
                <thead><tr><th>Aluno</th><th>Turma</th><th>Dias seguidos</th><th>Situação</th></tr></thead>
                <tbody>
                    <?php foreach ($relatorioConsecutivas as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['nome']) ?><br><small class="text-muted"><?= htmlspecialchars($l['documento']) ?></small></td>
                        <td><?= htmlspecialchars($l['turma']) ?></td>
                        <td><span class="badge <?= corSeveridade($l['dias']) ?>"><?= $l['dias'] ?> dias</span></td>
                        <td><?= $l['emAberto'] ? '<span class="text-danger font-weight-bold"><i class="fas fa-circle" style="font-size:.5rem"></i> Em aberto (última chamada faltou)</span>' : '<span class="text-muted">Interrompida depois</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Faltas não consecutivas -->
    <div class="card-rel">
        <div class="secao-titulo">
            <h5><i class="fas fa-calendar-times mr-2 text-secondary"></i>Faltas não consecutivas</h5>
            <span class="badge badge-light border"><?= LIMIAR_NAO_CONSECUTIVAS ?>+ faltas no período (seguidas ou não)</span>
        </div>
        <?php if (empty($relatorioNaoConsecutivas)): ?>
            <div class="sem-dados">Nenhum aluno com <?= LIMIAR_NAO_CONSECUTIVAS ?>+ faltas no período selecionado.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm tabela-rel">
                <thead><tr><th>Aluno</th><th>Turma</th><th>Faltas</th><th>Presença no período</th></tr></thead>
                <tbody>
                    <?php foreach ($relatorioNaoConsecutivas as $l):
                        $perc = $l['totalRegistros'] > 0 ? round((($l['totalRegistros'] - $l['totalFaltas']) / $l['totalRegistros']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($l['nome']) ?><br><small class="text-muted"><?= htmlspecialchars($l['documento']) ?></small></td>
                        <td><?= htmlspecialchars($l['turma']) ?></td>
                        <td><span class="badge badge-danger"><?= $l['totalFaltas'] ?> faltas</span></td>
                        <td><?= $perc ?>% <span class="text-muted">(<?= $l['totalRegistros'] ?> chamadas)</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ranking de turmas -->
    <div class="card-rel">
        <div class="secao-titulo">
            <h5><i class="fas fa-users mr-2 text-primary"></i>Turmas com mais faltas</h5>
            <span class="badge badge-light border">% de falta sobre o total de chamadas no período</span>
        </div>
        <?php if (empty($rankingTurmas)): ?>
            <div class="sem-dados">Nenhuma chamada lançada no período selecionado.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm tabela-rel">
                <thead><tr><th>Turma</th><th>Faltas</th><th>Chamadas</th><th style="width:160px">% de falta</th></tr></thead>
                <tbody>
                    <?php foreach ($rankingTurmas as $rt): ?>
                    <tr>
                        <td><a href="chamada.php?turma_id=<?= htmlspecialchars($rt['id']) ?>"><?= htmlspecialchars($rt['codigo']) ?></a></td>
                        <td><?= $rt['totalFaltas'] ?></td>
                        <td><?= $rt['totalRegistros'] ?></td>
                        <td>
                            <div class="d-flex align-items-center" style="gap:8px">
                                <div class="barra-perc flex-grow-1">
                                    <div class="barra-perc-fill" style="width:<?= min(100, $rt['percFalta']) ?>%"></div>
                                </div>
                                <span><?= $rt['percFalta'] ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-12 col-lg-6">
            <!-- Top 10 faltosos -->
            <div class="card-rel">
                <div class="secao-titulo">
                    <h5><i class="fas fa-list-ol mr-2 text-dark"></i>Top 10 faltosos</h5>
                </div>
                <?php if (empty($top10Faltosos)): ?>
                    <div class="sem-dados">Sem faltas registradas no período.</div>
                <?php else: ?>
                <table class="table table-sm tabela-rel">
                    <thead><tr><th>#</th><th>Aluno</th><th>Turma</th><th>Faltas</th></tr></thead>
                    <tbody>
                        <?php foreach ($top10Faltosos as $i => $l): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($l['nome']) ?></td>
                            <td><?= htmlspecialchars($l['turma']) ?></td>
                            <td><span class="badge badge-danger"><?= $l['totalFaltas'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <!-- Chamadas pendentes hoje -->
            <div class="card-rel">
                <div class="secao-titulo">
                    <h5><i class="fas fa-clock mr-2 text-warning"></i>Chamadas pendentes hoje</h5>
                </div>
                <?php if (empty($chamadasPendentes)): ?>
                    <div class="sem-dados"><i class="fas fa-check-circle text-success mr-1"></i>Todas as turmas vigentes já tiveram chamada lançada hoje.</div>
                <?php else: ?>
                <table class="table table-sm tabela-rel">
                    <thead><tr><th>Turma</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($chamadasPendentes as $tp): ?>
                        <tr>
                            <td><?= htmlspecialchars($tp['codigo']) ?></td>
                            <td class="text-right">
                                <a href="chamada.php?turma_id=<?= htmlspecialchars($tp['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-clipboard-check mr-1"></i>Lançar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /main-container -->
</div><!-- /wrapper -->
<script src="../js/menu.js"></script>
</body>
</html>