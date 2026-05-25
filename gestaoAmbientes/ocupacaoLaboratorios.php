<?php
/*
 * ocupacaoLaboratorios.php
 * Análise de ocupação de laboratórios e salas de aula.
 *
 * Fonte:
 *   - Laboratórios: tabela reservas (aprovado IN (0,1))
 *   - Salas de aula: tabela turma_sala (dias/turno cadastrados)
 *     Se a turma tiver reserva de laboratório no mesmo dia/turno, não conta
 *     para sala (evita duplicata).
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia','sup tecnica','sup pedagogica'])){
    header('location:../index.php'); exit;
}

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');

// Período padrão: mês atual
$periodoTipo = $_GET['periodo'] ?? 'mes'; // mes | semestre | ano | custom
$dataInicio  = $_GET['de']  ?? date('Y-m-01');
$dataFim     = $_GET['ate'] ?? date('Y-m-t');

switch($periodoTipo){
    case 'semestre':
        $mes = (int)date('m');
        if($mes <= 6){ $dataInicio=date('Y-01-01'); $dataFim=date('Y-06-30'); }
        else          { $dataInicio=date('Y-07-01'); $dataFim=date('Y-12-31'); }
        break;
    case 'ano':
        $dataInicio=date('Y-01-01'); $dataFim=date('Y-12-31');
        break;
    case 'mes':
        $dataInicio=date('Y-m-01'); $dataFim=date('Y-m-t');
        break;
    // custom: usa $_GET['de'] e $_GET['ate']
}
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataInicio)) $dataInicio=date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataFim))    $dataFim=date('Y-m-t');
if($dataFim < $dataInicio) $dataFim=$dataInicio;

// Dias úteis no período (Seg–Sáb)
function diasUteisNoPeriodo(string $inicio, string $fim): array {
    // Retorna contagem por dia da semana (1=Seg..6=Sáb) e total
    $dc = new DateTime($inicio); $df = new DateTime($fim);
    $contagem = array_fill(1,6,0);
    while($dc <= $df){
        $dow = (int)$dc->format('N'); // 1=Seg..7=Dom
        if($dow <= 6) $contagem[$dow]++;
        $dc->modify('+1 day');
    }
    return $contagem;
}
$diasPorDow = diasUteisNoPeriodo($dataInicio, $dataFim);
$totalDias  = array_sum($diasPorDow);

// Laboratórios que têm reserva habilitada
$labs = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temReserva=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

// Salas de aula (temSala=1)
$salas = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temSala=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);
$idsSalas = array_column($salas, 'idLaboratorio');

$turnos = ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'];
$horariosTurno = [
    'manha' => ['inicio'=>'06:00:00','fim'=>'12:59:59'],
    'tarde' => ['inicio'=>'13:00:00','fim'=>'17:59:59'],
    'noite' => ['inicio'=>'18:00:00','fim'=>'23:59:59'],
];

/* ────────────────────────────────────────────────────
   OCUPAÇÃO DOS LABORATÓRIOS (via reservas)
   ──────────────────────────────────────────────────── */
// Para cada lab e turno: conta dias distintos com reserva
$ocupLab = []; // [idLab][turno] = dias ocupados
foreach($labs as $l) $ocupLab[$l['idLaboratorio']] = ['manha'=>0,'tarde'=>0,'noite'=>0,'total'=>0];

