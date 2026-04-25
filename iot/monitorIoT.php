<?php
/*
 * monitorIoT.php — Monitor de dispositivos IoT
 * Estado: lido de /var/www/html/data/iot_estado.json (sem banco)
 * Acessos: lido de cadastroiot.historico (somente leitura)
 * Comandos: lido/escrito em intranet.iot_comandos
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
// Todos os usuários autenticados podem ver o monitor e enviar comandos
// Log de acessos disponível em logIoT.php (apenas grupo especial)
$podeVerLog = in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia']);

/* ── Conexão leitura cadastroiot ── */
try {
    $pdoIot = new PDO(
        "mysql:host=localhost;dbname=cadastroiot;charset=utf8mb4",
        "root", "BdP@25!",
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch(PDOException $e){ $pdoIot = null; }

/* ── Mapa MAC → {nome, descricao, tipo} vindo do banco ── */
// Prioridade de nome: banco (laboratorios) > device_name do MQTT
$labsPorMac = [];
try {
    $stLabs = $pdo->query("
        SELECT nome, descricao, macPorta, macArCondicionado
        FROM laboratorios
        WHERE macPorta IS NOT NULL OR macArCondicionado IS NOT NULL
    ");
    foreach($stLabs->fetchAll(PDO::FETCH_ASSOC) as $l){
        $nomeDesc = $l['nome'].($l['descricao'] ? ' — '.$l['descricao'] : '');
        if($l['macPorta'])
            $labsPorMac[strtoupper($l['macPorta'])]          = ['label'=>$nomeDesc,'tipo'=>'porta_ambiente'];
        if($l['macArCondicionado'])
            $labsPorMac[strtoupper($l['macArCondicionado'])] = ['label'=>$nomeDesc,'tipo'=>'ar_condicionado'];
    }
} catch(PDOException $e){}

define('ESTADO_JSON', '/var/www/html/data/iot_estado.json');

/* ── POST: enviar comando ── */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');
    if($_POST['acao']==='comando'){
        $tid     = trim($_POST['topico_id'] ?? '');
        $payload = trim($_POST['payload']   ?? '');
        if(!$tid || !$payload){ echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit; }
        $cmd   = sprintf('mosquitto_pub -h localhost -p 1883 -t %s -m %s 2>&1',
                    escapeshellarg('/'.$tid.'/comando'), escapeshellarg($payload));
        $saida = shell_exec($cmd);
        try {
            $pdo->prepare("INSERT INTO iot_comandos (topico_id,comando,payload,enviado_por) VALUES (?,?,?,?)")
                ->execute([$tid, $payload, $payload, $logado]);
        } catch(PDOException $e){}
        echo json_encode(['ok'=>true,'saida'=>trim($saida??'')]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']);
    }
    exit;
}

/* ── Lê estado do JSON ── */
$estado = [];
$jsonMtime = null;
if(file_exists(ESTADO_JSON)){
    $raw    = file_get_contents(ESTADO_JSON);
    $estado = json_decode($raw, true) ?: [];
    $jsonMtime = filemtime(ESTADO_JSON);
}

/* ── Enriquece estado com nome do banco (prioridade sobre device_name) ── */
foreach($estado as &$d){
    $mac = strtoupper($d['mac'] ?? '');
    if($mac && isset($labsPorMac[$mac])){
        $d['nomeExibir'] = $labsPorMac[$mac]['label'];
        $d['tipo']       = $labsPorMac[$mac]['tipo'];
    } else {
        // Sem MAC cadastrado: exibe só o topico_id (não exibe device_name)
        $d['nomeExibir'] = null;
    }
}
unset($d);

/* ── Separa portas e ACs ── */
$portas = array_filter($estado, fn($d) => ($d['tipo'] ?? '') !== 'ar_condicionado');
$ares   = array_filter($estado, fn($d) => ($d['tipo'] ?? '') === 'ar_condicionado');

function contarStatus(array $lista, string $s): int {
    return count(array_filter($lista, fn($d) => ($d['status']??'') === $s));
}

// Acessos e comandos movidos para logIoT.php

$tipoLabels = ['porta_ambiente'=>'Porta','portao_corredor'=>'Portão/Corredor','ar_condicionado'=>'Ar Condicionado'];
$tipoIcones = ['porta_ambiente'=>'fa-door-open','portao_corredor'=>'fa-archway','ar_condicionado'=>'fa-snowflake'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitor IoT</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
/* ── Grid de cards ── */
.iot-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:20px;}
.iot-card{border-radius:8px;padding:12px 14px;border:2px solid transparent;
    background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08);
    cursor:pointer;transition:transform .12s,box-shadow .12s;position:relative;}
.iot-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.13);}
.iot-card.online {border-color:#66bb6a;background:#f1f8f1;}
.iot-card.offline{border-color:#ef9a9a;background:#fff5f5;}
.iot-card.desconhecido{border-color:#e0e0e0;background:#fafafa;}

/* Dot animado */
.iot-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;flex-shrink:0;}
.online  .iot-dot{background:#2e7d32;animation:iot-pulse 2s infinite;}
.offline .iot-dot{background:#c62828;}
.desconhecido .iot-dot{background:#bdbdbd;}
@keyframes iot-pulse{0%{box-shadow:0 0 0 0 rgba(46,125,50,.6);}70%{box-shadow:0 0 0 7px rgba(46,125,50,0);}100%{box-shadow:0 0 0 0 rgba(46,125,50,0);}}

.iot-status{display:flex;align-items:center;font-size:.78rem;font-weight:700;margin-bottom:5px;color:#555;}
.iot-id   {font-size:1rem;font-weight:800;color:#212529;line-height:1.2;}
.iot-nome {font-size:.75rem;color:#555;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.iot-mac  {font-size:.66rem;color:#999;font-family:monospace;margin-top:3px;}
.iot-meta {font-size:.68rem;color:#888;margin-top:3px;display:flex;gap:8px;flex-wrap:wrap;}
.iot-tipo {display:inline-flex;align-items:center;gap:4px;font-size:.68rem;
    padding:2px 7px;border-radius:10px;background:rgba(0,0,0,.07);color:#666;margin-top:5px;}
.iot-marca{font-size:.7rem;color:#1565c0;margin-top:3px;}
.iot-acesso{font-size:.68rem;background:#e3f2fd;color:#0d47a1;border-radius:4px;
    padding:2px 6px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* Seção títulos */
.sec-titulo{font-size:.9rem;font-weight:700;color:#343a40;margin:20px 0 8px;
    display:flex;align-items:center;gap:8px;border-bottom:2px solid #dee2e6;padding-bottom:5px;}

/* Contadores */
.cnt-pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
.cnt-pill{display:flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;
    font-size:.82rem;font-weight:700;}
.cnt-pill.online {background:#e8f5e9;color:#2e7d32;}
.cnt-pill.offline{background:#ffebee;color:#c62828;}
.cnt-pill.desc   {background:#f5f5f5;color:#757575;}

/* Abas */
/* Tabelas */
.iot-table{font-size:.82rem;background:#fff;}
.iot-table th{background:#343a40;color:#fff;white-space:nowrap;}
.iot-table td,.iot-table th{padding:6px 10px;vertical-align:middle;}
.badge-cracha{display:inline-block;background:#e3f2fd;color:#0d47a1;border-radius:4px;
    padding:2px 7px;font-size:.72rem;font-weight:700;font-family:monospace;}
.badge-desc{background:#fff3e0 !important;color:#e65100 !important;}

/* Toast */
#toastIoT{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;display:none;
    padding:12px 18px;border-radius:6px;font-size:.875rem;font-weight:600;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastIoT.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastIoT.erro   {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

/* Nome principal (banco) e sub-id */
.iot-nome-principal{font-size:.95rem;font-weight:800;color:#212529;line-height:1.25;margin-top:2px;}
.iot-id-sub{font-size:.68rem;color:#999;font-family:monospace;margin-top:1px;}
/* Controles AC */
.ac-controles{margin-top:8px;border-top:1px solid rgba(0,0,0,.08);padding-top:8px;}
.ac-btns-liga{display:flex;gap:5px;margin-bottom:7px;}
.btn-ac-liga,.btn-ac-desliga{flex:1;padding:5px 6px;border:none;border-radius:5px;
    font-size:.72rem;font-weight:700;cursor:pointer;transition:background .15s;}
.btn-ac-liga{background:#e8f5e9;color:#2e7d32;}.btn-ac-liga:hover{background:#c8e6c9;}
.btn-ac-desliga{background:#ffebee;color:#c62828;}.btn-ac-desliga:hover{background:#ffcdd2;}
.ac-temp-ctrl{display:flex;align-items:center;gap:5px;justify-content:center;margin-top:4px;}
.btn-temp-adj{width:28px;height:28px;border-radius:50%;border:1px solid #ced4da;background:#fff;
    font-size:.78rem;cursor:pointer;display:flex;align-items:center;justify-content:center;
    color:#495057;transition:background .15s;padding:0;}
.btn-temp-adj:hover{background:#e9ecef;}
.ac-temp-display{display:flex;align-items:baseline;gap:1px;min-width:54px;justify-content:center;
    background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:3px 8px;}
.ac-temp-num{font-size:1.3rem;font-weight:800;color:#0d6efd;line-height:1;}
.ac-temp-deg{font-size:.75rem;color:#6c757d;font-weight:600;}
.btn-temp-send{width:28px;height:28px;border-radius:50%;border:none;background:#0d6efd;
    color:#fff;font-size:.75rem;cursor:pointer;display:flex;align-items:center;
    justify-content:center;padding:0;transition:background .15s;}
.btn-temp-send:hover{background:#0b5ed7;}
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

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-broadcast-tower mr-2"></i>Monitor IoT</h4>
        <div style="display:flex;gap:8px;align-items:center">
            <?php if($jsonMtime): ?>
            <small class="text-muted" style="font-size:.73rem">
                <i class="fas fa-clock mr-1"></i><?php echo date('d/m/Y H:i:s',$jsonMtime); ?>
            </small>
            <?php endif; ?>
            <span style="font-size:.72rem;background:#e9ecef;border-radius:4px;padding:3px 10px;color:#6c757d">
                <i class="fas fa-sync-alt mr-1"></i><span id="countdown">30</span>s
            </span>
            <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Atualizar agora">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <?php if(empty($estado)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Nenhum dado recebido ainda.</strong>
        Inicie o monitor:<br>
        <code>nohup python3 /var/www/html/scripts/mqttMonitor.py >> /var/www/html/logs/mqtt_monitor.log 2>&amp;1 &amp;</code>
    </div>
    <?php endif; ?>

    <!-- ══ PORTAS ══ -->
    <?php if(!empty($portas)): ?>
    <div class="sec-titulo">
        <i class="fas fa-door-open"></i>Controle de Acesso — Portas
        <div class="cnt-pills">
            <span class="cnt-pill online"><i class="fas fa-circle" style="font-size:.6rem"></i><?php echo contarStatus($portas,'online'); ?> online</span>
            <span class="cnt-pill offline"><i class="fas fa-circle" style="font-size:.6rem"></i><?php echo contarStatus($portas,'offline'); ?> offline</span>
            <?php if(contarStatus($portas,'desconhecido')>0): ?>
            <span class="cnt-pill desc"><i class="fas fa-circle" style="font-size:.6rem"></i><?php echo contarStatus($portas,'desconhecido'); ?> sem dados</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="iot-grid">
    <?php foreach($portas as $d):
        $st    = $d['status'] ?? 'desconhecido';
        $tipo  = $d['tipo']   ?? 'porta_ambiente';
        $icone = $tipoIcones[$tipo] ?? 'fa-microchip';
        $label = $tipoLabels[$tipo] ?? $tipo;
        $nome  = $d['nomeExibir'] ?? null;
        $ultima = isset($d['ultima_vez']) ? date('d/m H:i', strtotime($d['ultima_vez'])) : '—';
        $ua    = $d['ultimo_acesso'] ?? null;
    ?>
    <div class="iot-card <?php echo $st; ?>"
         onclick="abrirModal(<?php echo htmlspecialchars(json_encode($d,JSON_HEX_TAG),ENT_QUOTES); ?>)">
        <div class="iot-status">
            <span class="iot-dot"></span><?php echo ucfirst($st); ?>
            <i class="fas <?php echo $icone; ?> ml-auto text-muted" style="font-size:.85rem"></i>
        </div>
        <?php if($nome): ?>
        <div class="iot-nome-principal"><?php echo htmlspecialchars($nome); ?></div>
        <div class="iot-id-sub"><?php echo htmlspecialchars($d['topico_id']); ?></div>
        <?php else: ?>
        <div class="iot-id"><?php echo htmlspecialchars($d['topico_id']); ?></div>
        <?php endif; ?>
        <?php if($d['mac']??null): ?><div class="iot-mac"><?php echo htmlspecialchars($d['mac']); ?></div><?php endif; ?>
        <div class="iot-meta">
            <?php if($d['rssi']??null): ?><span><i class="fas fa-wifi"></i> <?php echo $d['rssi']; ?> dBm</span><?php endif; ?>
            <span><i class="fas fa-clock"></i> <?php echo $ultima; ?></span>
        </div>
        <div class="iot-tipo"><i class="fas <?php echo $icone; ?>"></i><?php echo $label; ?></div>
        <?php if($ua): ?>
        <?php
            // Resolve nome do crachá via cadastroiot.cadastro
            static $nomesCracha = null;
            if($nomesCracha === null){
                $nomesCracha = [];
                try {
                    $pdoC = new PDO("mysql:host=localhost;dbname=cadastroiot;charset=utf8mb4","root","BdP@25!",
                        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                    foreach($pdoC->query("SELECT cracha, nome FROM cadastro")->fetchAll(PDO::FETCH_ASSOC) as $c)
                        $nomesCracha[$c['cracha']] = $c['nome'];
                } catch(Exception $e){}
            }
            $nomeUa = $nomesCracha[$ua['cracha']] ?? $ua['cracha'];
        ?>
        <div class="iot-acesso">
            <i class="fas fa-id-card mr-1"></i><?php echo htmlspecialchars($nomeUa); ?>
            <span style="opacity:.7"><?php echo date('d/m H:i', strtotime($ua['data_hora'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ ARES CONDICIONADOS ══ -->
    <?php if(!empty($ares)): ?>
    <div class="sec-titulo">
        <i class="fas fa-snowflake"></i>Ar Condicionado
        <div class="cnt-pills">
            <span class="cnt-pill online"><i class="fas fa-circle" style="font-size:.6rem"></i><?php echo contarStatus($ares,'online'); ?> online</span>
            <span class="cnt-pill offline"><i class="fas fa-circle" style="font-size:.6rem"></i><?php echo contarStatus($ares,'offline'); ?> offline</span>
        </div>
    </div>
    <div class="iot-grid">
    <?php foreach($ares as $d):
        $st    = $d['status'] ?? 'desconhecido';
        $nome  = $d['nomeExibir'] ?? null;
        $ultima = isset($d['ultima_vez']) ? date('d/m H:i', strtotime($d['ultima_vez'])) : '—';
    ?>
    <div class="iot-card <?php echo $st; ?>"
         onclick="abrirModal(<?php echo htmlspecialchars(json_encode($d,JSON_HEX_TAG),ENT_QUOTES); ?>)">
        <div class="iot-status">
            <span class="iot-dot"></span><?php echo ucfirst($st); ?>
            <i class="fas fa-snowflake ml-auto text-muted" style="font-size:.85rem"></i>
        </div>
        <?php if($nome): ?>
        <div class="iot-nome-principal"><?php echo htmlspecialchars($nome); ?></div>
        <div class="iot-id-sub"><?php echo htmlspecialchars($d['topico_id']); ?></div>
        <?php else: ?>
        <div class="iot-id"><?php echo htmlspecialchars($d['topico_id']); ?></div>
        <?php endif; ?>
        <?php if($d['mac']??null): ?><div class="iot-mac"><?php echo htmlspecialchars($d['mac']); ?></div><?php endif; ?>
        <div class="iot-meta">
            <?php if($d['rssi']??null): ?><span><i class="fas fa-wifi"></i> <?php echo $d['rssi']; ?> dBm</span><?php endif; ?>
            <span><i class="fas fa-clock"></i> <?php echo $ultima; ?></span>
        </div>
        <?php if($d['marca']??null): ?>
        <div class="iot-marca"><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars(ucfirst($d['marca'])); ?></div>
        <?php endif; ?>
        <!-- Controles AC -->
        <div class="ac-controles" onclick="event.stopPropagation()">
            <div class="ac-btns-liga">
                <button class="btn-ac-liga" onclick="cmdAC('<?php echo $d['topico_id']; ?>','liga')">
                    <i class="fas fa-power-off"></i> Ligar
                </button>
                <button class="btn-ac-desliga" onclick="cmdAC('<?php echo $d['topico_id']; ?>','desliga')">
                    <i class="fas fa-power-off"></i> Desligar
                </button>
            </div>
            <div class="ac-temp-ctrl">
                <button class="btn-temp-adj" onclick="ajustarTemp('<?php echo $d['topico_id']; ?>',-1)">
                    <i class="fas fa-minus"></i>
                </button>
                <div class="ac-temp-display" id="temp-<?php echo $d['topico_id']; ?>">
                    <span class="ac-temp-num">22</span>
                    <span class="ac-temp-deg">°C</span>
                </div>
                <button class="btn-temp-adj" onclick="ajustarTemp('<?php echo $d['topico_id']; ?>',+1)">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="btn-temp-send" onclick="enviarTemp('<?php echo $d['topico_id']; ?>')"
                        title="Aplicar temperatura">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ Link para log (apenas grupo especial) ══ -->
    <?php if($podeVerLog): ?>
    <div class="mt-3 mb-1">
        <a href="logIoT.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-history mr-1"></i>Ver log de acessos e comandos
        </a>
    </div>
    <?php endif; ?>
            </tr>
            <?php //endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php //endif; ?>
    </div>

</div>
</div>

<!-- Modal dispositivo -->
<div class="modal fade" id="modalDisp" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header" id="modalDispHeader">
        <h5 class="modal-title" id="modalDispTitulo"></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div id="modalDispInfo" class="mb-3" style="font-size:.85rem;line-height:1.9"></div>
        <div id="modalCmdArea" style="display:none">
            <hr>
            <p class="mb-1" style="font-size:.82rem;font-weight:700">
                <i class="fas fa-paper-plane mr-1"></i>Enviar comando
            </p>
            <div class="d-flex" style="gap:8px">
                <input type="text" id="modalCmdInput" class="form-control form-control-sm"
                       placeholder="Payload..." style="flex:1">
                <button class="btn btn-warning btn-sm" onclick="enviarComando()">Enviar</button>
            </div>
            <small class="text-muted">Publica em <code id="modalCmdTopico"></code></small>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fechar</button>
    </div>
</div></div>
</div>

<div id="toastIoT"></div>
<script src="../js/menu.js"></script>
<script>
/* ── Modal ── */
var _dispAtual = null;
function abrirModal(d){
    _dispAtual = d;
    var cores = {online:'#2e7d32', offline:'#c62828', desconhecido:'#9e9e9e'};
    var cor   = cores[d.status] || '#555';
    document.getElementById('modalDispHeader').style.borderBottom = '3px solid '+cor;
    document.getElementById('modalDispTitulo').textContent =
        d.topico_id + (d.nomeAmbiente ? ' — '+d.nomeAmbiente : '');

    var html = '';
    var fs = [['Status',d.status],['Tipo',d.tipo],['Device',d.device_name],
              ['MAC',d.mac?'<code>'+esc(d.mac)+'</code>':null],
              ['Sinal WiFi',d.rssi?d.rssi+' dBm':null],
              ['WiFi',d.wifi],['MQTT',d.mqtt_status],['Marca',d.marca],
              ['Última atualização',d.ultima_vez]];
    fs.forEach(function(f){ if(f[1]) html+='<div><strong>'+f[0]+':</strong> '+f[1]+'</div>'; });
    if(d.ultimo_acesso){
        var ua = d.ultimo_acesso;
        html += '<div><strong>Último acesso:</strong> '+esc(ua.nome||ua.cracha)
              + ' — <span class="text-muted">'+esc(ua.data_hora)+'</span></div>';
    }
    document.getElementById('modalDispInfo').innerHTML = html||'<em class="text-muted">Sem dados.</em>';
    document.getElementById('modalCmdTopico').textContent = '/'+d.topico_id+'/comando';
    document.getElementById('modalCmdInput').value = '';
    // Mostra campo de comando apenas para ACs (portas não recebem comandos)
    var cmdArea = document.getElementById('modalCmdArea');
    if(cmdArea) cmdArea.style.display = d.tipo === 'ar_condicionado' ? '' : 'none';
    $('#modalDisp').modal('show');
}

/* ── Controle AC inline nos cards ── */
var _acTemps = {}; // {topico_id: temp_atual}

function ajustarTemp(tid, delta){
    if(!_acTemps[tid]) _acTemps[tid] = 22;
    _acTemps[tid] = Math.min(30, Math.max(16, _acTemps[tid] + delta));
    var el = document.getElementById('temp-' + tid);
    if(el) el.querySelector('.ac-temp-num').textContent = _acTemps[tid];
}

function enviarTemp(tid){
    if(!_acTemps[tid]) _acTemps[tid] = 22;
    _publicar(tid, String(_acTemps[tid]));
}

function cmdAC(tid, cmd){
    _publicar(tid, cmd);
}

function _publicar(tid, payload){
    var fd = new FormData();
    fd.append('acao','comando');
    fd.append('topico_id', tid);
    fd.append('payload', payload);
    fetch('monitorIoT.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){ mostrarToast(res.ok?'Enviado: '+payload:res.msg||'Erro.', res.ok?'sucesso':'erro'); })
        .catch(function(){ mostrarToast('Erro de comunicação.','erro'); });
}

function enviarComando(){
    if(!_dispAtual) return;
    var payload = document.getElementById('modalCmdInput').value.trim();
    if(!payload){ mostrarToast('Digite um comando.','erro'); return; }
    _publicar(_dispAtual.topico_id, payload);
    document.getElementById('modalCmdInput').value='';
}

/* ── Auto-refresh ── */
var _cd=30;
setInterval(function(){ _cd--; document.getElementById('countdown').textContent=_cd; if(_cd<=0) location.reload(); },1000);

/* ── Helpers ── */
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
var _tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastIoT');
    el.textContent=msg; el.className=tipo==='sucesso'?'sucesso':'erro'; el.style.display='block';
    if(_tt) clearTimeout(_tt);
    _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>