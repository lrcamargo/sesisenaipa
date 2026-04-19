<?php
/*
 * meuHorario.php
 * Calendário mensal do instrutor logado.
 * Lê o cache de horários (horariosCache.json) e filtra pelo nome do instrutor.
 * Usa o mesmo mapa de normalização do painelDocentes para resolver
 * variações de nome entre o banco e a planilha.
 */

require_once('../conexao.php');
require_once('horariosHelper.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

// Apenas instrutores
if($nivelNorm !== 'instrutor'){
    header('location:../index.php'); exit;
}

/* ── Dados do instrutor logado ──
 * $logado já contém o nome completo vindo da sessão.
 * Não precisa buscar no banco — usa diretamente para exibição
 * e para comparar com a planilha.
 */
$nomeCompleto = mb_strtoupper(trim($logado));

/* ── Normalização para comparar com nomes curtos da planilha ── */
function normalizarNome(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) ?: $s;
    return preg_replace('/[^a-z0-9 ]/','',$s);
}

// Tokens significativos do nome completo para match com planilha
$particulas = ['de','da','do','dos','das','e','a','o'];
$tokensInst = array_filter(
    explode(' ', normalizarNome($logado)),
    fn($t) => strlen($t) >= 3 && !in_array($t, $particulas)
);

/* ── Lê cache de horários ── */
$cacheJson = file_exists(HORARIOS_CACHE)
    ? json_decode(file_get_contents(HORARIOS_CACHE), true) : null;

/* ── Tipo de turma → cor e label ── */
function tipoTurma(string $cod): array {
    if(preg_match('/^AI-/i',  $cod)) return ['label'=>'Aprendizagem', 'cor'=>'#1565c0', 'bg'=>'#e3f2fd'];
    if(preg_match('/^HT-/i',  $cod)) return ['label'=>'Técnico',      'cor'=>'#1b5e20', 'bg'=>'#e8f5e9'];
    if(preg_match('/^APP-/i', $cod)) return ['label'=>'Aperfeiçoamento','cor'=>'#e65100','bg'=>'#fff3e0'];
    if(preg_match('/^Q-/i',   $cod)) return ['label'=>'Qualificação',  'cor'=>'#6a1b9a', 'bg'=>'#f3e5f5'];
    if(preg_match('/^EM-/i',  $cod)) return ['label'=>'Ens. Médio',    'cor'=>'#880e4f', 'bg'=>'#fce4ec'];
    return                                  ['label'=>'Outro',          'cor'=>'#37474f', 'bg'=>'#eceff1'];
}

/*
 * Verifica se um nome curto da planilha corresponde ao instrutor logado.
 * Usa a mesma lógica de token do painelDocentes:
 * qualquer token significativo do nome curto presente nos tokens do instrutor.
 */
function ehEsteInstrutor(string $nomeCurto, array $tokensInst): bool {
    $tokensCurto = array_filter(
        explode(' ', normalizarNome($nomeCurto)),
        fn($t) => strlen($t) >= 3
    );
    foreach($tokensCurto as $tok){
        if(in_array($tok, $tokensInst)) return true;
    }
    return false;
}

/*
 * Extrai o turno do código da turma:
 * AI- e HT-: 4º segmento separado por hífen é M/T/N
 * EM-: usa vínculo EM↔HT para descobrir o turno da HT correspondente
 * APP- e Q-: sem turno fixo — ordem = 99 (sempre por último no dia)
 */
function turnoOrdem(string $cod, array $vinculos): int {
    // Tenta extrair turno do código (posição 3, ex: HT-MET-01-M-25)
    $partes = explode('-', $cod);
    if(isset($partes[3])){
        $letra = strtoupper($partes[3]);
        if($letra === 'M') return 1;
        if($letra === 'T') return 2;
        if($letra === 'N') return 3;
    }
    // EM: busca o código HT vinculado e extrai turno dele
    if(preg_match('/^EM-/i', $cod)){
        foreach($vinculos as $v){
            if($v['codigoSistema'] === $cod){
                $partesHT = explode('-', $v['codigoExcel']);
                if(isset($partesHT[3])){
                    $l = strtoupper($partesHT[3]);
                    if($l==='M') return 1; if($l==='T') return 2; if($l==='N') return 3;
                }
            }
        }
        return 2; // EM sem vínculo: assume tarde (SESI é normalmente tarde)
    }
    // APP, Q e outros: sempre por último
    return 99;
}

// Carrega vínculos EM↔HT para ordenação
$stmtVincCod = $pdo->query("SELECT codigoSistema, codigoExcel FROM turma_codigos_alt");
$vinculosOrdem = $stmtVincCod->fetchAll(PDO::FETCH_ASSOC);

/* ── Monta eventos para o FullCalendar ── */
$eventosBrutos = [];
if($cacheJson && isset($cacheJson['dados'])){
    foreach($cacheJson['dados'] as $data => $turmas){
        foreach($turmas as $codTurma => $info){
            if(empty($info['instrutor'])) continue;
            if(!ehEsteInstrutor($info['instrutor'], $tokensInst)) continue;

            $tipo  = tipoTurma($codTurma);
            $uc    = $info['uc'] ?? '';
            $ordem = turnoOrdem($codTurma, $vinculosOrdem);

            $eventosBrutos[] = [
                'data'   => $data,
                'cod'    => $codTurma,
                'uc'     => $uc,
                'tipo'   => $tipo,
                'ordem'  => $ordem,
            ];
        }
    }
}

// Ordena: por data, depois por ordem de turno (M=1, T=2, N=3, APP/Q=99)
usort($eventosBrutos, fn($a,$b) =>
    $a['data'] !== $b['data']
        ? strcmp($a['data'], $b['data'])
        : $a['ordem'] - $b['ordem']
);