$stReserva = $pdo->prepare("
    SELECT laboratorio, data, horarioInicio, horarioFim
    FROM reservas
    WHERE data BETWEEN ? AND ?
      AND aprovado IN (0,1)
      AND laboratorio = ?
    ORDER BY data, horarioInicio
");

foreach($labs as $l){
    $stReserva->execute([$dataInicio, $dataFim, $l['idLaboratorio']]);
    $reservasLab = $stReserva->fetchAll(PDO::FETCH_ASSOC);

    // Agrupa por dia/turno (um dia pode ter múltiplas reservas, conta 1)
    $diasOcup = ['manha'=>[],'tarde'=>[],'noite'=>[]];
    foreach($reservasLab as $r){
        $hi = $r['horarioInicio'];
        foreach($horariosTurno as $tk => $faixa){
            if($hi >= $faixa['inicio'] && $hi <= $faixa['fim']){
                $diasOcup[$tk][$r['data']] = true;
                break;
            }
        }
    }
    foreach($turnos as $tk => $tl){
        $ocupLab[$l['idLaboratorio']][$tk] = count($diasOcup[$tk]);
    }
    $ocupLab[$l['idLaboratorio']]['total'] =
        count(array_unique(array_merge(
            array_keys($diasOcup['manha']),
            array_keys($diasOcup['tarde']),
            array_keys($diasOcup['noite'])
        )));
}

/* ────────────────────────────────────────────────────
   OCUPAÇÃO DAS SALAS (via turma_sala)
   Regra: se a turma tiver reserva de LAB no mesmo dia/turno → não conta para sala
   ──────────────────────────────────────────────────── */
$diasBits = [1=>1,2=>2,3=>4,4=>8,5=>16,6=>32]; // dow ISO → bitmask

// Carrega vínculos de salas
$vincSalas = $pdo->query("
    SELECT ts.codigoTurma, ts.idSala, ts.turno, ts.diasSemana
    FROM turma_sala ts
    JOIN laboratorios l ON l.idLaboratorio = ts.idSala
    WHERE l.temSala = 1
")->fetchAll(PDO::FETCH_ASSOC);

// Carrega reservas de laboratório por turma no período (para excluir duplicatas)
$reservasPorTurma = []; // [codigoTurma][data][turno] = true
$stRT = $pdo->prepare("
    SELECT r.turma, r.data, r.horarioInicio
    FROM reservas r
    WHERE r.data BETWEEN ? AND ?
      AND r.aprovado IN (0,1)
      AND r.turma IS NOT NULL
");
$stRT->execute([$dataInicio,$dataFim]);
foreach($stRT->fetchAll(PDO::FETCH_ASSOC) as $r){
    if(!$r['turma']) continue;
    $tk = 'manha';
    foreach($horariosTurno as $t => $faixa){
        if($r['horarioInicio'] >= $faixa['inicio'] && $r['horarioInicio'] <= $faixa['fim']){ $tk=$t; break; }
    }
    $reservasPorTurma[$r['turma']][$r['data']][$tk] = true;
}

// Para cada sala, conta dias de aula (por dia da semana × turno × bitmask)
$ocupSala = []; // [idSala][turno] = dias ocupados
foreach($idsSalas as $idS) $ocupSala[$idS] = ['manha'=>0,'tarde'=>0,'noite'=>0,'total'=>0];

$dc = new DateTime($dataInicio); $dfim = new DateTime($dataFim);
while($dc <= $dfim){
    $data = $dc->format('Y-m-d');
    $dow  = (int)$dc->format('N');
    if($dow > 6){ $dc->modify('+1 day'); continue; }
    $bit  = $diasBits[$dow] ?? 0;

    foreach($vincSalas as $v){
        if(!($v['diasSemana'] & $bit)) continue;
        $idSala = (int)$v['idSala'];
        $tk     = $v['turno'];
        // Verifica se a turma tem reserva de lab neste dia/turno → não conta para sala
        if(isset($reservasPorTurma[$v['codigoTurma']][$data][$tk])) continue;
        if(!isset($ocupSala[$idSala])) continue;
        // Conta o dia para esta sala/turno (pode ser contado múltiplas vezes se várias turmas, mas queremos "sala estava ocupada")
        // Usa flag para não duplicar
        $ocupSala[$idSala][$tk.'_dias'][$data] = true;
    }
    $dc->modify('+1 day');
}
// Converte flags para contagens — calcula total ANTES do unset
foreach($idsSalas as $idS){
    // Total: dias únicos com aula em qualquer turno
    $diasTotal = array_unique(array_merge(
        array_keys($ocupSala[$idS]['manha_dias'] ?? []),
        array_keys($ocupSala[$idS]['tarde_dias'] ?? []),
        array_keys($ocupSala[$idS]['noite_dias'] ?? [])
    ));
    $ocupSala[$idS]['total'] = count($diasTotal);
    // Agora converte cada turno e remove flags
    foreach(['manha','tarde','noite'] as $tk){
        $ocupSala[$idS][$tk] = count($ocupSala[$idS][$tk.'_dias'] ?? []);
        unset($ocupSala[$idS][$tk.'_dias']);
    }
}

// Agrega salas para o gráfico geral
$ocupSalasTotal = ['manha'=>0,'tarde'=>0,'noite'=>0];
$diasPossiveis  = $totalDias * max(count($idsSalas),1); // máximo teórico
foreach($idsSalas as $idS){
    foreach(['manha','tarde','noite'] as $tk) $ocupSalasTotal[$tk] += $ocupSala[$idS][$tk];
}

// Percentuais para laboratórios
function pct(int $dias, int $total): float {
    return $total > 0 ? round($dias/$total*100,1) : 0;
}

// Monta dados para Chart.js
$labNomes  = array_column($labs,'nome');
$labIds    = array_column($labs,'idLaboratorio');

$chartDataLabs = [];
foreach(['manha','tarde','noite'] as $tk){
    $chartDataLabs[$tk] = array_map(fn($id)=>pct($ocupLab[$id][$tk],$totalDias), $labIds);
}

$salaNomes = array_column($salas,'nome');
$salaIds   = $idsSalas;
$chartDataSalas = [];
foreach(['manha','tarde','noite'] as $tk){
    $chartDataSalas[$tk] = array_map(fn($id)=>pct($ocupSala[$id][$tk],$totalDias), $salaIds);
}

// Top/bottom salas por turno (para hover/click)
$rankingSalas = [];
foreach(['manha','tarde','noite'] as $tk){
    $arr=[];
    foreach($salas as $s) $arr[]=[ 'nome'=>$s['nome'], 'pct'=>pct($ocupSala[$s['idLaboratorio']][$tk],$totalDias) ];
    usort($arr,fn($a,$b)=>$b['pct']<=>$a['pct']);
    $rankingSalas[$tk]=$arr;
}

// Ranking de labs por turno
$rankingLabs = [];
foreach(['manha','tarde','noite'] as $tk){
    $arr=[];
    foreach($labs as $l) $arr[]=['nome'=>$l['nome'],'pct'=>pct($ocupLab[$l['idLaboratorio']][$tk],$totalDias),'dias'=>$ocupLab[$l['idLaboratorio']][$tk]];
    usort($arr,fn($a,$b)=>$b['pct']<=>$a['pct']);
    $rankingLabs[$tk]=$arr;
}

// Cards de média geral (todos os turnos combinados)
$mediaLab  = count($labs)  > 0 ? round(array_sum(array_map(fn($l)=>pct($ocupLab[$l['idLaboratorio']]['total'],$totalDias),$labs)) /count($labs),1)  : 0;
$mediaSala = count($salas) > 0 ? round(array_sum(array_map(fn($s)=>pct($ocupSala[$s['idLaboratorio']]['total'],$totalDias),$salas))/count($salas),1) : 0;

// Top 5 labs para pizza (por ocupação total)
$top5Labs = $rankingLabs['manha']; // base: manhã, mas usamos total
$top5PizzaData = [];
foreach($labs as $l){
    $top5PizzaData[]=['nome'=>$l['nome'],'pct'=>pct($ocupLab[$l['idLaboratorio']]['total'],$totalDias)];
}
usort($top5PizzaData,fn($a,$b)=>$b['pct']<=>$a['pct']);
$top5PizzaData=array_slice($top5PizzaData,0,5);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ocupação de Laboratórios</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<style>
.periodo-bar{background:#fff;border:1px solid #dee2e6;border-radius:8px;
    padding:12px 16px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.periodo-bar .fg{display:flex;flex-direction:column;gap:3px;}
.periodo-bar label{font-size:.75rem;font-weight:600;margin:0;}
.periodo-bar input,.periodo-bar select{padding:5px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.82rem;}
.btn-periodo{padding:5px 14px;border-radius:6px;border:2px solid #dee2e6;background:#fff;
    font-size:.82rem;font-weight:600;cursor:pointer;color:#495057;}
.btn-periodo.ativo{border-color:#0d6efd;background:#0d6efd;color:#fff;}
.chart-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:16px;
    margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.chart-card h6{font-weight:700;font-size:.9rem;margin-bottom:14px;color:#343a40;}
.turno-tabs-mini{display:flex;gap:4px;margin-bottom:14px;}
.turno-tab-mini{padding:4px 14px;border-radius:20px;border:1px solid #dee2e6;background:#f8f9fa;
    font-size:.78rem;font-weight:600;cursor:pointer;color:#495057;}
.turno-tab-mini.ativo{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.ranking-panel{background:#f8f9fa;border-radius:6px;padding:10px 14px;margin-top:10px;
    font-size:.8rem;display:none;}
.ranking-panel.visivel{display:block;}
.ranking-item{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #eee;}
.ranking-item:last-child{border:none;}
.pct-badge{font-weight:700;color:#0d6efd;}
.periodo-label{font-size:.75rem;color:#6c757d;margin-bottom:16px;}
.media-card{border-radius:10px;padding:14px 16px;text-align:center;
    box-shadow:0 1px 4px rgba(0,0,0,.07);}
.media-card .num{font-size:2rem;font-weight:800;line-height:1;}
.media-card .label{font-size:.72rem;font-weight:600;margin-top:4px;opacity:.8;}
.ranking-section{background:#fff;border:1px solid #dee2e6;border-radius:8px;
    padding:16px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.ranking-section h6{font-weight:700;font-size:.9rem;margin-bottom:12px;color:#343a40;}
.rank-tabs{display:flex;gap:4px;margin-bottom:12px;}
.rank-tab{padding:4px 14px;border-radius:20px;border:1px solid #dee2e6;background:#f8f9fa;
    font-size:.78rem;font-weight:600;cursor:pointer;color:#495057;}
.rank-tab.ativo{background:#343a40;color:#fff;border-color:#343a40;}
.rank-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.rank-pos{font-size:.72rem;font-weight:700;color:#adb5bd;min-width:18px;text-align:right;}
.rank-nome{font-size:.78rem;min-width:140px;max-width:160px;white-space:nowrap;
    overflow:hidden;text-overflow:ellipsis;}
.rank-bar-wrap{flex:1;background:#e9ecef;border-radius:4px;height:12px;overflow:hidden;}
.rank-bar-fill{height:100%;border-radius:4px;transition:width .4s ease;}
.rank-pct{font-size:.75rem;font-weight:700;min-width:36px;text-align:right;}
.pizza-wrap{display:flex;gap:20px;align-items:center;flex-wrap:wrap;}
.pizza-legend{flex:1;min-width:180px;}
.pizza-legend-item{display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:.8rem;}
.pizza-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
</style>
</head>
<body>
<div class="wrapper">
<div class="header"><div class="header-menu">
    <div class="title"><img src="../img/logo_white.svg"></div>
    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
    <ul>
        <li><a href="#" class="user"><?php echo htmlspecialchars($_SESSION['user']); ?></a></li>
        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
    </ul>
</div></div>
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Ocupação de Laboratórios e Salas</h4>
</div>

<!-- Filtro de período -->
<form method="GET" class="periodo-bar">
    <div style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">
        <a href="?periodo=mes"
           class="btn-periodo <?php echo $periodoTipo==='mes'&&!isset($_GET['de'])?'ativo':''; ?>">
            Mês atual
        </a>
        <a href="?periodo=semestre"
           class="btn-periodo <?php echo $periodoTipo==='semestre'?'ativo':''; ?>">
            Semestre
        </a>
        <a href="?periodo=ano"
           class="btn-periodo <?php echo $periodoTipo==='ano'?'ativo':''; ?>">
            Ano
        </a>
    </div>
    <div class="fg">
        <label>De</label>
        <input type="date" name="de" value="<?php echo $dataInicio; ?>">
    </div>
    <div class="fg">
        <label>Até</label>
        <input type="date" name="ate" value="<?php echo $dataFim; ?>">
    </div>
    <input type="hidden" name="periodo" value="custom">
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
        <i class="fas fa-search mr-1"></i>Filtrar
    </button>
</form>

<div class="periodo-label">
    <i class="fas fa-calendar-alt mr-1"></i>
    Período: <strong><?php echo date('d/m/Y',strtotime($dataInicio)); ?></strong>
    até <strong><?php echo date('d/m/Y',strtotime($dataFim)); ?></strong>
    — <?php echo $totalDias; ?> dias úteis (Seg–Sáb)
</div>

<!-- Cards de média + Pizza lado a lado -->
<?php
function corCard($p){ return $p>=75?['bg'=>'#e8f5e9','txt'=>'#1b5e20']:($p>=40?['bg'=>'#fff3e0','txt'=>'#e65100']:['bg'=>'#ffebee','txt'=>'#b71c1c']); }
$cLab=corCard($mediaLab); $cSal=corCard($mediaSala);
?>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;align-items:stretch">

    <!-- Coluna esquerda: cards de média -->
    <div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:12px">
        <div class="media-card" style="background:<?php echo $cLab['bg'];?>;color:<?php echo $cLab['txt'];?>;flex:1">
            <div class="num"><?php echo $mediaLab; ?>%</div>
            <div class="label"><i class="fas fa-flask mr-1"></i>Média Laboratórios</div>
            <div style="font-size:.65rem;opacity:.7;margin-top:3px">todos os turnos</div>
        </div>
        <div class="media-card" style="background:<?php echo $cSal['bg'];?>;color:<?php echo $cSal['txt'];?>;flex:1">
            <div class="num"><?php echo $mediaSala; ?>%</div>
            <div class="label"><i class="fas fa-chalkboard mr-1"></i>Média Salas</div>
            <div style="font-size:.65rem;opacity:.7;margin-top:3px">todos os turnos</div>
        </div>
        <div class="media-card" style="background:#e3f2fd;color:#0d47a1;flex:1">
            <div class="num"><?php echo count($labs); ?></div>
            <div class="label"><i class="fas fa-door-open mr-1"></i>Laboratórios ativos</div>
        </div>
        <div class="media-card" style="background:#f3e5f5;color:#6a1b9a;flex:1">
            <div class="num"><?php echo count($salas); ?></div>
            <div class="label"><i class="fas fa-chalkboard-teacher mr-1"></i>Salas de aula</div>
        </div>
    </div>

    <!-- Coluna direita: pizza Top 5 -->
    <div style="flex:2;min-width:300px;background:#fff;border:1px solid #dee2e6;border-radius:8px;
         padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
        <h6 style="font-weight:700;font-size:.9rem;margin-bottom:14px;color:#343a40">
            <i class="fas fa-chart-pie mr-2" style="color:#e65100"></i>Top 5 Laboratórios — Uso Geral (todos os turnos)
        </h6>
        <div class="pizza-wrap" style="justify-content:center;align-items:center;margin: 5% 0% 0% 20%;">
            <div style="width:300px;height:300px;flex-shrink:0">
                <canvas id="chartPizza"></canvas>
            </div>
            <div class="pizza-legend" id="pizzaLegend" style="min-width:100px;font-size:.85rem"></div>
        </div>
        
    </div>
</div>

<!-- Gráfico de Laboratórios -->
<div class="chart-card">
    <h6><i class="fas fa-flask mr-2 text-primary"></i>Laboratórios — Ocupação por Turno (%)</h6>
    <div class="turno-tabs-mini" id="tabsLab">
        <span class="turno-tab-mini ativo" onclick="trocarTurnoLab('manha',this)">☀️ Manhã</span>
        <span class="turno-tab-mini" onclick="trocarTurnoLab('tarde',this)">🌤 Tarde</span>
        <span class="turno-tab-mini" onclick="trocarTurnoLab('noite',this)">🌙 Noite</span>
    </div>
    <canvas id="chartLabs" height="90"></canvas>
</div>

<!-- Gráfico de Salas -->
<div class="chart-card">
    <h6><i class="fas fa-chalkboard mr-2 text-success"></i>Salas de Aula — Ocupação por Turno (%)</h6>
    <p style="font-size:.78rem;color:#6c757d;margin-bottom:10px">
        Clique ou passe o mouse nas barras para ver o ranking individual de salas.
    </p>
    <div class="turno-tabs-mini" id="tabsSala">
        <span class="turno-tab-mini ativo" onclick="trocarTurnoSala('manha',this)">☀️ Manhã</span>
        <span class="turno-tab-mini" onclick="trocarTurnoSala('tarde',this)">🌤 Tarde</span>
        <span class="turno-tab-mini" onclick="trocarTurnoSala('noite',this)">🌙 Noite</span>
    </div>
    <canvas id="chartSalas" height="90"></canvas>
    <div id="rankingPanel" class="ranking-panel">
        <strong id="rankingTitulo" style="font-size:.8rem"></strong>
        <div id="rankingLista" style="margin-top:6px"></div>
    </div>
</div>

<!-- Rankings lado a lado: Labs (esquerda) | Salas (direita) -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px">

    <!-- Ranking Labs -->
    <div style="flex:1;min-width:260px;background:#fff;border:1px solid #dee2e6;border-radius:8px;
         padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
        <h6 style="font-weight:700;font-size:.9rem;margin-bottom:10px;color:#343a40">
            <i class="fas fa-trophy mr-2" style="color:#ffc107"></i>Ranking Laboratórios
        </h6>
        <div class="rank-tabs" id="tabsRankLab">
            <span class="rank-tab ativo" onclick="trocarRankLab('manha',this)">☀️ Manhã</span>
            <span class="rank-tab" onclick="trocarRankLab('tarde',this)">🌤 Tarde</span>
            <span class="rank-tab" onclick="trocarRankLab('noite',this)">🌙 Noite</span>
        </div>
        <div id="rankLabLista"></div>
    </div>

    <!-- Ranking Salas -->
    <div style="flex:1;min-width:260px;background:#fff;border:1px solid #dee2e6;border-radius:8px;
         padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
        <h6 style="font-weight:700;font-size:.9rem;margin-bottom:10px;color:#343a40">
            <i class="fas fa-trophy mr-2" style="color:#198754"></i>Ranking Salas de Aula
        </h6>
        <div class="rank-tabs" id="tabsRankSala">
            <span class="rank-tab ativo" onclick="trocarRankSala('manha',this)">☀️ Manhã</span>
            <span class="rank-tab" onclick="trocarRankSala('tarde',this)">🌤 Tarde</span>
            <span class="rank-tab" onclick="trocarRankSala('noite',this)">🌙 Noite</span>
        </div>
        <div id="rankSalaLista"></div>
    </div>
</div>

</div></div>

<script>
/* ── Dados do PHP ── */
var labNomes     = <?php echo json_encode($labNomes, JSON_UNESCAPED_UNICODE); ?>;
var labData      = <?php echo json_encode($chartDataLabs, JSON_UNESCAPED_UNICODE); ?>;
var salaNomes    = <?php echo json_encode($salaNomes, JSON_UNESCAPED_UNICODE); ?>;
var salaData     = <?php echo json_encode($chartDataSalas, JSON_UNESCAPED_UNICODE); ?>;
var rankingSalas = <?php echo json_encode($rankingSalas, JSON_UNESCAPED_UNICODE); ?>;
var rankingLabs  = <?php echo json_encode($rankingLabs,  JSON_UNESCAPED_UNICODE); ?>;
var top5Pizza    = <?php echo json_encode($top5PizzaData, JSON_UNESCAPED_UNICODE); ?>;

var CORES = {
    manha: {bg:'rgba(255,193,7,.7)',  border:'#ffc107'},
    tarde: {bg:'rgba(13,110,253,.7)', border:'#0d6efd'},
    noite: {bg:'rgba(108,91,123,.7)', border:'#6c5b7b'},
};
var PIZZA_CORES = ['#1565c0','#e65100','#2e7d32','#6a1b9a','#c62828'];

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function corRank(i,total){ return i<3?'#1b5e20':(i>=total-3?'#b71c1c':'#212529'); }
function bgRank(i,total){ return i<3?'#4caf50':(i>=total-3?'#ef5350':'#90a4ae'); }

/* ── Gráfico Laboratórios (barras) ── */
var ctxLab = document.getElementById('chartLabs').getContext('2d');
var chartLab = new Chart(ctxLab, {
    type: 'bar',
    data: {
        labels: labNomes,
        datasets:[{ label:'Ocupação (%)', data:labData['manha'],
            backgroundColor:CORES.manha.bg, borderColor:CORES.manha.border,
            borderWidth:1, borderRadius:4 }]
    },
    options:{
        responsive:true,
        plugins:{ legend:{display:false},
            tooltip:{callbacks:{label:function(c){return c.parsed.y+'%';}}} },
        scales:{
            y:{min:0,max:100,ticks:{callback:function(v){return v+'%';}},
               title:{display:true,text:'% dos dias com reserva'}},
            x:{ticks:{maxRotation:30}}
        }
    }
});

function trocarTurnoLab(turno, el){
    document.querySelectorAll('#tabsLab .turno-tab-mini').forEach(function(t){t.classList.remove('ativo');});
    el.classList.add('ativo');
    chartLab.data.datasets[0].data = labData[turno];
    chartLab.data.datasets[0].backgroundColor = CORES[turno].bg;
    chartLab.data.datasets[0].borderColor = CORES[turno].border;
    chartLab.update();
}

/* ── Gráfico Salas (barras) ── */
var _turnoSalaAtual = 'manha';
var ctxSala = document.getElementById('chartSalas').getContext('2d');
var chartSala = new Chart(ctxSala, {
    type: 'bar',
    data: {
        labels: salaNomes,
        datasets:[{ label:'Ocupação (%)', data:salaData['manha'],
            backgroundColor:CORES.manha.bg, borderColor:CORES.manha.border,
            borderWidth:1, borderRadius:4 }]
    },
    options:{
        responsive:true,
        plugins:{ legend:{display:false},
            tooltip:{callbacks:{label:function(c){return c.parsed.y+'%';}}} },
        scales:{
            y:{min:0,max:100,ticks:{callback:function(v){return v+'%';}},
               title:{display:true,text:'% dos dias com aula'}},
            x:{ticks:{maxRotation:30}}
        },
        onClick:function(){ mostrarRankingSala(_turnoSalaAtual); },
        onHover:function(evt,elements){ if(elements&&elements.length) mostrarRankingSala(_turnoSalaAtual); }
    }
});

function trocarTurnoSala(turno, el){
    _turnoSalaAtual = turno;
    document.querySelectorAll('#tabsSala .turno-tab-mini').forEach(function(t){t.classList.remove('ativo');});
    el.classList.add('ativo');
    chartSala.data.datasets[0].data = salaData[turno];
    chartSala.data.datasets[0].backgroundColor = CORES[turno].bg;
    chartSala.data.datasets[0].borderColor = CORES[turno].border;
    chartSala.update();
    mostrarRankingSala(turno);
}

function mostrarRankingSala(turno){
    var nomes={manha:'Manhã',tarde:'Tarde',noite:'Noite'};
    var lista=rankingSalas[turno];
    var panel=document.getElementById('rankingPanel');
    var titulo=document.getElementById('rankingTitulo');
    var cont=document.getElementById('rankingLista');
    titulo.textContent='Salas — '+nomes[turno];
    cont.innerHTML='';
    lista.forEach(function(s,i){
        cont.innerHTML+='<div class="ranking-item">'
            +'<span style="color:'+corRank(i,lista.length)+'">'+(i+1)+'. '+esc(s.nome)+'</span>'
            +'<span class="pct-badge">'+s.pct+'%</span></div>';
    });
    panel.classList.add('visivel');
}

/* ── Pizza Top 5 Labs ── */
var ctxPizza = document.getElementById('chartPizza').getContext('2d');
var pizzaLabels = top5Pizza.map(function(x){return x.nome;});
var pizzaVals   = top5Pizza.map(function(x){return x.pct;});
new Chart(ctxPizza,{
    type:'doughnut',
    data:{
        labels:pizzaLabels,
        datasets:[{data:pizzaVals, backgroundColor:PIZZA_CORES,
            borderWidth:2, borderColor:'#fff', hoverOffset:6}]
    },
    options:{
        responsive:true, maintainAspectRatio:true,
        plugins:{legend:{display:false},
            tooltip:{callbacks:{label:function(c){return c.label+': '+c.parsed+'%';}}}}
    }
});
// Legenda manual
var leg=document.getElementById('pizzaLegend');
top5Pizza.forEach(function(x,i){
    leg.innerHTML+='<div class="pizza-legend-item">'
        +'<div class="pizza-dot" style="background:'+PIZZA_CORES[i]+'"></div>'
        +'<span title="'+esc(x.nome)+'">'+esc(x.nome.length>22?x.nome.substring(0,22)+'…':x.nome)
        +' <strong>'+x.pct+'%</strong></span></div>';
});

/* ── Ranking Labs ── */
function renderRankLab(turno){
    var lista=rankingLabs[turno];
    var cont=document.getElementById('rankLabLista');
    cont.innerHTML='';
    lista.forEach(function(l,i){
        cont.innerHTML+='<div class="rank-bar-row">'
            +'<span class="rank-pos">'+(i+1)+'</span>'
            +'<span class="rank-nome" title="'+esc(l.nome)+'">'+esc(l.nome)+'</span>'
            +'<div class="rank-bar-wrap"><div class="rank-bar-fill" style="width:'+l.pct+'%;background:'+bgRank(i,lista.length)+'"></div></div>'
            +'<span class="rank-pct" style="color:'+corRank(i,lista.length)+'">'+l.pct+'%</span>'
            +'</div>';
    });
}
function trocarRankLab(turno,el){
    document.querySelectorAll('#tabsRankLab .rank-tab').forEach(function(t){t.classList.remove('ativo');});
    el.classList.add('ativo');
    renderRankLab(turno);
}
renderRankLab('manha');

/* ── Ranking Salas ── */
function renderRankSala(turno){
    var lista=rankingSalas[turno];
    var cont=document.getElementById('rankSalaLista');
    cont.innerHTML='';
    lista.forEach(function(s,i){
        cont.innerHTML+='<div class="rank-bar-row">'
            +'<span class="rank-pos">'+(i+1)+'</span>'
            +'<span class="rank-nome" title="'+esc(s.nome)+'">'+esc(s.nome)+'</span>'
            +'<div class="rank-bar-wrap"><div class="rank-bar-fill" style="width:'+s.pct+'%;background:'+bgRank(i,lista.length)+'"></div></div>'
            +'<span class="rank-pct" style="color:'+corRank(i,lista.length)+'">'+s.pct+'%</span>'
            +'</div>';
    });
}
function trocarRankSala(turno,el){
    document.querySelectorAll('#tabsRankSala .rank-tab').forEach(function(t){t.classList.remove('ativo');});
    el.classList.add('ativo');
    renderRankSala(turno);
}
renderRankSala('manha');
</script>
</body>
</html>