<?php
/*
 * meuHorario.php
 * Calendário do instrutor logado.
 * Usa EXATAMENTE a mesma lógica do consultaHorario:
 *   1. Planilha → match pelo APELIDO (normalizado)
 *   2. APP manuais → match pelo NOME COMPLETO (banco)
 *   3. Férias → match pelo ID do instrutor
 */

require_once('../conexao.php');
require_once('horariosHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

if($nivelNorm !== 'instrutor'){
    header('location:../index.php'); exit;
}

/* ── Normalização — idêntica ao consultaHorario ── */
function normalizar(string $s): string {
    $s = preg_replace('/\s+/', ' ', trim($s));
    $s = mb_strtolower($s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return $s;
}

/* ── Busca o instrutor logado pelo nome de login ── */
// O $_SESSION['user'] contém o nome completo do instrutor
$instRow = null;
try {
    // Tenta match exato pelo nome
    $st = $pdo->prepare("SELECT id, nome, apelido FROM usuarios WHERE perfil='Instrutor' AND UPPER(nome) = UPPER(?) LIMIT 1");
    $st->execute([trim($logado)]);
    $instRow = $st->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e){}

if(!$instRow){
    // Fallback: busca por LIKE no nome
    try {
        $st = $pdo->prepare("SELECT id, nome, apelido FROM usuarios WHERE perfil='Instrutor' AND UPPER(nome) LIKE UPPER(?) LIMIT 1");
        $st->execute(['%'.trim($logado).'%']);
        $instRow = $st->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e){}
}

// O filtro para a planilha é o APELIDO do instrutor (igual ao consultaHorario)
$apelido = $instRow ? ($instRow['apelido'] ?: '') : '';

/* ── Cache de horários ── */
$cacheJson = file_exists(HORARIOS_CACHE)
    ? json_decode(file_get_contents(HORARIOS_CACHE), true) : null;

/* ── Tipo de turma → cor ── */
function tipoTurma(string $cod): array {
    if(preg_match('/^APP-/i', $cod)) return ['label'=>'Aperfeiçoamento','cor'=>'#e65100','bg'=>'#fff3e0'];
    if(preg_match('/^AI-/i',  $cod)) return ['label'=>'Aprendizagem',  'cor'=>'#1565c0', 'bg'=>'#e3f2fd'];
    if(preg_match('/^HT-/i',  $cod)) return ['label'=>'Técnico',       'cor'=>'#1b5e20', 'bg'=>'#e8f5e9'];
    if(preg_match('/^Q-/i',   $cod)) return ['label'=>'Qualificação',  'cor'=>'#6a1b9a', 'bg'=>'#f3e5f5'];
    if(preg_match('/^EM-/i',  $cod)) return ['label'=>'Ens. Médio',    'cor'=>'#880e4f', 'bg'=>'#fce4ec'];
    return                                  ['label'=>'Outro',          'cor'=>'#37474f', 'bg'=>'#eceff1'];
}

$eventos = [];

/* ════════════════════════════════════════════════
   FONTE 1 — Planilha (match pelo apelido)
   Idêntico ao consultaHorario modo instrutor
   ════════════════════════════════════════════════ */
if($apelido && $cacheJson && isset($cacheJson['dados'])){
    foreach($cacheJson['dados'] as $data => $turmas){
        foreach($turmas as $cod => $info){
            if(empty($info['instrutor'])) continue;
            if(normalizar($info['instrutor']) !== normalizar($apelido)) continue;
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
}

/* ════════════════════════════════════════════════
   FONTE 2 — APP manuais (match pelo nome completo)
   Idêntico ao consultaHorario modo instrutor
   ════════════════════════════════════════════════ */
if($instRow){
    try {
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
        $stApp->execute([$instRow['nome']]);
        $bitsD   = [1=>1,2=>2,3=>4,4=>8,5=>16,6=>32];
        $labelsT = ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'];
        foreach($stApp->fetchAll(PDO::FETCH_ASSOC) as $ap){
            $tipo = tipoTurma($ap['codigo']);
            $di   = new DateTime($ap['dataInicio']);
            $df   = new DateTime($ap['dataFim']);
            $dc   = clone $di;
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
                            'turma'  => $ap['codigo'],
                            'uc'     => $ap['nomeTurma'],
                            'tipo'   => $tipo['label'],
                            'cor'    => $tipo['cor'],
                            'linha2' => $lbl.' · '.$hr,
                            'horario'=> $hr,
                        ],
                    ];
                }
                $dc->modify('+1 day');
            }
        }
    } catch(PDOException $e){}
}

/* ════════════════════════════════════════════════
   FONTE 3 — Férias (match pelo id)
   Idêntico ao consultaHorario modo instrutor
   ════════════════════════════════════════════════ */
