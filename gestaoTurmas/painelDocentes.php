<?php
/*
 * painelDocentes.php — Painel de alocação de docentes
 * Acesso: sup tecnica, sup pedagogica, gerencia, admin
 */

require_once('../conexao.php');
require_once('horariosHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['sup tecnica','sup pedagogica','gerencia','admin','administrator'])){
    header('location:../index.php'); exit;
}

$logado = $_SESSION['user'];

date_default_timezone_set('America/Sao_Paulo');
$hoje    = date('Y-m-d');
$dataRef = $_GET['data'] ?? $hoje;
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) $dataRef = $hoje;

$dow       = (int)date('N', strtotime($dataRef));
$segSemana = date('Y-m-d', strtotime($dataRef . ' -' . ($dow-1) . ' days'));

$diasSemana = [];
for($i = 0; $i < 6; $i++)
    $diasSemana[] = date('Y-m-d', strtotime($segSemana . " +{$i} days"));

$semanaAnterior = date('Y-m-d', strtotime($segSemana . ' -7 days'));
$proximaSemana  = date('Y-m-d', strtotime($segSemana . ' +7 days'));
$diasNomes      = ['Seg','Ter','Qua','Qui','Sex','Sáb'];
$diasBits       = [1, 2, 4, 8, 16, 32];

const BIT_MANHA = 1;
const BIT_TARDE = 2;
const BIT_NOITE = 4;

$turnosConfig = [
    'manha' => ['label'=>'Manhã', 'bit'=>BIT_MANHA, 'icone'=>'fa-sun'],
    'tarde' => ['label'=>'Tarde', 'bit'=>BIT_TARDE, 'icone'=>'fa-cloud-sun'],
    'noite' => ['label'=>'Noite', 'bit'=>BIT_NOITE, 'icone'=>'fa-moon'],
];
$cargas = [20, 25, 30, 40];

$stmtInst = $pdo->prepare("SELECT id, nome, cargaHoraria, turnosTrabalho FROM usuarios WHERE perfil = 'Instrutor' ORDER BY nome");
$stmtInst->execute();
$instrutores = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

$stmtVincCod = $pdo->query("SELECT codigoSistema, codigoExcel FROM turma_codigos_alt");
$vinculosCodigos = [];
foreach($stmtVincCod->fetchAll(PDO::FETCH_ASSOC) as $v){
    $vinculosCodigos[$v['codigoSistema']] = $v['codigoExcel'];
    $vinculosCodigos[$v['codigoExcel']]   = $v['codigoSistema'];
}

