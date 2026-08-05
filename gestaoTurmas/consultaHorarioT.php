<?php
/*
 * consultaHorario.php
 */

require_once('../conexao.php');
require_once('horariosHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

if(!in_array($nivelNorm,['sup tecnica','sup pedagogica','gerencia','admin','administrator'])){
    header('location:../index.php'); exit;
}

/* ── Normalização para comparação ── */
function normalizar(string $s): string {
    $s = preg_replace('/\s+/', ' ', trim($s));
    $s = mb_strtolower($s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return $s;
}

/* ── Lista de instrutores do banco ── */
$stmtInst = $pdo->query("SELECT id, nome, apelido FROM usuarios WHERE perfil='Instrutor' ORDER BY nome");
$instrutores = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

/* ── Lê cache de horários ── */
$cacheJson = file_exists(HORARIOS_CACHE)
    ? json_decode(file_get_contents(HORARIOS_CACHE), true) : null;

/* ── Todas as turmas no cache ── */
$todasTurmasCache = [];
if($cacheJson && isset($cacheJson['dados'])){
    foreach($cacheJson['dados'] as $data => $turmas){
        foreach(array_keys($turmas) as $cod) $todasTurmasCache[$cod] = true;
    }
}
ksort($todasTurmasCache);

/* ── Tipo de turma → cor ──
   HT-I-XXXX = Técnico Semipresencial (turno "I"), identificado ANTES do HT genérico
   O padrão do código é: HT-<turno>-<numero>, turno I = semipresencial
*/
function tipoTurma(string $cod): array {
    if(preg_match('/^APP-/i',   $cod)) return ['label'=>'Aperfeiçoamento',        'cor'=>'#e65100','bg'=>'#fff3e0'];
    if(preg_match('/^AI-/i',    $cod)) return ['label'=>'Aprendizagem',           'cor'=>'#1565c0','bg'=>'#e3f2fd'];
    if(preg_match('/^HT/i', $cod) && strpos(strtoupper($cod), '-I-') !== false)
        return ['label'=>'Técnico Semipresencial', 'cor'=>'#be185d','bg'=>'#fce7f3'];
    if(preg_match('/^HT-/i',    $cod)) return ['label'=>'Técnico',                'cor'=>'#1b5e20','bg'=>'#e8f5e9'];
    if(preg_match('/^Q-/i',     $cod)) return ['label'=>'Qualificação',           'cor'=>'#6a1b9a','bg'=>'#f3e5f5'];
    if(preg_match('/^EM-/i',    $cod)) return ['label'=>'Ens. Médio',             'cor'=>'#880e4f','bg'=>'#fce4ec'];
    return                                    ['label'=>'Outro',                  'cor'=>'#37474f','bg'=>'#eceff1'];
}

/* ── Cor estável por texto (usada pra colorir por UC no modo turma) ──
   Mesmo texto sempre gera a mesma cor (hash simples → hue), então a cor
   não muda a cada recarregamento — só muda quando a UC muda. */
function hslParaHex(float $h, float $s, float $l): string {
    $h = $h / 360; $s = $s / 100; $l = $l / 100;
    if($s == 0){
        $r = $g = $b = $l;
    } else {
        $hue2rgb = function($p, $q, $t){
            if($t < 0) $t += 1;
            if($t > 1) $t -= 1;
            if($t < 1/6) return $p + ($q - $p) * 6 * $t;
            if($t < 1/2) return $q;
            if($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
            return $p;
        };
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = $hue2rgb($p, $q, $h + 1/3);
        $g = $hue2rgb($p, $q, $h);
        $b = $hue2rgb($p, $q, $h - 1/3);
    }
    return sprintf('#%02x%02x%02x', (int)round($r*255), (int)round($g*255), (int)round($b*255));
}
function corPorTexto(string $texto): array {
    $texto = trim($texto) ?: 'sem-uc';
    $hash = 0;
    for($i = 0; $i < strlen($texto); $i++){
        $hash = ($hash * 31 + ord($texto[$i])) & 0x7FFFFFFF;
    }
    $hue = $hash % 360;
    return [
        'cor' => hslParaHex($hue, 62, 33), // tom escuro/saturado: borda e texto
        'bg'  => hslParaHex($hue, 70, 93), // tom claro: fundo do card
    ];
}

/* ── Mapa de apelido normalizado → nome completo (banco) ── */
$mapaApelidos = [];
foreach($instrutores as $inst){
    if(!empty($inst['apelido'])){
        $mapaApelidos[normalizar($inst['apelido'])] = mb_strtoupper($inst['nome']);
    }
}

/* ── Monta eventos conforme filtro (AJAX) ── */
if(isset($_GET['ajax'])){
    header('Content-Type: application/json');
    $modo   = $_GET['modo'] ?? '';
    $filtro = trim($_GET['filtro'] ?? '');

    $eventos = [];
    if(!$filtro || !$cacheJson || !isset($cacheJson['dados'])){
        echo json_encode([]); exit;
    }

    if($modo === 'instrutor'){
        foreach($cacheJson['dados'] as $data => $turmas){
            foreach($turmas as $cod => $info){
                if(empty($info['instrutor'])) continue;
                if(normalizar($info['instrutor']) !== normalizar($filtro)) continue;

                $tipo = tipoTurma($cod);
                $uc   = $info['uc'] ?? '';
                $eventos[] = [
                    'id'              => md5($data.$cod),
                    'title'           => $uc ?: $cod,
                    'start'           => $data,
                    'allDay'          => true,
                    'backgroundColor' => $tipo['bg'],
                    'borderColor'     => $tipo['cor'],
                    'textColor'       => $tipo['cor'],
                    'extendedProps'   => [
                        'turma'  => $cod,
                        'uc'     => $uc,
                        'tipo'   => $tipo['label'],
                        'cor'    => $tipo['cor'],
                        'linha2' => $cod,
                    ],
                ];
            }
        }

        $nomeCompleto = null;
        $idInstrutor  = null;
        foreach($instrutores as $i){
            if(normalizar($i['apelido'] ?? '') === normalizar($filtro)){
                $nomeCompleto = $i['nome'];
                $idInstrutor  = (int)$i['id'];
                break;
            }
        }
        if($nomeCompleto){
            $bitsD = [1=>1,2=>2,3=>4,4=>8,5=>16,6=>32];
            $labT  = ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'];
            $stApp = $pdo->prepare("
                SELECT t.codigo, t.nome AS nomeTurma,
                       t.dataInicio, t.dataFim, t.diasSemana,
                       tt.turno, tt.horarioInicio, tt.horarioFim, tt.instrutor,
                       COALESCE(l.nome,'Externo') AS nomeLab
                FROM app_turmas t
                JOIN app_turma_turnos tt ON tt.idTurma = t.id
                LEFT JOIN laboratorios l ON l.idLaboratorio = tt.idLaboratorio
                WHERE t.ativo = 1 AND tt.instrutor = ?
                ORDER BY t.dataInicio
            ");
            $stApp->execute([$nomeCompleto]);
            foreach($stApp->fetchAll(PDO::FETCH_ASSOC) as $ap){
                $tipo = tipoTurma($ap['codigo']);
                $di   = new DateTime($ap['dataInicio']);
                $df   = new DateTime($ap['dataFim']);
                $dc   = clone $di;
                while($dc <= $df){
                    $dow  = (int)$dc->format('N');
                    $data = $dc->format('Y-m-d');
                    if((int)$ap['diasSemana'] & ($bitsD[$dow] ?? 0)){
                        $lbl = $labT[$ap['turno']] ?? $ap['turno'];
                        $hr  = substr($ap['horarioInicio'],0,5).' – '.substr($ap['horarioFim'],0,5);
                        $eventos[] = [
                            'id'              => 'app_'.md5($data.$ap['codigo'].$ap['turno']),
                            'title'           => $ap['nomeTurma'] ?: $ap['codigo'],
                            'start'           => $data,
                            'allDay'          => true,
                            'backgroundColor' => $tipo['bg'],
                            'borderColor'     => $tipo['cor'],
                            'textColor'       => $tipo['cor'],
                            'extendedProps'   => [
                                'turma'     => $ap['codigo'],
                                'uc'        => $ap['nomeTurma'],
                                'tipo'      => $tipo['label'],
                                'cor'       => $tipo['cor'],
                                'instrutor' => mb_strtoupper($ap['instrutor']),
                                'linha2'    => $lbl.' · '.$hr,
                                'horario'   => $hr,
                            ],
                        ];
                    }
                    $dc->modify('+1 day');
                }
            }

            $stFer = $pdo->prepare(
                "SELECT dataInicio, dataFim, descricao FROM instrutor_ferias WHERE idInstrutor = ? ORDER BY dataInicio"
            );
            $stFer->execute([$idInstrutor]);
            foreach($stFer->fetchAll(PDO::FETCH_ASSOC) as $f){
                $eventos[] = [
                    'id'              => 'fer_'.md5($idInstrutor.$f['dataInicio']),
                    'title'           => '🏖️ '.$f['descricao'],
                    'start'           => $f['dataInicio'],
                    'end'             => date('Y-m-d', strtotime($f['dataFim'].' +1 day')),
                    'allDay'          => true,
                    'backgroundColor' => '#fff3cd',
                    'borderColor'     => '#ffc107',
                    'textColor'       => '#856404',
                    'extendedProps'   => [
                        'isFerias'  => true,
                        'descricao' => $f['descricao'],
                        'de'        => date('d/m/Y', strtotime($f['dataInicio'])),
                        'ate'       => date('d/m/Y', strtotime($f['dataFim'])),
                        'tipo'      => 'Férias',
                        'cor'       => '#856404',
                        'turma'     => '',
                        'uc'        => '',
                        'linha2'    => '',
                    ],
                ];
            }
        }

    } elseif($modo === 'turma'){
        $tipo = tipoTurma($filtro);

        try {
            $stApp = $pdo->prepare("
                SELECT t.codigo, t.nome AS nomeTurma,
                       t.dataInicio, t.dataFim, t.diasSemana,
                       tt.turno, tt.horarioInicio, tt.horarioFim, tt.instrutor,
                       COALESCE(l.nome,'Externo') AS nomeLab
                FROM app_turmas t
                JOIN app_turma_turnos tt ON tt.idTurma = t.id
                LEFT JOIN laboratorios l ON l.idLaboratorio = tt.idLaboratorio
                WHERE t.ativo = 1 AND t.codigo = ?
                ORDER BY FIELD(tt.turno,'manha','tarde','noite')
            ");
            $stApp->execute([$filtro]);
            $bitsD   = [1=>1,2=>2,3=>4,4=>8,5=>16,6=>32];
            $labelsT = ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'];
            foreach($stApp->fetchAll(PDO::FETCH_ASSOC) as $ap){
                $di = new DateTime($ap['dataInicio']);
                $df = new DateTime($ap['dataFim']);
                $dc = clone $di;
                while($dc <= $df){
                    $dow  = (int)$dc->format('N');
                    $data = $dc->format('Y-m-d');
                    if((int)$ap['diasSemana'] & ($bitsD[$dow] ?? 0)){
                        $lbl = $labelsT[$ap['turno']] ?? $ap['turno'];
                        $hr  = substr($ap['horarioInicio'],0,5).' – '.substr($ap['horarioFim'],0,5);
                        $corUc = corPorTexto($ap['nomeTurma'] ?: $ap['codigo']);
                        $eventos[] = [
                            'id'              => 'app_'.md5($data.$ap['codigo'].$ap['turno']),
                            'title'           => $ap['nomeTurma'] ?: $ap['codigo'],
                            'start'           => $data,
                            'allDay'          => true,
                            'backgroundColor' => $corUc['bg'],
                            'borderColor'     => $corUc['cor'],
                            'textColor'       => $corUc['cor'],
                            'extendedProps'   => [
                                'turma'     => $ap['codigo'],
                                'uc'        => $ap['nomeTurma'],
                                'tipo'      => $tipo['label'],
                                'cor'       => $corUc['cor'],
                                'bg'        => $corUc['bg'],
                                'instrutor' => mb_strtoupper($ap['instrutor']),
                                'linha2'    => mb_strtoupper($ap['instrutor']),
                                'horario'   => $lbl.' · '.$hr,
                            ],
                        ];
                    }
                    $dc->modify('+1 day');
                }
            }
        } catch(PDOException $e){}

        foreach($cacheJson['dados'] as $data => $turmas){
            if(!isset($turmas[$filtro])) continue;
            $info     = $turmas[$filtro];
            $uc       = $info['uc'] ?? '';
            $inst     = $info['instrutor'] ?? '';
            $nomeInst = $inst ? ($mapaApelidos[normalizar($inst)] ?? mb_strtoupper($inst)) : '';
            $corUc = corPorTexto($uc ?: $filtro);
            $eventos[] = [
                'id'              => md5($data.$filtro),
                'title'           => $uc ?: $filtro,
                'start'           => $data,
                'allDay'          => true,
                'backgroundColor' => $corUc['bg'],
                'borderColor'     => $corUc['cor'],
                'textColor'       => $corUc['cor'],
                'extendedProps'   => [
                    'turma'     => $filtro,
                    'uc'        => $uc,
                    'tipo'      => $tipo['label'],
                    'cor'       => $corUc['cor'],
                    'bg'        => $corUc['bg'],
                    'instrutor' => $nomeInst,
                    'linha2'    => $nomeInst,
                ],
            ];
        }

        try {
            $stFT = $pdo->prepare("
                SELECT f.dataInicio, f.dataFim, f.descricao
                FROM turma_ferias f
                JOIN turma_ferias_turmas ft ON ft.idFerias = f.id
                WHERE ft.codigoTurma = ?
                ORDER BY f.dataInicio
            ");
            $stFT->execute([$filtro]);
            foreach($stFT->fetchAll(PDO::FETCH_ASSOC) as $f){
                $eventos[] = [
                    'id'              => 'fert_'.md5($filtro.$f['dataInicio']),
                    'title'           => '🏖️ '.$f['descricao'],
                    'start'           => $f['dataInicio'],
                    'end'             => date('Y-m-d', strtotime($f['dataFim'].' +1 day')),
                    'allDay'          => true,
                    'backgroundColor' => '#fff3cd',
                    'borderColor'     => '#ffc107',
                    'textColor'       => '#856404',
                    'extendedProps'   => [
                        'isFerias'  => true,
                        'descricao' => $f['descricao'],
                        'de'        => date('d/m/Y', strtotime($f['dataInicio'])),
                        'ate'       => date('d/m/Y', strtotime($f['dataFim'])),
                        'tipo'      => 'Férias',
                        'cor'       => '#856404',
                        'turma'     => $filtro,
                        'uc'        => '',
                        'linha2'    => '',
                    ],
                ];
            }
        } catch(PDOException $e){}
    }

    echo json_encode($eventos, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
    exit;
}

$cacheGeradoEm = $cacheJson['gerado_em'] ?? null;

$preSelModo   = in_array($_GET['modo'] ?? '', ['instrutor','turma']) ? $_GET['modo'] : '';
$preSelFiltro = trim($_GET['filtro'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Consulta de Horários</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.js"></script>
<style>
.filtro-bar{background:#fff;border:1px solid #dee2e6;border-radius:8px;
    padding:14px 16px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.filtro-bar .fg{display:flex;flex-direction:column;gap:3px;flex:1;min-width:180px;}
.filtro-bar label{font-size:.78rem;font-weight:600;color:#495057;}
.filtro-bar select,.filtro-bar input{padding:5px 10px;border:1px solid #ced4da;border-radius:4px;font-size:.85rem;}
.btn-buscar{padding:6px 20px;border-radius:4px;border:none;background:#0d6efd;color:#fff;
    font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-end;}
.btn-buscar:hover{background:#0b5ed7;}
.btn-imprimir{padding:6px 16px;border-radius:4px;border:none;background:#198754;color:#fff;
    font-size:.85rem;font-weight:600;cursor:pointer;align-self:flex-end;}
.btn-imprimir:hover{background:#157347;}
.legenda{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;}
.legenda-item{display:flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;}
.legenda-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
#calendario{background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.fc .fc-toolbar-title{font-size:1.1rem;font-weight:700;}
.fc .fc-daygrid-event{border-radius:4px;padding:1px 4px;font-size:.72rem;font-weight:600;
    cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fc .fc-day-today{background:#fffbea!important;}
.placeholder-cal{display:flex;align-items:center;justify-content:center;
    height:300px;color:#adb5bd;font-size:1rem;background:#fff;border-radius:8px;
    border:2px dashed #dee2e6;}
.badge-ferias{display:inline-block;background:#fff3cd;color:#856404;
    border:1px solid #ffc107;border-radius:4px;padding:2px 10px;
    font-size:.75rem;font-weight:700;margin-bottom:8px;}
#modalPeriodo .modal-header{background:#198754;}
#modalPeriodo .modal-title,#modalPeriodo .close{color:#fff !important;opacity:1;}
.periodo-opcoes{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;}
.btn-periodo{
    padding:10px 8px;border:2px solid #dee2e6;border-radius:6px;background:#f8f9fa;
    font-size:.82rem;font-weight:600;color:#495057;cursor:pointer;text-align:center;
    transition:all .15s;width:100%;}
.btn-periodo:hover,.btn-periodo.ativo{
    border-color:#198754;background:#d1e7dd;color:#0f5132;}
.campo-periodo{display:none;margin-bottom:12px;}
.campo-periodo.visivel{display:block;}
.campo-periodo label{font-size:.78rem;font-weight:600;color:#495057;display:block;margin-bottom:3px;}
.campo-periodo input{width:100%;padding:5px 10px;border:1px solid #ced4da;border-radius:4px;font-size:.85rem;}
.aviso-modal{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;
    padding:9px 12px;font-size:.8rem;color:#856404;}
#areaPrint{display:none;}
@media print {
    @page { size: A4 landscape; margin: 1cm 1.2cm; }
    body > *:not(#areaPrint) { display:none !important; }
    #areaPrint {
        display: block !important;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9pt;
        color: #111;
    }
    .ph { border-bottom: 2px solid #333; padding-bottom: 7px; margin-bottom: 10px; }
    .ph h2 { margin: 0 0 2px 0; font-size: 13pt; font-weight: 700; }
    .ph p  { margin: 0 0 1px 0; font-size: 8.5pt; color: #444; }
    .pa {
        background: #fff8e1 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        border: 1px solid #f9a825; border-radius: 3px; padding: 5px 10px;
        font-size: 8pt; color: #4e342e; margin-bottom: 14px; line-height: 1.5;
    }
    .pa strong { color: #bf360c; }
    .pm { page-break-inside: avoid; margin-bottom: 20px; }
    .pm-titulo {
        font-size: 11pt; font-weight: 700;
        background: #eeeeee !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        padding: 4px 8px; margin: 0 0 6px 0; border-left: 4px solid #333;
    }
    .cal-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .cal-grid th {
        background: #e0e0e0 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        border: 1px solid #bbb; padding: 3px 4px; text-align: center;
        font-size: 8pt; font-weight: 700;
    }
    .cal-grid td {
        border: 1px solid #ccc; vertical-align: top; width: 14.28%;
        height: 68px; padding: 2px 3px; font-size: 7.5pt;
    }
    .cal-grid td.vazia {
        background: #f9f9f9 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .cal-dia-num { font-weight: 700; font-size: 8pt; color: #333; display: block; margin-bottom: 2px; }
    .cal-hoje .cal-dia-num {
        color: #fff; background: #1565c0 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        border-radius: 50%; width: 16px; height: 16px; line-height: 16px;
        text-align: center; display: inline-block;
    }
    .cal-ev {
        display: block; border-radius: 2px; padding: 1px 3px; font-size: 6.5pt;
        font-weight: 700; margin-bottom: 1px; line-height: 1.3; overflow: hidden;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .cal-ev-ferias {
        background: #fff8e1 !important; color: #5d4037; border: 1px solid #f9a825;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .cal-legenda { margin-top: 6px; display: flex; gap: 10px; flex-wrap: wrap; }
    .cal-legenda-item { display: flex; align-items: center; gap: 3px; font-size: 7pt; font-weight: 600; }
    .cal-legenda-dot {
        width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Consulta de Horários</h4>
        <?php if($cacheGeradoEm): ?>
        <span class="badge badge-light border text-muted" style="font-size:.75rem">
            <i class="fas fa-sync-alt mr-1"></i>Cache: <?php echo date('d/m/Y \à\s H:i',strtotime($cacheGeradoEm)); ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="filtro-bar">
        <div class="fg" style="max-width:160px">
            <label>Consultar por</label>
            <select id="selModo" onchange="trocarModo()">
                <option value="instrutor" <?php echo $preSelModo==='instrutor'?'selected':''; ?>>Instrutor</option>
                <option value="turma"     <?php echo $preSelModo==='turma'?'selected':''; ?>>Turma</option>
            </select>
        </div>
        <div class="fg" id="fgInstrutor">
            <label>Instrutor</label>
            <select id="selInstrutor" class="form-control" style="font-size:.85rem">
                <option value="">— Selecione —</option>
                <?php foreach($instrutores as $i): ?>
                <?php if(empty($i['apelido'])) continue; ?>
                <option value="<?php echo htmlspecialchars($i['apelido']); ?>">
                    <?php echo htmlspecialchars(mb_strtoupper($i['nome'])); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg" id="fgTurma" style="display:none">
            <label>Turma</label>
            <input type="text" id="inputTurma" placeholder="Digite o código..." list="listaTurmas"
                   style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"
                   value="<?php echo htmlspecialchars($preSelFiltro); ?>">
            <datalist id="listaTurmas">
                <?php foreach(array_keys($todasTurmasCache) as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <button class="btn-buscar" onclick="buscar()">
            <i class="fas fa-search mr-1"></i>Buscar
        </button>
        <button class="btn-imprimir" id="btnImprimir" onclick="abrirModalPeriodo()" style="display:none">
            <i class="fas fa-print mr-1"></i>Imprimir Horário
        </button>
    </div>

    <div class="legenda" id="legendaInstrutor">
        <div class="legenda-item"><div class="legenda-dot" style="background:#e65100"></div>Aperfeiçoamento</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#1565c0"></div>Aprendizagem</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#be185d"></div>Técnico Semipresencial</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#1b5e20"></div>Técnico</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#6a1b9a"></div>Qualificação</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#880e4f"></div>Ens. Médio</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#37474f"></div>Outro</div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#ffc107;border-radius:2px;opacity:.8"></div>Férias
        </div>
    </div>

    <div id="placeholder" class="placeholder-cal">
        <span><i class="fas fa-search mr-2"></i>Selecione um instrutor ou turma e clique em Buscar</span>
    </div>
    <div id="calendario" style="display:none"></div>

</div>
</div>

<div class="modal fade" id="modalDetalhe" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-calendar-day mr-2"></i>Detalhes da aula</h6>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" id="modalDetalheBody"></div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Fechar</button>
    </div>
</div></div>
</div>

<div class="modal fade" id="modalPeriodo" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header" style="background:#198754;">
        <h6 class="modal-title" style="color:#fff;font-size:.92rem;">
            <i class="fas fa-print mr-2"></i>Imprimir Horário do Docente
        </h6>
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <p style="font-size:.82rem;color:#6c757d;margin-bottom:10px;">
            Selecione o período desejado:
        </p>
        <div class="periodo-opcoes">
            <button class="btn-periodo ativo" data-p="mes" onclick="selecionarPeriodo('mes')">
                <i class="fas fa-calendar-day d-block mb-1" style="font-size:1.1rem;"></i>
                Mês atual
            </button>
            <button class="btn-periodo" data-p="semestre" onclick="selecionarPeriodo('semestre')">
                <i class="fas fa-calendar-week d-block mb-1" style="font-size:1.1rem;"></i>
                Semestre atual
            </button>
            <button class="btn-periodo" data-p="ano" onclick="selecionarPeriodo('ano')">
                <i class="fas fa-calendar d-block mb-1" style="font-size:1.1rem;"></i>
                Ano atual
            </button>
            <button class="btn-periodo" data-p="livre" onclick="selecionarPeriodo('livre')">
                <i class="fas fa-sliders-h d-block mb-1" style="font-size:1.1rem;"></i>
                Período livre
            </button>
        </div>
        <div class="campo-periodo" id="campoPeriodoLivre">
            <div class="row">
                <div class="col-6">
                    <label>De</label>
                    <input type="date" id="printDe">
                </div>
                <div class="col-6">
                    <label>Até</label>
                    <input type="date" id="printAte">
                </div>
            </div>
        </div>
        <div class="aviso-modal">
            <i class="fas fa-info-circle mr-1"></i>
            O documento incluirá um <strong>aviso de versão</strong> com data e hora da impressão.
            A impressão sairá em <strong>paisagem (A4)</strong>, um mês por grade de calendário.
        </div>
    </div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-success" onclick="executarImpressao()">
            <i class="fas fa-print mr-1"></i>Imprimir / Gerar PDF
        </button>
    </div>
</div></div>
</div>

<div id="areaPrint"></div>

<script src="../js/menu.js"></script>
<script>
var _cal                = null;
var _eventosCache       = [];
var _tituloImpressao    = '';
var _modoImpressao      = '';
var _periodoSelecionado = 'mes';

function trocarModo(){
    var modo = document.getElementById('selModo').value;
    document.getElementById('fgInstrutor').style.display = modo==='instrutor' ? '' : 'none';
    document.getElementById('fgTurma').style.display     = modo==='turma'     ? '' : 'none';
    // Legenda por categoria só faz sentido no modo instrutor — no modo turma a
    // cor é por UC, sem legenda (só a cor mesmo).
    document.getElementById('legendaInstrutor').style.display = modo==='instrutor' ? '' : 'none';
    document.getElementById('btnImprimir').style.display = 'none';
}

function buscar(){
    var modo   = document.getElementById('selModo').value;
    var filtro = modo==='instrutor'
        ? document.getElementById('selInstrutor').value
        : document.getElementById('inputTurma').value.trim();

    if(!filtro){ alert('Selecione um '+(modo==='instrutor'?'instrutor':'turma')+'.'); return; }

    if(modo === 'instrutor'){
        var sel = document.getElementById('selInstrutor');
        _tituloImpressao = sel.options[sel.selectedIndex].text.trim();
    } else {
        _tituloImpressao = filtro;
    }
    _modoImpressao = modo;

    fetch('consultaHorarioT.php?ajax=1&modo='+encodeURIComponent(modo)+'&filtro='+encodeURIComponent(filtro))
        .then(function(r){ return r.json(); })
        .then(function(eventos){
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('calendario').style.display  = '';

            var dataAtual = _cal ? _cal.getDate() : null;
            if(_cal){ _cal.destroy(); _cal = null; }

            _eventosCache = eventos;

            document.getElementById('btnImprimir').style.display =
                (eventos.length > 0) ? 'inline-block' : 'none';

            _cal = new FullCalendar.Calendar(document.getElementById('calendario'), {
                locale:      'pt-br',
                initialView: 'dayGridMonth',
                initialDate: dataAtual ? dataAtual : undefined,
                height:      'auto',
                events:      eventos,
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  'dayGridMonth,listMonth'
                },
                buttonText:{ today:'Hoje', month:'Mês', listMonth:'Lista' },
                eventClick: function(info){
                    var p = info.event.extendedProps;
                    if(p.isFerias){
                        var html = '<div class="badge-ferias">🏖️ '+esc(p.descricao)+'</div>';
                        html += '<div style="font-size:.88rem;margin-bottom:3px"><strong>De:</strong> '+esc(p.de)+'</div>';
                        html += '<div style="font-size:.88rem"><strong>Até:</strong> '+esc(p.ate)+'</div>';
                        document.getElementById('modalDetalheBody').innerHTML = html;
                        $('#modalDetalhe').modal('show');
                        return;
                    }
                    var partes  = info.event.startStr.split('-');
                    var dataFmt = partes[2]+'/'+partes[1]+'/'+partes[0];
                    var cor     = p.cor;
                    var labelBadge = (_modoImpressao === 'turma' && p.uc) ? p.uc : p.tipo;
                    var html = '<span style="display:inline-block;border-radius:4px;padding:2px 10px;font-size:.75rem;font-weight:700;margin-bottom:8px;background:'+cor+'22;color:'+cor+';border:1px solid '+cor+'">'+esc(labelBadge)+'</span>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Data:</strong> '+dataFmt+'</div>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Turma:</strong> '+esc(p.turma)+'</div>';
                    if(p.uc)        html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>UC / Curso:</strong> '+esc(p.uc)+'</div>';
                    if(p.instrutor) html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Instrutor:</strong> '+esc(p.instrutor)+'</div>';
                    if(p.horario)   html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Horário:</strong> '+esc(p.horario)+'</div>';
                    document.getElementById('modalDetalheBody').innerHTML = html;
                    $('#modalDetalhe').modal('show');
                },
                eventContent: function(arg){
                    var p     = arg.event.extendedProps;
                    var turma = (p.turma || '').toUpperCase();

                    var cor, bg;
                    if(p.isFerias){
                        cor='#856404'; bg='#fff3cd';
                    } else if(_modoImpressao === 'turma'){
                        // No modo turma a cor é por UC (gerada no PHP), não por categoria.
                        cor = p.cor || '#37474f';
                        bg  = p.bg  || '#eceff1';
                    }
                    else if(/^APP-/i.test(turma))                          { cor='#e65100'; bg='#fff3e0'; }
                    else if(/^AI-/i.test(turma))                      { cor='#1565c0'; bg='#e3f2fd'; }
                    else if(/^HT/i.test(turma) && turma.indexOf('-I-') !== -1) { cor='#be185d'; bg='#fce7f3'; }
                    else if(/^HT-?/i.test(turma))                     { cor='#1b5e20'; bg='#e8f5e9'; }
                    else if(/^Q-/i.test(turma))                       { cor='#6a1b9a'; bg='#f3e5f5'; }
                    else if(/^EM-/i.test(turma))                      { cor='#880e4f'; bg='#fce4ec'; }
                    else                                               { cor='#37474f'; bg='#eceff1'; }

                    var l1  = arg.event.title || '';
                    var l2  = p.linha2 || '';
                    var div = document.createElement('div');
                    div.style.cssText = 'background:'+bg+';border:1.5px solid '+cor+';border-radius:4px;'
                        + 'color:'+cor+';line-height:1.3;overflow:hidden;padding:1px 4px;'
                        + 'width:100%;box-sizing:border-box;';
                    div.innerHTML = '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(l1)+'</div>'
                        + (l2 ? '<div style="font-size:.68rem;opacity:.9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(l2)+'</div>' : '');
                    return { domNodes: [div] };
                },
                eventDidMount: function(info){
                    var p = info.event.extendedProps;
                    info.el.style.background   = 'transparent';
                    info.el.style.border       = 'none';
                    info.el.style.boxShadow    = 'none';
                    if(p.isFerias){
                        info.el.title = '🏖️ '+p.descricao+' ('+p.de+' a '+p.ate+')';
                        return;
                    }
                    info.el.title = (p.uc?p.uc+' — ':'')+p.turma+(p.instrutor?' ('+p.instrutor+')':'')+(p.horario?' '+p.horario:'');
                }
            });
            _cal.render();
        })
        .catch(function(){ alert('Erro ao buscar dados.'); });
}

function abrirModalPeriodo(){
    var titulo = _modoImpressao === 'turma' ? 'Imprimir Horário da Turma' : 'Imprimir Horário do Docente';
    document.querySelector('#modalPeriodo .modal-title').innerHTML =
        '<i class="fas fa-print mr-2"></i>'+titulo;
    selecionarPeriodo('mes');
    $('#modalPeriodo').modal('show');
}

function selecionarPeriodo(p){
    _periodoSelecionado = p;
    document.querySelectorAll('.btn-periodo').forEach(function(b){
        b.classList.toggle('ativo', b.getAttribute('data-p') === p);
    });
    document.getElementById('campoPeriodoLivre').classList.toggle('visivel', p === 'livre');
}

function montarCalendarioMes(ano, mes0based, eventosDomes){
    var nomeDias  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    var hoje      = new Date();
    var hojeStr   = hoje.getFullYear()+'-'
                  + (hoje.getMonth()<9?'0':'')+(hoje.getMonth()+1)+'-'
                  + (hoje.getDate()<10?'0':'')+hoje.getDate();

    var evPorDia = {};
    eventosDomes.forEach(function(ev){
        var d = ev.start;
        if(!evPorDia[d]) evPorDia[d] = [];
        evPorDia[d].push(ev);
    });

    var primeiroDia   = new Date(ano, mes0based, 1);
    var ultimoDia     = new Date(ano, mes0based + 1, 0);
    var diaSemanaInicio = primeiroDia.getDay();
    var totalDias     = ultimoDia.getDate();

    var h = '<table class="cal-grid"><thead><tr>';
    nomeDias.forEach(function(d){ h += '<th>'+d+'</th>'; });
    h += '</tr></thead><tbody>';

    var dia = 1;
    var semanas = Math.ceil((diaSemanaInicio + totalDias) / 7);

    for(var s = 0; s < semanas; s++){
        h += '<tr>';
        for(var col = 0; col < 7; col++){
            var pos = s * 7 + col;
            if(pos < diaSemanaInicio || dia > totalDias){
                h += '<td class="vazia"></td>';
            } else {
                var mm  = mes0based + 1;
                var dataStr = ano+'-'+(mm<10?'0':'')+mm+'-'+(dia<10?'0':'')+dia;
                var isHoje  = (dataStr === hojeStr);
                h += '<td'+(isHoje?' class="cal-hoje"':'')+'>';
                h += '<span class="cal-dia-num">'+dia+'</span>';

                if(evPorDia[dataStr]){
                    evPorDia[dataStr].forEach(function(ev){
                        var p = ev.extendedProps;
                        if(p.isFerias){
                            h += '<span class="cal-ev cal-ev-ferias">🏖️ '+esc(p.descricao)+'</span>';
                        } else {
                            var cor = p.cor || '#333';
                            var bg  = cor+'22';
                            h += '<span class="cal-ev" style="background:'+bg+';color:'+cor+';border:1px solid '+cor+'">'
                               + esc(p.turma || ev.title || '')
                               + (p.horario ? ' · '+esc(p.horario) : (p.linha2 ? ' · '+esc(p.linha2) : ''))
                               + '</span>';
                        }
                    });
                }

                h += '</td>';
                dia++;
            }
        }
        h += '</tr>';
    }
    h += '</tbody></table>';
    return h;
}

function executarImpressao(){
    var agora = new Date();
    var ano   = agora.getFullYear();
    var mes   = agora.getMonth();
    var de, ate;

    if(_periodoSelecionado === 'mes'){
        de  = new Date(ano, mes, 1);
        ate = new Date(ano, mes + 1, 0);
    } else if(_periodoSelecionado === 'semestre'){
        if(mes < 6){ de = new Date(ano,0,1);  ate = new Date(ano,5,30); }
        else        { de = new Date(ano,6,1);  ate = new Date(ano,11,31); }
    } else if(_periodoSelecionado === 'ano'){
        de  = new Date(ano, 0, 1);
        ate = new Date(ano, 11, 31);
    } else {
        var vDe  = document.getElementById('printDe').value;
        var vAte = document.getElementById('printAte').value;
        if(!vDe || !vAte){ alert('Informe as datas de início e fim.'); return; }
        de  = new Date(vDe  + 'T00:00:00');
        ate = new Date(vAte + 'T00:00:00');
        if(de > ate){ alert('A data inicial deve ser anterior à data final.'); return; }
    }

    var evs = _eventosCache.filter(function(ev){
        var d = new Date(ev.start + 'T00:00:00');
        return d >= de && d <= ate;
    });

    var meses = [];
    var cursor = new Date(de.getFullYear(), de.getMonth(), 1);
    var fimMes = new Date(ate.getFullYear(), ate.getMonth(), 1);
    while(cursor <= fimMes){
        meses.push({ ano: cursor.getFullYear(), mes0: cursor.getMonth() });
        cursor.setMonth(cursor.getMonth() + 1);
    }

    var pad = function(n){ return n<10?'0'+n:n; };
    var nomeMeses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                     'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    var dataImpressao =
        pad(agora.getDate())+'/'+pad(agora.getMonth()+1)+'/'+agora.getFullYear()+
        ' às '+pad(agora.getHours())+':'+pad(agora.getMinutes())+':'+pad(agora.getSeconds());

    var labelPeriodo =
        pad(de.getDate())+'/'+pad(de.getMonth()+1)+'/'+de.getFullYear()+
        ' a '+
        pad(ate.getDate())+'/'+pad(ate.getMonth()+1)+'/'+ate.getFullYear();

    var labelTipo = _modoImpressao === 'turma' ? 'Turma'            : 'Docente';
    var tituloDoc = _modoImpressao === 'turma' ? 'Horário da Turma' : 'Horário do Docente';

    var h = '';

    h += '<div class="ph">';
    h += '<h2>'+esc(tituloDoc)+'</h2>';
    h += '<p><strong>'+esc(labelTipo)+':</strong> '+esc(_tituloImpressao)+'</p>';
    h += '<p><strong>Período:</strong> '+esc(labelPeriodo)+'</p>';
    h += '</div>';

    h += '<div class="pa">';
    h += '<strong>⚠️ ATENÇÃO:</strong> Sempre confira se esta é a versão mais atualizada do horário. ';
    h += 'Horários estão sujeitos a alterações a qualquer momento.<br>';
    h += '<span style="font-size:7.5pt;">Data de impressão: <strong>'+esc(dataImpressao)+'</strong></span>';
    h += '</div>';

    if(meses.length === 0 || evs.length === 0){
        h += '<p style="color:#777;font-size:9pt;">Nenhuma aula encontrada no período selecionado.</p>';
    } else {
        meses.forEach(function(m){
            var chaveM = m.ano+'-'+(m.mes0<9?'0':'')+(m.mes0+1);
            var evsMes = evs.filter(function(ev){
                return ev.start.substring(0,7) === chaveM;
            });

            h += '<div class="pm">';
            h += '<div class="pm-titulo">'+nomeMeses[m.mes0]+' de '+m.ano+'</div>';
            h += montarCalendarioMes(m.ano, m.mes0, evsMes);

            var tiposVisto = {};
            if(_modoImpressao !== 'turma'){
                evsMes.forEach(function(ev){
                    var p = ev.extendedProps;
                    if(!p.isFerias && p.tipo && p.cor){
                        tiposVisto[p.tipo] = p.cor;
                    }
                });
            }
            var tiposArr = Object.keys(tiposVisto);
            if(tiposArr.length > 0){
                h += '<div class="cal-legenda">';
                tiposArr.forEach(function(t){
                    h += '<span class="cal-legenda-item">'
                       + '<span class="cal-legenda-dot" style="background:'+tiposVisto[t]+'"></span>'
                       + esc(t)+'</span>';
                });
                var temFerias = evsMes.some(function(ev){ return ev.extendedProps.isFerias; });
                if(temFerias){
                    h += '<span class="cal-legenda-item">'
                       + '<span class="cal-legenda-dot" style="background:#ffc107;border-radius:2px;"></span>'
                       + 'Férias</span>';
                }
                h += '</div>';
            }

            h += '</div>';
        });
    }

    document.getElementById('areaPrint').innerHTML = h;
    $('#modalPeriodo').modal('hide');
    setTimeout(function(){ window.print(); }, 380);
}

function esc(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

<?php if($preSelModo && $preSelFiltro): ?>
document.addEventListener('DOMContentLoaded', function(){
    trocarModo();
    buscar();
});
<?php endif; ?>
</script>
</body>
</html>