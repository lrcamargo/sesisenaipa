<?php
/*
 * plantaViewer.php — Planta IoT ao vivo
 * Planta ocupa a tela toda. Clique na zona → modal grande com controles.
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

define('PLANTA_JSON', '/var/www/html/data/planta.json');
define('ESTADO_JSON', '/var/www/html/data/iot_estado.json');

/* ── AJAX estado ── */
if(isset($_GET['ajax_estado'])){
    header('Content-Type: application/json');
    echo file_exists(ESTADO_JSON) ? file_get_contents(ESTADO_JSON) : '{}';
    exit;
}

/* ── POST comando ── */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='comando'){
    ob_clean(); header('Content-Type: application/json');
    $tid=trim($_POST['topico_id']??''); $payload=trim($_POST['payload']??'');
    if(!$tid||!$payload){ echo json_encode(['ok'=>false]); exit; }
    shell_exec(sprintf('mosquitto_pub -h localhost -p 1883 -t %s -m %s 2>&1',
        escapeshellarg('/'.$tid.'/comando'), escapeshellarg($payload)));
    try { $pdo->prepare("INSERT INTO iot_comandos (topico_id,comando,payload,enviado_por) VALUES (?,?,?,?)")
             ->execute([$tid,$payload,$payload,$_SESSION['user']]); } catch(Exception $e){}
    echo json_encode(['ok'=>true]); exit;
}

/* ── Dados ── */
function lerPlanta(): array {
    if(!file_exists(PLANTA_JSON)) return ['pisos'=>[]];
    $raw=json_decode(file_get_contents(PLANTA_JSON),true)?:[];
    if(isset($raw['zonas'])&&!isset($raw['pisos']))
        return ['pisos'=>[['id'=>1,'nome'=>'Piso 1','imagem'=>$raw['imagem']??null,'zonas'=>$raw['zonas']??[]]]];
    return $raw;
}

$planta  = lerPlanta();
$pisos   = $planta['pisos'] ?? [];
$estado  = file_exists(ESTADO_JSON) ? (json_decode(file_get_contents(ESTADO_JSON),true)?:[]) : [];
$macMap  = [];
foreach($estado as $d) if($d['mac']??null) $macMap[strtoupper($d['mac'])]=$d;

$labsIoT=[];
foreach($pdo->query("SELECT idLaboratorio,nome,descricao,macPorta,macArCondicionado FROM laboratorios")->fetchAll(PDO::FETCH_ASSOC) as $l)
    $labsIoT[$l['idLaboratorio']]=$l;