if($instRow){
    try {
        $stFer = $pdo->prepare(
            "SELECT dataInicio, dataFim, descricao FROM instrutor_ferias WHERE idInstrutor = ? ORDER BY dataInicio"
        );
        $stFer->execute([$instRow['id']]);
        foreach($stFer->fetchAll(PDO::FETCH_ASSOC) as $f){
            $eventos[] = [
                'id'              => 'fer_'.md5($instRow['id'].$f['dataInicio']),
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
    } catch(PDOException $e){}
}

$eventosJson   = json_encode($eventos, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$cacheGeradoEm = $cacheJson['gerado_em'] ?? null;
$nomeCompleto  = $instRow ? mb_strtoupper($instRow['nome']) : mb_strtoupper($logado);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meu Horário</title>
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
.aviso-horario{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;
    padding:10px 16px;margin-bottom:16px;font-size:.85rem;color:#856404;display:flex;
    align-items:center;gap:10px;}
.aviso-horario i{font-size:1.1rem;flex-shrink:0;}
.legenda{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.legenda-item{display:flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;}
.legenda-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
#calendario{background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.fc .fc-toolbar-title{font-size:1.1rem;font-weight:700;}
.fc .fc-daygrid-event{border-radius:4px;padding:1px 4px;font-size:.72rem;font-weight:600;
    cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fc .fc-day-today{background:#fffbea!important;}
.detalhe-tipo{display:inline-block;border-radius:4px;padding:2px 10px;
    font-size:.75rem;font-weight:700;margin-bottom:8px;}
.detalhe-linha{font-size:.88rem;margin-bottom:4px;}
.badge-ferias{display:inline-block;background:#fff3cd;color:#856404;
    border:1px solid #ffc107;border-radius:4px;padding:2px 10px;
    font-size:.75rem;font-weight:700;margin-bottom:8px;}
.cache-info{font-size:.73rem;color:#6c757d;text-align:right;margin-top:8px;}
</style>
</head>
<body>
<div class="wrapper">
<div class="header"><div class="header-menu">
    <div class="title"><img src="../img/logo_white.svg"></div>
    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
    <ul>
        <li><a href="#" class="user"><?php echo htmlspecialchars($logado); ?></a></li>
        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
    </ul>
</div></div>
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="fas fa-calendar-alt mr-2"></i>Meu Horário
        <small class="text-muted" style="font-size:.65em;font-weight:400">
            — <?php echo htmlspecialchars($nomeCompleto); ?>
        </small>
    </h4>
</div>

<?php if(!$instRow || !$apelido): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    Instrutor não encontrado ou sem apelido cadastrado. Peça ao administrador para configurar seu cadastro.
</div>
<?php endif; ?>

<div class="aviso-horario">
    <i class="fas fa-exclamation-triangle"></i>
    <span>
        <strong>Atenção:</strong> este calendário é gerado automaticamente a partir da planilha de horários
        e pode sofrer alterações. Consulte com frequência e confirme com a coordenação em caso de dúvidas.
        <?php if($cacheGeradoEm): ?>
        <br><small><i class="fas fa-sync-alt mr-1"></i>Última atualização:
        <strong><?php echo date('d/m/Y \à\s H:i', strtotime($cacheGeradoEm)); ?></strong></small>
        <?php endif; ?>
    </span>
</div>

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

<div id="calendario"></div>

<?php if($cacheGeradoEm): ?>
<div class="cache-info">
    <i class="fas fa-sync-alt mr-1"></i>
    Horários atualizados em: <?php echo date('d/m/Y \à\s H:i', strtotime($cacheGeradoEm)); ?>
</div>
<?php endif; ?>

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
var _eventos = <?php echo $eventosJson; ?>;

document.addEventListener('DOMContentLoaded', function(){
    var cal = new FullCalendar.Calendar(document.getElementById('calendario'), {
        locale:      'pt-br',
        initialView: 'dayGridMonth',
        height:      'auto',
        events:      _eventos,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,listMonth'
        },
        buttonText:{ today:'Hoje', month:'Mês', listMonth:'Lista' },

        eventClick: function(info){
            var p    = info.event.extendedProps;
            var html = '';
            if(p.isFerias){
                html += '<div class="badge-ferias">🏖️ '+esc(p.descricao)+'</div>';
                html += '<div class="detalhe-linha"><strong>De:</strong> '+esc(p.de)+'</div>';
                html += '<div class="detalhe-linha"><strong>Até:</strong> '+esc(p.ate)+'</div>';
                document.getElementById('modalDetalheBody').innerHTML = html;
                $('#modalDetalhe').modal('show');
                return;
            }
            var partes  = info.event.startStr.split('-');
            var dataFmt = partes[2]+'/'+partes[1]+'/'+partes[0];
            var cor     = p.cor;
            html += '<span class="detalhe-tipo" style="background:'+cor+'22;color:'+cor+';border:1px solid '+cor+'">'+esc(p.tipo)+'</span>';
            html += '<div class="detalhe-linha"><strong>Data:</strong> '+dataFmt+'</div>';
            html += '<div class="detalhe-linha"><strong>Turma:</strong> '+esc(p.turma)+'</div>';
            if(p.uc)      html += '<div class="detalhe-linha"><strong>UC / Curso:</strong> '+esc(p.uc)+'</div>';
            if(p.horario) html += '<div class="detalhe-linha"><strong>Horário:</strong> '+esc(p.horario)+'</div>';
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
                +(l2?'<div style="font-size:.68rem;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(l2)+'</div>':'');
            return { domNodes: [div] };
        },

        eventDidMount: function(info){
            var p = info.event.extendedProps;
            if(p.isFerias){
                info.el.title = '🏖️ '+p.descricao+' ('+p.de+' a '+p.ate+')';
                return;
            }
            info.el.title = (p.uc?p.uc+' — ':'')+p.turma+(p.horario?' '+p.horario:'');
        }
    });
    cal.render();
});

function esc(s){
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>