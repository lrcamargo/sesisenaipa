<!DOCTYPE html>
<?php
/*
 * painel.php — Painel público para TV / Raspberry Pi (Chrome kiosk)
 * Sem login. Sem scroll. Layout em linhas (uma por ambiente).
 * Mostra apenas o turno vigente. Pagina automaticamente em loop.
 * Atualiza dados a cada 5 minutos via location.reload().
 */

require_once('../conexao.php');
require_once('ocupacaoHelper.php');

$hoje = date('Y-m-d');

/* ── Turno vigente por minutos desde meia-noite ── */
$horaMin = (int)date('H') * 60 + (int)date('i');

if($horaMin <= (12*60+20)){
    $turnoKey   = 'manha';
    $turnoLabel = 'Manhã';
    $turnoIcone = '☀️';
} elseif($horaMin <= (17*60+30)){
    $turnoKey   = 'tarde';
    $turnoLabel = 'Tarde';
    $turnoIcone = '🌤️';
} else {
    $turnoKey   = 'noite';
    $turnoLabel = 'Noite';
    $turnoIcone = '🌙';
}

$turnos = [
    'manha' => ['inicio'=>'07:00:00','fim'=>'12:20:00'],
    'tarde' => ['inicio'=>'13:00:00','fim'=>'17:30:00'],
    'noite' => ['inicio'=>'18:00:00','fim'=>'22:30:00'],
];