$stmtFer = $pdo->prepare("
    SELECT f.id, f.data, f.nome, f.tipo,
           GROUP_CONCAT(ft.codigoTurma SEPARATOR ',') AS turmasRecesso
    FROM feriados f
    LEFT JOIN feriado_turmas ft ON ft.idFeriado = f.id
    WHERE f.data BETWEEN ? AND ?
    GROUP BY f.id
");
$stmtFer->execute([$diasSemana[0], $diasSemana[5]]);
$feriadosSemana = [];
foreach($stmtFer->fetchAll(PDO::FETCH_ASSOC) as $f){
    $feriadosSemana[$f['data']] = [
        'nome'          => $f['nome'],
        'tipo'          => $f['tipo'],
        'turmasRecesso' => $f['turmasRecesso'] ? explode(',', $f['turmasRecesso']) : [],
    ];
}

function temFeriado(string $codTurma, string $data): ?array {
    global $feriadosSemana, $vinculosCodigos;
    if(!isset($feriadosSemana[$data])) return null;
    $f = $feriadosSemana[$data];
    if($f['tipo'] === 'nacional' || $f['tipo'] === 'municipal') return $f;
    if(empty($f['turmasRecesso'])) return $f;
    $codAlt = $vinculosCodigos[$codTurma] ?? null;
    if(in_array($codTurma, $f['turmasRecesso'])) return $f;
    if($codAlt && in_array($codAlt, $f['turmasRecesso'])) return $f;
    return null;
}

function ehTurmaSenai(string $cod): bool {
    if(preg_match('/^(HT|AI|APP)-/i', $cod))  return true;
    if(preg_match('/^EM-\w+-O\b/i',  $cod))   return true;
    return false;
}
function ehTurmaEM(string $cod): bool {
    return (bool)preg_match('/^EM-/i', $cod);
}

$turmasAPI = []; $apiDisp = false;
$ch = curl_init('http://172.16.95.253:3002/backapi/Turmas?dataInicio=' . $dataRef);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,
    CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($httpCode >= 200 && $httpCode < 300 && $resp){
    $dec = json_decode($resp, true);
    if(is_array($dec) && count($dec) > 0){
        foreach($dec as $t){
            $cod = $t['nome'] ?? $t['codigo'] ?? null;
            if($cod && ehTurmaSenai($cod)) $turmasAPI[$cod] = true;
        }
        $apiDisp = true;
    }
}

$cacheJson = file_exists(HORARIOS_CACHE)
    ? json_decode(file_get_contents(HORARIOS_CACHE), true) : null;

$turmasPlanilha = [];
if($cacheJson && isset($cacheJson['dados'])){
    foreach($diasSemana as $d){
        if(!isset($cacheJson['dados'][$d])) continue;
        foreach(array_keys($cacheJson['dados'][$d]) as $cod){
            if(ehTurmaSenai($cod)) $turmasPlanilha[$cod] = true;
        }
    }
}

$todasTurmas = array_keys(array_merge($turmasPlanilha, $turmasAPI));
foreach(array_keys($turmasAPI) as $c) if(!in_array($c,$todasTurmas)) $todasTurmas[] = $c;
$todasTurmas = array_unique($todasTurmas);
sort($todasTurmas);

$turmasEM    = array_filter($todasTurmas, 'ehTurmaEM');
$turmasNaoEM = array_filter($todasTurmas, fn($c) => !ehTurmaEM($c));

$stmtVinc = $pdo->query("
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci AS turno
    FROM turma_sala
    UNION ALL
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci AS turno
    FROM turma_sala_externa
");
$vinculos = $stmtVinc->fetchAll(PDO::FETCH_ASSOC);
$vincIdx  = [];
foreach($vinculos as $v){
    $vincIdx[$v['codigoTurma']][$v['turno']][] = (int)$v['diasSemana'];
}

/* Mapa turma → idPredio externo (null = prédio principal) */
$stmtPredioTurma = $pdo->query("
    SELECT tse.codigoTurma, se.idPredio, p.nome AS nomePredio
    FROM turma_sala_externa tse
    JOIN salas_externas se ON se.id = tse.idSala
    JOIN predios p ON p.id = se.idPredio
");
$turmaParaPredio = []; // [codigoTurma] => ['id'=>idPredio, 'nome'=>nomePredio]
foreach($stmtPredioTurma->fetchAll(PDO::FETCH_ASSOC) as $r){
    $turmaParaPredio[$r['codigoTurma']] = [
        'id'   => (int)$r['idPredio'],
        'nome' => mb_strtolower($r['nomePredio']),
    ];
}
// Nome parcial da escola com regra especial (case-insensitive)
define('PREDIO_DUPLICATA_PERMITIDA', 'presidente bernardes');


function instrutorNaData(string $cod, string $data): ?array {
    global $cacheJson;
    return $cacheJson['dados'][$data][$cod] ?? null;
}


/*
 * Abrevia nome: 1º + 2º token (se 2º for partícula, usa 1º + 3º)
 * JOSÉ DE FARIA TOLEDO → JOSÉ FARIA | GABRIEL HENRIQUE NOGUEIRA → GABRIEL HENRIQUE
 */
function abreviarNome(string $nome): string {
    $particulas = ['DE','DA','DO','DOS','DAS','E','A','O'];
    $tokens = array_values(array_filter(explode(' ', mb_strtoupper(trim($nome)))));
    if(count($tokens) <= 1) return $nome;
    if(in_array($tokens[1] ?? '', $particulas))
        return $tokens[0].($tokens[2] ? ' '.$tokens[2] : '');
    return $tokens[0].' '.$tokens[1];
}

function resolverNomeInstrutor(string $nomeCurto): string {
    global $mapaInstrutores;
    $token = normalizarNome($nomeCurto);
    return $mapaInstrutores[$token] ?? mb_strtoupper($nomeCurto);
}

function normalizarNome(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s;
    return preg_replace('/[^a-z0-9 ]/','',$s);
}

/*
 * Mapa de resolução de nomes:
 * Passo 1 — indexa APENAS o primeiro nome de cada instrutor (prioridade máxima).
 *            Ex: "felipe" → FELIPE MATHEUS (não ERYSON FELIPE)
 * Passo 2 — indexa tokens subsequentes SÓ SE ainda não mapeados.
 *            Ex: "eryson" → ERYSON FELIPE FERNANDES (primeiro nome, sem conflito)
 * Resultado: a planilha escreve "Felipe" → resolve para FELIPE MATHEUS corretamente.
 */
$mapaInstrutores = [];
$particulas      = ['de','da','do','dos','das','e','a','o'];

// Passo 1: somente primeiros nomes
foreach($instrutores as $inst){
    $tokens = array_values(array_filter(explode(' ', normalizarNome($inst['nome']))));
    if(empty($tokens)) continue;
    $primeiro = $tokens[0];
    if(strlen($primeiro) >= 3 && !in_array($primeiro, $particulas))
        $mapaInstrutores[$primeiro] = mb_strtoupper($inst['nome']);
}

// Passo 2: tokens subsequentes (sobrenomes/apelidos), sem sobrescrever
foreach($instrutores as $inst){
    $tokens = array_values(array_filter(explode(' ', normalizarNome($inst['nome']))));
    foreach(array_slice($tokens, 1) as $tok){
        if(strlen($tok) < 3 || in_array($tok, $particulas)) continue;
        if(!isset($mapaInstrutores[$tok]))
            $mapaInstrutores[$tok] = mb_strtoupper($inst['nome']);
    }
}

function turmaTemAulaHoje(string $cod, string $turno, int $bit): bool {
    global $vincIdx;
    if(!isset($vincIdx[$cod][$turno])) return false;
    foreach($vincIdx[$cod][$turno] as $mask){
        if(($mask & $bit) > 0) return true;
    }
    return false;
}

function turnoDoCodigoTurma(string $cod): ?string {
    if(preg_match('/^[A-Z]+-[A-Z]+-\d+-([MTN])-/i', $cod, $m)){
        $letra = strtoupper($m[1]);
        if($letra === 'M') return 'manha';
        if($letra === 'T') return 'tarde';
        if($letra === 'N') return 'noite';
    }
    return null;
}

function turmaDeveExibirNaTurno(string $cod, string $turno, array $diasSemana, array $diasBits): bool {
    global $vincIdx, $cacheJson;
    if(isset($vincIdx[$cod][$turno])){
        foreach($diasSemana as $di => $data){
            if(turmaTemAulaHoje($cod, $turno, $diasBits[$di])) return true;
        }
        return false;
    }
    $turnoCodigo = turnoDoCodigoTurma($cod);
    if($turnoCodigo !== null && $turnoCodigo !== $turno) return false;
    if($cacheJson && isset($cacheJson['dados'])){
        foreach($diasSemana as $data){
            if(isset($cacheJson['dados'][$data][$cod])) return true;
        }
    }
    return false;
}

/* ── POST: atualizar cache de horários (executa o script Python) ── */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_cache'){
    ob_clean(); header('Content-Type: application/json');
    $script = '/var/www/html/scripts/gerarHorariosCache.py';
    $lock   = '/tmp/gerarHorariosCache.lock';
    if(file_exists($lock)){
        echo json_encode(['ok'=>false,'msg'=>'Atualização já em andamento. Aguarde.']);
    } else {
        // Executa em background para não travar a requisição
        exec("nohup python3 {$script} >> /var/www/html/logs/horarios_cache.log 2>&1 &");
        // Aguarda até 8s para o lock aparecer e desaparecer (script rápido)
        $inicio = time();
        while(!file_exists($lock) && time()-$inicio < 3) usleep(200000);
        echo json_encode(['ok'=>true,'msg'=>'Atualização iniciada. O cache será regenerado em instantes.']);
    }
    exit;
}

/* ── POST: salvar carga horária ── */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');
    if($_POST['acao'] === 'salvar_carga'){
        $idInst     = intval($_POST['idInstrutor']    ?? 0);
        $carga      = intval($_POST['cargaHoraria']   ?? 0);
        $turnosMask = intval($_POST['turnosTrabalho'] ?? 7);
        if(!$idInst || !in_array($carga,[20,25,30,40])){
            echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
        }
        try{
            $pdo->prepare("UPDATE usuarios SET cargaHoraria=?,turnosTrabalho=? WHERE id=?")
                ->execute([$carga,$turnosMask,$idInst]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
        }
        exit;
    }
}

/* ── Alertas ── */
$alertasEM = []; $alertasOutro = [];
foreach($todasTurmas as $cod){
    foreach($turnosConfig as $turnoKey => $tConf){
        if(!turmaDeveExibirNaTurno($cod, $turnoKey, $diasSemana, $diasBits)) continue;
        foreach($diasSemana as $di => $data){
            $bit        = $diasBits[$di];
            $temVinculo = isset($vincIdx[$cod][$turnoKey]);
            if($temVinculo && !turmaTemAulaHoje($cod, $turnoKey, $bit)) continue;
            $info = instrutorNaData($cod, $data);
            if(!$temVinculo && !$info) continue;
            if(temFeriado($cod, $data)) continue;
            if(!$info || empty($info['instrutor'])){
                $codExcel = $vinculosCodigos[$cod] ?? null;
                if($codExcel){ $infoAlt = instrutorNaData($codExcel, $data); if($infoAlt && !empty($infoAlt['instrutor'])) continue; }
                $entry = ['turma'=>$cod,'turno'=>$tConf['label'],'dia'=>$diasNomes[$di].' '.date('d/m',strtotime($data)),'tipo'=>'sem_docente'];
                if(ehTurmaEM($cod)) $alertasEM[] = $entry; else $alertasOutro[] = $entry;
            }
        }
    }
}
$instPorDiaTurno = [];
foreach($todasTurmas as $cod){
    foreach($turnosConfig as $turnoKey => $tConf){
        foreach($diasSemana as $di => $data){
            $bit = $diasBits[$di];
            if(!turmaTemAulaHoje($cod,$turnoKey,$bit)) continue;
            $info = instrutorNaData($cod,$data);
            if(!$info || empty($info['instrutor'])) continue;
            $instPorDiaTurno[$data][$turnoKey][$info['instrutor']][] = $cod;
        }
    }
}
foreach($instPorDiaTurno as $data => $turnos){
    $di = array_search($data, $diasSemana);
    foreach($turnos as $turnoKey => $mapa){
        foreach($mapa as $inst => $turmasInst){
            if(count($turmasInst) < 2) continue;

            /*
             * Regra especial: E.E. Presidente Bernardes na sexta ou sábado.
             * Instrutoras têm apenas 2 aulas por turma nesses dias,
             * então podem cobrir 2 turmas — não é duplicata.
             * Condições: todas as turmas no mesmo prédio (Presidente Bernardes)
             *            E o dia é sexta (bit 16) ou sábado (bit 32).
             */
            $diaDaSemana = date('N', strtotime($data)); // 5=Sex, 6=Sáb
            if(in_array($diaDaSemana, [5, 6])){
                $prediosNomes = array_map(fn($c) => $turmaParaPredio[$c]['nome'] ?? '', $turmasInst);
                $prediosIds   = array_unique(array_map(fn($c) => $turmaParaPredio[$c]['id'] ?? 0, $turmasInst));
                $todasMesmoPredio = count($prediosIds) === 1 && $prediosIds[0] !== 0;
                $ehPresidenteBernardes = $todasMesmoPredio &&
                    str_contains(reset($prediosNomes), PREDIO_DUPLICATA_PERMITIDA);
                if($ehPresidenteBernardes) continue;
            }

            $entry = ['turma'=>implode(' + ',$turmasInst),'turno'=>$turnosConfig[$turnoKey]['label'],
                      'dia'=>$diasNomes[$di].' '.date('d/m',strtotime($data)),'tipo'=>'duplicado','inst'=>mb_strtoupper($inst)];
            if(ehTurmaEM($turmasInst[0])) $alertasEM[] = $entry; else $alertasOutro[] = $entry;
        }
    }
}

/* ── Pré-processa dados para o popup JS ── */
$jsInstrutores = array_values(array_map(fn($i) => [
    'nome'      => mb_strtoupper($i['nome']),
    'bit'       => (int)($i['turnosTrabalho'] ?: 7),
    'primeiro'  => mb_strtolower(explode(' ', trim($i['nome']))[0]),
], $instrutores));

// Ocupa por dia/turno: [turno][data] = [primeiros nomes em minúsculo]
// Inclui turmas COM vínculo (bitmask) E turmas SEM vínculo (só planilha)
$jsOcupados = [];
foreach(['manha','tarde','noite'] as $tk){
    $jsOcupados[$tk] = [];
    foreach($diasSemana as $data) $jsOcupados[$tk][$data] = [];
}
foreach($todasTurmas as $cod){
    foreach(['manha','tarde','noite'] as $tk){
        foreach($diasSemana as $di => $data){
            $temVinc = isset($vincIdx[$cod][$tk]);
            if($temVinc){
                // Com vínculo: só conta se bitmask indicar aula neste dia
                if(!turmaTemAulaHoje($cod,$tk,$diasBits[$di])) continue;
            } else {
                // Sem vínculo: só inclui se a planilha tiver dado neste dia
                $inf = instrutorNaData($cod,$data);
                if(!$inf) continue; // sem dado na planilha = sem aula
                // Filtra pelo turno do código (M/T/N); se indeterminado, inclui em todos
                $turnoCod = turnoDoCodigoTurma($cod);
                if($turnoCod !== null && $turnoCod !== $tk) continue;
            }
            $inf = instrutorNaData($cod,$data);
            if($inf && !empty($inf['instrutor']))
                $jsOcupados[$tk][$data][] = mb_strtolower(trim($inf['instrutor']));
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Docentes</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.alerta-critico{background:#fff5f5;border:2px solid #dc3545;border-radius:8px;padding:12px 16px;margin-bottom:10px;}
.alerta-critico h6{color:#dc3545;font-weight:700;margin-bottom:8px;font-size:.9rem;}
.badge-alerta{display:inline-block;background:#dc3545;color:#fff;border-radius:4px;padding:3px 8px;font-size:.78rem;font-weight:700;margin:2px;}
.badge-alerta.duplicado{background:#856404;color:#fff3cd;}
.badge-alerta.feriado{background:#e6a817;color:#212529;}
.alerta-em-toggle,.alerta-em-body{border-color:#e6a817!important;background:#fffbea!important;}
.alerta-em-toggle h6,.alerta-em-toggle .toggle-icon{color:#856404!important;}
.badge-alerta.em{background:#e6a817;color:#212529;}
.badge-alerta.em.duplicado{background:#c07a00;color:#fff;}
.alerta-em-toggle{display:flex;align-items:center;justify-content:space-between;background:#fff5f5;border:2px solid #dc3545;border-radius:8px;padding:10px 16px;cursor:pointer;margin-bottom:0;user-select:none;}
.alerta-em-toggle h6{margin:0;color:#dc3545;font-weight:700;font-size:.9rem;}
.alerta-em-toggle .toggle-icon{color:#dc3545;transition:transform .25s;}
.alerta-em-toggle.aberto .toggle-icon{transform:rotate(180deg);}
.alerta-em-body{display:none;background:#fff5f5;border:2px solid #dc3545;border-top:none;border-radius:0 0 8px 8px;padding:10px 16px;margin-bottom:10px;}
.alerta-em-body.aberto{display:block;}
.disponivel-box{background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:10px 14px;margin-bottom:10px;}
.disponivel-box h6{color:#155724;font-weight:700;margin-bottom:6px;font-size:.9rem;}
.badge-disp{display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:4px;padding:5px 12px;font-size:.85rem;font-weight:600;margin:2px;}
.nav-semana{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 0;margin-bottom:4px;}
.btn-nav{padding:5px 14px;border-radius:6px;border:1px solid #dee2e6;background:#fff;cursor:pointer;font-size:.85rem;text-decoration:none;color:#495057;}
.btn-nav:hover{background:#e9ecef;text-decoration:none;}
.semana-label{font-weight:700;font-size:.9rem;color:#343a40;}
.data-picker{display:flex;gap:6px;align-items:center;margin-left:auto;}
.data-picker input{padding:4px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.85rem;}
.data-picker button{padding:4px 12px;border-radius:4px;border:none;background:#0d6efd;color:#fff;font-size:.82rem;cursor:pointer;}
.turno-tabs{display:flex;gap:4px;margin-bottom:0;border-bottom:2px solid #dee2e6;margin-top:8px;}
.turno-tab{padding:8px 18px;border-radius:6px 6px 0 0;border:1px solid #dee2e6;border-bottom:none;background:#f8f9fa;cursor:pointer;font-size:.85rem;font-weight:600;color:#495057;margin-bottom:-2px;}
.turno-tab.ativo{background:#fff;border-bottom:2px solid #fff;color:#0d6efd;}
.turno-panel{display:none;padding:12px 0;}
.turno-panel.ativo{display:block;}
.tabela-wrapper{overflow-x:auto;border-radius:0 8px 8px 8px;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.tabela-docentes{width:100%;border-collapse:collapse;font-size:.82rem;background:#fff;}
.tabela-docentes th{background:#343a40;color:#fff;padding:8px 10px;text-align:center;white-space:nowrap;position:sticky;top:0;z-index:0;}
.tabela-docentes th:first-child{text-align:left;min-width:200px;position:sticky;left:0;z-index:0;background:#343a40;}
.tabela-docentes td{padding:7px 10px;border:1px solid #dee2e6;vertical-align:middle;text-align:center;}
.tabela-docentes td:first-child{text-align:left;font-weight:600;background:#f8f9fa;position:sticky;left:0;z-index:0;border-right:2px solid #dee2e6;}
.tabela-docentes tbody tr:hover td{background:#f0f4ff!important;}
.tabela-docentes tbody tr:hover td:first-child{background:#e8edff!important;}
.cel-doc-nome{font-size:.78rem;font-weight:700;color:#212529;}
.cel-sem-aula{color:#dee2e6;font-size:1.4rem;}
.cel-sem-doc{background:#fff3cd;color:#856404;border-radius:3px;padding:2px 5px;font-size:.72rem;font-weight:600;}
.th-hoje{background:#c07a00!important;}
td.td-hoje{background:#fff9e6!important;}
/* ── Cabeçalho clicável ── */
.th-clicavel{cursor:pointer;}
.th-clicavel:hover{background:#23272b!important;}
.th-clicavel.ativo{background:#1a5276!important;outline:2px solid #aed6f1;}
.turnos-check{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;}
.turno-ck-btn{padding:6px 14px;border-radius:4px;border:1px solid #ced4da;background:#fff;font-size:.85rem;cursor:pointer;font-weight:600;transition:all .15s;}
.turno-ck-btn.sel{background:#0d6efd;color:#fff;border-color:#0d6efd;}
#toastDocentes{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;display:none;padding:12px 18px;border-radius:6px;font-size:.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastDocentes.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastDocentes.erro{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-chalkboard-teacher mr-2"></i>Painel de Docentes</h4>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline-success btn-sm" onclick="atualizarCache(this)"
                    title="Força a releitura do Excel e regenera o cache de horários">
                <i class="fas fa-sync-alt mr-1"></i>Atualizar horários
            </button>
            <?php if(isset($cacheJson['gerado_em'])): ?>
            <small class="text-muted align-self-center" style="font-size:.73rem">
                <i class="fas fa-clock mr-1"></i><?php echo date('d/m/Y H:i',strtotime($cacheJson['gerado_em'])); ?>
            </small>
            <?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#modalCarga">
                <i class="fas fa-user-clock mr-1"></i>Turnos de trabalho
            </button>
        </div>
    </div>

    <?php if(!$apiDisp){ ?>
    <div class="alert alert-warning py-2 mb-2">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        API de turmas indisponível — exibindo apenas turmas da planilha.
    </div>
    <?php } ?>

    <?php if(!empty($alertasOutro)){ ?>
    <div class="alerta-critico">
        <h6><i class="fas fa-exclamation-circle mr-1"></i>Atenção — ação necessária</h6>
        <?php foreach($alertasOutro as $al):
            $cls=$al['tipo']==='duplicado'?'duplicado':''; $icone=$al['tipo']==='duplicado'?'⚠️':'❌';
            $txt=$al['tipo']==='sem_docente'?"{$icone} {$al['turma']} — sem docente — {$al['turno']} {$al['dia']}":"{$icone} Duplicado: {$al['inst']} em {$al['turma']} — {$al['turno']} {$al['dia']}";
        ?><span class="badge-alerta <?php echo $cls; ?>"><?php echo htmlspecialchars($txt); ?></span>
        <?php endforeach; ?>
    </div>
    <?php } ?>

    <?php if(!empty($alertasEM)){ ?>
    <div class="alerta-em-toggle" id="toggleEM" onclick="toggleAlertas()">
        <h6><i class="fas fa-exclamation-circle mr-1"></i>Inconsistências — Turmas EM
            <span class="badge badge-danger ml-1"><?php echo count($alertasEM); ?></span></h6>
        <i class="fas fa-chevron-down toggle-icon"></i>
    </div>
    <div class="alerta-em-body" id="bodyEM">
        <?php foreach($alertasEM as $al):
            $cls=$al['tipo']==='duplicado'?'em duplicado':'em';
            $txt=$al['tipo']==='sem_docente'?"⚠️ {$al['turma']} — sem docente — {$al['turno']} {$al['dia']}":"⚠️ Duplicado: {$al['inst']} em {$al['turma']} — {$al['turno']} {$al['dia']}";
        ?><span class="badge-alerta <?php echo $cls; ?>"><?php echo htmlspecialchars($txt); ?></span>
        <?php endforeach; ?>
    </div>
    <?php } ?>

    <div class="turno-tabs">
        <?php foreach($turnosConfig as $key => $t){ ?>
        <div class="turno-tab <?php echo $key==='manha'?'ativo':''; ?>" onclick="trocarTurno('<?php echo $key; ?>')">
            <i class="fas <?php echo $t['icone']; ?> mr-1"></i><?php echo $t['label']; ?>
        </div>
        <?php } ?>
    </div>

    <?php foreach($turnosConfig as $turnoKey => $tConf):
        $bit_turno = $tConf['bit'];
        $instOcupados = [];
        foreach($todasTurmas as $cod){
            foreach($diasSemana as $di => $data){
                if(!turmaTemAulaHoje($cod,$turnoKey,$diasBits[$di])) continue;
                $info = instrutorNaData($cod,$data);
                if($info && !empty($info['instrutor']))
                    $instOcupados[mb_strtolower(trim($info['instrutor']))] = true;
            }
        }
        $instDisponiveis = array_filter($instrutores, function($inst) use($bit_turno,$instOcupados){
            $mask = (int)$inst['turnosTrabalho']; if($mask===0) $mask=7;
            if(!($mask & $bit_turno)) return false;
            $primeiroNome = mb_strtolower(explode(' ',trim($inst['nome']))[0]);
            return !isset($instOcupados[$primeiroNome]);
        });
    ?>
    <div class="turno-panel <?php echo $turnoKey==='manha'?'ativo':''; ?>" id="panel-<?php echo $turnoKey; ?>">

        <div class="nav-semana">
            <a href="?data=<?php echo $semanaAnterior; ?>" class="btn-nav"><i class="fas fa-chevron-left"></i> Anterior</a>
            <span class="semana-label"><?php echo date('d/m',strtotime($segSemana)); ?> – <?php echo date('d/m/Y',strtotime($diasSemana[5])); ?></span>
            <a href="?data=<?php echo $proximaSemana; ?>" class="btn-nav">Próxima <i class="fas fa-chevron-right"></i></a>
            <a href="?data=<?php echo $hoje; ?>" class="btn-nav">Hoje</a>
            <div class="data-picker">
                <input type="date" class="input-data-ref" value="<?php echo $dataRef; ?>">
                <button onclick="irParaData(this)">Ir</button>
            </div>
        </div>

        <div class="disponivel-box" id="disp-box-<?php echo $turnoKey; ?>"
             <?php if(empty($instDisponiveis)) echo "style='display:none'"; ?>>
            <h6 id="disp-titulo-<?php echo $turnoKey; ?>">
                <i class="fas fa-user-check mr-1"></i>Disponíveis — <?php echo $tConf['label']; ?>
            </h6>
            <div id="disp-corpo-<?php echo $turnoKey; ?>">
            <?php foreach($instDisponiveis as $inst){ ?>
            <span class="badge-disp"><?php echo htmlspecialchars(mb_strtoupper($inst['nome'])); ?></span>
            <?php } ?>
            </div>
        </div>

        <div class="tabela-wrapper">
        <table class="tabela-docentes">
            <thead>
                <tr>
                    <th>Turma</th>
                    <?php foreach($diasSemana as $di => $data){
                        $ehHoje = ($data === $hoje);
                        $cls    = $ehHoje ? "th-hoje th-clicavel" : "th-clicavel";
                        $dFmt   = date('d/m', strtotime($data));
                        echo "<th class='{$cls}' data-data='{$data}' data-turno='{$turnoKey}' onclick='mostrarDisponiveis(this)'>{$diasNomes[$di]}<br><small>{$dFmt}</small></th>";
                    } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $GLOBALS['pdo'] = $pdo;
            $renderLinhas = function(array $lista) use ($turnoKey,$diasSemana,$diasBits,$hoje,$diasNomes,$vinculosCodigos): void {
                foreach($lista as $cod){
                    if(!turmaDeveExibirNaTurno($cod,$turnoKey,$diasSemana,$diasBits)) continue;
                    // Coluna turma clicável → abre consultaHorario filtrado
                    echo "<tr><td title='Clique para ver o horário da turma' style='cursor:pointer'
                        onclick='window.open(\"consultaHorario.php?modo=turma&filtro=" . urlencode($cod) . "\", \"_blank\")'>
                        " . htmlspecialchars($cod) . "
                    </td>";
                    foreach($diasSemana as $di => $data){
                        $bit   = $diasBits[$di];
                        $tdCls = ($data===$hoje) ? " class='td-hoje'" : '';
                        $info       = instrutorNaData($cod,$data);
                        $temVinculo = isset($GLOBALS['vincIdx'][$cod][$turnoKey]);
                        $temAulaDia = $temVinculo ? turmaTemAulaHoje($cod,$turnoKey,$bit) : ($info !== null);

                        // Verifica feriado ANTES do — para mostrar 🏖️ mesmo sem aula cadastrada
                        $feriado = temFeriado($cod,$data);
                        if(!$temAulaDia){
                            if($feriado){
                                // Dia de feriado sem aula = correto
                                echo "<td{$tdCls}><span class='cel-sem-aula' title='".htmlspecialchars($feriado['nome'])."'>🏖️</span></td>";
                            } else {
                                echo "<td{$tdCls}><span class='cel-sem-aula'>—</span></td>";
                            }
                            continue;
                        }
                        // $temAulaDia = true abaixo — feriado será tratado no bloco seguinte
                        if($feriado){
                            $infoFer=$info; $codAltF=$vinculosCodigos[$cod]??null;
                            $infoAltF=$codAltF?instrutorNaData($codAltF,$data):null;
                            $temInst=($infoFer&&!empty($infoFer['instrutor']))||($infoAltF&&!empty($infoAltF['instrutor']));
                            if($temInst){
                                echo "<td style='background:#fff3cd'><div class='cel-docente'>";
                                echo "<span style='font-size:.7rem;font-weight:700;color:#856404'>⚠️ ".htmlspecialchars($feriado['nome'])."</span>";
                                echo "<span class='cel-doc-nome' style='color:#856404'>".htmlspecialchars(resolverNomeInstrutor($infoFer['instrutor']??$infoAltF['instrutor']??''))."</span>";
                                echo "</div></td>";
                            } else {
                                echo "<td style='background:#f8f9fa'><span class='cel-sem-aula' title='".htmlspecialchars($feriado['nome'])."'>🏖️</span></td>";
                            }
                            continue;
                        }
                        echo "<td{$tdCls}><div class='cel-docente'>";
                        // Determina instrutor e UC (direto ou via código alternativo EM↔HT)
                        $instNome = null; $instUC = null;
                        if($info && !empty($info['instrutor'])){
                            $instNome = resolverNomeInstrutor($info['instrutor']);
                            $instUC   = $info['uc'] ?? null;
                        } else {
                            $codAlt  = $vinculosCodigos[$cod] ?? null;
                            $infoAlt = $codAlt ? instrutorNaData($codAlt,$data) : null;
                            if($infoAlt && !empty($infoAlt['instrutor'])){
                                $instNome = resolverNomeInstrutor($infoAlt['instrutor']);
                                $instUC   = $infoAlt['uc'] ?? null;
                            }
                        }
                        if($instNome){
                            // UC — linha 1
                            if($instUC) echo "<span class='cel-uc'>".htmlspecialchars(mb_strtoupper($instUC))."</span>";
                            echo "</br>";
                            // Nome abreviado — linha 2
                            echo "<span class='cel-doc-nome'>".htmlspecialchars(abreviarNome($instNome))."</span>";
                            echo "</br>";
                            // Sala — linha 3
                            // Prioridade (igual ao painel de ocupação):
                            // 1. Reserva aprovada/aguardando no laboratório nesta data
                            // 2. Vínculo permanente em turma_sala (prédio principal)
                            // 3. Vínculo permanente em turma_sala_externa
                            // 4. Aviso de sem sala
                            static $salaCache = [];
                            $chSala = $cod.'|'.$data.'|'.$turnoKey;
                            if(!isset($salaCache[$chSala])){
                                $salaTurma = null;

                                // 1. Reserva do dia (mesmo padrão do ocupacaoHelper)
                                $stR = $GLOBALS['pdo']->prepare("
                                    SELECT l.nome
                                    FROM reservas r
                                    JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
                                    WHERE r.turma = ? AND r.data = ? AND r.aprovado IN (0,1)
                                    LIMIT 1
                                ");
                                $stR->execute([$cod, $data]);
                                $rR = $stR->fetch(PDO::FETCH_ASSOC);
                                if($rR) $salaTurma = $rR['nome'];

                                // 2. Vínculo permanente — prédio principal
                                if(!$salaTurma){
                                    $stS = $GLOBALS['pdo']->prepare("
                                        SELECT l.nome FROM turma_sala ts
                                        JOIN laboratorios l ON l.idLaboratorio = ts.idSala
                                        WHERE ts.codigoTurma=? AND ts.turno=? LIMIT 1
                                    ");
                                    $stS->execute([$cod, $turnoKey]);
                                    $rS = $stS->fetch(PDO::FETCH_ASSOC);
                                    if($rS) $salaTurma = $rS['nome'];
                                }

                                // 3. Vínculo permanente — prédio externo
                                if(!$salaTurma){
                                    $stE = $GLOBALS['pdo']->prepare("
                                        SELECT se.nome AS nomeSala, p.nome AS nomePredio
                                        FROM turma_sala_externa tse
                                        JOIN salas_externas se ON se.id=tse.idSala
                                        JOIN predios p ON p.id=se.idPredio
                                        WHERE tse.codigoTurma=? AND tse.turno=? LIMIT 1
                                    ");
                                    $stE->execute([$cod, $turnoKey]);
                                    $rE = $stE->fetch(PDO::FETCH_ASSOC);
                                    if($rE) $salaTurma = $rE['nomeSala'].' ('.$rE['nomePredio'].')';
                                }

                                $salaCache[$chSala] = $salaTurma;
                            }
                            $salaTurma = $salaCache[$chSala];
                            if($salaTurma)
                                echo "<span class='cel-local'>".htmlspecialchars($salaTurma)."</span>";
                            else
                                echo "<span class='cel-sem-sala'>⚠️ sem sala</span>";
                        } else {
                            echo "<span class='cel-sem-doc'>Sem docente</span>";
                        }
                        echo "</div></td>";
                    }
                    echo "</tr>";
                }
            };
            $renderLinhas(array_values($turmasNaoEM));
            $renderLinhas(array_values($turmasEM));
            ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</div>

<!-- Modal carga horária -->
<div class="modal fade" id="modalCarga" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-clock mr-2"></i>Turnos de trabalho dos instrutores</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <p class="text-muted mb-2" style="font-size:.85rem">Configure a carga horária e os turnos de cada instrutor.</p>
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light"><tr><th>Instrutor</th><th style="width:130px">Carga</th><th>Turnos de trabalho</th><th style="width:60px"></th></tr></thead>
            <tbody>
            <?php foreach($instrutores as $inst): $mask=(int)$inst['turnosTrabalho']; if($mask===0) $mask=7; ?>
            <tr>
                <td class="align-middle"><strong><?php echo htmlspecialchars(mb_strtoupper($inst['nome'])); ?></strong></td>
                <td><select class="form-control form-control-sm sel-carga" data-id="<?php echo $inst['id']; ?>">
                    <option value="">—</option>
                    <?php foreach($cargas as $c){ $sel=($inst['cargaHoraria']==$c)?'selected':''; echo "<option value='{$c}' {$sel}>{$c}h</option>"; } ?>
                </select></td>
                <td><div class="turnos-check">
                <?php foreach([BIT_MANHA=>'Manhã',BIT_TARDE=>'Tarde',BIT_NOITE=>'Noite'] as $bit=>$label){
                    $sel=($mask&$bit)?'sel':'';
                    echo "<button type='button' class='turno-ck-btn {$sel}' data-bit='{$bit}' data-id='{$inst['id']}'>{$label}</button>";
                } ?>
                </div></td>
                <td class="text-center align-middle">
                    <button class="btn btn-sm btn-primary btn-salvar-inst" data-id="<?php echo $inst['id']; ?>"><i class="fas fa-save"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button></div>
</div></div>
</div>

<div id="toastDocentes"></div>
<script src="../js/menu.js"></script>
<script>
/* Dados para o box de disponíveis — gerados pelo PHP antes do HTML */
var _instList  = <?php echo json_encode($jsInstrutores, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
var _ocupados  = <?php echo json_encode($jsOcupados,    JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
var _feriados  = <?php
    $jsFeriados = [];
    foreach($feriadosSemana as $d => $f){ $jsFeriados[$d] = $f['nome']; }
    echo json_encode($jsFeriados, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
?>;

/* Dados originais da semana (para restaurar ao clicar no th "Turma") */
var _dispOriginal = <?php
    $orig = [];
    foreach(['manha','tarde','noite'] as $tk){
        $bit_tk = ['manha'=>BIT_MANHA,'tarde'=>BIT_TARDE,'noite'=>BIT_NOITE][$tk];
        $ocup = [];
        foreach($todasTurmas as $cod){
            foreach($diasSemana as $di => $data){
                if(!turmaTemAulaHoje($cod,$tk,$diasBits[$di])) continue;
                $inf = instrutorNaData($cod,$data);
                if($inf && !empty($inf['instrutor']))
                    $ocup[mb_strtolower(trim($inf['instrutor']))] = true;
            }
        }
        $dispTurno = [];
        foreach($instrutores as $inst){
            $mask = (int)$inst['turnosTrabalho']; if($mask===0) $mask=7;
            if(!($mask & $bit_tk)) continue;
            $primeiro = mb_strtolower(explode(' ',trim($inst['nome']))[0]);
            if(!isset($ocup[$primeiro]))
                $dispTurno[] = mb_strtoupper($inst['nome']);
        }
        $orig[$tk] = $dispTurno;
    }
    echo json_encode($orig, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
?>;

/*
 * Ao clicar no th de um dia: atualiza o box "Disponíveis" do turno
 * mostrando quem está livre NAQUELE DIA ESPECÍFICO.
 * Ao clicar na coluna "Turma" (th sem data): restaura a visão da semana.
 */
function mostrarDisponiveis(th) {
    var data  = th.dataset.data;
    var turno = th.dataset.turno;
    var bits  = {manha:1, tarde:2, noite:4};
    var bit   = bits[turno] || 0;
    var ocup  = (_ocupados[turno] && _ocupados[turno][data]) ? _ocupados[turno][data] : [];
    var labels = {manha:'Manhã', tarde:'Tarde', noite:'Noite'};
    var ehFeria = _feriados[data] || null;

    var disp = _instList.filter(function(inst){
        if(!(inst.bit & bit)) return false;
        return ocup.indexOf(inst.primeiro) === -1;
    });

    var box    = document.getElementById('disp-box-'   + turno);
    var titulo = document.getElementById('disp-titulo-' + turno);
    var corpo  = document.getElementById('disp-corpo-'  + turno);
    if(!box) return;

    // Destaca o th clicado e remove destaque dos outros do mesmo turno
    document.querySelectorAll('#panel-' + turno + ' .th-clicavel').forEach(function(t){
        t.classList.remove('ativo');
    });
    th.classList.add('ativo');

    // Monta título
    var dFmt = th.querySelector('small') ? th.querySelector('small').textContent : data;
    var tituloTxt = 'Disponíveis ' + (labels[turno]||turno) + ' — ' + th.childNodes[0].textContent.trim() + ' ' + dFmt;
    if(ehFeria) tituloTxt += ' 🏖️';

    titulo.innerHTML = '<i class="fas fa-user-check mr-1"></i>' + tituloTxt;
    corpo.innerHTML = '';

    // Aviso de feriado
    if(ehFeria){
        var aviso = document.createElement('div');
        aviso.style.cssText = 'font-size:.78rem;color:#856404;background:#fff3cd;border-radius:4px;padding:3px 8px;margin-bottom:5px;';
        aviso.textContent = '🏖️ ' + ehFeria;
        corpo.appendChild(aviso);
    }

    if(disp.length === 0){
        var s = document.createElement('span');
        s.className = 'badge-disp';
        s.style.background = '#f8d7da'; s.style.color = '#721c24'; s.style.borderColor = '#f5c6cb';
        s.textContent = 'Nenhum disponível';
        corpo.appendChild(s);
    } else {
        disp.forEach(function(inst){
            var s = document.createElement('span');
            s.className   = 'badge-disp';
            s.textContent = inst.nome;
            corpo.appendChild(s);
        });
    }

    box.style.display = '';
    // Rola suavemente até o box
    box.scrollIntoView({behavior:'smooth', block:'nearest'});
}

/* ── Alertas EM ── */
function toggleAlertas(){
    document.getElementById('toggleEM').classList.toggle('aberto');
    document.getElementById('bodyEM').classList.toggle('aberto');
}

/* ── Abas de turno ── */
function trocarTurno(chave){
    document.querySelectorAll('.turno-tab').forEach(function(t){ t.classList.remove('ativo'); });
    document.querySelectorAll('.turno-panel').forEach(function(p){ p.classList.remove('ativo'); });
    document.querySelector('.turno-tab[onclick*="'+chave+'"]').classList.add('ativo');
    document.getElementById('panel-'+chave).classList.add('ativo');
}

/* ── Navegação ── */
function irParaData(btn){
    var inp = btn.closest('.nav-semana').querySelector('.input-data-ref');
    if(inp && inp.value) window.location.href = '?data=' + inp.value;
}
document.querySelectorAll('.input-data-ref').forEach(function(inp){
    inp.addEventListener('keydown', function(e){
        if(e.key==='Enter' && this.value) window.location.href = '?data=' + this.value;
    });
});

/* ── Turnos no modal ── */
document.querySelectorAll('.turno-ck-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        this.classList.toggle('sel');
        if(!this.classList.contains('sel')){
            this.style.background=''; this.style.color=''; this.style.borderColor='';
        }
    });
});

/* ── Salvar carga horária ── */
document.querySelectorAll('.btn-salvar-inst').forEach(function(btn){
    btn.addEventListener('click', function(){
        var id=this.dataset.id;
        var carga=document.querySelector('.sel-carga[data-id="'+id+'"]').value;
        var mask=0;
        document.querySelectorAll('.turno-ck-btn[data-id="'+id+'"].sel').forEach(function(b){ mask|=parseInt(b.dataset.bit); });
        if(!carga){ mostrarToast('Selecione a carga horária.','erro'); return; }
        var fd=new FormData();
        fd.append('acao','salvar_carga'); fd.append('idInstrutor',id);
        fd.append('cargaHoraria',carga); fd.append('turnosTrabalho',mask);
        fetch('painelDocentes.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(res){ mostrarToast(res.ok?'Salvo!':res.msg||'Erro.',res.ok?'sucesso':'erro'); })
            .catch(function(){ mostrarToast('Erro de comunicação.','erro'); });
    });
});

/* ── Atualizar cache de horários ── */
function atualizarCache(btn){
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Atualizando...';
    var fd = new FormData();
    fd.append('acao','atualizar_cache');
    fetch('painelDocentes.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            mostrarToast(res.msg || (res.ok ? 'Cache atualizado!' : 'Erro.'), res.ok ? 'sucesso' : 'erro');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Atualizar horários';
            if(res.ok) setTimeout(function(){ location.reload(); }, 4000);
        })
        .catch(function(){
            mostrarToast('Erro de comunicação.','erro');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Atualizar horários';
        });
}

/* ── Toast ── */
var _tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastDocentes');
    el.textContent=msg; el.className=tipo==='sucesso'?'sucesso':'erro'; el.style.display='block';
    if(_tt) clearTimeout(_tt);
    _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>