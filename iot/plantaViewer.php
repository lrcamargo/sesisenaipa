<?php
/*
 * plantaViewer.php — Visualização ao vivo da planta com controle IoT
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

define('PLANTA_JSON', '/var/www/html/data/planta.json');
define('ESTADO_JSON', '/var/www/html/data/iot_estado.json');

/* ── AJAX estado ── */
if(isset($_GET['ajax_estado'])){
    header('Content-Type: application/json');
    echo file_exists(ESTADO_JSON)
        ? file_get_contents(ESTADO_JSON) : '{}';
    exit;
}

/* ── POST comando ── */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='comando'){
    ob_clean(); header('Content-Type: application/json');
    $tid     = trim($_POST['topico_id'] ?? '');
    $payload = trim($_POST['payload']   ?? '');
    if(!$tid||!$payload){ echo json_encode(['ok'=>false]); exit; }
    shell_exec(sprintf('mosquitto_pub -h localhost -p 1883 -t %s -m %s 2>&1',
        escapeshellarg('/'.$tid.'/comando'), escapeshellarg($payload)));
    try {
        $pdo->prepare("INSERT INTO iot_comandos (topico_id,comando,payload,enviado_por) VALUES (?,?,?,?)")
            ->execute([$tid,$payload,$payload,$_SESSION['user']]);
    } catch(Exception $e){}
    echo json_encode(['ok'=>true]); exit;
}

/* ── Dados ── */
function lerPlanta(): array {
    if(!file_exists(PLANTA_JSON)) return ['pisos'=>[]];
    $raw = json_decode(file_get_contents(PLANTA_JSON), true) ?: [];
    if(isset($raw['zonas'])&&!isset($raw['pisos']))
        return ['pisos'=>[['id'=>1,'nome'=>'Piso 1','imagem'=>$raw['imagem']??null,'zonas'=>$raw['zonas']??[]]]];
    return $raw;
}

$planta = lerPlanta();
$pisos  = $planta['pisos'] ?? [];
$estado = file_exists(ESTADO_JSON)
    ? (json_decode(file_get_contents(ESTADO_JSON), true) ?: []) : [];

$macParaDisp = [];
foreach($estado as $d){ if($d['mac']??null) $macParaDisp[strtoupper($d['mac'])] = $d; }

$labsIoT = [];
foreach($pdo->query("SELECT idLaboratorio,nome,descricao,macPorta,macArCondicionado FROM laboratorios")->fetchAll(PDO::FETCH_ASSOC) as $l)
    $labsIoT[$l['idLaboratorio']] = $l;

