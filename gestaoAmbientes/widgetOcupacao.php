<?php
/*
 * widget_ocupacao.php
 * Include no dashboard dos gestores.
 * Mostra resumo de ocupação de laboratórios e salas do mês atual.
 * Clicável → redireciona para ocupacaoLaboratorios.php
 *
 * USO: <?php include 'gestaoAmbientes/widget_ocupacao.php'; ?>
 */

// Garante que $pdo esteja disponível (já incluído pelo dashboard)
if(!isset($pdo)) return;

date_default_timezone_set('America/Sao_Paulo');
$_wDataInicio = date('Y-m-01');
$_wDataFim    = date('Y-m-t');

// Dias úteis no mês (Seg–Sáb)
$_wTotalDias = 0;
$_wDc = new DateTime($_wDataInicio);
$_wDf = new DateTime($_wDataFim);
while($_wDc <= $_wDf){
    if((int)$_wDc->format('N') <= 6) $_wTotalDias++;
    $_wDc->modify('+1 day');
}

// Laboratórios com reserva
$_wLabs = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temReserva=1 AND temSala=0 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

// Reservas do mês por laboratório
$_wStR = $pdo->prepare("
    SELECT laboratorio, COUNT(DISTINCT data) AS diasOcup
    FROM reservas
    WHERE data BETWEEN ? AND ? AND aprovado IN (0,1)
    GROUP BY laboratorio
");
$_wStR->execute([$_wDataInicio, $_wDataFim]);
$_wReservasPorLab = [];
foreach($_wStR->fetchAll(PDO::FETCH_ASSOC) as $r)
    $_wReservasPorLab[$r['laboratorio']] = (int)$r['diasOcup'];

// Monta dados dos labs
$_wDadosLabs = [];
foreach($_wLabs as $l){
    $dias = $_wReservasPorLab[$l['idLaboratorio']] ?? 0;
    $pct  = $_wTotalDias > 0 ? round($dias/$_wTotalDias*100) : 0;
    $_wDadosLabs[] = ['nome'=>$l['nome'],'pct'=>$pct,'dias'=>$dias];
}
usort($_wDadosLabs, fn($a,$b)=>$b['pct']<=>$a['pct']);

// Salas de aula
$_wSalas = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temSala=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);
$_wIdsSalas = array_column($_wSalas,'idLaboratorio');
$_wDiasBits = [1=>1,2=>2,3=>4,4=>8,5=>16,6=>32];

