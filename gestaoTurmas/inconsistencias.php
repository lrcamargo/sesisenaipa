<?php
/*
 * inconsistencias.php
 * Lista todas as inconsistências do período selecionado com filtros.
 * Permite gerar PDF via impressão do navegador.
 */

require_once('../conexao.php');
require_once('horariosHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['sup tecnica','sup pedagogica','gerencia','admin','administrator'])){
    header('location:../index.php'); exit;
}

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');

$dow       = (int)date('N');
$segSemana = date('Y-m-d', strtotime("$hoje -".($dow-1)." days"));
$sabSemana = date('Y-m-d', strtotime("$segSemana +5 days"));

$dataInicio = $_GET['de']  ?? $segSemana;
$dataFim    = $_GET['ate'] ?? $sabSemana;
$filtroTipo   = $_GET['tipo']    ?? '';
$ocultarEmSD  = isset($_GET['ocultar_em_sd']); // ocultar EM sem docente

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataInicio)) $dataInicio=$segSemana;
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataFim))    $dataFim=$sabSemana;
if($dataFim < $dataInicio) $dataFim=$dataInicio;

/* ── Helpers ── */
function normalizarNome(string $s): string {
    $s=mb_strtolower(trim($s));
    $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s;
    return preg_replace('/[^a-z0-9 ]/','',$s);
}

/* ── Carga de dados ── */
$cacheJson = file_exists(HORARIOS_CACHE)
    ? json_decode(file_get_contents(HORARIOS_CACHE), true) : null;