$nomesCracha = [];
try {
    $pdoIot = new PDO("mysql:host=localhost;dbname=cadastroiot;charset=utf8mb4","root","BdP@25!",
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach($pdoIot->query("SELECT cracha,nome FROM cadastro")->fetchAll(PDO::FETCH_ASSOC) as $c)
        $nomesCracha[$c['cracha']] = $c['nome'];
} catch(Exception $e){}

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
$ehAdmin   = in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia']);

$pisosJson       = json_encode($pisos,       JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
$estadoJson      = json_encode($estado,      JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
$labsIoTJson     = json_encode($labsIoT,     JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
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
/* ══ Abas piso ══ */
.piso-tabs{display:flex;gap:4px;border-bottom:2px solid #dee2e6;flex-wrap:wrap;margin-bottom:0;}
.piso-tab{padding:8px 20px;border-radius:8px 8px 0 0;border:1px solid #dee2e6;border-bottom:none;
    background:#f8f9fa;cursor:pointer;font-size:.85rem;font-weight:600;color:#6c757d;margin-bottom:-2px;
    transition:all .15s;}
.piso-tab:hover{background:#e9ecef;color:#343a40;}
.piso-tab.ativo{background:#fff;border-bottom:2px solid #fff;color:#0d6efd;}

/* ══ Canvas da planta ══ */
.viewer-outer{border:1px solid #dee2e6;border-radius:0 10px 10px 10px;
    overflow:hidden;background:#f0f0f0;box-shadow:0 2px 8px rgba(0,0,0,.08);}
.viewer-wrap{position:relative;cursor:default;}
.viewer-wrap img{display:block;width:100%;height:auto;user-select:none;}
#vlayer{position:absolute;top:0;left:0;width:100%;height:100%;}

/* ══ Zona no viewer ══ */
.zona-vw{position:absolute;box-sizing:border-box;border-radius:6px;
    transition:all .2s;cursor:pointer;}
.zona-vw:hover .zona-overlay{opacity:1;}
.zona-vw.s-livre  {border:2px solid rgba(150,150,150,.25);background:rgba(200,200,200,.05);}
.zona-vw.s-online {border:2px solid rgba(46,160,67,.55); background:rgba(46,160,67,.07);}
.zona-vw.s-offline{border:2px solid rgba(220,53,69,.45); background:rgba(220,53,69,.06);}
.zona-vw.s-misto  {border:2px solid rgba(255,152,0,.55); background:rgba(255,152,0,.07);}

/* Hover overlay */
.zona-overlay{position:absolute;inset:0;background:rgba(13,110,253,.08);
    border-radius:4px;opacity:0;transition:opacity .15s;pointer-events:none;}

/* Badges no canto superior direito */
.zona-badges{position:absolute;top:4px;right:4px;display:flex;flex-direction:column;gap:3px;align-items:flex-end;}
.zbadge{display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border-radius:20px;
    font-size:.67rem;font-weight:700;backdrop-filter:blur(4px);white-space:nowrap;}
.zbadge.p-on {background:rgba(46,125,50,.9); color:#fff;}
.zbadge.p-off{background:rgba(198,40,40,.85);color:#fff;}
.zbadge.a-on {background:rgba(21,101,192,.9); color:#fff;}
.zbadge.a-off{background:rgba(100,100,100,.7);color:#fff;}

/* Último acesso no canto sup esq */
.zona-acesso{position:absolute;top:4px;left:4px;font-size:.62rem;font-weight:600;
    background:rgba(0,0,0,.55);color:#fff;border-radius:12px;
    padding:2px 7px;max-width:55%;white-space:nowrap;overflow:hidden;
    text-overflow:ellipsis;pointer-events:none;backdrop-filter:blur(2px);}

/* Rótulo no centro-baixo */
.zona-label{position:absolute;bottom:4px;left:4px;right:4px;
    font-size:.68rem;font-weight:700;color:#fff;
    background:rgba(0,0,0,.48);border-radius:4px;
    padding:2px 5px;text-align:center;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;
    backdrop-filter:blur(2px);}

/* ══ PAINEL LATERAL DE CONTROLE ══ */
.ctrl-panel{width:280px;flex-shrink:0;display:flex;flex-direction:column;gap:10px;}
.ctrl-card{background:#fff;border-radius:10px;border:1px solid #dee2e6;
    overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.07);transition:box-shadow .15s;}
.ctrl-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.12);}
.ctrl-card-header{padding:10px 14px;display:flex;align-items:center;gap:8px;
    font-weight:700;font-size:.85rem;}
.ctrl-card-header.porta{background:linear-gradient(135deg,#e8f5e9,#f1f8e9);}
.ctrl-card-header.ar   {background:linear-gradient(135deg,#e3f2fd,#e8eaf6);}
.ctrl-card-header .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.dot.online {background:#2e7d32;box-shadow:0 0 0 0 rgba(46,125,50,.5);animation:pulse-dot 2s infinite;}
.dot.offline{background:#c62828;}
.dot.desc   {background:#9e9e9e;}
@keyframes pulse-dot{0%{box-shadow:0 0 0 0 rgba(46,125,50,.5);}70%{box-shadow:0 0 0 7px rgba(46,125,50,0);}100%{box-shadow:0 0 0 0 rgba(46,125,50,0);}}
.ctrl-card-body{padding:12px 14px;}

/* Porta — info */
.info-row{display:flex;justify-content:space-between;align-items:center;
    font-size:.8rem;padding:3px 0;border-bottom:1px solid #f5f5f5;}
.info-row:last-child{border:none;}
.info-label{color:#6c757d;font-weight:500;}
.info-val  {font-weight:600;color:#212529;text-align:right;max-width:60%;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* AC — controles grandes */
.ac-power-row{display:flex;gap:8px;margin-bottom:12px;}
.btn-power{flex:1;padding:10px 6px;border:none;border-radius:8px;
    font-size:.82rem;font-weight:700;cursor:pointer;transition:all .15s;
    display:flex;align-items:center;justify-content:center;gap:6px;}
.btn-power.liga   {background:#e8f5e9;color:#1b5e20;border:2px solid #a5d6a7;}
.btn-power.liga:hover   {background:#c8e6c9;border-color:#66bb6a;}
.btn-power.desliga{background:#ffebee;color:#b71c1c;border:2px solid #ef9a9a;}
.btn-power.desliga:hover{background:#ffcdd2;border-color:#e57373;}
.btn-power i{font-size:1rem;}

/* Termostato */
.termostato{background:linear-gradient(135deg,#e3f2fd,#f3e5f5);
    border-radius:12px;padding:14px;text-align:center;position:relative;overflow:hidden;}
.termostato::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;
    background:rgba(13,110,253,.06);border-radius:50%;}
.termo-label{font-size:.7rem;font-weight:600;color:#6c757d;text-transform:uppercase;
    letter-spacing:.05em;margin-bottom:6px;}
.termo-display{display:flex;align-items:center;justify-content:center;gap:10px;margin:4px 0;}
.btn-termo{width:36px;height:36px;border-radius:50%;border:2px solid #dee2e6;
    background:#fff;font-size:.95rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:all .15s;color:#495057;box-shadow:0 1px 3px rgba(0,0,0,.1);}
.btn-termo:hover{border-color:#0d6efd;color:#0d6efd;box-shadow:0 2px 6px rgba(13,110,253,.2);}
.btn-termo:active{transform:scale(.92);}
.termo-num{font-size:2.8rem;font-weight:800;color:#0d6efd;line-height:1;
    min-width:70px;text-align:center;}
.termo-deg{font-size:1.1rem;font-weight:700;color:#6c757d;align-self:flex-start;margin-top:6px;}
.btn-aplicar{width:100%;margin-top:10px;padding:8px;border:none;border-radius:8px;
    background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;
    font-weight:700;font-size:.85rem;cursor:pointer;
    box-shadow:0 2px 8px rgba(13,110,253,.35);transition:all .15s;}
.btn-aplicar:hover{box-shadow:0 4px 14px rgba(13,110,253,.45);transform:translateY(-1px);}
.btn-aplicar:active{transform:translateY(0);}

/* Presets de temperatura */
.temp-presets{display:flex;gap:5px;flex-wrap:wrap;margin-top:8px;justify-content:center;}
.btn-preset{padding:4px 10px;border-radius:15px;border:1px solid #dee2e6;background:#fff;
    font-size:.72rem;font-weight:600;cursor:pointer;color:#495057;transition:all .12s;}
.btn-preset:hover{border-color:#0d6efd;color:#0d6efd;background:#e8f0fe;}
.btn-preset.ativo{background:#0d6efd;color:#fff;border-color:#0d6efd;}

/* RSSI badge */
.rssi-badge{display:inline-flex;align-items:center;gap:3px;font-size:.68rem;
    color:#6c757d;background:#f8f9fa;border-radius:10px;padding:2px 7px;}

/* Sem seleção */
.ctrl-hint{text-align:center;color:#adb5bd;padding:30px 10px;}
.ctrl-hint i{font-size:2rem;display:block;margin-bottom:8px;color:#dee2e6;}
.ctrl-hint p{font-size:.82rem;line-height:1.5;}

/* ── Pegadas Mapa do Maroto ── */
.pegadas-wrap{position:absolute;inset:0;pointer-events:none;overflow:hidden;border-radius:4px;}
/* SVG da pegada fica no canvas, animado via JS */
.marauder-nome{position:absolute;font-size:.6rem;font-weight:700;color:#5c3317;
    background:rgba(255,248,220,.88);border-radius:10px;padding:2px 7px;
    white-space:nowrap;pointer-events:none;font-family:Georgia,serif;font-style:italic;
    box-shadow:0 1px 3px rgba(0,0,0,.2);border:1px solid rgba(92,51,23,.3);}

/* Toast */
.toast-iot{position:fixed;bottom:24px;right:24px;z-index:9999;
    padding:12px 20px;border-radius:8px;font-size:.875rem;font-weight:600;
    box-shadow:0 4px 16px rgba(0,0,0,.2);display:none;min-width:220px;
    animation:slideUp .2s ease;}
@keyframes slideUp{from{transform:translateY(10px);opacity:0;}to{transform:translateY(0);opacity:1;}}
.toast-iot.ok  {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.toast-iot.err {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.toast-iot.info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}

/* Sem planta */
.sem-planta{text-align:center;padding:60px 20px;color:#adb5bd;}
.sem-planta i{font-size:3rem;display:block;margin-bottom:12px;}

/* Layout */
.viewer-layout{display:flex;gap:14px;align-items:flex-start;}
.viewer-main{flex:1;min-width:0;}

/* Badge refresh */
.refresh-pill{font-size:.72rem;background:#e9ecef;border-radius:20px;
    padding:4px 12px;color:#6c757d;display:inline-flex;align-items:center;gap:5px;}
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

<!-- Cabeçalho -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="fas fa-map-marked-alt mr-2" style="color:#0d6efd"></i>Planta IoT</h4>
        <small class="text-muted">Clique em uma sala para ver detalhes e controlar dispositivos</small>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="refresh-pill">
            <i class="fas fa-circle" style="font-size:.5rem;color:#2e7d32"></i>
            Ao vivo · <span id="countdown">15</span>s
        </span>
        <button class="btn btn-sm btn-outline-secondary" onclick="atualizarEstado()" title="Atualizar agora">
            <i class="fas fa-sync-alt"></i>
        </button>
        <?php if($ehAdmin): ?>
        <a href="plantaEditor.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-edit mr-1"></i>Editar planta
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if(empty($pisos)): ?>
<div class="sem-planta">
    <i class="fas fa-map"></i>
    <p style="font-size:.95rem">Nenhuma planta configurada ainda.</p>
    <?php if($ehAdmin): ?>
    <a href="plantaEditor.php" class="btn btn-primary">
        <i class="fas fa-edit mr-1"></i>Configurar planta
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Abas piso -->
<div class="piso-tabs mb-0" id="pisoTabs">
<?php foreach($pisos as $i=>$p): ?>
<div class="piso-tab <?php echo $i===0?'ativo':''; ?>"
     onclick="trocarPiso(<?php echo $i; ?>)">
    <i class="fas fa-layer-group mr-1"></i><?php echo htmlspecialchars($p['nome']); ?>
</div>
<?php endforeach; ?>
</div>

<div class="viewer-layout" style="margin-top:0">
    <!-- Planta -->
    <div class="viewer-main">
        <?php foreach($pisos as $i=>$p): ?>
        <div id="ppanel-<?php echo $i; ?>" class="<?php echo $i!==0?'d-none':''; ?>">
            <?php if(!($p['imagem']??null)): ?>
            <div class="sem-planta" style="padding:40px">
                <i class="fas fa-image" style="font-size:2rem"></i>
                <p>Nenhuma imagem para este piso.</p>
            </div>
            <?php else: ?>
            <div class="viewer-outer">
            <div class="viewer-wrap" id="vwrap-<?php echo $i; ?>">
                <img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt="Planta <?php echo htmlspecialchars($p['nome']); ?>">
                <div id="vlayer-<?php echo $i; ?>"></div>
            </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Painel lateral de controle -->
    <div class="ctrl-panel" id="ctrlPanel">
        <div class="ctrl-hint" id="ctrlHint">
            <i class="fas fa-hand-pointer"></i>
            <p>Clique em uma sala na planta para ver os dispositivos e controles disponíveis.</p>
        </div>
    </div>
</div>
<?php endif; ?>

</div></div>

<div class="toast-iot" id="toastIoT"></div>
<script src="../js/menu.js"></script>
<script>
/* ═══ DADOS ═══ */
var _pisos       = <?php echo $pisosJson; ?>;
var _estado      = <?php echo $estadoJson; ?>;
var _labsIoT     = <?php echo $labsIoTJson; ?>;
var _nomesCracha = <?php echo $nomesCrachaJson; ?>;
var _pisoAtivo   = 0;
var _zonaAtiva   = null;  // {z, lab, dispPorta, dispAR}
var _acTemps     = {};    // {topico_id: temp}

function macMap(est){
    var m={};
    Object.values(est).forEach(function(d){ if(d.mac) m[d.mac.toUpperCase()]=d; });
    return m;
}
var _macMap = macMap(_estado);

/* ═══ PISOS ═══ */
function trocarPiso(i){
    _pisoAtivo=i;
    _zonaAtiva=null;
    document.querySelectorAll('.piso-tab').forEach(function(t,j){
        t.classList.toggle('ativo',j===i);
    });
    _pisos.forEach(function(p,j){
        var el=document.getElementById('ppanel-'+j);
        if(el) el.className=(j===i?'':'d-none');
    });
    renderLayer(i);
    renderPainel(null);
}

/* ═══ RENDERIZA ZONAS ═══ */
function dispParaLab(lab, tipo){
    var mac = tipo==='porta' ? (lab.macPorta||'') : (lab.macArCondicionado||'');
    return mac ? _macMap[mac.toUpperCase()] : null;
}

function renderLayer(pi){
    var layer=document.getElementById('vlayer-'+pi);
    if(!layer) return;
    layer.innerHTML='';
    var p=_pisos[pi]; if(!p) return;
    (p.zonas||[]).forEach(function(z,zi){
        var div=document.createElement('div');
        div.className='zona-vw';
        div.style.cssText='left:'+z.x+'%;top:'+z.y+'%;width:'+z.w+'%;height:'+z.h+'%';

        var lab = z.idLaboratorio ? _labsIoT[z.idLaboratorio] : null;
        var dispP = lab ? dispParaLab(lab,'porta') : null;
        var dispA = lab ? dispParaLab(lab,'ar')    : null;

        // Classe de estado geral
        var stP=dispP?dispP.status:null, stA=dispA?dispA.status:null;
        if(stP==='online'&&stA==='online')       div.classList.add('s-online');
        else if(stP==='online'||stA==='online')  div.classList.add('s-misto');
        else if(stP==='offline'||stA==='offline')div.classList.add('s-offline');
        else div.classList.add('s-livre');

        // Overlay hover
        var ov=document.createElement('div'); ov.className='zona-overlay';
        div.appendChild(ov);

        // Badges
        if(dispP||dispA){
            var bd=document.createElement('div'); bd.className='zona-badges';
            if(dispP){
                var b=document.createElement('span');
                b.className='zbadge '+(stP==='online'?'p-on':'p-off');
                b.innerHTML='<i class="fas fa-door-open"></i>'+(stP==='online'?'Aberta/Online':'Offline');
                bd.appendChild(b);
            }
            if(dispA){
                var b2=document.createElement('span');
                b2.className='zbadge '+(stA==='online'?'a-on':'a-off');
                b2.innerHTML='<i class="fas fa-snowflake"></i>'+(stA==='online'?'Online':'Offline');
                bd.appendChild(b2);
            }
            div.appendChild(bd);
        }

         // Último acesso + pegadas se recente (≤30min)
         if(dispP&&dispP.ultimo_acesso){
             var ua=dispP.ultimo_acesso;
             var nome=(_nomesCracha[ua.cracha]||ua.cracha);
             var el=document.createElement('div'); el.className='zona-acesso';
             el.textContent=nome;
             el.title='Último acesso: '+nome+' em '+ua.data_hora;
             div.appendChild(el);
             // Animação de pegadas se acesso recente
             if(acessoRecente(ua.data_hora, 30)){
                 div.appendChild(criarPegadas(nome));
             }
         }

        // Rótulo
        var lbl=document.createElement('div'); lbl.className='zona-label';
        lbl.textContent=z.label||(lab?lab.nome:'');
        div.appendChild(lbl);

        // Clique
        div.onclick=(function(z,lab,dispP,dispA){
            return function(){
                _zonaAtiva={z:z,lab:lab,dispPorta:dispP,dispAR:dispA};
                // Destaca zona selecionada
                document.querySelectorAll('.zona-vw').forEach(function(d){ d.style.outline=''; });
                this.style.outline='3px solid #0d6efd';
                renderPainel({z:z,lab:lab,dispPorta:dispP,dispAR:dispA});
            };
        })(z,lab,dispP,dispA);

        layer.appendChild(div);
    });
}

/* ═══ PAINEL LATERAL ═══ */
function renderPainel(sel){
    var panel=document.getElementById('ctrlPanel');
    panel.innerHTML='';

    if(!sel||!sel.lab){
        panel.innerHTML='<div class="ctrl-hint"><i class="fas fa-hand-pointer"></i><p>Clique em uma sala para ver os controles.</p></div>';
        return;
    }

    var lab=sel.lab, dispP=sel.dispPorta, dispA=sel.dispAR;

    // Título da sala
    var tit=document.createElement('div');
    tit.style.cssText='padding:10px 14px 6px;font-size:.95rem;font-weight:800;color:#212529;'+
        'border-bottom:2px solid #0d6efd;margin-bottom:2px;';
    tit.innerHTML='<i class="fas fa-door-open mr-2" style="color:#0d6efd"></i>'+esc(lab.nome)+
        (lab.descricao?'<div style="font-size:.73rem;font-weight:400;color:#6c757d;margin-top:1px">'+esc(lab.descricao)+'</div>':'');
    panel.appendChild(tit);

    // Card da porta
    if(dispP){
        var stP=dispP.status;
        var card=document.createElement('div');
        card.className='ctrl-card';
        var ua=dispP.ultimo_acesso;
        var nomeUa=ua?(_nomesCracha[ua.cracha]||ua.cracha):'—';
        var horaUa=ua?ua.data_hora.substr(11,5):'';

        card.innerHTML='<div class="ctrl-card-header porta">'
            +'<div class="dot '+stP+'"></div>'
            +'<span><i class="fas fa-door-open mr-1"></i>Controle de Acesso</span>'
            +(dispP.rssi?'<span class="rssi-badge ml-auto"><i class="fas fa-wifi"></i>'+dispP.rssi+'dBm</span>':'')
            +'</div>'
            +'<div class="ctrl-card-body">'
            +'<div class="info-row"><span class="info-label">Status</span>'
            +'<span class="info-val" style="color:'+(stP==='online'?'#2e7d32':'#c62828')+'"><i class="fas fa-circle mr-1" style="font-size:.55rem"></i>'+ucfirst(stP)+'</span></div>'
            +(dispP.mac?'<div class="info-row"><span class="info-label">MAC</span><span class="info-val" style="font-family:monospace;font-size:.75rem">'+esc(dispP.mac)+'</span></div>':'')
            +'<div class="info-row"><span class="info-label">Último acesso</span><span class="info-val">'+esc(nomeUa)+'</span></div>'
            +(ua?'<div class="info-row"><span class="info-label">Horário</span><span class="info-val">'+esc(horaUa)+' — '+esc(ua.data_hora.substr(0,10).split('-').reverse().join('/'))+'</span></div>':'')
            +'</div>';
        panel.appendChild(card);
    }

    // Card do AC
    if(dispA){
        var stA=dispA.status;
        var tid=dispA.topico_id;
        if(!_acTemps[tid]) _acTemps[tid]=22;

        var card2=document.createElement('div');
        card2.className='ctrl-card';
        card2.innerHTML='<div class="ctrl-card-header ar">'
            +'<div class="dot '+stA+'"></div>'
            +'<span><i class="fas fa-snowflake mr-1"></i>Ar Condicionado</span>'
            +(dispA.marca?'<span style="font-size:.7rem;color:#6c757d;margin-left:auto">'+esc(ucfirst(dispA.marca))+'</span>':'')
            +'</div>'
            +'<div class="ctrl-card-body">'
            // Liga/desliga
            +'<div class="ac-power-row">'
            +'<button class="btn-power liga" onclick="cmdAC(\''+tid+'\',\'liga\')">'
            +'<i class="fas fa-power-off"></i>Ligar</button>'
            +'<button class="btn-power desliga" onclick="cmdAC(\''+tid+'\',\'desliga\')">'
            +'<i class="fas fa-power-off"></i>Desligar</button>'
            +'</div>'
            // Termostato
            +'<div class="termostato">'
            +'<div class="termo-label"><i class="fas fa-thermometer-half mr-1"></i>Temperatura</div>'
            +'<div class="termo-display">'
            +'<button class="btn-termo" onclick="ajTemp(\''+tid+'\',-1)"><i class="fas fa-minus"></i></button>'
            +'<div class="termo-num" id="tmp-'+tid+'">'+_acTemps[tid]+'</div>'
            +'<div class="termo-deg">°C</div>'
            +'<button class="btn-termo" onclick="ajTemp(\''+tid+'\',+1)"><i class="fas fa-plus"></i></button>'
            +'</div>'
            // Presets rápidos
            +'<div class="temp-presets">'
            +[16,18,20,22,24,26].map(function(t){
                return '<button class="btn-preset'+(t===_acTemps[tid]?' ativo':'')+'" id="pr-'+tid+'-'+t+'" onclick="setTemp(\''+tid+'\','+t+')">'+t+'°</button>';
            }).join('')
            +'</div>'
            +'<button class="btn-aplicar" onclick="enviarTemp(\''+tid+'\')">'
            +'<i class="fas fa-check mr-1"></i>Aplicar temperatura</button>'
            +'</div>'
            +'</div>';
        panel.appendChild(card2);
    }

    // Sem dispositivos
    if(!dispP&&!dispA){
        var nd=document.createElement('div');
        nd.className='ctrl-hint';
        nd.innerHTML='<i class="fas fa-plug"></i><p>Nenhum dispositivo IoT vinculado a esta sala.</p>';
        panel.appendChild(nd);
    }
}

/* ═══ CONTROLES AC ═══ */
function ajTemp(tid, d){
    _acTemps[tid]=Math.min(30,Math.max(16,(_acTemps[tid]||22)+d));
    var el=document.getElementById('tmp-'+tid); if(el) el.textContent=_acTemps[tid];
    // Atualiza presets
    [16,18,20,22,24,26].forEach(function(t){
        var b=document.getElementById('pr-'+tid+'-'+t);
        if(b) b.classList.toggle('ativo',t===_acTemps[tid]);
    });
}
function setTemp(tid, t){
    _acTemps[tid]=t;
    var el=document.getElementById('tmp-'+tid); if(el) el.textContent=t;
    [16,18,20,22,24,26].forEach(function(v){
        var b=document.getElementById('pr-'+tid+'-'+v);
        if(b) b.classList.toggle('ativo',v===t);
    });
}
function enviarTemp(tid){ cmdAC(tid, String(_acTemps[tid]||22)); }

function cmdAC(tid, payload){
    var fd=new FormData();
    fd.append('acao','comando'); fd.append('topico_id',tid); fd.append('payload',String(payload));
    fetch('plantaViewer.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            var label = payload==='liga'?'Ligado ✓' : payload==='desliga'?'Desligado ✓' : payload+'°C enviado ✓';
            mostrarToast(res.ok?label:'Erro ao enviar.', res.ok?'ok':'err');
        })
        .catch(function(){mostrarToast('Erro de comunicação.','err');});
}

/* ═══ ATUALIZAÇÃO ═══ */
function atualizarEstado(){
    fetch('plantaViewer.php?ajax_estado=1')
        .then(function(r){return r.json();})
        .then(function(est){
            _estado=est; _macMap=macMap(est);
            _pisos.forEach(function(p,i){ renderLayer(i); });
            // Atualiza painel se há zona ativa
            if(_zonaAtiva&&_zonaAtiva.lab){
                var lab=_zonaAtiva.lab;
                var dispP=dispParaLab(lab,'porta'), dispA=dispParaLab(lab,'ar');
                _zonaAtiva.dispPorta=dispP; _zonaAtiva.dispAR=dispA;
                renderPainel(_zonaAtiva);
            }
        })
        .catch(function(){});
}

/* ═══ PEGADAS — Mapa do Maroto ═══ */
function acessoRecente(dataHora, minutos){
    if(!dataHora) return false;
    var ts = new Date(dataHora.replace(' ','T')).getTime();
    return (Date.now() - ts) <= minutos * 60 * 1000;
}

/*
 * SVG de pegada de sapato (esquerda/direita) — estilo clássico HP.
 * Retorna elemento <svg>.
 */
function svgPegada(direita, cor, tamanho){
    cor = cor || '#5c3317';
    tamanho = tamanho || 14;
    // Forma de sola de sapato simplificada
    var d = direita
        ? 'M6,2 C3,2 1,4 1,7 L1,13 C1,16 3,18 6,18 L8,18 C10,18 11,16 11,14 L12,8 C12,5 10,2 8,2 Z M3,6 Q6,4 9,6'
        : 'M6,2 C9,2 11,4 11,7 L11,13 C11,16 9,18 6,18 L4,18 C2,18 1,16 1,14 L0,8 C0,5 2,2 4,2 Z M9,6 Q6,4 3,6';
    var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('viewBox','0 0 13 20');
    svg.setAttribute('width', tamanho);
    svg.setAttribute('height', tamanho*1.5);
    svg.style.cssText = 'display:block;filter:drop-shadow(0 1px 1px rgba(0,0,0,.3))';
    var path = document.createElementNS('http://www.w3.org/2000/svg','path');
    path.setAttribute('d', d);
    path.setAttribute('fill', cor);
    path.setAttribute('opacity','0.85');
    svg.appendChild(path);
    return svg;
}

/* Cria a camada de pegadas com animação circular contínua */
function criarPegadas(nome){
    var wrap = document.createElement('div');
    wrap.className = 'pegadas-wrap';

    // Canvas SVG para as pegadas animadas
    var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('width','100%');
    svg.setAttribute('height','100%');
    svg.style.cssText='position:absolute;inset:0;overflow:hidden;';
    wrap.appendChild(svg);

    // Nome flutuante lateral
    var nomeEl = document.createElement('div');
    nomeEl.className = 'marauder-nome';
    nomeEl.textContent = nome || '?';
    nomeEl.style.cssText='position:absolute;left:50%;top:5px;transform:translateX(-50%);';
    wrap.appendChild(nomeEl);

    // Anima via rAF — pegadas aparecem uma a uma percorrendo a zona
    var pegadas = []; // [{el, x, y, alpha}]
    var N = 8;        // máx pegadas visíveis simultaneamente
    var passo = 0;    // passo atual na órbita
    var cx=50, cy=55; // centro do círculo (%)
    var rx=32, ry=25; // raios da elipse (%)
    var cor = '#5c3317';
    var tamanho = 11;
    var velocidade = 0.012; // rad por frame

    // Cria os elementos SVG das pegadas antecipadamente
    for(var i=0;i<N;i++){
        var g = document.createElementNS('http://www.w3.org/2000/svg','g');
        g.style.opacity='0';
        // Sola de sapato como path SVG inline
        var dir = i%2===0;
        var d = dir
            ? 'M3,1 C1.5,1 0.5,2.5 0.5,4 L0.5,9 C0.5,11 1.5,12 3,12 L5,12 C6.5,12 7,10.5 7,9 L7.5,4.5 C7.5,2.5 6,1 4.5,1 Z'
            : 'M4.5,1 C6,1 7.5,2.5 7.5,4.5 L7.5,9 C7.5,10.5 7,12 5,12 L3,12 C1.5,12 0.5,11 0.5,9 L0.5,4 C0.5,2.5 1.5,1 3,1 Z';
        var path = document.createElementNS('http://www.w3.org/2000/svg','path');
        path.setAttribute('d', d);
        path.setAttribute('fill', cor);
        g.appendChild(path);
        svg.appendChild(g);
        pegadas.push({el:g, angle: (i/N)*Math.PI*2, alpha:0, ativa:false});
    }

    var raf;
    function frame(){
        passo += velocidade;
        pegadas.forEach(function(p, i){
            var a = p.angle + passo;
            var px = cx + rx * Math.cos(a);
            var py = cy + ry * Math.sin(a);
            // Direção tangente
            var dx = -rx * Math.sin(a);
            var dy =  ry * Math.cos(a);
            var angulo = Math.atan2(dy, dx) * 180/Math.PI + (i%2===0?-20:20);
            // Desvanece nas extremidades laterais para dar profundidade
            var alpha = 0.5 + 0.5*Math.abs(Math.sin(a));
            p.el.setAttribute('transform',
                'translate('+(px-4)+','+(py-6)+') rotate('+angulo+',4,6) scale('+(0.85+0.25*alpha)+')');
            p.el.style.opacity = (0.35 + 0.65*alpha).toFixed(2);
        });
        raf = requestAnimationFrame(frame);
    }
    frame();
    // Para a animação quando o elemento sair do DOM
    wrap._stopPegadas = function(){ cancelAnimationFrame(raf); };
    return wrap;
}

/* ═══ COUNTDOWN ═══ */
var _cd=15;
setInterval(function(){
    _cd--; document.getElementById('countdown').textContent=_cd;
    if(_cd<=0){_cd=15;atualizarEstado();}
},1000);

/* ═══ HELPERS ═══ */
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function ucfirst(s){s=String(s||'');return s.charAt(0).toUpperCase()+s.slice(1);}
var _tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastIoT');
    el.textContent=msg; el.className='toast-iot '+(tipo||'info'); el.style.display='block';
    if(_tt)clearTimeout(_tt);
    _tt=setTimeout(function(){el.style.display='none';},3000);
}

// Init
_pisos.forEach(function(p,i){ renderLayer(i); });
</script>
</body>
</html>