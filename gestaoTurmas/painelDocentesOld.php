<!DOCTYPE html>
<?php
/*
 * painelDocentes.php — Painel de alocação de docentes
 * Acesso: sup tecnica, sup pedagogica, gerencia, admin
 *
 * Correções v2:
 * 1. Turmas: prioridade API (catraca) + complemento planilha (nunca remove da catraca)
 * 2. Instrutor disponível: filtra corretamente pelo bit de turno cadastrado
 * 3. Alertas EM- em dropdown separado (colapsável)
 * 4. Navegação de semana movida para abaixo dos destaques
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

/* ── Data de referência ── */
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

/* ── Bitmask turnos de trabalho ── */
const BIT_MANHA = 1;
const BIT_TARDE = 2;
const BIT_NOITE = 4;

$turnosConfig = [
    'manha' => ['label'=>'Manhã', 'bit'=>BIT_MANHA, 'icone'=>'fa-sun'],
    'tarde' => ['label'=>'Tarde', 'bit'=>BIT_TARDE, 'icone'=>'fa-cloud-sun'],
    'noite' => ['label'=>'Noite', 'bit'=>BIT_NOITE, 'icone'=>'fa-moon'],
];
$cargas = [20, 25, 30, 40];

/* ── Instrutores do banco ── */
$stmtInst = $pdo->prepare("
    SELECT id, nome, cargaHoraria, turnosTrabalho
    FROM usuarios WHERE perfil = 'Instrutor' ORDER BY nome
");
$stmtInst->execute();
$instrutores = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

/*
 * Mapa de nomes para busca flexível:
 * normaliza acentos e busca por qualquer token do nome.
 * Resolve: GABRIEL HENRIQUE NOGUEIRA DA SILVA ← "Gabriel"
 *          JOSÉ DE FARIA TOLEDO               ← "Faria" ou "Jose"
 */
function normalizarNome(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s;
    return preg_replace('/[^a-z0-9 ]/','',$s);
}

$mapaInstrutores = []; // [token_normalizado] => nome completo original
foreach($instrutores as $inst){
    $tokens = array_filter(explode(' ', normalizarNome($inst['nome'])));
    // Ignora partículas
    $particulas = ['de','da','do','dos','das','e','a','o'];
    foreach($tokens as $tok){
        if(strlen($tok) < 3 || in_array($tok,$particulas)) continue;
        if(!isset($mapaInstrutores[$tok]))
            $mapaInstrutores[$tok] = mb_strtoupper($inst['nome']);
    }
}

/* ── Vínculos EM↔HT (código sistema ↔ código Excel) ── */
$stmtVincCod = $pdo->query("SELECT codigoSistema, codigoExcel FROM turma_codigos_alt");
$vinculosCodigos = [];
foreach($stmtVincCod->fetchAll(PDO::FETCH_ASSOC) as $v){
    $vinculosCodigos[$v['codigoSistema']] = $v['codigoExcel']; // EM → HT
    $vinculosCodigos[$v['codigoExcel']]   = $v['codigoSistema']; // HT → EM
}

/* ── Feriados da semana ── */
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

/*
 * Verifica se uma turma tem feriado/recesso em uma data.
 * Nacional/municipal: aplica a todas.
 * Recesso: aplica à turma se ela estiver na lista, ou a todas se lista vazia.
 */
function temFeriado(string $codTurma, string $data): ?array {
    global $feriadosSemana, $vinculosCodigos;
    if(!isset($feriadosSemana[$data])) return null;
    $f = $feriadosSemana[$data];
    if($f['tipo'] === 'nacional' || $f['tipo'] === 'municipal') return $f;
    // Recesso
    if(empty($f['turmasRecesso'])) return $f; // aplica a todas
    // Verifica código direto e código alternativo
    $codAlt = $vinculosCodigos[$codTurma] ?? null;
    if(in_array($codTurma, $f['turmasRecesso'])) return $f;
    if($codAlt && in_array($codAlt, $f['turmasRecesso'])) return $f;
    return null;
}

/* ══════════════════════════════════════════════════════════
   TURMAS — prioridade API + complemento planilha
   Regra:
   - Base = API (catraca): nunca removemos turma vigente da catraca
   - Complemento = planilha: adiciona turmas com aula nesta semana
     que não estão na API
   - Filtra SENAI: HT-, AI-, APP-, EM-XX-O
   ══════════════════════════════════════════════════════════ */
function ehTurmaSenai(string $cod): bool {
    if(preg_match('/^(HT|AI|APP)-/i', $cod))  return true;
    if(preg_match('/^EM-\w+-O\b/i',  $cod))   return true; // EM-1A-O, EM-2B-O
    return false;
}
function ehTurmaEM(string $cod): bool {
    return (bool)preg_match('/^EM-/i', $cod);
}

/* API */
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

/* Planilha — turmas com aula nos dias desta semana */
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

/*
 * União: API é base, planilha só adiciona o que não está na API.
 * Nunca removemos o que está na API.
 */
$todasTurmas = array_keys(array_merge($turmasPlanilha, $turmasAPI));
// array_merge com API por último → API prevalece (mas como usamos keys não importa)
// Garante que turmas da API nunca sejam removidas
foreach(array_keys($turmasAPI) as $c) if(!in_array($c,$todasTurmas)) $todasTurmas[] = $c;
$todasTurmas = array_unique($todasTurmas);
sort($todasTurmas);

/* Separa EM das demais */
$turmasEM     = array_filter($todasTurmas, 'ehTurmaEM');
$turmasNaoEM  = array_filter($todasTurmas, fn($c) => !ehTurmaEM($c));

/* ── Vínculos turma→sala (principal + externos) ── */
$stmtVinc = $pdo->query("
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci AS turno
    FROM turma_sala
    UNION ALL
    SELECT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma,
           diasSemana, CONVERT(turno USING utf8mb4) COLLATE utf8mb4_general_ci AS turno
    FROM turma_sala_externa
");
$vinculos  = $stmtVinc->fetchAll(PDO::FETCH_ASSOC);
$vincIdx   = [];
foreach($vinculos as $v){
    $vincIdx[$v['codigoTurma']][$v['turno']][] = (int)$v['diasSemana'];
}

function instrutorNaData(string $cod, string $data): ?array {
    global $cacheJson;
    return $cacheJson['dados'][$data][$cod] ?? null;
}

/* Resolve nome curto da planilha para nome completo do banco via mapa normalizado */
function resolverNomeInstrutor(string $nomeCurto): string {
    global $mapaInstrutores;
    $token = normalizarNome($nomeCurto);
    return $mapaInstrutores[$token] ?? mb_strtoupper($nomeCurto);
}

function turmaTemAulaHoje(string $cod, string $turno, int $bit): bool {
    global $vincIdx;
    if(!isset($vincIdx[$cod][$turno])) return false;
    foreach($vincIdx[$cod][$turno] as $mask){
        if(($mask & $bit) > 0) return true;
    }
    return false;
}

/*
 * Extrai o turno esperado do código da turma.
 * Padrão: HT-XXX-NN-[M|T|N]-YY-NNNNN
 *   M = manhã, T = tarde, N = noite
 * Retorna 'manha', 'tarde', 'noite' ou null se não identificado.
 */
function turnoDoCodigoTurma(string $cod): ?string {
    // Padrão: XX-XXX-NN-[M|T|N]-...
    if(preg_match('/^[A-Z]+-[A-Z]+-\d+-([MTN])-/i', $cod, $m)){
        $letra = strtoupper($m[1]);
        if($letra === 'M') return 'manha';
        if($letra === 'T') return 'tarde';
        if($letra === 'N') return 'noite';
    }
    return null; // não identificado — aparece em todos
}

/*
 * Verifica se a turma deve aparecer na linha da tabela neste turno.
 *
 * Turmas com vínculo (principal OU externo): usa bitmask dos dias cadastrados.
 * Turmas sem vínculo algum (ainda não alocadas):
 *   1. Filtra pelo turno do código (M/T/N) para não aparecer em todos os turnos
 *   2. Aparece se a planilha tiver dados desta semana neste turno
 *
 * Nota: vincIdx agora inclui turma_sala_externa via UNION, portanto
 * turmas externas alocadas passam pelo mesmo caminho das turmas principais.
 */
function turmaDeveExibirNaTurno(string $cod, string $turno, array $diasSemana, array $diasBits): bool {
    global $vincIdx, $cacheJson;

    // Tem vínculo cadastrado (principal ou externo) → usa bitmask
    if(isset($vincIdx[$cod][$turno])){
        foreach($diasSemana as $di => $data){
            if(turmaTemAulaHoje($cod, $turno, $diasBits[$di])) return true;
        }
        return false;
    }

    // Sem vínculo: filtra pelo turno do código da turma
    $turnoCodigo = turnoDoCodigoTurma($cod);
    if($turnoCodigo !== null && $turnoCodigo !== $turno) return false;

    // Aparece se a planilha tiver dados nesta semana
    if($cacheJson && isset($cacheJson['dados'])){
        foreach($diasSemana as $data){
            if(isset($cacheJson['dados'][$data][$cod])) return true;
        }
    }

    return false;
}

/* ── POST: salvar carga horária ── */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');
    if($_POST['acao'] === 'salvar_carga'){
        $idInst      = intval($_POST['idInstrutor']    ?? 0);
        $carga       = intval($_POST['cargaHoraria']   ?? 0);
        $turnosMask  = intval($_POST['turnosTrabalho'] ?? 7);
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

/* ══════════════════════════════════════════════════════════
   ALERTAS
   ══════════════════════════════════════════════════════════ */
$alertasEM    = [];
$alertasOutro = [];

// Turmas que são o "outro lado" de um vínculo EM↔HT — não gerar alerta duplicado
$codigosVinculadosExcel = array_values($vinculosCodigos);

foreach($todasTurmas as $cod){
    foreach($turnosConfig as $turnoKey => $tConf){
        if(!turmaDeveExibirNaTurno($cod, $turnoKey, $diasSemana, $diasBits)) continue;

        foreach($diasSemana as $di => $data){
            $bit        = $diasBits[$di];
            $temVinculo = isset($vincIdx[$cod][$turnoKey]);

            if($temVinculo && !turmaTemAulaHoje($cod, $turnoKey, $bit)) continue;

            $info = instrutorNaData($cod, $data);
            if(!$temVinculo && !$info) continue;

            // Se é feriado/recesso para esta turma → não é inconsistência
            $feriado = temFeriado($cod, $data);
            if($feriado) continue;

            if(!$info || empty($info['instrutor'])){
                // Turma EM vinculada ao Excel: busca pelo código HT alternativo
                $codExcel = $vinculosCodigos[$cod] ?? null;
                if($codExcel){
                    $infoAlt = instrutorNaData($codExcel, $data);
                    if($infoAlt && !empty($infoAlt['instrutor'])) continue; // tem instrutor pelo código alternativo
                }

                $entry = [
                    'turma'=>$cod,'turno'=>$tConf['label'],
                    'dia'=>$diasNomes[$di].' '.date('d/m',strtotime($data)),
                    'tipo'=>'sem_docente',
                ];
                if(ehTurmaEM($cod)) $alertasEM[]    = $entry;
                else                $alertasOutro[] = $entry;
            }
        }
    }
}

/* Duplicados */
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
            // Alerta apenas se o mesmo instrutor aparecer em 3+ turmas no mesmo dia/turno
            // (até 2 turmas é permitido — ex: instrutoras externas com 2 aulas)
            if(count($turmasInst) < 3) continue;
            $entry = [
                'turma'=>implode(' + ',$turmasInst),
                'turno'=>$turnosConfig[$turnoKey]['label'],
                'dia'=>$diasNomes[$di].' '.date('d/m',strtotime($data)),
                'tipo'=>'duplicado','inst'=>mb_strtoupper($inst),
            ];
            // Classifica pelo primeiro código
            if(ehTurmaEM($turmasInst[0])) $alertasEM[]    = $entry;
            else                          $alertasOutro[] = $entry;
        }
    }
}
?>
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
/* ── Alertas críticos ── */
.alerta-critico{background:#fff5f5;border:2px solid #dc3545;border-radius:8px;padding:12px 16px;margin-bottom:10px;}
.alerta-critico h6{color:#dc3545;font-weight:700;margin-bottom:8px;font-size:.9rem;}
.badge-alerta{display:inline-block;background:#dc3545;color:#fff;border-radius:4px;padding:3px 8px;font-size:.78rem;font-weight:700;margin:2px;}
.badge-alerta.duplicado{background:#856404;color:#fff3cd;}
.badge-alerta.feriado{background:#e6a817;color:#212529;}
.alerta-em-toggle,.alerta-em-body{border-color:#e6a817!important;background:#fffbea!important;}
.alerta-em-toggle h6,.alerta-em-toggle .toggle-icon{color:#856404!important;}
.badge-alerta.em{background:#e6a817;color:#212529;}
.badge-alerta.em.duplicado{background:#c07a00;color:#fff;}

/* ── Dropdown de alertas EM (estilo filtros da imagem) ── */
.alerta-em-toggle{
    display:flex;align-items:center;justify-content:space-between;
    background:#fff5f5;border:2px solid #dc3545;border-radius:8px;
    padding:10px 16px;cursor:pointer;margin-bottom:0;user-select:none;
}
.alerta-em-toggle h6{margin:0;color:#dc3545;font-weight:700;font-size:.9rem;}
.alerta-em-toggle .toggle-icon{color:#dc3545;transition:transform .25s;}
.alerta-em-toggle.aberto .toggle-icon{transform:rotate(180deg);}
.alerta-em-body{
    display:none;
    background:#fff5f5;border:2px solid #dc3545;border-top:none;
    border-radius:0 0 8px 8px;padding:10px 16px;margin-bottom:10px;
}
.alerta-em-body.aberto{display:block;}

/* ── Instrutores disponíveis ── */
.disponivel-box{background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:10px 14px;margin-bottom:10px;}
.disponivel-box h6{color:#155724;font-weight:700;margin-bottom:6px;font-size:.9rem;}
.badge-disp{display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:4px;padding:5px 12px;font-size:.85rem;font-weight:600;margin:2px;}

/* ── Navegação de semana ── */
.nav-semana{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 0;margin-bottom:4px;}
.btn-nav{padding:5px 14px;border-radius:6px;border:1px solid #dee2e6;background:#fff;cursor:pointer;font-size:.85rem;text-decoration:none;color:#495057;}
.btn-nav:hover{background:#e9ecef;text-decoration:none;}
.semana-label{font-weight:700;font-size:.9rem;color:#343a40;}
.data-picker{display:flex;gap:6px;align-items:center;margin-left:auto;}
.data-picker input{padding:4px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.85rem;}
.data-picker button{padding:4px 12px;border-radius:4px;border:none;background:#0d6efd;color:#fff;font-size:.82rem;cursor:pointer;}

/* ── Abas de turno ── */
.turno-tabs{display:flex;gap:4px;margin-bottom:0;border-bottom:2px solid #dee2e6;margin-top:8px;}
.turno-tab{padding:8px 18px;border-radius:6px 6px 0 0;border:1px solid #dee2e6;border-bottom:none;background:#f8f9fa;cursor:pointer;font-size:.85rem;font-weight:600;color:#495057;margin-bottom:-2px;}
.turno-tab.ativo{background:#fff;border-bottom:2px solid #fff;color:#0d6efd;}
.turno-panel{display:none;padding:12px 0;}
.turno-panel.ativo{display:block;}

/* ── Tabela ── */
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

/* ── Modal carga horária ── */
.turnos-check{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;}
.turno-ck-btn{padding:6px 14px;border-radius:4px;border:1px solid #ced4da;background:#fff;font-size:.85rem;cursor:pointer;font-weight:600;transition:all .15s;}
.turno-ck-btn.sel{background:#0d6efd;color:#fff;border-color:#0d6efd;}

/* ── Toast ── */
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
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#modalCarga">
            <i class="fas fa-user-clock mr-1"></i>Turnos de trabalho
        </button>
    </div>

    <?php if(!$apiDisp){ ?>
    <div class="alert alert-warning py-2 mb-2">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        API de turmas indisponível — exibindo apenas turmas da planilha.
    </div>
    <?php } ?>

    <!-- ══ ALERTAS CRÍTICOS — outras turmas ══ -->
    <?php if(!empty($alertasOutro)){ ?>
    <div class="alerta-critico">
        <h6><i class="fas fa-exclamation-circle mr-1"></i>Atenção — ação necessária</h6>
        <?php foreach($alertasOutro as $al):
            $cls   = $al['tipo']==='duplicado' ? 'duplicado' : '';
            $icone = $al['tipo']==='duplicado' ? '⚠️' : '❌';
            $txt   = $al['tipo']==='sem_docente'
                ? "{$icone} {$al['turma']} — sem docente — {$al['turno']} {$al['dia']}"
                : "{$icone} Duplicado: {$al['inst']} em {$al['turma']} — {$al['turno']} {$al['dia']}";
        ?>
            <span class="badge-alerta <?php echo $cls; ?>"><?php echo htmlspecialchars($txt); ?></span>
        <?php endforeach; ?>
    </div>
    <?php } ?>

    <!-- ══ ALERTAS EM — dropdown colapsável ══ -->
    <?php if(!empty($alertasEM)){ ?>
    <div class="alerta-em-toggle" id="toggleEM" onclick="toggleAlertas()">
        <h6>
            <i class="fas fa-exclamation-circle mr-1"></i>
            Inconsistências — Turmas EM
            <span class="badge badge-danger ml-1"><?php echo count($alertasEM); ?></span>
        </h6>
        <i class="fas fa-chevron-down toggle-icon"></i>
    </div>
    <div class="alerta-em-body" id="bodyEM">
        <?php foreach($alertasEM as $al):
            $cls   = $al['tipo']==='duplicado' ? 'em duplicado' : 'em';
            $icone = $al['tipo']==='duplicado' ? '⚠️' : '⚠️';
            $txt   = $al['tipo']==='sem_docente'
                ? "{$icone} {$al['turma']} — sem docente — {$al['turno']} {$al['dia']}"
                : "{$icone} Duplicado: {$al['inst']} em {$al['turma']} — {$al['turno']} {$al['dia']}";
        ?>
            <span class="badge-alerta <?php echo $cls; ?>"><?php echo htmlspecialchars($txt); ?></span>
        <?php endforeach; ?>
    </div>
    <?php } ?>

    <!-- ══ ABAS DE TURNO ══ -->
    <div class="turno-tabs">
        <?php foreach($turnosConfig as $key => $t){ ?>
        <div class="turno-tab <?php echo $key==='manha'?'ativo':''; ?>"
             onclick="trocarTurno('<?php echo $key; ?>')">
            <i class="fas <?php echo $t['icone']; ?> mr-1"></i><?php echo $t['label']; ?>
        </div>
        <?php } ?>
    </div>

    <?php foreach($turnosConfig as $turnoKey => $tConf):
        $bit_turno = $tConf['bit'];

        /* Instrutores ocupados neste turno esta semana */
        $instOcupados = [];
        foreach($todasTurmas as $cod){
            foreach($diasSemana as $di => $data){
                if(!turmaTemAulaHoje($cod, $turnoKey, $diasBits[$di])) continue;
                $info = instrutorNaData($cod, $data);
                if($info && !empty($info['instrutor']))
                    $instOcupados[mb_strtolower(trim($info['instrutor']))] = true;
            }
        }

        /*
         * CORREÇÃO 2: instrutor disponível neste turno =
         *   (turnosTrabalho & bit_turno) > 0  AND  não está ocupado
         * turnosTrabalho=0 (não configurado) → usa padrão 7 (todos)
         */
        $instDisponiveis = array_filter($instrutores, function($inst) use($bit_turno, $instOcupados){
            $mask = (int)$inst['turnosTrabalho'];
            if($mask === 0) $mask = 7; // fallback: todos os turnos
            if(!($mask & $bit_turno)) return false; // não trabalha neste turno
            // Compara pelo primeiro nome (planilha usa nome curto)
            $primeiroNome = mb_strtolower(explode(' ', trim($inst['nome']))[0]);
            return !isset($instOcupados[$primeiroNome]);
        });
    ?>
    <div class="turno-panel <?php echo $turnoKey==='manha'?'ativo':''; ?>"
         id="panel-<?php echo $turnoKey; ?>">

        <!-- Navegação de semana — dentro do painel, acima da tabela -->
        <div class="nav-semana">
            <a href="?data=<?php echo $semanaAnterior; ?>" class="btn-nav">
                <i class="fas fa-chevron-left"></i> Anterior
            </a>
            <span class="semana-label">
                <?php echo date('d/m', strtotime($segSemana)); ?> –
                <?php echo date('d/m/Y', strtotime($diasSemana[5])); ?>
            </span>
            <a href="?data=<?php echo $proximaSemana; ?>" class="btn-nav">
                Próxima <i class="fas fa-chevron-right"></i>
            </a>
            <a href="?data=<?php echo $hoje; ?>" class="btn-nav">Hoje</a>
            <div class="data-picker">
                <input type="date" class="input-data-ref" value="<?php echo $dataRef; ?>">
                <button onclick="irParaData(this)">Ir</button>
            </div>
        </div>

        <!-- Instrutores disponíveis -->
        <?php if(!empty($instDisponiveis)){ ?>
        <div class="disponivel-box">
            <h6><i class="fas fa-user-check mr-1"></i>
                Disponíveis — <?php echo $tConf['label']; ?>
            </h6>
            <?php foreach($instDisponiveis as $inst){ ?>
            <span class="badge-disp"><?php echo htmlspecialchars(mb_strtoupper($inst['nome'])); ?></span>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Tabela turmas × dias -->
        <div class="tabela-wrapper">
        <table class="tabela-docentes">
            <thead>
                <tr>
                    <th>Turma</th>
                    <?php foreach($diasSemana as $di => $data){
                        $cls = ($data === $hoje) ? ' class="th-hoje"' : '';
                        echo "<th{$cls}>{$diasNomes[$di]}<br><small>".date('d/m',strtotime($data))."</small></th>";
                    } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            /* Função inline para renderizar linhas de um grupo de turmas */
            $renderLinhas = function(array $lista) use ($turnoKey, $diasSemana, $diasBits, $hoje, $diasNomes, $vinculosCodigos): void {
                foreach($lista as $cod){
                    // Exibe a linha se a turma deve aparecer neste turno
                    // (tem vínculo de sala com aula OU é turma externa com dados)
                    if(!turmaDeveExibirNaTurno($cod, $turnoKey, $diasSemana, $diasBits)) continue;

                    echo "<tr><td title='".htmlspecialchars($cod)."'>".htmlspecialchars($cod)."</td>";
                    foreach($diasSemana as $di => $data){
                        $bit   = $diasBits[$di];
                        $tdCls = ($data===$hoje) ? " class='td-hoje'" : '';

                        // Turmas com vínculo: usa bitmask para saber se tem aula neste dia
                        // Turmas externas: mostra instrutor se existir na planilha, — se não
                        $info       = instrutorNaData($cod, $data);
                        $temVinculo = isset($GLOBALS['vincIdx'][$cod][$turnoKey]);
                        $temAulaDia = $temVinculo
                            ? turmaTemAulaHoje($cod, $turnoKey, $bit)
                            : ($info !== null); // externa: tem aula se planilha tiver dado

                        if(!$temAulaDia){
                            echo "<td{$tdCls}><span class='cel-sem-aula'>—</span></td>"; continue;
                        }
                        // Verifica feriado/recesso
                        $feriado = temFeriado($cod, $data);
                        if($feriado){
                            // Feriado com instrutor alocado = inconsistência (destaque laranja)
                            $infoFer  = instrutorNaData($cod, $data);
                            $codAltF  = $vinculosCodigos[$cod] ?? null;
                            $infoAltF = $codAltF ? instrutorNaData($codAltF,$data) : null;
                            $temInst  = ($infoFer && !empty($infoFer['instrutor']))
                                     || ($infoAltF && !empty($infoAltF['instrutor']));
                            if($temInst){
                                // Feriado com instrutor = inconsistência laranja
                                echo "<td style='background:#fff3cd'><div class='cel-docente'>";
                                echo "<span style='font-size:.7rem;font-weight:700;color:#856404'>⚠️ ".htmlspecialchars($feriado['nome'])."</span>";
                                $nomeFer = resolverNomeInstrutor($infoFer['instrutor'] ?? $infoAltF['instrutor'] ?? '');
                                echo "<span class='cel-doc-nome' style='color:#856404'>".htmlspecialchars($nomeFer)."</span>";
                                echo "</div></td>";
                            } else {
                                // Feriado sem instrutor = correto
                                echo "<td style='background:#f8f9fa'><span class='cel-sem-aula' title='".htmlspecialchars($feriado['nome'])."'>🏖️</span></td>";
                            }
                            continue;
                        }
                        echo "<td{$tdCls}><div class='cel-docente'>";
                        // Resolve nome curto → nome completo via mapa normalizado
                        if($info && !empty($info['instrutor'])){
                            $nomeResolvido = resolverNomeInstrutor($info['instrutor']);
                            echo "<span class='cel-doc-nome'>".htmlspecialchars($nomeResolvido)."</span>";
                        } else {
                            // Tenta código alternativo EM↔HT
                            $codAlt = $vinculosCodigos[$cod] ?? null;
                            $infoAlt = $codAlt ? instrutorNaData($codAlt, $data) : null;
                            if($infoAlt && !empty($infoAlt['instrutor'])){
                                echo "<span class='cel-doc-nome'>".htmlspecialchars(resolverNomeInstrutor($infoAlt['instrutor']))."</span>";
                            } else {
                                echo "<span class='cel-sem-doc'>Sem docente</span>";
                            }
                        }
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
        <p class="text-muted mb-2" style="font-size:.85rem">
            Configure a carga horária e os turnos de cada instrutor.
            Instrutores fora do turno não aparecem como disponíveis naquele período.
        </p>
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Instrutor</th>
                    <th style="width:130px">Carga</th>
                    <th>Turnos de trabalho</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($instrutores as $inst):
                $mask = (int)$inst['turnosTrabalho'];
                if($mask===0) $mask=7;
            ?>
            <tr>
                <td class="align-middle">
                    <strong><?php echo htmlspecialchars(mb_strtoupper($inst['nome'])); ?></strong>
                </td>
                <td>
                    <select class="form-control form-control-sm sel-carga" data-id="<?php echo $inst['id']; ?>">
                        <option value="">—</option>
                        <?php foreach($cargas as $c){
                            $sel = ($inst['cargaHoraria']==$c) ? 'selected' : '';
                            echo "<option value='{$c}' {$sel}>{$c}h</option>";
                        } ?>
                    </select>
                </td>
                <td>
                    <div class="turnos-check">
                    <?php foreach([BIT_MANHA=>'Manhã',BIT_TARDE=>'Tarde',BIT_NOITE=>'Noite'] as $bit=>$label){
                        $sel = ($mask & $bit) ? 'sel' : '';
                        echo "<button type='button' class='turno-ck-btn {$sel}'
                                      data-bit='{$bit}' data-id='{$inst['id']}'>{$label}</button>";
                    } ?>
                    </div>
                </td>
                <td class="text-center align-middle">
                    <button class="btn btn-sm btn-primary btn-salvar-inst" data-id="<?php echo $inst['id']; ?>">
                        <i class="fas fa-save"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
    </div>
</div></div>
</div>

<div id="toastDocentes"></div>
<script src="../js/menu.js"></script>
<script>
/* ── Dropdown alertas EM ── */
function toggleAlertas(){
    var toggle = document.getElementById('toggleEM');
    var body   = document.getElementById('bodyEM');
    toggle.classList.toggle('aberto');
    body.classList.toggle('aberto');
}

/* ── Abas de turno ── */
function trocarTurno(chave){
    document.querySelectorAll('.turno-tab').forEach(function(t){ t.classList.remove('ativo'); });
    document.querySelectorAll('.turno-panel').forEach(function(p){ p.classList.remove('ativo'); });
    document.querySelector('.turno-tab[onclick*="'+chave+'"]').classList.add('ativo');
    document.getElementById('panel-'+chave).classList.add('ativo');
}

/* ── Navegação: cada painel tem seu próprio input de data ── */
function irParaData(btn){
    var inp = btn.closest('.nav-semana').querySelector('.input-data-ref');
    if(inp && inp.value) window.location.href = '?data=' + inp.value;
}
document.querySelectorAll('.input-data-ref').forEach(function(inp){
    inp.addEventListener('keydown', function(e){
        if(e.key==='Enter'){
            if(this.value) window.location.href = '?data=' + this.value;
        }
    });
});

/* ── Toggle turnos no modal ── */
document.querySelectorAll('.turno-ck-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        this.classList.toggle('sel');
        // Atualiza cor inline ao desmarcar
        if(!this.classList.contains('sel')){
            this.style.background=''; this.style.color=''; this.style.borderColor='';
        }
    });
});

/* ── Salvar carga horária ── */
document.querySelectorAll('.btn-salvar-inst').forEach(function(btn){
    btn.addEventListener('click', function(){
        var id    = this.dataset.id;
        var carga = document.querySelector('.sel-carga[data-id="'+id+'"]').value;
        var mask  = 0;
        document.querySelectorAll('.turno-ck-btn[data-id="'+id+'"].sel').forEach(function(b){
            mask |= parseInt(b.dataset.bit);
        });
        if(!carga){ mostrarToast('Selecione a carga horária.','erro'); return; }
        var fd = new FormData();
        fd.append('acao','salvar_carga');
        fd.append('idInstrutor',id);
        fd.append('cargaHoraria',carga);
        fd.append('turnosTrabalho',mask);
        fetch('painelDocentes.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(res){ mostrarToast(res.ok?'Salvo!':res.msg||'Erro.', res.ok?'sucesso':'erro'); })
            .catch(function(){ mostrarToast('Erro de comunicação.','erro'); });
    });
});

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