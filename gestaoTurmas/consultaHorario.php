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

/* ── Tipo de turma → cor ── */
function tipoTurma(string $cod): array {
    if(preg_match('/^APP-/i', $cod)) return ['label'=>'Aperfeiçoamento', 'cor'=>'#e65100','bg'=>'#fff3e0'];
    if(preg_match('/^AI-/i',  $cod)) return ['label'=>'Aprendizagem',    'cor'=>'#1565c0','bg'=>'#e3f2fd'];
    if(preg_match('/^HT-/i',  $cod)) return ['label'=>'Técnico',         'cor'=>'#1b5e20','bg'=>'#e8f5e9'];
    if(preg_match('/^Q-/i',   $cod)) return ['label'=>'Qualificação',    'cor'=>'#6a1b9a','bg'=>'#f3e5f5'];
    if(preg_match('/^EM-/i',  $cod)) return ['label'=>'Ens. Médio',      'cor'=>'#880e4f','bg'=>'#fce4ec'];
    return                                  ['label'=>'Outro',            'cor'=>'#37474f','bg'=>'#eceff1'];
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

        // APP manuais: busca pelo nome completo do instrutor
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

            // Férias pelo id do instrutor
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

        // APP manual
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
                                'linha2'    => mb_strtoupper($ap['instrutor']),
                                'horario'   => $lbl.' · '.$hr,
                            ],
                        ];
                    }
                    $dc->modify('+1 day');
                }
            }
        } catch(PDOException $e){}

        // Planilha
        foreach($cacheJson['dados'] as $data => $turmas){
            if(!isset($turmas[$filtro])) continue;
            $info     = $turmas[$filtro];
            $uc       = $info['uc'] ?? '';
            $inst     = $info['instrutor'] ?? '';
            $nomeInst = $inst ? ($mapaApelidos[normalizar($inst)] ?? mb_strtoupper($inst)) : '';
            $eventos[] = [
                'id'              => md5($data.$filtro),
                'title'           => $uc ?: $filtro,
                'start'           => $data,
                'allDay'          => true,
                'backgroundColor' => $tipo['bg'],
                'borderColor'     => $tipo['cor'],
                'textColor'       => $tipo['cor'],
                'extendedProps'   => [
                    'turma'     => $filtro,
                    'uc'        => $uc,
                    'tipo'      => $tipo['label'],
                    'cor'       => $tipo['cor'],
                    'instrutor' => $nomeInst,
                    'linha2'    => $nomeInst,
                ],
            ];
        }

        // Férias da turma
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

    <!-- Filtro -->
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
    </div>

    <!-- Legenda -->
    <div class="legenda">
        <div class="legenda-item"><div class="legenda-dot" style="background:#e65100"></div>Aperfeiçoamento</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#1565c0"></div>Aprendizagem</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#1b5e20"></div>Técnico</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#6a1b9a"></div>Qualificação</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#880e4f"></div>Ens. Médio</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#37474f"></div>Outro</div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#ffc107;border-radius:2px;opacity:.8"></div>Férias
        </div>
    </div>

    <!-- Calendário ou placeholder -->
    <div id="placeholder" class="placeholder-cal">
        <span><i class="fas fa-search mr-2"></i>Selecione um instrutor ou turma e clique em Buscar</span>
    </div>
    <div id="calendario" style="display:none"></div>

</div>
</div>

<!-- Modal detalhe -->
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

<script src="../js/menu.js"></script>
<script>
var _cal = null;

function trocarModo(){
    var modo = document.getElementById('selModo').value;
    document.getElementById('fgInstrutor').style.display = modo==='instrutor' ? '' : 'none';
    document.getElementById('fgTurma').style.display     = modo==='turma'     ? '' : 'none';
}

function buscar(){
    var modo   = document.getElementById('selModo').value;
    var filtro = modo==='instrutor'
        ? document.getElementById('selInstrutor').value
        : document.getElementById('inputTurma').value.trim();

    if(!filtro){ alert('Selecione um '+(modo==='instrutor'?'instrutor':'turma')+'.'); return; }

    fetch('consultaHorario.php?ajax=1&modo='+encodeURIComponent(modo)+'&filtro='+encodeURIComponent(filtro))
        .then(function(r){ return r.json(); })
        .then(function(eventos){
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('calendario').style.display  = '';

            if(_cal){ _cal.destroy(); _cal = null; }

            _cal = new FullCalendar.Calendar(document.getElementById('calendario'), {
                locale:      'pt-br',
                initialView: 'dayGridMonth',
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

                    var partes = info.event.startStr.split('-');
                    var dataFmt = partes[2]+'/'+partes[1]+'/'+partes[0];
                    var cor = p.cor;
                    var html = '<span style="display:inline-block;border-radius:4px;padding:2px 10px;font-size:.75rem;font-weight:700;margin-bottom:8px;background:'+cor+'22;color:'+cor+';border:1px solid '+cor+'">'+esc(p.tipo)+'</span>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Data:</strong> '+dataFmt+'</div>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Turma:</strong> '+esc(p.turma)+'</div>';
                    if(p.uc)        html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>UC / Curso:</strong> '+esc(p.uc)+'</div>';
                    if(p.instrutor) html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Instrutor:</strong> '+esc(p.instrutor)+'</div>';
                    if(p.horario)   html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Horário:</strong> '+esc(p.horario)+'</div>';
                    document.getElementById('modalDetalheBody').innerHTML = html;
                    $('#modalDetalhe').modal('show');
                },
                eventContent: function(arg){
                    var p   = arg.event.extendedProps;
                    var l1  = arg.event.title || '';
                    var l2  = p.linha2 || '';
                    var div = document.createElement('div');
                    div.style.cssText = 'line-height:1.3;overflow:hidden;padding:1px 2px';
                    div.innerHTML = '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(l1)+'</div>'
                        + (l2 ? '<div style="font-size:.68rem;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(l2)+'</div>' : '');
                    return { domNodes: [div] };
                },
                eventDidMount: function(info){
                    var p = info.event.extendedProps;
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