$instrutores = $pdo->query(
    "SELECT id, nome, apelido, turnosTrabalho FROM usuarios WHERE perfil='Instrutor' ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

// Mapa apelido_norm → nome completo (para resolver nomes curtos da planilha)
$mapaApelidos = [];
foreach($instrutores as $i){
    if(!empty($i['apelido']))
        $mapaApelidos[normalizarNome($i['apelido'])] = mb_strtoupper($i['nome']);
}

// Vínculos
$stmtVinc = $pdo->query("
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci AS turno
    FROM turma_sala UNION ALL
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci
    FROM turma_sala_externa
");
$vincIdx=[];
foreach($stmtVinc->fetchAll(PDO::FETCH_ASSOC) as $v)
    $vincIdx[$v['codigoTurma']][$v['turno']][]=(int)$v['diasSemana'];

// Vínculos EM↔HT
$vinculosCodigos=[];
foreach($pdo->query("SELECT codigoSistema,codigoExcel FROM turma_codigos_alt")->fetchAll(PDO::FETCH_ASSOC) as $v){
    $vinculosCodigos[$v['codigoSistema']]=$v['codigoExcel'];
    $vinculosCodigos[$v['codigoExcel']]=$v['codigoSistema'];
}

// Turmas COM vínculo cadastrado (set para lookup rápido)
$turmasComVinculo = array_keys($vincIdx);

// Turmas EM- que têm vínculo com HT em turma_codigos_alt
// Qualquer EM que apareça em qualquer coluna da tabela tem vínculo
$emComVinculo = [];
foreach($pdo->query("SELECT codigoSistema, codigoExcel FROM turma_codigos_alt")->fetchAll(PDO::FETCH_ASSOC) as $v){
    if(preg_match('/^EM-/i', $v['codigoSistema'])) $emComVinculo[] = $v['codigoSistema'];
    if(preg_match('/^EM-/i', $v['codigoExcel']))   $emComVinculo[] = $v['codigoExcel'];
}
$emComVinculo = array_unique($emComVinculo);

// Prédio de cada turma externa (para regra Presidente Bernardes)
$stmtPred = $pdo->query("
    SELECT tse.codigoTurma, LOWER(p.nome) AS nomePredio, se.idPredio
    FROM turma_sala_externa tse
    JOIN salas_externas se ON se.id=tse.idSala
    JOIN predios p ON p.id=se.idPredio
");
$turmaParaPredio=[];
foreach($stmtPred->fetchAll(PDO::FETCH_ASSOC) as $r)
    $turmaParaPredio[$r['codigoTurma']] = ['id'=>(int)$r['idPredio'],'nome'=>$r['nomePredio']];

define('PREDIO_PB', 'presidente bernardes');

// Feriados
$stmtFer=$pdo->prepare("SELECT f.data,f.nome,f.tipo,GROUP_CONCAT(ft.codigoTurma SEPARATOR ',') AS turmas FROM feriados f LEFT JOIN feriado_turmas ft ON ft.idFeriado=f.id WHERE f.data BETWEEN ? AND ? GROUP BY f.id");
$stmtFer->execute([$dataInicio,$dataFim]);
$feriadosSemana=[];
foreach($stmtFer->fetchAll(PDO::FETCH_ASSOC) as $f)
    $feriadosSemana[$f['data']]=['nome'=>$f['nome'],'tipo'=>$f['tipo'],'turmasRecesso'=>$f['turmas']?explode(',',$f['turmas']):[]];

// Férias de turmas
$stmtFT=$pdo->prepare("SELECT ft.codigoTurma,f.dataInicio,f.dataFim FROM turma_ferias f JOIN turma_ferias_turmas ft ON ft.idFerias=f.id WHERE f.dataFim>=? AND f.dataInicio<=?");
$stmtFT->execute([$dataInicio,$dataFim]);
$feriasTurmas=[];
foreach($stmtFT->fetchAll(PDO::FETCH_ASSOC) as $fv){
    $dc=new DateTime($fv['dataInicio']); $df2=new DateTime($fv['dataFim']);
    while($dc<=$df2){ $feriasTurmas[$fv['codigoTurma']][$dc->format('Y-m-d')]=true; $dc->modify('+1 day'); }
}

// Férias de instrutores — indexado por apelido normalizado (mesma lógica do painelDocentes)
$stmtFI=$pdo->prepare("
    SELECT u.nome, u.apelido, f.dataInicio, f.dataFim
    FROM instrutor_ferias f
    JOIN usuarios u ON u.id=f.idInstrutor
    WHERE f.dataFim>=? AND f.dataInicio<=?
");
$stmtFI->execute([$dataInicio,$dataFim]);
$feriasInst=[];   // [apelido_norm][data] = true
$feriasInstNome=[];// [nome_norm][data] = true  (para match com APP que usa nome completo)
foreach($stmtFI->fetchAll(PDO::FETCH_ASSOC) as $fv){
    $apNorm = !empty($fv['apelido']) ? normalizarNome($fv['apelido']) : normalizarNome(explode(' ',trim($fv['nome']))[0]);
    $nomeNorm = normalizarNome($fv['nome']);
    $dc=new DateTime($fv['dataInicio']); $df2=new DateTime($fv['dataFim']);
    while($dc<=$df2){
        $d=$dc->format('Y-m-d');
        $feriasInst[$apNorm][$d]=true;
        $feriasInstNome[$nomeNorm][$d]=true;
        $dc->modify('+1 day');
    }
}

// Turmas APP manuais
$appTurmas=[];
try {
    $stApp=$pdo->query("
        SELECT t.codigo, t.diasSemana, t.dataInicio, t.dataFim,
               tt.turno, tt.instrutor
        FROM app_turmas t
        JOIN app_turma_turnos tt ON tt.idTurma=t.id
        WHERE t.ativo=1
    ");
    foreach($stApp->fetchAll(PDO::FETCH_ASSOC) as $ap)
        $appTurmas[$ap['codigo']][$ap['turno']]=$ap;
} catch(PDOException $e){}

// APP também são turmas com vínculo
foreach(array_keys($appTurmas) as $c) $turmasComVinculo[]=$c;
$turmasComVinculo=array_unique($turmasComVinculo);

$diasBits=[1,2,4,8,16,32];

/* ── Funções auxiliares ── */
function instrutorNaData(string $cod, string $data) {
    global $cacheJson;
    return $cacheJson['dados'][$data][$cod] ?? null;
}
function turmaTemAulaHoje(string $cod, string $turno, int $bit): bool {
    global $vincIdx;
    if(!isset($vincIdx[$cod][$turno])) return false;
    foreach($vincIdx[$cod][$turno] as $mask) if(($mask&$bit)>0) return true;
    return false;
}
function ehFeriado(string $cod, string $data): bool {
    global $feriadosSemana, $vinculosCodigos;
    if(!isset($feriadosSemana[$data])) return false;
    $f=$feriadosSemana[$data];
    if($f['tipo']==='nacional'||$f['tipo']==='municipal') return true;
    if(empty($f['turmasRecesso'])) return true;
    $alt=$vinculosCodigos[$cod]??null;
    return in_array($cod,$f['turmasRecesso'])||($alt&&in_array($alt,$f['turmasRecesso']));
}
function ehFerias(string $cod, string $data): bool {
    global $feriasTurmas, $vinculosCodigos;
    if(isset($feriasTurmas[$cod][$data])) return true;
    $alt=$vinculosCodigos[$cod]??null;
    return $alt&&isset($feriasTurmas[$alt][$data]);
}
// Match instrutor da planilha (apelido) com férias
function instEmFeriasApelido(string $nomeNaCachePlanilha, string $data): bool {
    global $feriasInst, $mapaApelidos;
    $norm=normalizarNome($nomeNaCachePlanilha);
    return isset($feriasInst[$norm][$data]);
}
// Match instrutor do APP (nome completo) com férias
function instEmFeriasNome(string $nomeCompleto, string $data): bool {
    global $feriasInstNome;
    $norm=normalizarNome($nomeCompleto);
    return isset($feriasInstNome[$norm][$data]);
}

// Verifica se é turma EF (fundamental) — excluir
function ehTurmaEF(string $cod): bool {
    return (bool)preg_match('/^EF-/i', $cod);
}
// Verifica se é turma EM sem vínculo com HT em turma_codigos_alt — excluir das inconsistências
function ehEMSemVinculo(string $cod): bool {
    global $emComVinculo;
    return preg_match('/^EM-/i', $cod) && !in_array($cod, $emComVinculo);
}
// Deve processar esta turma?
function deveProcessar(string $cod): bool {
    if(ehTurmaEF($cod)) return false;
    if(ehEMSemVinculo($cod)) return false;
    return true;
}

// Verifica regra Presidente Bernardes (Sex/Sáb, mesmo prédio)
function ehDuplicataPB(array $turmas, string $data): bool {
    global $turmaParaPredio, $pdo;
    $diaN=(int)date('N',strtotime($data));
    if(!in_array($diaN,[5,6])) return false; // só Sex e Sáb
    $predios=[];
    foreach($turmas as $tc){
        if(isset($turmaParaPredio[$tc])){
            $predios[]=$turmaParaPredio[$tc]['id'];
        } else {
            try {
                $st=$pdo->prepare("SELECT se.idPredio FROM turma_sala_externa tse JOIN salas_externas se ON se.id=tse.idSala WHERE tse.codigoTurma=? LIMIT 1");
                $st->execute([$tc]);
                $r=$st->fetch(PDO::FETCH_ASSOC);
                if($r) $predios[]=(int)$r['idPredio'];
            } catch(Exception $e){}
        }
    }
    $prediosUniq=array_unique(array_filter($predios));
    if(count($prediosUniq)!==1) return false; // prédios diferentes = duplicata real
    try {
        $st=$pdo->prepare("SELECT LOWER(nome) AS n FROM predios WHERE id=? LIMIT 1");
        $st->execute([reset($prediosUniq)]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        return $r && str_contains($r['n'], PREDIO_PB);
    } catch(Exception $e){ return false; }
}

/* ── Gera inconsistências no período ── */
$inconsistencias = [];
$turnosLabels=['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'];

$dcAtual = new DateTime($dataInicio);
$dfFim   = new DateTime($dataFim);

while($dcAtual <= $dfFim){
    $data   = $dcAtual->format('Y-m-d');
    $dowIdx = (int)$dcAtual->format('N') - 1; // 0=Seg..5=Sab
    if($dowIdx > 5){ $dcAtual->modify('+1 day'); continue; }
    $bit      = $diasBits[$dowIdx];
    $diaLabel = ['Seg','Ter','Qua','Qui','Sex','Sáb'][$dowIdx].' '.date('d/m',strtotime($data));

    // Mapa instrutor→turmas por turno (planilha + APP) para detectar duplicados
    $instPorTurno=[];

    /* ─── 1. Turmas do cache (planilha) ─── */
    if($cacheJson && isset($cacheJson['dados'][$data])){
        foreach($cacheJson['dados'][$data] as $cod=>$info){
            if(!deveProcessar($cod)) continue;
            if(ehFeriado($cod,$data)||ehFerias($cod,$data)) continue;
            if(empty($info['instrutor'])) continue;

            // Determina o turno desta turma neste dia
            // Prioridade: vínculo cadastrado; fallback: turno do código; fallback: todos os turnos
            $turnosAula = [];
            foreach($turnosLabels as $tk=>$tl){
                if(turmaTemAulaHoje($cod,$tk,$bit)) $turnosAula[$tk]=$tl;
            }
            // Se não tem vínculo, tenta inferir pelo código (M/T/N)
            if(empty($turnosAula)){
                if(preg_match('/^[A-Z]+-[A-Z]+-\d+-([MTN])-/i',$cod,$m)){
                    $letra=strtoupper($m[1]);
                    $mapa=['M'=>'manha','T'=>'tarde','N'=>'noite'];
                    if(isset($mapa[$letra])) $turnosAula[$mapa[$letra]]=$turnosLabels[$mapa[$letra]];
                }
            }
            // Se ainda vazio, inclui em todos (turma sem padrão de código)
            if(empty($turnosAula)) $turnosAula=$turnosLabels;

            foreach($turnosAula as $turnoKey=>$turnoLabel){
                // Valida instrutor: ignora valores inválidos (muito curtos, numéricos)
                $instRaw = trim($info['instrutor']);
                if(mb_strlen($instRaw) < 3 || is_numeric($instRaw)) continue;
                // Chave lowercase para match com APP
                $chave=mb_strtolower($instRaw);
                $instPorTurno[$turnoKey][$chave][]=$cod;

                // Instrutor em férias com aula
                if(instEmFeriasApelido($info['instrutor'],$data)){
                    $nomeCompleto=$mapaApelidos[normalizarNome($info['instrutor'])]??mb_strtoupper($info['instrutor']);
                    // Evita duplicata de alerta
                    $jaExiste=false;
                    foreach($inconsistencias as $inc){
                        if($inc['tipo']==='ferias_instrutor'&&$inc['turma']===$cod&&$inc['dia']===$diaLabel){$jaExiste=true;break;}
                    }
                    if(!$jaExiste)
                        $inconsistencias[]=['tipo'=>'ferias_instrutor','turma'=>$cod,'dia'=>$diaLabel,'turno'=>$turnoLabel,'inst'=>$nomeCompleto,'fonte'=>'planilha'];
                }
            }
        }
    }

    /* ─── 1b. Turmas com vínculo sem instrutor na planilha → sem_docente ─── */
    foreach($vincIdx as $cod=>$turnos){
        if(!deveProcessar($cod)) continue;
        foreach($turnos as $turnoKey=>$masks){
            $temBit=false;
            foreach($masks as $m) if($m&$bit){$temBit=true;break;}
            if(!$temBit) continue;
            if(ehFeriado($cod,$data)||ehFerias($cod,$data)) continue;
            $info=instrutorNaData($cod,$data);
            $codAlt=$vinculosCodigos[$cod]??null;
            $infoAlt=$codAlt?instrutorNaData($codAlt,$data):null;
            if((!$info||empty($info['instrutor']))&&(!$infoAlt||empty($infoAlt['instrutor']))){
                $jaExiste=false;
                foreach($inconsistencias as $inc){
                    if($inc['tipo']==='sem_docente'&&$inc['turma']===$cod&&$inc['dia']===$diaLabel&&$inc['turno']===$turnosLabels[$turnoKey]){$jaExiste=true;break;}
                }
                if(!$jaExiste)
                    $inconsistencias[]=['tipo'=>'sem_docente','turma'=>$cod,'dia'=>$diaLabel,'turno'=>$turnosLabels[$turnoKey]??$turnoKey,'inst'=>'','fonte'=>'planilha'];
            }
        }
    }

    /* ─── 3. Turmas APP manuais ─── */
    foreach($appTurmas as $cod=>$turnos){
        foreach($turnos as $turnoKey=>$ap){
            // Verifica se está ativa nesta data
            if($data < $ap['dataInicio'] || $data > $ap['dataFim']) continue;
            if(!($ap['diasSemana'] & $bit)) continue;
            if(ehFerias($cod,$data)) continue;

            $turnoLabel=$turnosLabels[$turnoKey]??$turnoKey;
            $nomeInst=mb_strtoupper(trim($ap['instrutor']));

            // Chave lowercase (mesmo padrão da planilha — mas APP usa nome completo)
            // Resolve apelido para unificar com planilha
            $chaveApp=mb_strtolower($nomeInst);
            foreach($instrutores as $i){
                if(mb_strtoupper(trim($i['nome']))===$nomeInst){
                    $chaveApp=!empty($i['apelido'])
                        ?mb_strtolower(trim($i['apelido']))
                        :mb_strtolower(explode(' ',trim($i['nome']))[0]);
                    break;
                }
            }
            $instPorTurno[$turnoKey][$chaveApp][]=$cod;

            // Instrutor em férias com aula APP
            if(instEmFeriasNome($ap['instrutor'],$data))
                $inconsistencias[]=['tipo'=>'ferias_instrutor','turma'=>$cod,'dia'=>$diaLabel,'turno'=>$turnoLabel,'inst'=>$nomeInst,'fonte'=>'app'];
        }
    }

    /* ─── 4. Duplicados (planilha + APP combinados) ─── */
    foreach($instPorTurno as $turnoKey=>$mapa){
        $turnoLabel=$turnosLabels[$turnoKey]??$turnoKey;
        foreach($mapa as $chave=>$turmas){
            $turmas=array_unique($turmas);
            if(count($turmas)<2) continue;

            // Regra Presidente Bernardes: Sex/Sáb, mesmo prédio → não é duplicata
            if(ehDuplicataPB($turmas,$data)) continue;

            // Resolve nome exibível
            $nomeExibir=$mapaApelidos[normalizarNome($chave)]??mb_strtoupper($chave);

            // Verifica se envolve APP
            $envolvePP=false;
            foreach($turmas as $tc) if(preg_match('/^APP-/i',$tc)){$envolvePP=true;break;}

            $inconsistencias[]=[
                'tipo'   => 'duplicado',
                'turma'  => implode(' + ',$turmas),
                'dia'    => $diaLabel,
                'turno'  => $turnoLabel,
                'inst'   => $nomeExibir,
                'fonte'  => $envolvePP ? 'app' : 'planilha',
            ];
        }
    }

    $dcAtual->modify('+1 day');
}

// Remove duplicatas exatas
$inconsistencias = array_unique($inconsistencias, SORT_REGULAR);

// Filtro: ocultar EM sem docente
if($ocultarEmSD){
    $inconsistencias = array_filter($inconsistencias, function($inc){
        global $emComVinculo, $vinculosCodigos;
        if($inc['tipo'] !== 'sem_docente') return true; // mantém outros tipos
        $cod = $inc['turma'];
        // Remove se a turma for EM ou se for HT vinculada a EM
        if(preg_match('/^EM-/i', $cod)) return false;
        $codAlt = $vinculosCodigos[$cod] ?? null;
        if($codAlt && preg_match('/^EM-/i', $codAlt)) return false;
        return true;
    });
    $inconsistencias = array_values($inconsistencias);
}

// Filtro por tipo
if($filtroTipo === 'app'){
    $inconsistencias=array_filter($inconsistencias,fn($i)=>($i['fonte']??'')!=='app'?false:true
        || preg_match('/^APP-/i', $i['turma']));
    // Mais preciso: mostra tudo que envolve APP
    $inconsistencias=array_filter($inconsistencias,function($i){
        return ($i['fonte']??'')==='app' || preg_match('/APP-/i',$i['turma']);
    });
} elseif($filtroTipo){
    $inconsistencias=array_filter($inconsistencias,fn($i)=>$i['tipo']===$filtroTipo);
}
$inconsistencias=array_values($inconsistencias);

$tipoLabels=[
    'sem_docente'      => 'Sem docente',
    'duplicado'        => 'Duplicado',
    'ferias_instrutor' => 'Instrutor em férias c/ aula',
];
$tipoCores=[
    'sem_docente'      => 'danger',
    'duplicado'        => 'warning',
    'ferias_instrutor' => 'info',
];
$tipoIcones=[
    'sem_docente'      => '❌',
    'duplicado'        => '⚠️',
    'ferias_instrutor' => '✈️',
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inconsistências</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.filtro-bar{background:#fff;border:1px solid #dee2e6;border-radius:8px;
    padding:12px 16px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.filtro-bar .fg{display:flex;flex-direction:column;gap:3px;}
.filtro-bar label{font-size:.75rem;font-weight:600;margin:0;}
.filtro-bar input,.filtro-bar select{padding:5px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.83rem;}
.inc-table th{background:#343a40;color:#fff;font-size:.8rem;white-space:nowrap;}
.inc-table td{font-size:.82rem;vertical-align:middle;}
.badge-tipo{display:inline-block;border-radius:4px;padding:2px 8px;font-size:.72rem;font-weight:700;}
.badge-app{background:#fff3e0;color:#e65100;border:1px solid #e65100;border-radius:4px;
    padding:1px 6px;font-size:.68rem;font-weight:700;margin-left:4px;}
.resumo-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.resumo-pill{padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:700;
    display:flex;align-items:center;gap:6px;}
@media print {
    .wrapper > .header, .wrapper > .sidebar, .no-print { display:none !important; }
    .main-container { margin:0!important; padding:10px!important; }
    .filtro-bar { display:none!important; }
    body { font-size:11pt; }
    .inc-table { font-size:9pt; }
    h4 { font-size:13pt; }
    .resumo-bar { margin-bottom:10px; }
}
.print-only { display:none; }
@media print { .print-only { display:block!important; } }
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
    <h4 class="mb-0"><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Inconsistências</h4>
    <div class="no-print" style="display:flex;gap:8px">
        <a href="painelDocentes.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i>Painel
        </a>
        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print mr-1"></i>Imprimir / PDF
        </button>
    </div>
</div>

<!-- Filtros -->
<form method="GET" class="filtro-bar no-print">
    <?php if($ocultarEmSD): ?><input type="hidden" name="ocultar_em_sd" value="1"><?php endif; ?>
    <div class="fg">
        <label>De</label>
        <input type="date" name="de" value="<?php echo $dataInicio; ?>">
    </div>
    <div class="fg">
        <label>Até</label>
        <input type="date" name="ate" value="<?php echo $dataFim; ?>">
    </div>
    <div class="fg">
        <label>Tipo</label>
        <select name="tipo">
            <option value="">Todos</option>
            <option value="sem_docente"      <?php echo $filtroTipo==='sem_docente'?'selected':''; ?>>Sem docente</option>
            <option value="duplicado"        <?php echo $filtroTipo==='duplicado'?'selected':''; ?>>Duplicado</option>
            <option value="ferias_instrutor" <?php echo $filtroTipo==='ferias_instrutor'?'selected':''; ?>>Instrutor em férias c/ aula</option>
            <option value="app"              <?php echo $filtroTipo==='app'?'selected':''; ?>>🟠 Relacionados a APP</option>
        </select>
    </div>
    <div class="fg">
        <label>Turmas EM</label>
        <button type="submit" name="ocultar_em_sd" value="1"
                class="btn btn-sm <?php echo $ocultarEmSD?'btn-warning':'btn-outline-warning'; ?>"
                title="Oculta turmas EM e seus vínculos HT da lista de sem docente">
            <i class="fas fa-eye-slash mr-1"></i>
            <?php echo $ocultarEmSD?'EM ocultas':'Ocultar EM s/ doc.'; ?>
        </button>
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
        <i class="fas fa-search mr-1"></i>Filtrar
    </button>
    <a href="inconsistencias.php" class="btn btn-outline-secondary btn-sm" style="align-self:flex-end">Limpar</a>
</form>

<!-- Período impresso -->
<div class="print-only mb-2">
    <strong>Período:</strong> <?php echo date('d/m/Y',strtotime($dataInicio)); ?> a <?php echo date('d/m/Y',strtotime($dataFim)); ?>
    &nbsp;|&nbsp; Gerado em: <?php echo date('d/m/Y H:i'); ?> por <?php echo htmlspecialchars($_SESSION['user']); ?>
</div>

<!-- Resumo -->
<?php
$contagem=['sem_docente'=>0,'duplicado'=>0,'ferias_instrutor'=>0,'app'=>0];
foreach($inconsistencias as $inc){
    if(isset($contagem[$inc['tipo']])) $contagem[$inc['tipo']]++;
    if(($inc['fonte']??'')==='app'||preg_match('/APP-/i',$inc['turma'])) $contagem['app']++;
}
// Contagem total antes do filtro para mostrar no resumo
$contagemTotal=['sem_docente'=>0,'duplicado'=>0,'ferias_instrutor'=>0,'app'=>0];
// já filtrado — mostra o que está na tela
?>
<div class="resumo-bar">
    <div class="resumo-pill" style="background:#f8d7da;color:#721c24">
        ❌ <?php echo $contagem['sem_docente']; ?> sem docente
    </div>
    <div class="resumo-pill" style="background:#fff3cd;color:#856404">
        ⚠️ <?php echo $contagem['duplicado']; ?> duplicados
    </div>
    <div class="resumo-pill" style="background:#d1ecf1;color:#0c5460">
        ✈️ <?php echo $contagem['ferias_instrutor']; ?> férias c/ aula
    </div>
    <div class="resumo-pill" style="background:#fff3e0;color:#e65100">
        🟠 <?php echo $contagem['app']; ?> envolvem APP
    </div>
    <div class="resumo-pill ml-auto" style="background:#e9ecef;color:#495057">
        Total: <strong><?php echo count($inconsistencias); ?></strong>
    </div>
</div>

<?php if(empty($inconsistencias)): ?>
<div class="alert alert-success py-3 text-center">
    <i class="fas fa-check-circle mr-2"></i>
    Nenhuma inconsistência encontrada no período de
    <?php echo date('d/m/Y',strtotime($dataInicio)); ?> a <?php echo date('d/m/Y',strtotime($dataFim)); ?>.
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-bordered inc-table bg-white">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Dia</th>
            <th>Turno</th>
            <th>Turma(s)</th>
            <th>Instrutor</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($inconsistencias as $inc):
        $cor   = $tipoCores[$inc['tipo']] ?? 'secondary';
        $icone = $tipoIcones[$inc['tipo']] ?? '•';
        $label = $tipoLabels[$inc['tipo']] ?? $inc['tipo'];
        $isApp = ($inc['fonte']??'')==='app' || preg_match('/APP-/i',$inc['turma']);
    ?>
    <tr <?php echo $isApp?"style='background:#fff8f3'":""; ?>>
        <td>
            <span class="badge-tipo badge-<?php echo $cor; ?>">
                <?php echo $icone; ?> <?php echo $label; ?>
            </span>
            <?php if($isApp): ?><span class="badge-app">APP</span><?php endif; ?>
        </td>
        <td><strong><?php echo htmlspecialchars($inc['dia']); ?></strong></td>
        <td><?php echo htmlspecialchars($inc['turno']); ?></td>
        <td><?php echo htmlspecialchars($inc['turma']); ?></td>
        <td><?php echo htmlspecialchars($inc['inst'] ?? '—'); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

</div></div>
<script src="../js/menu.js"></script>
</body>
</html>