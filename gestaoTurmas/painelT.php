<!DOCTYPE html>
<?php
/*
 * painel.php — Painel público para TV / Raspberry Pi (Chrome kiosk)
 * Sem login. Sem scroll. Layout em linhas (uma por ambiente).
 * Mostra apenas o turno vigente. Pagina automaticamente em loop,
 * intercalando com mídias publicitárias cadastradas em cadastroMidiasPainel.php.
 * Atualiza dados a cada 5 minutos via location.reload().
 */
require_once('../conexao.php');
require_once('ocupacaoHelperT.php');
$hoje = date('Y-m-d');
//$hoje = '2026-04-07';
/* ── Turno vigente por minutos desde meia-noite ── */
// Garante o fuso horário correto independente da configuração do servidor
date_default_timezone_set('America/Sao_Paulo');
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

/* ── Mídias publicitárias ativas e dentro do período cadastrado ──
   $hoje é a mesma data usada para a ocupação (linha de teste acima);
   quando o $hoje real (date('Y-m-d')) entrar em produção, o filtro de
   período passa a valer para o dia corrente de verdade. */
$stmtMidias = $pdo->prepare("
    SELECT titulo, tipo, arquivo, duracaoSegundos
    FROM painel_midias
    WHERE ativo = 1
      AND (dataInicio IS NULL OR dataInicio <= ?)
      AND (dataFim    IS NULL OR dataFim    >= ?)
    ORDER BY ordem ASC, id ASC
");
$stmtMidias->execute([$hoje, $hoje]);
$midias = $stmtMidias->fetchAll(PDO::FETCH_ASSOC);

/* ── Notícias (manchetes via RSS, com cache em arquivo) ──
   Troque FEED_NOTICIAS pela URL do feed que preferir (ex: educação,
   um portal local, etc). Falha de rede nunca derruba o painel: em caso
   de erro, mantém o cache antigo (se existir) ou simplesmente não mostra
   a faixa de notícias. */
define('FEED_NOTICIAS', 'https://g1.globo.com/rss/g1/');
define('CACHE_NOTICIAS', __DIR__ . '/../cache/noticias.json');
define('CACHE_NOTICIAS_SEGUNDOS', 1800); // re-busca o feed no máximo a cada 30min

function buscarNoticias($feedUrl, $cacheFile, $cacheSegundos, $maxItens = 8){
    if(!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0775, true);

    if(file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheSegundos)){
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if(is_array($cached) && !empty($cached)) return $cached;
    }

    $itens = [];
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 4]]);
        $xml = @file_get_contents($feedUrl, false, $ctx);
        if($xml){
            $rss = @simplexml_load_string($xml);
            if($rss && isset($rss->channel->item)){
                foreach($rss->channel->item as $item){
                    $titulo = trim((string)$item->title);
                    if($titulo !== ''){ $itens[] = $titulo; }
                    if(count($itens) >= $maxItens) break;
                }
            }
        }
    } catch(Throwable $e){
        $itens = [];
    }

    if(!empty($itens)){
        @file_put_contents($cacheFile, json_encode($itens, JSON_UNESCAPED_UNICODE));
        return $itens;
    }
    // Busca falhou agora: usa o cache antigo, mesmo vencido, se existir
    if(file_exists($cacheFile)){
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if(is_array($cached)) return $cached;
    }
    return [];
}
$noticias = buscarNoticias(FEED_NOTICIAS, CACHE_NOTICIAS, CACHE_NOTICIAS_SEGUNDOS);
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Ocupação</title>
<style>
*{ box-sizing:border-box; margin:0; padding:0; }
html,body{
    width:100vw; height:100vh; overflow:hidden;
    background:#0d1117; color:#e6edf3;
    font-family:'Segoe UI',Arial,sans-serif;
}
.painel-root{ display:flex; flex-direction:column; height:100vh; width:100vw; }
.painel-header{
    flex-shrink:0; background:#161b22; border-bottom:2px solid #21262d;
    padding:0 28px; height:64px; display:flex; align-items:center;
    justify-content:space-between; gap:16px;
}
.header-logo  { height:32px; opacity:.9; }
.header-titulo{ font-size:clamp(1rem,1.8vw,1.3rem); font-weight:700; display:flex; align-items:center; gap:10px; }
.header-turno{
    background:#1c2a3a; border:1px solid #30363d; border-radius:20px; padding:4px 18px;
    font-size:clamp(.8rem,1.2vw,1rem); font-weight:700; color:#58a6ff; white-space:nowrap;
}
.header-clima{
    background:#1c2a3a; border:1px solid #30363d; border-radius:20px; padding:4px 16px;
    font-size:clamp(.78rem,1.1vw,.95rem); font-weight:700; color:#e6edf3; white-space:nowrap;
    display:none; align-items:center; gap:6px;
}
.header-info{ text-align:right; font-size:clamp(.7rem,1vw,.9rem); color:#8b949e; line-height:1.5; white-space:nowrap; }
.header-info strong{ color:#e6edf3; font-size:clamp(.9rem,1.3vw,1.1rem); }
.painel-paginador{ flex-shrink:0; display:flex; justify-content:center; gap:6px; padding:5px 0 3px; min-height:20px; }
.dot-pagina{ width:7px; height:7px; border-radius:50%; background:#30363d; transition:background .3s; }
.dot-pagina.ativo{ background:#58a6ff; }
.painel-body{ flex:1; min-height:0; min-width:0; overflow:hidden; padding:8px 20px 4px; }
.pagina-linhas{ display:none; height:100%; width:100%; gap:6px; }
.pagina-linhas.visivel{ display:grid; animation:fadeIn .35s ease; }
.pagina-linhas.visivel.pagina-midia{ display:flex; }
.par-col{ display:flex; flex-direction:column; gap:6px; min-width:0; }
@keyframes fadeIn{ from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:translateY(0)} }
.linha-amb{ display:flex; align-items:stretch; border-radius:8px; overflow:hidden; border:1px solid #21262d; flex:1; min-height:0; min-width:0; }
.linha-amb-nome{
    flex-shrink:0; width:clamp(120px,22vw,150px); background:#161b22;
    border-right:1px solid #21262d; display:flex; flex-direction:column;
    justify-content:center; padding:10px 16px; overflow:hidden;
}
.amb-nome-txt{ font-size:clamp(.8rem,1.3vw,1rem); font-weight:700; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#e6edf3; }
.amb-desc-txt{ font-size:clamp(.62rem,.85vw,.78rem); color:#8b949e; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.linha-amb-status{ flex:1; min-width:0; display:flex; align-items:center; padding:10px 18px; gap:14px; overflow:hidden; }
.status-textos{ flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
.status-principal{ font-size:clamp(.82rem,1.3vw,1rem); font-weight:700; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.status-secundario{ font-size:clamp(.68rem,.95vw,.82rem); font-weight:400; opacity:.75; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.status-docente{ font-size:clamp(.65rem,.9vw,.78rem); font-weight:600; opacity:.65; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.linha-amb.ocupado    { border-color:rgba(248,81,73,.35); }
.linha-amb.ocupado    .linha-amb-status{ background:rgba(248,81,73,.1); }
.linha-amb.ocupado    .status-principal { color:#ff7b72; }
.linha-amb.ocupado    .status-secundario{ color:#ff7b72; }
.linha-amb.ocupado    .status-docente   { color:#ff9a93; }
.linha-amb.confirmado { border-color:rgba(46,160,67,.5); }
.linha-amb.confirmado .linha-amb-status{ background:rgba(46,160,67,.12); }
.linha-amb.confirmado .status-principal { color:#3fb950; }
.linha-amb.confirmado .status-secundario{ color:#3fb950; }
.linha-amb.confirmado .status-docente   { color:#56d364; }
.linha-amb.aguardando { border-color:rgba(210,153,34,.35); }
.linha-amb.aguardando .linha-amb-status{ background:rgba(210,153,34,.1); }
.linha-amb.aguardando .status-principal { color:#e3b341; }
.linha-amb.aguardando .status-secundario{ color:#e3b341; }
.linha-amb.aguardando .status-docente   { color:#f0c96a; }
.linha-amb.na-sala    { border-color:rgba(63,185,80,.3); }
.linha-amb.na-sala    .linha-amb-status{ background:rgba(63,185,80,.08); }
.linha-amb.na-sala    .status-principal { color:#56d364; }
.linha-amb.na-sala    .status-secundario{ color:#56d364; }
.linha-amb.livre      { border-color:rgba(63,185,80,.2); }
.linha-amb.livre      .linha-amb-status{ background:rgba(63,185,80,.04); }
.linha-amb.livre      .status-principal { color:#3fb950; }
.linha-amb.livre      .status-secundario{ color:#3fb950; opacity:.6; }
.linha-amb.materiais  { border-color:rgba(255,165,0,.35); }
.linha-amb.materiais  .linha-amb-status{ background:rgba(255,165,0,.08); }
.linha-amb.materiais  .status-principal { color:#ffa500; }
.linha-amb.materiais  .status-secundario{ color:#ffa500; opacity:.7; }
.linha-amb.vazio      { border-color:#21262d; opacity:.55; }
.linha-amb.vazio      .linha-amb-status{ background:transparent; }
.linha-amb.vazio      .status-principal { color:#484f58; }
.painel-footer{ flex-shrink:0; position:relative; height:26px; overflow:hidden;
    color:#21262d; font-size:clamp(.55rem,.75vw,.68rem); }
.footer-padrao{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
.ticker-wrap{ position:absolute; inset:0; display:none; align-items:center; overflow:hidden;
    background:#161b22; border-top:1px solid #21262d; }
.ticker-track{ display:inline-flex; white-space:nowrap; animation:tickerMove linear infinite; }
.ticker-track span{ margin-right:48px; font-size:clamp(.66rem,.95vw,.82rem); color:#8b949e; }
@keyframes tickerMove{ from{ transform:translateX(0); } to{ transform:translateX(-50%); } }
.midia-slide{ position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center;
    background:#000; border-radius:8px; overflow:hidden; }
.midia-slide img, .midia-slide video, .midia-slide iframe{ width:100%; height:100%; object-fit:contain; border:0; }
.midia-caption{ position:absolute; bottom:14px; left:18px; background:rgba(0,0,0,.6); color:#fff;
    padding:5px 16px; border-radius:6px; font-size:clamp(.8rem,1.2vw,1rem); font-weight:600; }
</style>
</head>
<body>
<div class="painel-root">
    <div class="painel-header">
        <img src="../img/logo_white.svg" class="header-logo" alt="">
        <div class="header-titulo"><span>📋</span><span>Painel de Ocupação</span></div>
        <div class="header-turno"><?php echo $turnoIcone . ' ' . $turnoLabel; ?></div>
        <div class="header-clima" id="climaWidget">
            <span id="climaIcone">🌡️</span><span id="climaTemp">--°C</span>
        </div>
        <div class="header-info">
            <strong id="relogio">--:--</strong><br>
            <?php echo $diaNome . ', ' . date('d/m/Y'); ?>
        </div>
    </div>
    <div class="painel-paginador" id="paginador"></div>
    <div class="painel-body" id="painelBody"></div>
    <div class="painel-footer">
        <div class="footer-padrao" id="footerPadrao">
            Atualiza a cada 5 minutos &nbsp;·&nbsp; <?php echo date('d/m/Y H:i'); ?>
        </div>
        <div class="ticker-wrap" id="tickerWrap">
            <div class="ticker-track" id="tickerTrack"></div>
        </div>
    </div>
</div>
<script>
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
var MIDIAS = <?php
    $saidaMidias = [];
    foreach($midias as $m){
        // 'embed' já guarda a URL externa completa (YouTube/Canva); arquivo local
        // precisa do prefixo "../" pra resolver a partir deste arquivo.
        $enderecoFinal = $m['tipo'] === 'embed' ? $m['arquivo'] : ('../' . $m['arquivo']);
        $saidaMidias[] = [
            'titulo'          => $m['titulo'],
            'tipo'            => $m['tipo'],
            'arquivo'         => $enderecoFinal,
            'duracaoSegundos' => (int)$m['duracaoSegundos'],
        ];
    }
    echo json_encode($saidaMidias, JSON_UNESCAPED_UNICODE);
?>;
var NOTICIAS = <?php echo json_encode($noticias, JSON_UNESCAPED_UNICODE); ?>;
var SEGUNDOS = 8;
// A cada quantas páginas de ocupação entra 1 BLOCO de anúncios.
var INTERVALO_ANUNCIO = 2;
// Quantos anúncios no máximo aparecem seguidos em cada bloco (peça pediu 2 ou 3).
var ADS_POR_BLOCO = 2;
var ICONES = { ocupado:'🔴', confirmado:'🟢', aguardando:'🟡', 'na-sala':'🟢', livre:'🟢', materiais:'🟠', vazio:'⚪' };
function calcLayout(){
    var body=document.getElementById('painelBody');
    var W=body.clientWidth, H=body.clientHeight;
    var MIN_W=340, MIN_H=52, GAP=6;
    var cols=Math.max(1,Math.min(3,Math.floor((W+GAP)/(MIN_W+GAP))));
    var rows=Math.max(1,Math.floor((H+GAP)/(MIN_H+GAP)));
    return {cols:cols,rows:rows,perPage:cols*rows};
}
function criarLinha(dado){
    var el=document.createElement('div');
    el.className='linha-amb '+dado.status;
    var esq=document.createElement('div'); esq.className='linha-amb-nome';
    var nt=document.createElement('div'); nt.className='amb-nome-txt'; nt.textContent=dado.nome; esq.appendChild(nt);
    if(dado.desc){ var dt=document.createElement('div'); dt.className='amb-desc-txt'; dt.textContent=dado.desc; esq.appendChild(dt); }
    el.appendChild(esq);
    var dir=document.createElement('div'); dir.className='linha-amb-status';
    var txts=document.createElement('div'); txts.className='status-textos';
    var princ=document.createElement('div'); princ.className='status-principal'; princ.textContent=dado.turma||'—'; txts.appendChild(princ);
    if(dado.sub){ var sec=document.createElement('div'); sec.className='status-secundario'; sec.textContent=dado.sub; txts.appendChild(sec); }
    if(dado.solicitante){ var doc=document.createElement('div'); doc.className='status-docente'; doc.textContent='👤 '+dado.solicitante; txts.appendChild(doc); }
    dir.appendChild(txts); el.appendChild(dir);
    return el;
}
/* Slide de mídia. paginaIndex é a posição desse slide dentro de PAGINAS_ATUAIS.
   IMPORTANTE: aqui só monta a estrutura — vídeo e embed só de fato começam a
   tocar quando a página vira a ativa (ver ativarPagina/desativarPagina),
   senão o som tocaria em segundo plano antes da vez dele. */
function criarSlideMidia(midia, paginaIndex){
    var wrap=document.createElement('div'); wrap.className='midia-slide';
    if(midia.tipo==='video'){
        var v=document.createElement('video');
        v.src=midia.arquivo;
        v.muted=false;   // tenta com som; ver fallback em ativarPagina() se o navegador bloquear
        v.loop=false;    // toca uma vez, do início ao fim, e dispara "ended"
        v.playsInline=true;
        v.preload='metadata';
        v.addEventListener('ended', function(){
            if(paginaAtual===paginaIndex){ avancarPagina(); }
        });
        wrap.appendChild(v);
    } else if(midia.tipo==='embed'){
        // o <iframe> (YouTube/Canva) só é criado em ativarPagina(), quando
        // esta página realmente fica visível — e é removido ao sair dela.
    } else {
        var img=document.createElement('img');
        img.src=midia.arquivo; img.alt=midia.titulo||'';
        wrap.appendChild(img);
    }
    if(midia.titulo){
        var cap=document.createElement('div'); cap.className='midia-caption'; cap.textContent=midia.titulo;
        wrap.appendChild(cap);
    }
    return wrap;
}
/* Liga o que precisa tocar/carregar só quando a página vira a visível. */
function ativarPagina(idx){
    var pag = PAGINAS_ATUAIS[idx];
    if(!pag || pag.type!=='midia') return;
    var div = document.getElementById('pag-'+idx);
    if(!div) return;
    if(pag.midia.tipo==='video'){
        var v = div.querySelector('video');
        if(!v) return;
        v.currentTime = 0;
        var p = v.play();
        if(p && typeof p.catch === 'function'){
            p.catch(function(){
                // Autoplay com som bloqueado pelo navegador (comum sem o flag de kiosk
                // --autoplay-policy=no-user-gesture-required). Toca mutado como reserva.
                v.muted = true;
                v.play().catch(function(){});
            });
        }
    } else if(pag.midia.tipo==='embed'){
        var slide = div.querySelector('.midia-slide');
        if(slide && !slide.querySelector('iframe')){
            var ifr = document.createElement('iframe');
            ifr.src = pag.midia.arquivo;
            ifr.allow = 'autoplay; encrypted-media; fullscreen';
            ifr.setAttribute('frameborder','0');
            ifr.allowFullscreen = true;
            slide.insertBefore(ifr, slide.firstChild);
        }
    }
}
/* Desliga/pausa ao saída da página, pra nunca deixar áudio/vídeo tocando
   escondido enquanto outra página (ocupação ou outro anúncio) está visível. */
function desativarPagina(idx){
    var pag = PAGINAS_ATUAIS[idx];
    if(!pag || pag.type!=='midia') return;
    var div = document.getElementById('pag-'+idx);
    if(!div) return;
    if(pag.midia.tipo==='video'){
        var v = div.querySelector('video');
        if(v) v.pause();
    } else if(pag.midia.tipo==='embed'){
        var slide = div.querySelector('.midia-slide');
        var ifr = slide && slide.querySelector('iframe');
        // Em embed externo (YouTube/Canva) não dá pra só "pausar" por JS (outra
        // origem) — remover o <iframe> de fato é o jeito confiável de garantir
        // que o som/vídeo pare ao sair da página.
        if(ifr) ifr.remove();
    }
}
function montarPaginas(){
    var layout=calcLayout(), perPage=layout.perPage, total=LINHAS.length;
    var paginasOcup=[];
    for(var i=0;i<total;i+=perPage){
        paginasOcup.push({type:'ocupacao', cols:layout.cols, rows:layout.rows, linhas:LINHAS.slice(i,i+perPage)});
    }
    if(paginasOcup.length===0){
        paginasOcup=[{type:'ocupacao', cols:layout.cols, rows:layout.rows, linhas:[]}];
    }
    if(!MIDIAS.length) return paginasOcup;

    var resultado=[], midiaIdx=0, blocoInserido=false;
    paginasOcup.forEach(function(p,i){
        resultado.push(p);
        if((i+1)%INTERVALO_ANUNCIO===0){
            for(var b=0; b<ADS_POR_BLOCO && b<MIDIAS.length; b++){
                resultado.push({type:'midia', midia:MIDIAS[midiaIdx % MIDIAS.length]});
                midiaIdx++;
            }
            blocoInserido=true;
        }
    });
    // garante pelo menos 1 bloco de anúncio mesmo com poucas páginas de ocupação
    if(!blocoInserido){
        for(var b=0; b<ADS_POR_BLOCO && b<MIDIAS.length; b++){
            resultado.push({type:'midia', midia:MIDIAS[midiaIdx % MIDIAS.length]});
            midiaIdx++;
        }
    }
    return resultado;
}
var PAGINAS_ATUAIS=[];
function renderPainel(){
    var body=document.getElementById('painelBody'), paginador=document.getElementById('paginador');
    var paginas=montarPaginas();
    body.innerHTML=''; paginador.innerHTML='';
    paginas.forEach(function(pag,pi){
        var div=document.createElement('div');
        div.className='pagina-linhas'+(pi===0?' visivel':'');
        div.id='pag-'+pi;
        if(pag.type==='midia'){
            div.classList.add('pagina-midia');
            div.appendChild(criarSlideMidia(pag.midia, pi));
        } else {
            div.style.gridTemplateColumns='repeat('+pag.cols+',1fr)';
            for(var c=0;c<pag.cols;c++){
                var colDiv=document.createElement('div'); colDiv.className='par-col';
                for(var r=0;r<pag.rows;r++){
                    var idx=c*pag.rows+r;
                    if(idx<pag.linhas.length){ colDiv.appendChild(criarLinha(pag.linhas[idx])); }
                    else { var v=document.createElement('div'); v.className='linha-amb vazio'; v.style.visibility='hidden'; colDiv.appendChild(v); }
                }
                div.appendChild(colDiv);
            }
        }
        body.appendChild(div);
        if(paginas.length>1){ var dot=document.createElement('div'); dot.className='dot-pagina'+(pi===0?' ativo':''); dot.id='dot-'+pi; paginador.appendChild(dot); }
    });
    PAGINAS_ATUAIS=paginas;
    return paginas.length;
}
var paginaAtual=0, totalPaginas=1, timerPagina=null;
function duracaoPagina(idx){
    var p=PAGINAS_ATUAIS[idx];
    if(p && p.type==='midia' && p.midia && p.midia.duracaoSegundos){ return p.midia.duracaoSegundos*1000; }
    return SEGUNDOS*1000;
}
function agendarProxima(){
    if(timerPagina) clearTimeout(timerPagina);
    if(totalPaginas<=1) return;
    var pag=PAGINAS_ATUAIS[paginaAtual];
    if(pag && pag.type==='midia' && pag.midia.tipo==='video'){
        // Vídeo avança sozinho pelo evento "ended" (toca a duração real dele).
        // Este timeout é só uma rede de segurança pro caso (raro) do vídeo nunca
        // disparar "ended" — por isso é bem generoso (10min), pra nunca cortar
        // um vídeo legítimo no meio. Se seus vídeos passarem disso, aumente aqui.
        timerPagina = setTimeout(avancarPagina, 10*60*1000);
        return;
    }
    timerPagina = setTimeout(avancarPagina, duracaoPagina(paginaAtual));
}
function avancarPagina(){
    if(totalPaginas<=1) return;
    var ant=paginaAtual; paginaAtual=(paginaAtual+1)%totalPaginas;
    var eAnt=document.getElementById('pag-'+ant), eNov=document.getElementById('pag-'+paginaAtual);
    var dAnt=document.getElementById('dot-'+ant), dNov=document.getElementById('dot-'+paginaAtual);
    if(eAnt) eAnt.classList.remove('visivel');
    if(eNov) eNov.classList.add('visivel');
    if(dAnt) dAnt.classList.remove('ativo');
    if(dNov) dNov.classList.add('ativo');
    desativarPagina(ant);
    ativarPagina(paginaAtual);
    agendarProxima();
}
function iniciarPaginacao(total){ totalPaginas=total; agendarProxima(); }
function atualizarRelogio(){ var d=new Date(),h=String(d.getHours()).padStart(2,'0'),m=String(d.getMinutes()).padStart(2,'0'); var el=document.getElementById('relogio'); if(el) el.textContent=h+':'+m; }
setInterval(atualizarRelogio,1000); atualizarRelogio();
setTimeout(function(){ location.reload(); },5*60*1000);

/* ── Clima (Open-Meteo, sem chave de API) ──
   Coordenadas de Pouso Alegre, MG — troque se a escola for em outra cidade. */
var CLIMA_LAT = -22.2295, CLIMA_LON = -45.9364;
var CODIGOS_CLIMA = {
    0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',
    51:'🌦️',53:'🌦️',55:'🌧️',61:'🌧️',63:'🌧️',65:'🌧️',
    71:'🌨️',73:'🌨️',75:'❄️',80:'🌦️',81:'🌧️',82:'⛈️',
    95:'⛈️',96:'⛈️',99:'⛈️'
};
function carregarClima(){
    var url = 'https://api.open-meteo.com/v1/forecast?latitude='+CLIMA_LAT+'&longitude='+CLIMA_LON
        + '&current=temperature_2m,weather_code&timezone=America%2FSao_Paulo';
    fetch(url).then(function(r){ return r.json(); }).then(function(data){
        var cur = data && data.current;
        if(!cur) return;
        var icone = CODIGOS_CLIMA[cur.weather_code] || '🌡️';
        document.getElementById('climaIcone').textContent = icone;
        document.getElementById('climaTemp').textContent = Math.round(cur.temperature_2m) + '°C';
        document.getElementById('climaWidget').style.display = 'flex';
    }).catch(function(){ /* sem internet/erro: mantém o selo de clima escondido */ });
}
carregarClima();
setInterval(carregarClima, 15*60*1000);

/* ── Faixa de notícias (gerada no PHP a partir do RSS, com cache) ── */
if(NOTICIAS && NOTICIAS.length){
    var track = document.getElementById('tickerTrack');
    // duplica a lista pra a faixa rolar em loop contínuo sem deixar buraco
    NOTICIAS.concat(NOTICIAS).forEach(function(titulo){
        var s = document.createElement('span'); s.textContent = '📰 ' + titulo;
        track.appendChild(s);
    });
    document.getElementById('tickerWrap').style.display = 'flex';
    document.getElementById('footerPadrao').style.display = 'none';
    // Velocidade constante em pixels/segundo — antes a faixa tinha um tempo
    // FIXO (50s) pra percorrer a distância, então quanto mais notícias vinham
    // do feed, mais rápido ela tinha que rodar pra caber no mesmo tempo.
    // Calculando a duração a partir da largura real, a velocidade de leitura
    // fica sempre a mesma, com 1, 5 ou 20 manchetes. Ajuste aqui se quiser
    // mais rápido (número maior) ou mais lento (número menor).
    var TICKER_PX_POR_SEGUNDO = 55;
    requestAnimationFrame(function(){
        var larguraUmaCopia = track.scrollWidth / 2; // track tem 2 cópias coladas
        var duracao = Math.max(15, larguraUmaCopia / TICKER_PX_POR_SEGUNDO);
        track.style.animationDuration = duracao + 's';
    });
}

var resizeTimer=null;
window.addEventListener('resize',function(){ if(resizeTimer) clearTimeout(resizeTimer); resizeTimer=setTimeout(function(){ if(timerPagina) clearTimeout(timerPagina); init(); },300); });
function init(){ paginaAtual=0; var t=renderPainel(); iniciarPaginacao(t); ativarPagina(0); }
/* Watchdog de layout: em alguns kiosks (Chrome/Raspberry Pi) a janela ainda
   está se ajustando ao tamanho final no momento do "load", então a medição
   inicial pode sair errada. Espera dois frames antes da 1ª medição e depois
   reconfere periodicamente; se o layout calculado mudar, refaz a paginação. */
var _ultimoLayout = null;
function layoutMudou(){
    var l = calcLayout();
    var mudou = !_ultimoLayout || l.cols!==_ultimoLayout.cols || l.rows!==_ultimoLayout.rows;
    _ultimoLayout = l;
    return mudou;
}
window.addEventListener('load', function(){
    requestAnimationFrame(function(){ requestAnimationFrame(function(){
        layoutMudou(); // só registra a referência inicial, sem forçar re-render
        init();
        setInterval(function(){
            var pagAtual = PAGINAS_ATUAIS[paginaAtual];
            var tocandoMidia = pagAtual && pagAtual.type==='midia'
                && (pagAtual.midia.tipo==='video' || pagAtual.midia.tipo==='embed');
            // Nunca interrompe um vídeo/embed em reprodução — só reage à mudança
            // de layout numa página de ocupação ou imagem, pra não cortar mídia.
            if(!tocandoMidia && layoutMudou()){
                if(timerPagina) clearTimeout(timerPagina);
                init();
            }
        }, 30000);
    }); });
});
</script>
</body>
</html>