// Vínculos de salas
$_wVincSalas = [];
if(!empty($_wIdsSalas)){
    $in = implode(',', $_wIdsSalas);
    $_wVincSalas = $pdo->query("
        SELECT ts.codigoTurma, ts.idSala, ts.turno, ts.diasSemana
        FROM turma_sala ts WHERE ts.idSala IN ($in)
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Reservas de lab por turma no mês (para excluir duplicatas sala/lab)
$_wHorasTurno = [
    'manha'=>['i'=>'06:00:00','f'=>'12:59:59'],
    'tarde'=>['i'=>'13:00:00','f'=>'17:59:59'],
    'noite'=>['i'=>'18:00:00','f'=>'23:59:59'],
];
$_wStRT = $pdo->prepare("SELECT turma,data,horarioInicio FROM reservas WHERE data BETWEEN ? AND ? AND aprovado IN(0,1) AND turma IS NOT NULL");
$_wStRT->execute([$_wDataInicio,$_wDataFim]);
$_wResPorTurma=[];
foreach($_wStRT->fetchAll(PDO::FETCH_ASSOC) as $r){
    if(!$r['turma']) continue;
    $tk='manha';
    foreach($_wHorasTurno as $t=>$f){ if($r['horarioInicio']>=$f['i']&&$r['horarioInicio']<=$f['f']){$tk=$t;break;} }
    $_wResPorTurma[$r['turma']][$r['data']][$tk]=true;
}

// Conta dias de aula nas salas
$_wDiasSala = []; // [idSala][data] = true
$_wDc2 = new DateTime($_wDataInicio);
while($_wDc2 <= $_wDf){
    $data=$_wDc2->format('Y-m-d'); $dow=(int)$_wDc2->format('N');
    if($dow<=6){
        $bit=$_wDiasBits[$dow]??0;
        foreach($_wVincSalas as $v){
            if(!($v['diasSemana']&$bit)) continue;
            if(isset($_wResPorTurma[$v['codigoTurma']][$data])) continue;
            $_wDiasSala[(int)$v['idSala']][$data]=true;
        }
    }
    $_wDc2->modify('+1 day');
}

$_wDadosSalas=[];
foreach($_wSalas as $s){
    $dias=count($_wDiasSala[$s['idLaboratorio']]??[]);
    $pct=$_wTotalDias>0?round($dias/$_wTotalDias*100):0;
    $_wDadosSalas[]=['nome'=>$s['nome'],'pct'=>$pct,'dias'=>$dias];
}
usort($_wDadosSalas,fn($a,$b)=>$b['pct']<=>$a['pct']);

// Médias gerais
$_wMediaLab  = count($_wDadosLabs)  > 0 ? round(array_sum(array_column($_wDadosLabs,'pct')) /count($_wDadosLabs))  : 0;
$_wMediaSala = count($_wDadosSalas) > 0 ? round(array_sum(array_column($_wDadosSalas,'pct'))/count($_wDadosSalas)) : 0;

$_wUrlBase = 'gestaoAmbientes/ocupacaoLaboratorios.php?periodo=mes';

function _wCorPct(int $pct): string {
    if($pct >= 75) return '#1b5e20';
    if($pct >= 40) return '#e65100';
    return '#b71c1c';
}
function _wBgPct(int $pct): string {
    if($pct >= 75) return '#e8f5e9';
    if($pct >= 40) return '#fff3e0';
    return '#ffebee';
}
?>
<div class="widget-ocupacao" id="widgetOcupacao" style="background:#fff;border:1px solid #dee2e6;border-radius:10px;
     padding:16px 18px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.07)">

    <!-- Cabeçalho -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h6 style="margin:0;font-weight:700;font-size:.92rem;color:#212529">
            <i class="fas fa-chart-bar mr-2" style="color:#0d6efd"></i>Ocupação — <?php echo date('M/Y'); ?>
            <div style="margin-top:6px;padding-top:6px;font-size:.85rem;color:#adb5bd;display:flex;justify-content:space-between">
                <span><i class="fas fa-info-circle mr-1"></i><?php echo $_wTotalDias; ?> dias úteis no período. Para saber mais clique em "Análise Completa" ou em qualquer informação.</span>
            </div>
        </h6>
        <div style="display:flex;align-items:center;gap:10px">
            <!--<a href="<?php //echo $_wUrlBase; ?>" style="font-size:.75rem;color:#0d6efd;text-decoration:none">-->
                <span style="font-size:.85rem;color:#0d6efd;text-decoration:none"> Ver detalhes <i class="fas fa-arrow-right ml-1"></i>
            </a>
            <button onclick="wOcupToggle(this)"
                    id="wOcupBtn"
                    title="Ocultar/exibir widget"
                    style="background:none;border:none;cursor:pointer;color:#adb5bd;
                           font-size:.85rem;padding:2px 6px;border-radius:4px;line-height:1"
                    onmouseover="this.style.color='#6c757d'" onmouseout="this.style.color='#adb5bd'">
                <i class="fas fa-chevron-up" id="wOcupIcon"></i>
            </button>
        </div>
    </div>

    <!-- Conteúdo colapsável -->
    <div id="wOcupBody">

    <!-- Cards de média geral -->
    <div style="display:flex;gap:10px;margin-bottom:16px">
        <a href="<?php echo $_wUrlBase; ?>" style="flex:1;text-decoration:none"
           title="Clique para ver detalhes dos laboratórios">
            <div style="background:<?php echo _wBgPct($_wMediaLab); ?>;border-radius:8px;padding:12px 14px;text-align:center">
                <div style="font-size:1.6rem;font-weight:800;color:<?php echo _wCorPct($_wMediaLab); ?>">
                    <?php echo $_wMediaLab; ?>%
                </div>
                <div style="font-size:.72rem;font-weight:600;color:#555;margin-top:2px">
                    <i class="fas fa-flask mr-1"></i>Média Laboratórios
                </div>
            </div>
        </a>
        <a href="<?php echo $_wUrlBase; ?>" style="flex:1;text-decoration:none"
           title="Clique para ver detalhes das salas">
            <div style="background:<?php echo _wBgPct($_wMediaSala); ?>;border-radius:8px;padding:12px 14px;text-align:center">
                <div style="font-size:1.6rem;font-weight:800;color:<?php echo _wCorPct($_wMediaSala); ?>">
                    <?php echo $_wMediaSala; ?>%
                </div>
                <div style="font-size:.72rem;font-weight:600;color:#555;margin-top:2px">
                    <i class="fas fa-chalkboard mr-1"></i>Média Salas
                </div>
            </div>
        </a>
    </div>

    <!-- Top laboratórios -->
    <?php if(!empty($_wDadosLabs)): ?>
    <div style="margin-bottom:14px">
        <div style="font-size:.72rem;font-weight:700;color:#6c757d;text-transform:uppercase;
             letter-spacing:.05em;margin-bottom:8px">
            <i class="fas fa-flask mr-1"></i>Laboratórios
        </div>
        <?php foreach(array_slice($_wDadosLabs,0,5) as $lab): ?>
        <a href="<?php echo $_wUrlBase; ?>" style="display:block;text-decoration:none;margin-bottom:6px"
           title="Ver ocupação detalhada">
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:.75rem;color:#212529;min-width:120px;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px"
                      title="<?php echo htmlspecialchars($lab['nome']); ?>">
                    <?php echo htmlspecialchars($lab['nome']); ?>
                </span>
                <div style="flex:1;background:#e9ecef;border-radius:4px;height:10px;overflow:hidden">
                    <div style="width:<?php echo $lab['pct']; ?>%;height:100%;
                         background:<?php echo _wCorPct($lab['pct']); ?>;border-radius:4px;
                         transition:width .4s ease"></div>
                </div>
                <span style="font-size:.72rem;font-weight:700;color:<?php echo _wCorPct($lab['pct']); ?>;
                      min-width:34px;text-align:right">
                    <?php echo $lab['pct']; ?>%
                </span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php if(count($_wDadosLabs)>5): ?>
        <a href="<?php echo $_wUrlBase; ?>" style="font-size:.72rem;color:#0d6efd">
            + <?php echo count($_wDadosLabs)-5; ?> laboratórios →
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Top salas -->
    <?php if(!empty($_wDadosSalas)): ?>
    <div>
        <div style="font-size:.72rem;font-weight:700;color:#6c757d;text-transform:uppercase;
             letter-spacing:.05em;margin-bottom:8px">
            <i class="fas fa-chalkboard mr-1"></i>Salas de Aula
        </div>
        <?php foreach(array_slice($_wDadosSalas,0,4) as $sala): ?>
        <a href="<?php echo $_wUrlBase; ?>" style="display:block;text-decoration:none;margin-bottom:6px">
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:.75rem;color:#212529;min-width:120px;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px"
                      title="<?php echo htmlspecialchars($sala['nome']); ?>">
                    <?php echo htmlspecialchars($sala['nome']); ?>
                </span>
                <div style="flex:1;background:#e9ecef;border-radius:4px;height:10px;overflow:hidden">
                    <div style="width:<?php echo $sala['pct']; ?>%;height:100%;
                         background:<?php echo _wCorPct($sala['pct']); ?>;border-radius:4px;
                         transition:width .4s ease"></div>
                </div>
                <span style="font-size:.72rem;font-weight:700;color:<?php echo _wCorPct($sala['pct']); ?>;
                      min-width:34px;text-align:right">
                    <?php echo $sala['pct']; ?>%
                </span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php if(count($_wDadosSalas)>4): ?>
        <a href="<?php echo $_wUrlBase; ?>" style="font-size:.72rem;color:#0d6efd">
            + <?php echo count($_wDadosSalas)-4; ?> salas →
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Rodapé -->
    <div style="margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f0;
         font-size:.7rem;color:#adb5bd;display:flex;justify-content:space-between">
        <span><i class="fas fa-info-circle mr-1"></i><?php echo $_wTotalDias; ?> dias úteis no período</span>
        <a href="<?php echo $_wUrlBase; ?>" style="color:#0d6efd;font-size:.8rem">
            Análise completa →
        </a>
    </div>
    </div><!-- /wOcupBody -->
</div>
<script>
(function(){
    var KEY = 'wOcupCollapsed';
    var body = document.getElementById('wOcupBody');
    var icon = document.getElementById('wOcupIcon');
    // Restaura estado salvo
    if(localStorage.getItem(KEY) === '1'){
        body.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
    }
})();
function wOcupToggle(btn){
    var KEY = 'wOcupCollapsed';
    var body = document.getElementById('wOcupBody');
    var icon = document.getElementById('wOcupIcon');
    var collapsed = body.style.display === 'none';
    if(collapsed){
        body.style.display = '';
        icon.className = 'fas fa-chevron-up';
        localStorage.removeItem(KEY);
    } else {
        body.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        localStorage.setItem(KEY,'1');
    }
}
</script>