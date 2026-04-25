<?php
/*
 * consultaHorario.php
 * Tela para supervisores consultarem o calendário de horários
 * de um instrutor específico ou de uma turma específica.
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

/* ── Normalização (mesma do painelDocentes) ── */
function normalizarNome(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s;
    return preg_replace('/[^a-z0-9 ]/','',$s);
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
    if(preg_match('/^AI-/i',  $cod)) return ['label'=>'Aprendizagem',    'cor'=>'#1565c0','bg'=>'#e3f2fd'];
    if(preg_match('/^HT-/i',  $cod)) return ['label'=>'Técnico',         'cor'=>'#1b5e20','bg'=>'#e8f5e9'];
    if(preg_match('/^APP-/i', $cod)) return ['label'=>'Aperfeiçoamento', 'cor'=>'#e65100','bg'=>'#fff3e0'];
    if(preg_match('/^Q-/i',   $cod)) return ['label'=>'Qualificação',    'cor'=>'#6a1b9a','bg'=>'#f3e5f5'];
    if(preg_match('/^EM-/i',  $cod)) return ['label'=>'Ens. Médio',      'cor'=>'#880e4f','bg'=>'#fce4ec'];
    return                                  ['label'=>'Outro',            'cor'=>'#37474f','bg'=>'#eceff1'];
}

/* ── Mapa de nomes banco → tokens (para match com planilha) ── */
$mapaInstrutores = []; // [token_normalizado] => nome completo
$particulas = ['de','da','do','dos','das','e','a','o'];
foreach($instrutores as $inst){
    $tokens = array_filter(explode(' ', normalizarNome($inst['nome'])),
        fn($t) => strlen($t)>=3 && !in_array($t,$particulas));
    // Passo 1: primeiro nome tem prioridade
    $tks = array_values($tokens);
    if(!empty($tks) && !isset($mapaInstrutores[$tks[0]]))
        $mapaInstrutores[$tks[0]] = mb_strtoupper($inst['nome']);
}
foreach($instrutores as $inst){
    $tokens = array_values(array_filter(explode(' ', normalizarNome($inst['nome'])),
        fn($t) => strlen($t)>=3 && !in_array($t,$particulas)));
    foreach(array_slice($tokens,1) as $tok){
        if(!isset($mapaInstrutores[$tok]))
            $mapaInstrutores[$tok] = mb_strtoupper($inst['nome']);
    }
}

/* ── Monta eventos conforme filtro (AJAX) ── */
if(isset($_GET['ajax'])){
    header('Content-Type: application/json');
    $modo   = $_GET['modo']   ?? '';   // 'instrutor' ou 'turma'
    $filtro = trim($_GET['filtro'] ?? '');

    $eventos = [];
    if(!$filtro || !$cacheJson || !isset($cacheJson['dados'])){
        echo json_encode([]); exit;
    }

    if($modo === 'instrutor'){
        // Filtra pelo nome do instrutor — mesma lógica de tokens
        $tokensF = array_filter(explode(' ', normalizarNome($filtro)),
            fn($t) => strlen($t)>=3 && !in_array($t,$particulas));

        foreach($cacheJson['dados'] as $data => $turmas){
            foreach($turmas as $cod => $info){
                if(empty($info['instrutor'])) continue;
                // Match: algum token do nome curto está nos tokens do instrutor buscado
                $tokensCurto = array_filter(explode(' ', normalizarNome($info['instrutor'])),
                    fn($t) => strlen($t)>=3);
                $match = false;
                foreach($tokensCurto as $tok){ if(in_array($tok,$tokensF)){$match=true;break;} }
                if(!$match) continue;

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
    } elseif($modo === 'turma'){
        foreach($cacheJson['dados'] as $data => $turmas){
            if(!isset($turmas[$filtro])) continue;
            $info = $turmas[$filtro];
            $tipo = tipoTurma($filtro);
            $uc   = $info['uc'] ?? '';
            $inst = $info['instrutor'] ?? '';
            // Resolve nome completo do instrutor
            $nomeInst = '';
            if($inst){
                $tokInst = array_filter(explode(' ', normalizarNome($inst)), fn($t)=>strlen($t)>=3);
                foreach($tokInst as $tok){
                    if(isset($mapaInstrutores[$tok])){ $nomeInst = $mapaInstrutores[$tok]; break; }
                }
                if(!$nomeInst) $nomeInst = mb_strtoupper($inst);
            }
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
                    'linha2'    => $nomeInst, // instrutor aparece como 2ª linha
                ],
            ];
        }
    }

    echo json_encode($eventos, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
    exit;
}

$cacheGeradoEm = $cacheJson['gerado_em'] ?? null;

// Parâmetros GET para pré-selecionar filtro (vindo do clique na turma do painelDocentes)
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
.cache-info{font-size:.73rem;color:#6c757d;text-align:right;margin-top:8px;}
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
                <option value="turma"     <?php echo ($preSelModo==='turma'||!$preSelModo)?'':''; ?>
                    <?php echo $preSelModo==='turma'?'selected':''; ?>>Turma</option>
            </select>
        </div>
        <div class="fg" id="fgInstrutor">
            <label>Instrutor</label>
            <select id="selInstrutor" class="form-control" style="font-size:.85rem">
                <option value="">— Selecione —</option>
                <?php foreach($instrutores as $i): ?>
                <option value="<?php echo htmlspecialchars($i['nome']); ?>">
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
        <div class="legenda-item"><div class="legenda-dot" style="background:#1565c0"></div>Aprendizagem</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#1b5e20"></div>Técnico</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#e65100"></div>Aperfeiçoamento</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#6a1b9a"></div>Qualificação</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#880e4f"></div>Ens. Médio</div>
        <div class="legenda-item"><div class="legenda-dot" style="background:#37474f"></div>Outro</div>
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
                    var partes = info.event.startStr.split('-');
                    var dataFmt = partes[2]+'/'+partes[1]+'/'+partes[0];
                    var cor = p.cor;
                    var html = '<span style="display:inline-block;border-radius:4px;padding:2px 10px;font-size:.75rem;font-weight:700;margin-bottom:8px;background:'+cor+'22;color:'+cor+';border:1px solid '+cor+'">'+esc(p.tipo)+'</span>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Data:</strong> '+dataFmt+'</div>';
                    html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Turma:</strong> '+esc(p.turma)+'</div>';
                    if(p.uc)       html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>UC:</strong> '+esc(p.uc)+'</div>';
                    if(p.instrutor)html += '<div style="font-size:.88rem;margin-bottom:4px"><strong>Instrutor:</strong> '+esc(p.instrutor)+'</div>';
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
                    info.el.title = (p.uc?p.uc+' — ':'')+p.turma+(p.instrutor?' ('+p.instrutor+')':'');
                }
            });
            _cal.render();
        })
        .catch(function(){ alert('Erro ao buscar dados.'); });
}

function esc(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Busca automática se veio com parâmetros pré-selecionados
<?php if($preSelModo && $preSelFiltro): ?>
document.addEventListener('DOMContentLoaded', function(){
    trocarModo();
    buscar();
});
<?php endif; ?>
</script>
</body>
</html>