/* ── Ambientes (labs + salas) ── */
$stmtAmb = $pdo->prepare("
    SELECT idLaboratorio, nome, descricao, temReserva, temSala
    FROM laboratorios
    WHERE temReserva=1 OR temSala=1
    ORDER BY temReserva DESC, nome ASC
");
$stmtAmb->execute();
$ambientes = $stmtAmb->fetchAll(PDO::FETCH_ASSOC);

/* ── Calcula ocupação via helper (inclui solicitante) ── */
$ocupacao = calcularOcupacao($pdo, $hoje, $ambientes, $turnos);

/* ── Monta linhas apenas para o turno vigente ── */
$linhas = [];
foreach($ambientes as $amb){
    $id  = $amb['idLaboratorio'];
    $ocp = $ocupacao[$id][$turnoKey] ?? ['status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''];
    $linhas[] = [
        'nome'        => $amb['nome'],
        'desc'        => trim($amb['descricao'] ?? ''),
        'temSala'     => (bool)$amb['temSala'],
        'status'      => $ocp['status'],
        'turma'       => $ocp['turma'],
        'sub'         => $ocp['sub'],
        'solicitante' => $ocp['solicitante'],
    ];
}

$diaNome = [
    'Sunday'   =>'Domingo','Monday'   =>'Segunda-feira',
    'Tuesday'  =>'Terça-feira','Wednesday'=>'Quarta-feira',
    'Thursday' =>'Quinta-feira','Friday'   =>'Sexta-feira',
    'Saturday' =>'Sábado',
][date('l')] ?? '';
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Ocupação</title>
<style>
/* ══════════════════════════════════════════
   BASE — sem scroll, ocupa 100vh
   ══════════════════════════════════════════ */
*{ box-sizing:border-box; margin:0; padding:0; }
html,body{
    width:100vw; height:100vh; overflow:hidden;
    background:#0d1117; color:#e6edf3;
    font-family:'Segoe UI',Arial,sans-serif;
}

/* ══════════════════════════════════════════
   ESTRUTURA PRINCIPAL
   ══════════════════════════════════════════ */
.painel-root{
    display:flex; flex-direction:column;
    height:100vh; width:100vw;
}

/* ── Cabeçalho ── */
.painel-header{
    flex-shrink:0;
    background:#161b22;
    border-bottom:2px solid #21262d;
    padding:0 28px; height:64px;
    display:flex; align-items:center;
    justify-content:space-between; gap:16px;
}
.header-logo  { height:32px; opacity:.9; }
.header-titulo{
    font-size:clamp(1rem,1.8vw,1.3rem);
    font-weight:700;
    display:flex; align-items:center; gap:10px;
}
.header-turno{
    background:#1c2a3a; border:1px solid #30363d;
    border-radius:20px; padding:4px 18px;
    font-size:clamp(.8rem,1.2vw,1rem);
    font-weight:700; color:#58a6ff; white-space:nowrap;
}
.header-info{
    text-align:right;
    font-size:clamp(.7rem,1vw,.9rem);
    color:#8b949e; line-height:1.5; white-space:nowrap;
}
.header-info strong{ color:#e6edf3; font-size:clamp(.9rem,1.3vw,1.1rem); }

/* ── Dots de paginação ── */
.painel-paginador{
    flex-shrink:0; display:flex;
    justify-content:center; gap:6px;
    padding:5px 0 3px; min-height:20px;
}
.dot-pagina{ width:7px; height:7px; border-radius:50%; background:#30363d; transition:background .3s; }
.dot-pagina.ativo{ background:#58a6ff; }

/* ── Área de conteúdo ── */
.painel-body{
    flex:1; min-height:0; overflow:hidden;
    padding:8px 20px 4px;
}

/* ── Página: grid de N colunas de pares ── */
.pagina-linhas{
    display:none;
    height:100%;
    gap:6px;
    /* grid-template-columns definido pelo JS conforme largura disponível */
}
.pagina-linhas.visivel{
    display:grid;
    animation:fadeIn .35s ease;
}
/* Cada "par" é uma coluna: nome + status lado a lado */
.par-col{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:0;
}
@keyframes fadeIn{
    from{opacity:0; transform:translateY(5px)}
    to  {opacity:1; transform:translateY(0)  }
}

/* ══════════════════════════════════════════
   LINHA DE AMBIENTE
   ══════════════════════════════════════════ */
.linha-amb{
    display:flex; align-items:stretch;
    border-radius:8px; overflow:hidden;
    border:1px solid #21262d;
    flex:1;           /* divide o espaço igualmente */
    min-height:0;     /* não extrapola */
}

/* Coluna esquerda — nome do ambiente */
.linha-amb-nome{
    flex-shrink:0;
    width:clamp(120px, 22vw, 150px);
    background:#161b22;
    border-right:1px solid #21262d;
    display:flex; flex-direction:column;
    justify-content:center;
    padding:10px 16px;
    overflow:hidden;
}
.amb-nome-txt{
    font-size:clamp(.8rem,1.3vw,1rem);
    font-weight:700; line-height:1.2;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    color:#e6edf3;
}
.amb-desc-txt{
    font-size:clamp(.62rem,.85vw,.78rem);
    color:#8b949e; margin-top:3px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}

/* Coluna direita — status de ocupação */
.linha-amb-status{
    flex:1; display:flex; align-items:center;
    padding:10px 18px; gap:14px; overflow:hidden;
}

/* Ícone de status 
.status-icon{
    flex-shrink:0;
    font-size:clamp(1.2rem,2vw,1.6rem);
    line-height:1;
}*/

/* Textos de status */
.status-textos{
    flex:1; min-width:0;
    display:flex; flex-direction:column; gap:2px;
}
.status-principal{
    font-size:clamp(.82rem,1.3vw,1rem);
    font-weight:700; line-height:1.2;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.status-secundario{
    font-size:clamp(.68rem,.95vw,.82rem);
    font-weight:400; opacity:.75;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.status-docente{
    font-size:clamp(.65rem,.9vw,.78rem);
    font-weight:600; opacity:.65;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}

/* ── Cores por status ── */
/* Ocupado */
.linha-amb.ocupado    { border-color:rgba(248,81,73,.35); }
.linha-amb.ocupado    .linha-amb-status{ background:rgba(248,81,73,.1); }
.linha-amb.ocupado    .status-principal { color:#ff7b72; }
.linha-amb.ocupado    .status-secundario{ color:#ff7b72; }
.linha-amb.ocupado    .status-docente   { color:#ff9a93; }

/* Aguardando */
.linha-amb.aguardando { border-color:rgba(210,153,34,.35); }
.linha-amb.aguardando .linha-amb-status{ background:rgba(210,153,34,.1); }
.linha-amb.aguardando .status-principal { color:#e3b341; }
.linha-amb.aguardando .status-secundario{ color:#e3b341; }
.linha-amb.aguardando .status-docente   { color:#f0c96a; }

/* Na sala (turma na sala originária) */
.linha-amb.na-sala    { border-color:rgba(63,185,80,.3); }
.linha-amb.na-sala    .linha-amb-status{ background:rgba(63,185,80,.08); }
.linha-amb.na-sala    .status-principal { color:#56d364; }
.linha-amb.na-sala    .status-secundario{ color:#56d364; }

/* Livre (turma no laboratório → sala vazia) */
.linha-amb.livre      { border-color:rgba(63,185,80,.2); }
.linha-amb.livre      .linha-amb-status{ background:rgba(63,185,80,.04); }
.linha-amb.livre      .status-principal { color:#3fb950; }
.linha-amb.livre      .status-secundario{ color:#3fb950; opacity:.6; }

/* Vazio */
.linha-amb.vazio      { border-color:#21262d; opacity:.55; }
.linha-amb.vazio      .linha-amb-status{ background:transparent; }
.linha-amb.vazio      .status-principal { color:#484f58; }

/* Separador de seção (labs vs salas) */
.secao-sep{
    flex-shrink:0;
    font-size:clamp(.62rem,.85vw,.75rem);
    font-weight:700; letter-spacing:.1em;
    text-transform:uppercase; color:#30363d;
    padding:2px 4px;
}

/* ── Rodapé ── */
.painel-footer{
    flex-shrink:0; text-align:center;
    padding:3px 16px 6px;
    color:#21262d; font-size:clamp(.55rem,.75vw,.68rem);
}
</style>
</head>
<body>
<div class="painel-root">

    <!-- Cabeçalho -->
    <div class="painel-header">
        <img src="../img/logo_white.svg" class="header-logo" alt="">
        <div class="header-titulo">
            <span>📋</span><span>Painel de Ocupação</span>
        </div>
        <div class="header-turno">
            <?php echo $turnoIcone . ' ' . $turnoLabel; ?>
        </div>
        <div class="header-info">
            <strong id="relogio">--:--</strong><br>
            <?php echo $diaNome . ', ' . date('d/m/Y'); ?>
        </div>
    </div>

    <!-- Dots -->
    <div class="painel-paginador" id="paginador"></div>

    <!-- Corpo -->
    <div class="painel-body" id="painelBody"></div>

    <!-- Rodapé -->
    <div class="painel-footer">
        Atualiza a cada 5 minutos &nbsp;·&nbsp; <?php echo date('d/m/Y H:i'); ?>
    </div>

</div>

<script>
/* ══════════════════════════════════════════
   DADOS INJETADOS PELO PHP
   ══════════════════════════════════════════ */
var LINHAS = <?php
    $saida = [];
    foreach($linhas as $l){
        $saida[] = [
            'nome'        => $l['nome'],
            'desc'        => $l['desc'],
            'temSala'     => $l['temSala'],
            'status'      => $l['status'],
            'turma'       => $l['turma'],
            'sub'         => $l['sub'],
            'solicitante' => $l['solicitante'],
        ];
    }
    echo json_encode($saida, JSON_UNESCAPED_UNICODE);
?>;

/* Segundos por página antes de virar */
var SEGUNDOS = 8;

/* Ícones por status */
var ICONES = {
    ocupado   : '🔴',
    aguardando: '🟡',
    'na-sala' : '🟢',
    livre     : '🟢',
    vazio     : '⚪',
};

/* ══════════════════════════════════════════
   CALCULA LAYOUT: quantas colunas de pares
   e quantas linhas por coluna cabem na tela
   ══════════════════════════════════════════ */
function calcLayout(){
    var body  = document.getElementById('painelBody');
    var W     = body.clientWidth;
    var H     = body.clientHeight;
    var MIN_W = 340;   /* largura mínima de cada par (nome+status) */
    var MIN_H = 52;    /* altura mínima de cada linha */
    var GAP   = 6;

    var cols = Math.max(1, Math.min(3, Math.floor((W + GAP) / (MIN_W + GAP))));
    var rows = Math.max(1, Math.floor((H + GAP) / (MIN_H + GAP)));
    return { cols: cols, rows: rows, perPage: cols * rows };
}

/* ══════════════════════════════════════════
   CRIA ELEMENTO DE UMA LINHA
   ══════════════════════════════════════════ */
function criarLinha(dado){
    var el = document.createElement('div');
    el.className = 'linha-amb ' + dado.status;

    /* Coluna esquerda — nome */
    var esq = document.createElement('div');
    esq.className = 'linha-amb-nome';

    var nt = document.createElement('div');
    nt.className = 'amb-nome-txt';
    nt.textContent = dado.nome;
    esq.appendChild(nt);

    if(dado.desc){
        var dt = document.createElement('div');
        dt.className = 'amb-desc-txt';
        dt.textContent = dado.desc;
        esq.appendChild(dt);
    }
    el.appendChild(esq);

    /* Coluna direita — status */
    var dir = document.createElement('div');
    dir.className = 'linha-amb-status';

    var txts = document.createElement('div');
    txts.className = 'status-textos';

    /* Linha principal: turma ou "Livre" ou "—" */
    var princ = document.createElement('div');
    princ.className = 'status-principal';
    princ.textContent = dado.turma || '—';
    txts.appendChild(princ);

    /* Linha secundária: sub (ex: "Reservado", "Na sala") */
    if(dado.sub){
        var sec = document.createElement('div');
        sec.className = 'status-secundario';
        sec.textContent = dado.sub;
        txts.appendChild(sec);
    }

    /* Docente (solicitante) — só para reservas */
    if(dado.solicitante){
        var doc = document.createElement('div');
        doc.className = 'status-docente';
        doc.textContent = '👤 ' + dado.solicitante;
        txts.appendChild(doc);
    }

    dir.appendChild(txts);
    el.appendChild(dir);
    return el;
}

/* ══════════════════════════════════════════
   RENDERIZA O PAINEL EM PÁGINAS
   Grid de N colunas de pares: cada coluna
   recebe rows linhas distribuídas em ordem.
   ══════════════════════════════════════════ */
function renderPainel(){
    var body      = document.getElementById('painelBody');
    var paginador = document.getElementById('paginador');
    var layout    = calcLayout();
    var cols      = layout.cols;
    var rows      = layout.rows;
    var perPage   = layout.perPage;
    var total     = LINHAS.length;

    /* Divide em páginas */
    var paginas = [];
    for(var i = 0; i < total; i += perPage){
        paginas.push(LINHAS.slice(i, i + perPage));
    }
    if(paginas.length === 0) paginas = [[]];

    body.innerHTML      = '';
    paginador.innerHTML = '';

    paginas.forEach(function(pag, pi){
        var div = document.createElement('div');
        div.className = 'pagina-linhas' + (pi === 0 ? ' visivel' : '');
        div.id        = 'pag-' + pi;
        /* Define as colunas do grid dinamicamente */
        div.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';

        /* Cria uma div por coluna e distribui as linhas */
        for(var c = 0; c < cols; c++){
            var colDiv = document.createElement('div');
            colDiv.className = 'par-col';
            for(var r = 0; r < rows; r++){
                var idx = c * rows + r;
                if(idx < pag.length){
                    colDiv.appendChild(criarLinha(pag[idx]));
                } else {
                    /* Espaço vazio para manter o grid alinhado */
                    var vazio = document.createElement('div');
                    vazio.className = 'linha-amb vazio';
                    vazio.style.visibility = 'hidden';
                    colDiv.appendChild(vazio);
                }
            }
            div.appendChild(colDiv);
        }

        body.appendChild(div);

        if(paginas.length > 1){
            var dot = document.createElement('div');
            dot.className = 'dot-pagina' + (pi === 0 ? ' ativo' : '');
            dot.id        = 'dot-' + pi;
            paginador.appendChild(dot);
        }
    });

    return paginas.length;
}

/* ══════════════════════════════════════════
   PAGINAÇÃO AUTOMÁTICA EM LOOP
   ══════════════════════════════════════════ */
var paginaAtual  = 0;
var totalPaginas = 1;
var timerPagina  = null;

function avancarPagina(){
    if(totalPaginas <= 1) return;
    var ant = paginaAtual;
    paginaAtual = (paginaAtual + 1) % totalPaginas;

    var eAnt = document.getElementById('pag-'  + ant);
    var eNov = document.getElementById('pag-'  + paginaAtual);
    var dAnt = document.getElementById('dot-'  + ant);
    var dNov = document.getElementById('dot-'  + paginaAtual);

    if(eAnt) eAnt.classList.remove('visivel');
    if(eNov) eNov.classList.add('visivel');
    if(dAnt) dAnt.classList.remove('ativo');
    if(dNov) dNov.classList.add('ativo');
}

function iniciarPaginacao(total){
    totalPaginas = total;
    if(timerPagina) clearInterval(timerPagina);
    if(total > 1) timerPagina = setInterval(avancarPagina, SEGUNDOS * 1000);
}

/* ══════════════════════════════════════════
   RELÓGIO
   ══════════════════════════════════════════ */
function atualizarRelogio(){
    var d = new Date();
    var h = String(d.getHours()).padStart(2,'0');
    var m = String(d.getMinutes()).padStart(2,'0');
    var el = document.getElementById('relogio');
    if(el) el.textContent = h + ':' + m;
}
setInterval(atualizarRelogio, 1000);
atualizarRelogio();

/* Recarrega dados a cada 5 minutos */
setTimeout(function(){ location.reload(); }, 5 * 60 * 1000);

/* Reinicia ao redimensionar */
var resizeTimer = null;
window.addEventListener('resize', function(){
    if(resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function(){
        if(timerPagina) clearInterval(timerPagina);
        init();
    }, 300);
});

function init(){
    paginaAtual = 0;
    var t = renderPainel();
    iniciarPaginacao(t);
}
window.addEventListener('load', init);
</script>
</body>
</html>