$nomesCracha=[];
try {
    $pdoIot=new PDO("mysql:host=localhost;dbname=cadastroiot;charset=utf8mb4","root","BdP@25!",
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach($pdoIot->query("SELECT cracha,nome FROM cadastro")->fetchAll(PDO::FETCH_ASSOC) as $c)
        $nomesCracha[$c['cracha']]=$c['nome'];
} catch(Exception $e){}

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
$ehAdmin   = in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia']);
$pisosJson       = json_encode($pisos,   JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
$estadoJson      = json_encode($estado,  JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
$labsIoTJson     = json_encode($labsIoT, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
$nomesCrachaJson = json_encode($nomesCracha, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Planta IoT</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
/* ── Layout tela cheia ── */
.main-container{padding:0!important;}
.planta-header{display:flex;align-items:center;justify-content:space-between;
    padding:8px 16px;background:#fff;border-bottom:1px solid #dee2e6;
    position:sticky;top:0;z-index:100;}
.planta-header h4{margin:0;font-size:.95rem;font-weight:700;}

/* ── Abas piso ── */
.piso-tabs{display:flex;gap:0;border-bottom:2px solid #dee2e6;padding:0 16px;background:#f8f9fa;}
.piso-tab{padding:8px 20px;cursor:pointer;font-size:.83rem;font-weight:600;
    color:#6c757d;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s;}
.piso-tab:hover{color:#343a40;}
.piso-tab.ativo{color:#0d6efd;border-bottom-color:#0d6efd;}

/* ── Canvas da planta ── */
.planta-canvas{position:relative;width:100%;background:#e9e9e9;overflow:hidden;}
.planta-canvas img{display:block;width:100%;height:auto;}
#vlayer{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;}

/* ── Zonas ── */
.zona-vw{position:absolute;box-sizing:border-box;border-radius:6px;
    cursor:pointer;pointer-events:all;transition:filter .15s;}
.zona-vw:hover{filter:brightness(.88);}
.zona-vw.s-livre  {border:2px solid rgba(120,120,120,.3);background:rgba(200,200,200,.06);}
.zona-vw.s-online {border:2px solid rgba(46,160,67,.7); background:rgba(46,160,67,.1);}
.zona-vw.s-offline{border:2px solid rgba(220,53,69,.6); background:rgba(220,53,69,.1);}
.zona-vw.s-misto  {border:2px solid rgba(255,152,0,.7); background:rgba(255,152,0,.1);}

/* Label da zona */
.zona-label{position:absolute;bottom:0;left:0;right:0;font-size:.62rem;font-weight:700;
    color:#fff;background:rgba(0,0,0,.5);padding:2px 5px;text-align:center;
    border-radius:0 0 4px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    pointer-events:none;}

/* Ícones de status no canto */
.zona-icons{position:absolute;top:3px;right:3px;display:flex;gap:2px;pointer-events:none;}
.zi{width:18px;height:18px;border-radius:50%;display:flex;align-items:center;
    justify-content:center;font-size:.6rem;font-weight:700;backdrop-filter:blur(4px);}
.zi.p-on {background:rgba(46,125,50,.9);color:#fff;}
.zi.p-off{background:rgba(198,40,40,.85);color:#fff;}
.zi.a-on {background:rgba(21,101,192,.9);color:#fff;}
.zi.a-off{background:rgba(120,120,120,.7);color:#fff;}

/* Pegadas — canvas SVG embutido */
.pegadas-svg{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:hidden;}
/* Nome do último acesso */
.zona-acesso-nome{position:absolute;top:3px;left:3px;font-size:.58rem;font-weight:700;
    color:#fff;background:rgba(0,0,0,.55);border-radius:8px;padding:1px 5px;
    white-space:nowrap;max-width:60%;overflow:hidden;text-overflow:ellipsis;pointer-events:none;
    font-style:italic;}

/* ── Modal grande ── */
#modalZona .modal-dialog{max-width:620px;}
#modalZona .modal-content{border-radius:12px;overflow:hidden;}
#modalZona .modal-header{padding:16px 20px;border-bottom:1px solid #dee2e6;}
.zona-modal-titulo{font-size:1.1rem;font-weight:800;color:#212529;}
.zona-modal-sub{font-size:.78rem;color:#6c757d;margin-top:2px;}

/* Cards de dispositivo no modal */
.disp-cards{display:flex;gap:12px;flex-wrap:wrap;padding:16px 20px;}
.disp-card-modal{flex:1;min-width:220px;border-radius:10px;overflow:hidden;
    border:1px solid #dee2e6;box-shadow:0 2px 8px rgba(0,0,0,.07);}
.dcm-header{padding:12px 16px;display:flex;align-items:center;gap:8px;font-weight:700;font-size:.88rem;}
.dcm-header.porta{background:linear-gradient(135deg,#e8f5e9,#f1f8e9);color:#1b5e20;}
.dcm-header.ar   {background:linear-gradient(135deg,#e3f2fd,#ede7f6);color:#0d47a1;}
.dcm-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.dcm-dot.online{background:#2e7d32;animation:pulse-g 2s infinite;}
.dcm-dot.offline{background:#c62828;}
@keyframes pulse-g{0%{box-shadow:0 0 0 0 rgba(46,125,50,.5);}70%{box-shadow:0 0 0 8px rgba(46,125,50,0);}100%{box-shadow:0 0 0 0 rgba(46,125,50,0);}}
.dcm-body{padding:12px 16px;background:#fff;}
.dcm-info{font-size:.8rem;display:flex;justify-content:space-between;
    border-bottom:1px solid #f5f5f5;padding:3px 0;}
.dcm-info:last-child{border:none;}
.dcm-info .lbl{color:#6c757d;}
.dcm-info .val{font-weight:600;text-align:right;max-width:65%;word-break:break-all;}

/* Termostato */
.termostato{background:linear-gradient(135deg,#e3f2fd,#ede7f6);
    border-radius:8px;padding:14px;text-align:center;margin-top:10px;}
.termo-row{display:flex;align-items:center;justify-content:center;gap:10px;margin:6px 0;}
.btn-tm{width:34px;height:34px;border-radius:50%;border:2px solid #dee2e6;background:#fff;
    font-size:.95rem;cursor:pointer;transition:all .12s;display:flex;align-items:center;justify-content:center;}
.btn-tm:hover{border-color:#0d6efd;color:#0d6efd;}
.tm-num{font-size:2.6rem;font-weight:800;color:#0d6efd;min-width:65px;text-align:center;line-height:1;}
.tm-deg{font-size:1rem;color:#6c757d;font-weight:700;align-self:flex-start;margin-top:4px;}
.presets{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:8px;}
.btn-pr{padding:4px 10px;border-radius:12px;border:1px solid #dee2e6;background:#fff;
    font-size:.72rem;font-weight:600;cursor:pointer;color:#495057;transition:all .12s;}
.btn-pr:hover{border-color:#0d6efd;color:#0d6efd;}
.btn-pr.ativo{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.btn-ac-row{display:flex;gap:8px;margin-top:10px;}
.btn-ac{flex:1;padding:9px;border:2px solid;border-radius:8px;font-weight:700;
    font-size:.83rem;cursor:pointer;transition:all .12s;display:flex;align-items:center;justify-content:center;gap:5px;}
.btn-ac.liga   {background:#e8f5e9;color:#1b5e20;border-color:#a5d6a7;}
.btn-ac.liga:hover{background:#c8e6c9;}
.btn-ac.desliga{background:#ffebee;color:#b71c1c;border-color:#ef9a9a;}
.btn-ac.desliga:hover{background:#ffcdd2;}
.btn-aplicar{width:100%;margin-top:8px;padding:9px;border:none;border-radius:8px;
    background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;font-weight:700;
    font-size:.85rem;cursor:pointer;box-shadow:0 2px 8px rgba(13,110,253,.3);transition:all .15s;}
.btn-aplicar:hover{box-shadow:0 4px 14px rgba(13,110,253,.4);transform:translateY(-1px);}

/* Sem planta */
.sem-planta{text-align:center;padding:80px 20px;color:#adb5bd;}
.sem-planta i{font-size:3rem;display:block;margin-bottom:12px;}

/* Toast */
.toast-iot{position:fixed;bottom:20px;right:20px;z-index:9999;padding:11px 18px;
    border-radius:8px;font-weight:600;font-size:.875rem;display:none;min-width:200px;
    box-shadow:0 4px 16px rgba(0,0,0,.2);animation:slideUp .2s ease;}
@keyframes slideUp{from{transform:translateY(8px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.toast-iot.ok  {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.toast-iot.err {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

/* Refresh pill */
.refresh-pill{font-size:.72rem;background:#e9ecef;border-radius:20px;padding:3px 12px;
    color:#6c757d;display:inline-flex;align-items:center;gap:5px;}
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

<?php if(empty($pisos)): ?>
<div class="sem-planta">
    <i class="fas fa-map"></i>
    <p style="font-size:.95rem">Nenhuma planta configurada.</p>
    <?php if($ehAdmin): ?>
    <a href="plantaEditor.php" class="btn btn-primary"><i class="fas fa-edit mr-1"></i>Configurar planta</a>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Cabeçalho compacto -->
<div class="planta-header">
    <h4><i class="fas fa-map-marked-alt mr-2" style="color:#0d6efd"></i>Planta IoT</h4>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="refresh-pill">
            <i class="fas fa-circle" style="font-size:.45rem;color:#2e7d32"></i>
            <span id="countdown">15</span>s
        </span>
        <button class="btn btn-sm btn-outline-secondary" onclick="atualizarEstado()">
            <i class="fas fa-sync-alt"></i>
        </button>
        <?php if($ehAdmin): ?>
        <a href="plantaEditor.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-edit mr-1"></i>Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Abas piso -->
<?php if(count($pisos)>1): ?>
<div class="piso-tabs" id="pisoTabs">
    <?php foreach($pisos as $i=>$p): ?>
    <div class="piso-tab <?php echo $i===0?'ativo':''; ?>" onclick="trocarPiso(<?php echo $i; ?>)">
        <i class="fas fa-layer-group mr-1" style="font-size:.75rem"></i><?php echo htmlspecialchars($p['nome']); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Painéis de piso -->
<?php foreach($pisos as $i=>$p): ?>
<div id="ppanel-<?php echo $i; ?>" class="<?php echo $i!==0?'d-none':''; ?>">
    <?php if(!($p['imagem']??null)): ?>
    <div class="sem-planta" style="padding:40px">
        <i class="fas fa-image" style="font-size:2rem"></i>
        <p>Nenhuma imagem para este piso.</p>
    </div>
    <?php else: ?>
    <div class="planta-canvas" id="vwrap-<?php echo $i; ?>">
        <img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt="Planta" id="vimg-<?php echo $i; ?>">
        <div id="vlayer-<?php echo $i; ?>"></div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div></div>

<!-- Modal zona -->
<div class="modal fade" id="modalZona" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <div>
            <div class="zona-modal-titulo" id="mZonaTitulo">—</div>
            <div class="zona-modal-sub" id="mZonaSub"></div>
        </div>
        <button type="button" class="close" data-dismiss="modal" style="margin-left:auto"><span>&times;</span></button>
    </div>
    <div class="disp-cards" id="mZonaCards">
        <div class="text-muted text-center py-3 w-100">
            <i class="fas fa-plug mr-2"></i>Nenhum dispositivo IoT vinculado a esta sala.
        </div>
    </div>
    <div class="modal-footer py-2" style="border-top:1px solid #dee2e6">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Fechar</button>
    </div>
</div></div>
</div>

<div class="toast-iot" id="toastIoT"></div>
<script src="../js/menu.js"></script>
<script>
var _pisos       = <?php echo $pisosJson; ?>;
var _estado      = <?php echo $estadoJson; ?>;
var _labsIoT     = <?php echo $labsIoTJson; ?>;
var _nomesCracha = <?php echo $nomesCrachaJson; ?>;
var _pisoAtivo   = 0;
var _acTemps     = {};
var _rafHandles  = []; // animações de pegadas ativas

function buildMacMap(est){
    var m={};
    Object.values(est).forEach(function(d){ if(d.mac) m[d.mac.toUpperCase()]=d; });
    return m;
}
var _macMap = buildMacMap(_estado);

/* ── Troca piso ── */
function trocarPiso(i){
    _pisoAtivo=i;
    document.querySelectorAll('.piso-tab').forEach(function(t,j){ t.classList.toggle('ativo',j===i); });
    _pisos.forEach(function(p,j){
        var el=document.getElementById('ppanel-'+j);
        if(el) el.className=(j===i?'':'d-none');
    });
    renderLayer(i);
}

/* ── Pegadas Mapa do Maroto (SVG inline, loop contínuo) ── */
function acessoRecente(dh, min){
    if(!dh) return false;
    return Date.now()-new Date(dh.replace(' ','T')).getTime()<=min*60000;
}

function iniciarPegadas(container, nome){
    // Para qualquer animação anterior
    _rafHandles.forEach(function(h){ cancelAnimationFrame(h.id); });
    _rafHandles=[];

    var ns='http://www.w3.org/2000/svg';
    var svg=document.createElementNS(ns,'svg');
    svg.setAttribute('width','100%'); svg.setAttribute('height','100%');
    svg.style.cssText='position:absolute;inset:0;overflow:visible;pointer-events:none;';
    svg.setAttribute('viewBox','0 0 100 100');
    svg.setAttribute('preserveAspectRatio','none');
    container.appendChild(svg);

    // Sola de sapato — esquerda e direita
    function solaPath(dir){
        return dir
            ? 'M2,0 C0.5,0 0,1.5 0,3 L0,8 C0,10 0.5,11 2,11 L4,11 C5.5,11 6,9.5 6,8 L6.5,3 C6.5,1 5,0 3.5,0 Z'
            : 'M4,0 C5.5,0 7,1 7,3 L7,8 C7,9.5 6.5,11 4,11 L2,11 C0.5,11 0,10 0,8 L0,3 C0,1.5 0.5,0 2,0 Z';
    }

    var N=10; var cor='#5c3317';
    var passos=[];
    for(var i=0;i<N;i++){
        var g=document.createElementNS(ns,'g');
        var path=document.createElementNS(ns,'path');
        path.setAttribute('d', solaPath(i%2===0));
        path.setAttribute('fill',cor);
        g.appendChild(path);
        svg.appendChild(g);
        passos.push({el:g, angle:(i/N)*Math.PI*2});
    }

    // Nome flutuante — texto SVG
    var txt=document.createElementNS(ns,'text');
    txt.setAttribute('text-anchor','middle');
    txt.setAttribute('font-size','5');
    txt.setAttribute('fill','#3a1f00');
    txt.setAttribute('font-family','Georgia,serif');
    txt.setAttribute('font-style','italic');
    txt.setAttribute('font-weight','700');
    txt.textContent=nome||'?';
    // Fundo do texto
    var rect=document.createElementNS(ns,'rect');
    rect.setAttribute('rx','3'); rect.setAttribute('ry','3');
    rect.setAttribute('fill','rgba(255,248,220,0.88)');
    rect.setAttribute('stroke','rgba(92,51,23,0.3)');
    rect.setAttribute('stroke-width','0.5');
    svg.appendChild(rect); svg.appendChild(txt);

    var t=0;
    var cx=50, cy=50;
    var rx=35, ry=22;

    function frame(){
        t+=0.015;
        passos.forEach(function(p,i){
            var a=p.angle+t;
            var x=cx+rx*Math.cos(a);
            var y=cy+ry*Math.sin(a);
            var dx=-rx*Math.sin(a), dy=ry*Math.cos(a);
            var ang=Math.atan2(dy,dx)*180/Math.PI + (i%2===0?-18:18);
            var alpha=0.4+0.6*Math.abs(Math.sin(a));
            var scale=0.8+0.3*alpha;
            p.el.setAttribute('transform',
                'translate('+(x-3.5)+','+(y-5.5)+') rotate('+ang+',3.5,5.5) scale('+scale+')');
            p.el.style.opacity=alpha.toFixed(2);
        });
        // Posiciona o nome no topo da elipse
        var nx=cx, ny=cy-ry-8;
        txt.setAttribute('x',nx); txt.setAttribute('y',ny);
        var bbox;
        try{ bbox=txt.getBBox(); } catch(e){ bbox={x:nx-15,y:ny-5,width:30,height:6}; }
        rect.setAttribute('x',bbox.x-2); rect.setAttribute('y',bbox.y-1);
        rect.setAttribute('width',bbox.width+4); rect.setAttribute('height',bbox.height+2);

        var h={id:requestAnimationFrame(frame)};
        _rafHandles=[h];
    }
    frame();
}

/* ── Renderiza zonas ── */
function renderLayer(pi){
    // Para animações antigas
    _rafHandles.forEach(function(h){ cancelAnimationFrame(h.id); });
    _rafHandles=[];

    var layer=document.getElementById('vlayer-'+pi);
    if(!layer) return;
    layer.innerHTML='';
    var p=_pisos[pi]; if(!p) return;

    (p.zonas||[]).forEach(function(z){
        var div=document.createElement('div');
        div.className='zona-vw';
        div.style.cssText='left:'+z.x+'%;top:'+z.y+'%;width:'+z.w+'%;height:'+z.h+'%';

        var lab=z.idLaboratorio?_labsIoT[z.idLaboratorio]:null;
        var dispP=lab&&lab.macPorta?_macMap[lab.macPorta.toUpperCase()]:null;
        var dispA=lab&&lab.macArCondicionado?_macMap[lab.macArCondicionado.toUpperCase()]:null;

        var stP=dispP?dispP.status:null, stA=dispA?dispA.status:null;
        if(stP==='online'&&stA==='online')       div.classList.add('s-online');
        else if(stP==='online'||stA==='online')  div.classList.add('s-misto');
        else if(stP==='offline'||stA==='offline')div.classList.add('s-offline');
        else div.classList.add('s-livre');

        // Ícones compactos
        if(dispP||dispA){
            var ic=document.createElement('div'); ic.className='zona-icons';
            if(dispP){ var b=document.createElement('div'); b.className='zi '+(stP==='online'?'p-on':'p-off'); b.innerHTML='<i class="fas fa-door-open"></i>'; ic.appendChild(b); }
            if(dispA){ var b2=document.createElement('div'); b2.className='zi '+(stA==='online'?'a-on':'a-off'); b2.innerHTML='<i class="fas fa-snowflake"></i>'; ic.appendChild(b2); }
            div.appendChild(ic);
        }

        // Pegadas + nome se acesso recente
        if(dispP&&dispP.ultimo_acesso){
            var ua=dispP.ultimo_acesso;
            var nome=(_nomesCracha[ua.cracha]||ua.cracha);
            // Nome do acesso
            var na=document.createElement('div'); na.className='zona-acesso-nome';
            na.textContent=nome; na.title='Último acesso: '+ua.data_hora;
            div.appendChild(na);
        }

        // Rótulo
        var lbl=document.createElement('div'); lbl.className='zona-label';
        lbl.textContent=z.label||(lab?lab.nome:'');
        div.appendChild(lbl);

        // Clique → modal
        div.onclick=(function(z,lab,dispP,dispA){
            return function(){ abrirModal(z,lab,dispP,dispA); };
        })(z,lab,dispP,dispA);

        layer.appendChild(div);

        // Inicia pegadas se acesso recente
        if(dispP&&dispP.ultimo_acesso&&acessoRecente(dispP.ultimo_acesso.data_hora,30)){
            var nome2=(_nomesCracha[dispP.ultimo_acesso.cracha]||dispP.ultimo_acesso.cracha);
            // Pequeno delay para o div estar no DOM
            (function(d,n){ setTimeout(function(){ iniciarPegadas(d,n); },50); })(div,nome2);
        }
    });
}

/* ── Modal ── */
var _acTid=null;

function abrirModal(z,lab,dispP,dispA){
    document.getElementById('mZonaTitulo').textContent=z.label||(lab?lab.nome:'Zona');
    document.getElementById('mZonaSub').textContent=lab&&lab.descricao?lab.descricao:'';

    var cards=document.getElementById('mZonaCards');
    cards.innerHTML='';

    if(!dispP&&!dispA){
        cards.innerHTML='<div class="text-muted text-center py-3 w-100"><i class="fas fa-plug mr-2"></i>Nenhum dispositivo IoT nesta sala.</div>';
    }

    // Card Porta
    if(dispP){
        var stP=dispP.status;
        var ua=dispP.ultimo_acesso;
        var nome=ua?(_nomesCracha[ua.cracha]||ua.cracha):'—';
        var hora=ua?ua.data_hora.substr(11,5):'—';
        var data=ua?ua.data_hora.substr(0,10).split('-').reverse().join('/'):'';
        var card=document.createElement('div'); card.className='disp-card-modal';
        card.innerHTML='<div class="dcm-header porta"><div class="dcm-dot '+stP+'"></div><i class="fas fa-door-open mr-1"></i>Acesso</div>'
            +'<div class="dcm-body">'
            +'<div class="dcm-info"><span class="lbl">Status</span><span class="val" style="color:'+(stP==='online'?'#2e7d32':'#c62828')+'">'+ucfirst(stP)+'</span></div>'
            +(dispP.rssi?'<div class="dcm-info"><span class="lbl">Sinal WiFi</span><span class="val">'+dispP.rssi+' dBm</span></div>':'')
            +(dispP.mac?'<div class="dcm-info"><span class="lbl">MAC</span><span class="val" style="font-family:monospace;font-size:.72rem">'+esc(dispP.mac)+'</span></div>':'')
            +'<div class="dcm-info"><span class="lbl">Último acesso</span><span class="val">'+esc(nome)+'</span></div>'
            +(ua?'<div class="dcm-info"><span class="lbl">Quando</span><span class="val">'+hora+' · '+data+'</span></div>':'')
            +'</div>';
        cards.appendChild(card);
    }

    // Card AC
    if(dispA){
        _acTid=dispA.topico_id;
        if(!_acTemps[_acTid]) _acTemps[_acTid]=22;
        var stA=dispA.status;
        var card2=document.createElement('div'); card2.className='disp-card-modal';
        var presets=[16,18,20,22,24,26];
        var presetsHtml=presets.map(function(t){
            return '<button class="btn-pr'+(t===_acTemps[_acTid]?' ativo':'')+'" id="pr-m-'+t+'" onclick="setTempM('+t+')">'+t+'°</button>';
        }).join('');
        card2.innerHTML='<div class="dcm-header ar"><div class="dcm-dot '+stA+'"></div><i class="fas fa-snowflake mr-1"></i>Ar Condicionado'+(dispA.marca?' <span style="font-size:.7rem;opacity:.7">'+esc(ucfirst(dispA.marca))+'</span>':'')+'</div>'
            +'<div class="dcm-body">'
            +'<div class="dcm-info"><span class="lbl">Status</span><span class="val" style="color:'+(stA==='online'?'#1565c0':'#c62828')+'">'+ucfirst(stA)+'</span></div>'
            +(dispA.rssi?'<div class="dcm-info"><span class="lbl">Sinal WiFi</span><span class="val">'+dispA.rssi+' dBm</span></div>':'')
            // Controles
            +'<div class="btn-ac-row">'
            +'<button class="btn-ac liga" onclick="cmdAC(\'liga\')"><i class="fas fa-power-off"></i>Ligar</button>'
            +'<button class="btn-ac desliga" onclick="cmdAC(\'desliga\')"><i class="fas fa-power-off"></i>Desligar</button>'
            +'</div>'
            +'<div class="termostato">'
            +'<div style="font-size:.7rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><i class="fas fa-thermometer-half mr-1"></i>Temperatura</div>'
            +'<div class="termo-row">'
            +'<button class="btn-tm" onclick="ajTempM(-1)"><i class="fas fa-minus"></i></button>'
            +'<div class="tm-num" id="tm-modal">'+_acTemps[_acTid]+'</div>'
            +'<div class="tm-deg">°C</div>'
            +'<button class="btn-tm" onclick="ajTempM(+1)"><i class="fas fa-plus"></i></button>'
            +'</div>'
            +'<div class="presets">'+presetsHtml+'</div>'
            +'<button class="btn-aplicar" onclick="enviarTempM()"><i class="fas fa-check mr-1"></i>Aplicar temperatura</button>'
            +'</div>'
            +'</div>';
        cards.appendChild(card2);
    }

    $('#modalZona').modal('show');
}

function ajTempM(d){
    if(!_acTid) return;
    _acTemps[_acTid]=Math.min(30,Math.max(16,(_acTemps[_acTid]||22)+d));
    var el=document.getElementById('tm-modal'); if(el) el.textContent=_acTemps[_acTid];
    [16,18,20,22,24,26].forEach(function(t){
        var b=document.getElementById('pr-m-'+t); if(b) b.classList.toggle('ativo',t===_acTemps[_acTid]);
    });
}
function setTempM(t){
    if(!_acTid) return;
    _acTemps[_acTid]=t;
    var el=document.getElementById('tm-modal'); if(el) el.textContent=t;
    [16,18,20,22,24,26].forEach(function(v){
        var b=document.getElementById('pr-m-'+v); if(b) b.classList.toggle('ativo',v===t);
    });
}
function enviarTempM(){ if(_acTid) cmdAC(String(_acTemps[_acTid]||22)); }
function cmdAC(payload){
    if(!_acTid) return;
    var fd=new FormData(); fd.append('acao','comando'); fd.append('topico_id',_acTid); fd.append('payload',payload);
    fetch('plantaViewer.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            var lbl=payload==='liga'?'Ligado ✓':payload==='desliga'?'Desligado ✓':payload+'°C ✓';
            toast(res.ok?lbl:'Erro.', res.ok?'ok':'err');
        }).catch(function(){toast('Erro.','err');});
}

/* ── Atualização ── */
function atualizarEstado(){
    fetch('plantaViewer.php?ajax_estado=1')
        .then(function(r){return r.json();})
        .then(function(est){
            _estado=est; _macMap=buildMacMap(est);
            _pisos.forEach(function(p,i){ renderLayer(i); });
        }).catch(function(){});
}
var _cd=15;
setInterval(function(){
    _cd--; document.getElementById('countdown').textContent=_cd;
    if(_cd<=0){_cd=15;atualizarEstado();}
},1000);

/* ── Helpers ── */
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function ucfirst(s){s=String(s||'');return s.charAt(0).toUpperCase()+s.slice(1);}
var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toastIoT');
    el.textContent=msg; el.className='toast-iot '+(tipo||'ok'); el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}

// Inicia
_pisos.forEach(function(p,i){ renderLayer(i); });
</script>
</body>
</html>