$eventos = [];
foreach($eventosBrutos as $ev){
    $tipo   = $ev['tipo'];
    $eventos[] = [
        'id'              => md5($ev['data'].$ev['cod']),
        'title'           => $ev['cod'],
        'start'           => $ev['data'],
        'allDay'          => true,
        'backgroundColor' => $tipo['bg'],
        'borderColor'     => $tipo['cor'],
        'textColor'       => $tipo['cor'],
        'extendedProps'   => [
            'turma' => $ev['cod'],
            'uc'    => $ev['uc'],
            'tipo'  => $tipo['label'],
            'cor'   => $tipo['cor'],
        ],
    ];
}

$eventosJson   = json_encode($eventos, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$cacheGeradoEm = $cacheJson['gerado_em'] ?? null;
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
/* Aviso */
.aviso-horario{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;
    padding:10px 16px;margin-bottom:16px;font-size:.85rem;color:#856404;display:flex;
    align-items:center;gap:10px;}
.aviso-horario i{font-size:1.1rem;flex-shrink:0;}

/* Legenda */
.legenda{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.legenda-item{display:flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;}
.legenda-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}

/* Calendário */
#calendario{background:#fff;border-radius:8px;padding:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.08);}
.fc .fc-toolbar-title{font-size:1.1rem;font-weight:700;}
.fc .fc-daygrid-event{border-radius:4px;padding:1px 4px;font-size:.72rem;
    font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fc .fc-day-today{background:#fffbea!important;}

/* Modal detalhe */
#modalDetalhe .modal-header{padding:12px 16px;}
#modalDetalhe .detalhe-tipo{display:inline-block;border-radius:4px;padding:2px 10px;
    font-size:.75rem;font-weight:700;margin-bottom:8px;}
#modalDetalhe .detalhe-linha{font-size:.88rem;margin-bottom:4px;}
#modalDetalhe .detalhe-linha strong{min-width:50px;display:inline-block;}

/* Atualização do cache */
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
        <h4 class="mb-0">
            <i class="fas fa-calendar-alt mr-2"></i>Meu Horário
            <small class="text-muted" style="font-size:.65em;font-weight:400">
                — <?php echo htmlspecialchars($nomeCompleto); ?>
            </small>
        </h4>
    </div>

    <!-- Aviso -->
    <div class="aviso-horario">
        <i class="fas fa-exclamation-triangle"></i>
        <span>
            <strong>Atenção:</strong> este calendário é gerado automaticamente a partir da planilha de horários
            e pode sofrer alterações. Consulte com frequência e confirme com a coordenação em caso de dúvidas.
            <?php if($cacheGeradoEm): ?>
            <br><small><i class="fas fa-sync-alt mr-1"></i>Última atualização: <strong><?php echo date('d/m/Y \à\s H:i', strtotime($cacheGeradoEm)); ?></strong></small>
            <?php endif; ?>
        </span>
    </div>

    <!-- Legenda -->
    <div class="legenda">
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#1565c0"></div>Aprendizagem (AI)
        </div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#1b5e20"></div>Técnico (HT)
        </div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#e65100"></div>Aperfeiçoamento (APP)
        </div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#6a1b9a"></div>Qualificação (Q)
        </div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#880e4f"></div>Ens. Médio (EM)
        </div>
        <div class="legenda-item">
            <div class="legenda-dot" style="background:#37474f"></div>Outro
        </div>
    </div>

    <!-- Calendário -->
    <div id="calendario"></div>

    <?php if($cacheGeradoEm): ?>
    <div class="cache-info">
        <i class="fas fa-sync-alt mr-1"></i>
        Horários atualizados em: <?php echo date('d/m/Y \à\s H:i', strtotime($cacheGeradoEm)); ?>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Modal detalhe do evento -->
<div class="modal fade" id="modalDetalhe" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-calendar-day mr-2"></i>Detalhes da aula</h6>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" id="modalDetalheBody">
    </div>
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
        buttonText: {
            today:      'Hoje',
            month:      'Mês',
            listMonth:  'Lista'
        },
        eventClick: function(info){
            var p = info.event.extendedProps;
            var data = info.event.startStr;
            // Formata data
            var partes = data.split('-');
            var dataFmt = partes[2]+'/'+partes[1]+'/'+partes[0];

            var cor   = p.cor;
            var html  = '<span class="detalhe-tipo" style="background:'+cor+'22;color:'+cor+';border:1px solid '+cor+'">'+p.tipo+'</span>';
            html += '<div class="detalhe-linha"><strong>Data:</strong> '+dataFmt+'</div>';
            html += '<div class="detalhe-linha"><strong>Turma:</strong> '+htmlEscape(p.turma)+'</div>';
            if(p.uc){
                html += '<div class="detalhe-linha"><strong>UC:</strong> '+htmlEscape(p.uc)+'</div>';
            }

            document.getElementById('modalDetalheBody').innerHTML = html;
            $('#modalDetalhe').modal('show');
        },
        // Tooltip simples no hover
        eventContent: function(arg){
            var p   = arg.event.extendedProps;
            var div = document.createElement('div');
            div.style.cssText = 'line-height:1.3;overflow:hidden;padding:1px 2px';
            div.innerHTML = '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                + htmlEscape(arg.event.title) + '</div>'
                + (p.uc ? '<div style="font-size:.68rem;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                    + htmlEscape(p.uc) + '</div>' : '');
            return { domNodes: [div] };
        },
        eventDidMount: function(info){
            var p = info.event.extendedProps;
            info.el.title = (p.uc ? p.uc + ' — ' : '') + p.turma;
        }
    });
    cal.render();
});

function htmlEscape(str){
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}
</script>
</body>
</html>