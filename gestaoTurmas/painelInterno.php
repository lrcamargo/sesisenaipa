<!DOCTYPE html>
<?php
require_once('../conexao.php');
require_once('ocupacaoHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia','sup adm','admin','administrator'
]);

$diasVisiveis = $supervisao ? 20 : 7;

$hoje    = date('Y-m-d');
$datas   = [];
for($i=0;$i<$diasVisiveis;$i++) $datas[] = date('Y-m-d', strtotime("+{$i} days"));

$dataSel = $_GET['data'] ?? $hoje;
if(!in_array($dataSel,$datas)) $dataSel = $hoje;

$turnos = [
    'manha' => ['label'=>'Manhã',  'inicio'=>'07:00:00','fim'=>'12:20:00'],
    'tarde' => ['label'=>'Tarde',  'inicio'=>'13:00:00','fim'=>'17:30:00'],
    'noite' => ['label'=>'Noite',  'inicio'=>'18:00:00','fim'=>'22:30:00'],
];

/* ── Turno vigente (para destacar coluna quando for hoje) ── */
$horaMin    = (int)date('H')*60 + (int)date('i');
$turnoAtivo = null;
if($dataSel === $hoje){
    if($horaMin <= (12*60+20))       $turnoAtivo = 'manha';
    elseif($horaMin <= (17*60+30))   $turnoAtivo = 'tarde';
    else                             $turnoAtivo = 'noite';
}

/* ── Ambientes ── */
$stmtAmb = $pdo->prepare("
    SELECT idLaboratorio, nome, descricao, temReserva, temSala
    FROM laboratorios WHERE temReserva=1 OR temSala=1
    ORDER BY temReserva DESC, nome ASC
");
$stmtAmb->execute();
$ambientes = $stmtAmb->fetchAll(PDO::FETCH_ASSOC);

/* ── Calcula ocupação para a data selecionada ── */
$ocupacao = calcularOcupacao($pdo, $dataSel, $ambientes, $turnos);

$labs  = array_filter($ambientes, fn($a) => $a['temReserva']==1 && !$a['temSala']);
$salas = array_filter($ambientes, fn($a) => $a['temSala']==1);

$diaSemCurto = ['Sunday'=>'Dom','Monday'=>'Seg','Tuesday'=>'Ter',
                'Wednesday'=>'Qua','Thursday'=>'Qui','Friday'=>'Sex','Saturday'=>'Sáb'];
$diaSemLong  = ['Sunday'=>'Domingo','Monday'=>'Segunda-feira','Tuesday'=>'Terça-feira',
                'Wednesday'=>'Quarta-feira','Thursday'=>'Quinta-feira',
                'Friday'=>'Sexta-feira','Saturday'=>'Sábado'];

$iconesTurno = ['manha'=>'fa-sun','tarde'=>'fa-cloud-sun','noite'=>'fa-moon'];
$coresStatus = [
    'ocupado'   => ['bg'=>'#ffebee','cor'=>'#c62828','borda'=>'#ef9a9a','icone'=>'fa-lock'],
    'aguardando'=> ['bg'=>'#fff8e1','cor'=>'#e65100','borda'=>'#ffcc80','icone'=>'fa-clock'],
    'na-sala'   => ['bg'=>'#e8f5e9','cor'=>'#1b5e20','borda'=>'#a5d6a7','icone'=>'fa-chalkboard'],
    'livre'     => ['bg'=>'#f1f8e9','cor'=>'#33691e','borda'=>'#c5e1a5','icone'=>'fa-check'],
    'vazio'     => ['bg'=>'#fafafa','cor'=>'#bdbdbd','borda'=>'#eeeeee','icone'=>'fa-minus'],
];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Ocupação</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>
/* Seletor de datas */
.nav-datas{ display:flex; gap:5px; flex-wrap:wrap; margin-bottom:16px; }
.btn-data{
    padding:5px 10px; border-radius:6px; border:1px solid #dee2e6;
    background:#fff; font-size:.76rem; font-weight:600; cursor:pointer;
    text-align:center; text-decoration:none; color:#495057; transition:all .15s; line-height:1.4;
}
.btn-data:hover{ background:#e9ecef; color:#212529; text-decoration:none; }
.btn-data.ativo{ background:#0d6efd; color:#fff; border-color:#0d6efd; }
.btn-data.hoje-mark{ border-color:#0d6efd; color:#0d6efd; }
.btn-data.hoje-mark.ativo{ color:#fff; }
.btn-data small{ display:block; font-weight:400; font-size:.68rem; opacity:.8; }

/* Grade */
.grade-painel{ width:100%; border-collapse:collapse; font-size:.84rem; }
.grade-painel th{
    background:#343a40; color:#fff; padding:9px 12px;
    text-align:center; white-space:nowrap;
}
.grade-painel th:first-child{ text-align:left; min-width:160px; }
.grade-painel th.turno-ativo{ background:#0d47a1; }

.grade-painel td{ padding:7px 10px; border:1px solid #dee2e6; vertical-align:middle; }
.grade-painel tbody tr:hover td{ background:#f8f9fa; }
.grade-painel td:first-child{ font-weight:700; background:#f8f9fa; }
.grade-painel td.col-turno-ativo{ background:#f0f4ff !important; }

/* Células de status */
.celula{
    display:flex; align-items:center; gap:8px;
    border-radius:5px; padding:7px 10px; min-height:42px;
    font-size:.8rem; font-weight:600;
}
.celula .turma-nome{ font-weight:700; font-size:.78rem; }
.celula .turma-sub { font-size:.7rem; font-weight:400; opacity:.75; }

.secao-titulo{
    margin:18px 0 6px; font-size:.76rem; font-weight:700;
    letter-spacing:.08em; text-transform:uppercase; color:#6c757d;
}
.link-tv{ font-size:.78rem; color:#6c757d; }
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
<div class="sidebar">
    <div class="sidebar-menu"><?php include_once('../menu.php'); ?></div>
</div>
<div class="main-container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-th mr-2"></i>Painel de Ocupação</h4>
        <a href="painel.php" target="_blank" class="link-tv">
            <i class="fas fa-tv mr-1"></i>Versão TV
        </a>
    </div>

    <!-- Seletor de datas -->
    <div class="nav-datas">
    <?php foreach($datas as $d){
        $ativo   = $d===$dataSel ? 'ativo' : '';
        $ehHoje  = $d===$hoje    ? 'hoje-mark' : '';
        $nomeDia = $diaSemCurto[date('l',strtotime($d))] ?? '';
        $numDia  = date('d/m',strtotime($d));
        echo "<a href='?data={$d}' class='btn-data {$ativo} {$ehHoje}'>";
        echo "<span>{$nomeDia}</span><small>{$numDia}</small>";
        if($d===$hoje) echo "<small style='color:inherit'>Hoje</small>";
        echo "</a>";
    } ?>
    </div>

    <?php
    $nomeDiaSel = $diaSemLong[date('l',strtotime($dataSel))] ?? '';
    echo "<p class='text-muted mb-3'><strong>{$nomeDiaSel}, ".date('d/m/Y',strtotime($dataSel))."</strong>";
    if($dataSel===$hoje) echo " <span class='badge badge-primary ml-1'>Hoje</span>";
    echo "</p>";

    /* Renderiza uma grade de ambientes */
    function renderGrade(array $lista, array $ocupacao, array $turnos, array $coresStatus, ?string $turnoAtivo): void {
        echo "<table class='grade-painel'><thead><tr><th>Ambiente</th>";
        foreach($turnos as $key=>$t){
            $cls = $key===$turnoAtivo ? 'turno-ativo' : '';
            $icone = ['manha'=>'fa-sun','tarde'=>'fa-cloud-sun','noite'=>'fa-moon'][$key] ?? 'fa-clock';
            echo "<th class='{$cls}'><i class='fas {$icone} mr-1'></i>{$t['label']}</th>";
        }
        echo "</tr></thead><tbody>";

        foreach($lista as $amb){
            $id = $amb['idLaboratorio'];
            echo "<tr><td>".htmlspecialchars($amb['nome']);
            if(!empty(trim($amb['descricao'] ?? '')))
                echo "<br><small class='text-muted'>".htmlspecialchars(trim($amb['descricao']))."</small>";
            echo "</td>";

            foreach($turnos as $key=>$t){
                $ocp   = $ocupacao[$id][$key] ?? ['status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''];
                $cores = $coresStatus[$ocp['status']] ?? $coresStatus['vazio'];
                $colCls = $key===$turnoAtivo ? 'col-turno-ativo' : '';
                echo "<td class='{$colCls}'>";
                echo "<div class='celula' style='background:{$cores['bg']};color:{$cores['cor']};border:1px solid {$cores['borda']}'>";
                echo "<i class='fas {$cores['icone']}'></i>";
                echo "<div>";
                if($ocp['turma']){
                    echo "<div class='turma-nome'>".htmlspecialchars($ocp['turma'])."</div>";
                    $subTxt = $ocp['sub'];
                    if(!empty($ocp['solicitante'])) $subTxt .= ' · '.htmlspecialchars($ocp['solicitante']);
                    if($subTxt) echo "<div class='turma-sub'>{$subTxt}</div>";
                } else {
                    echo "<div class='turma-nome' style='opacity:.4'>—</div>";
                }
                echo "</div></div></td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    ?>

    <?php if(!empty($labs)){ ?>
    <div class="secao-titulo"><i class="fas fa-flask mr-1"></i>Laboratórios</div>
    <?php renderGrade($labs, $ocupacao, $turnos, $coresStatus, $turnoAtivo); ?>
    <?php } ?>

    <?php if(!empty($salas)){ ?>
    <div class="secao-titulo" style="margin-top:22px"><i class="fas fa-chalkboard mr-1"></i>Salas de Aula</div>
    <?php renderGrade($salas, $ocupacao, $turnos, $coresStatus, $turnoAtivo); ?>
    <?php } ?>

</div>
</div>
<script src="../js/menu.js"></script>
</body>
</html>