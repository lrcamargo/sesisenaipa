<?php
/*
 * plantaEditor.php — Editor de planta com múltiplos pisos
 * Schema JSON: {pisos: [{id, nome, imagem, zonas:[{id,x,y,w,h,label,idLaboratorio}]}]}
 * Migração automática do schema antigo {imagem, zonas} para o novo.
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:../index.php'); exit;
}

define('PLANTA_JSON', '/var/www/html/data/planta.json');
define('PLANTA_DIR',  '/var/www/html/data/');
define('PLANTA_URL',  '/data/');

function lerPlanta(): array {
    if(!file_exists(PLANTA_JSON)) return ['pisos'=>[]];
    $raw = json_decode(file_get_contents(PLANTA_JSON), true) ?: [];
    // Migração schema antigo → novo
    if(isset($raw['zonas']) && !isset($raw['pisos'])){
        return ['pisos'=>[[
            'id'=>1, 'nome'=>'Piso 1',
            'imagem'=>$raw['imagem'] ?? null,
            'zonas' =>$raw['zonas']  ?? [],
        ]]];
    }
    return $raw;
}
function salvarPlanta(array $p): void {
    $tmp = PLANTA_JSON.'.tmp';
    file_put_contents($tmp, json_encode($p, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    rename($tmp, PLANTA_JSON);
}

/* ── POST ── */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    if($_POST['acao']==='upload_fundo'){
        $pisoId = intval($_POST['piso_id'] ?? 0);
        if(!isset($_FILES['imagem']) || $_FILES['imagem']['error']!==0){
            echo json_encode(['ok'=>false,'msg'=>'Arquivo inválido.']); exit;
        }
        $f   = $_FILES['imagem'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp'])){
            echo json_encode(['ok'=>false,'msg'=>'Formato inválido.']); exit;
        }
        $nome = 'planta_piso'.$pisoId.'.'.$ext;
        // Remove imagens anteriores deste piso
        foreach(['jpg','jpeg','png','webp'] as $e)
            @unlink(PLANTA_DIR.'planta_piso'.$pisoId.'.'.$e);
        if(!move_uploaded_file($f['tmp_name'], PLANTA_DIR.$nome)){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']); exit;
        }
        $url    = PLANTA_URL.$nome.'?v='.time();
        $planta = lerPlanta();
        foreach($planta['pisos'] as &$p){
            if($p['id']===$pisoId){ $p['imagem']=$url; break; }
        }
        unset($p);
        salvarPlanta($planta);
        echo json_encode(['ok'=>true,'url'=>$url]); exit;
    }

    if($_POST['acao']==='salvar_planta'){
        $pisos = json_decode($_POST['pisos'] ?? '[]', true);
        if(!is_array($pisos)){ echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit; }
        // Preserva imagens já existentes (não sobrescreve campo imagem com null)
        $atual = lerPlanta();
        $mapaAtual = [];
        foreach($atual['pisos'] as $p) $mapaAtual[$p['id']] = $p;
        foreach($pisos as &$p){
            if(isset($mapaAtual[$p['id']]) && !($p['imagem'] ?? null))
                $p['imagem'] = $mapaAtual[$p['id']]['imagem'] ?? null;
        }
        unset($p);
        salvarPlanta(['pisos'=>$pisos]);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

$planta    = lerPlanta();
$pisosJson = json_encode($planta['pisos'] ?? [], JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);

$ambientes = $pdo->query("
    SELECT idLaboratorio, nome, descricao, macPorta, macArCondicionado
    FROM laboratorios ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);
$ambientesJson = json_encode($ambientes, JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor de Planta</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<style>
.editor-wrap{display:flex;gap:14px;align-items:flex-start;}
.editor-sidebar{width:230px;flex-shrink:0;}
.sb-section{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:11px;margin-bottom:10px;}
.sb-section h6{font-size:.8rem;font-weight:700;color:#343a40;margin-bottom:8px;}

/* Abas de piso */
.piso-tabs{display:flex;gap:4px;margin-bottom:0;border-bottom:2px solid #dee2e6;flex-wrap:wrap;}
.piso-tab{padding:6px 14px;border-radius:6px 6px 0 0;border:1px solid #dee2e6;border-bottom:none;
    background:#f8f9fa;cursor:pointer;font-size:.82rem;font-weight:600;color:#495057;
    margin-bottom:-2px;display:flex;align-items:center;gap:5px;white-space:nowrap;}
.piso-tab.ativo{background:#fff;border-bottom:2px solid #fff;color:#0d6efd;}
.piso-tab .tab-del{font-size:.65rem;color:#aaa;cursor:pointer;padding:1px 3px;
    border-radius:2px;line-height:1;}
.piso-tab .tab-del:hover{background:#ffebee;color:#c62828;}
.btn-add-piso{padding:6px 10px;border-radius:6px 6px 0 0;border:1px dashed #ced4da;
    border-bottom:none;background:#f8f9fa;cursor:pointer;font-size:.78rem;color:#6c757d;
    margin-bottom:-2px;}
.btn-add-piso:hover{background:#e9ecef;}

/* Canvas */
.canvas-outer{border:1px solid #dee2e6;border-radius:0 8px 8px 8px;overflow:hidden;background:#f0f0f0;}
.editor-canvas-wrap{position:relative;cursor:crosshair;min-height:380px;
    display:flex;align-items:center;justify-content:center;}
.editor-canvas-wrap img{display:block;width:100%;height:auto;user-select:none;pointer-events:none;}
#zonaLayer{position:absolute;top:0;left:0;width:100%;height:100%;}

.zona{position:absolute;border:2px solid rgba(13,110,253,.7);background:rgba(13,110,253,.12);
    border-radius:4px;cursor:move;box-sizing:border-box;}
.zona:hover{background:rgba(13,110,253,.22);}
.zona.selecionada{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.3);}
.zona.tem-porta {border-color:#2e7d32;background:rgba(46,125,50,.15);}
.zona.tem-ar    {border-color:#1565c0;background:rgba(21,101,192,.15);}
.zona.tem-ambos {border-color:#6a1b9a;background:rgba(106,27,154,.15);}
.zona-label{position:absolute;bottom:2px;left:3px;right:3px;font-size:.62rem;font-weight:700;
    color:#212529;background:rgba(255,255,255,.85);border-radius:2px;
    padding:1px 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;}
.zona-resize{position:absolute;width:9px;height:9px;background:#0d6efd;border:1px solid #fff;
    border-radius:2px;bottom:-4px;right:-4px;cursor:se-resize;}
.canvas-hint{text-align:center;color:#adb5bd;padding:40px 20px;}
.canvas-hint i{font-size:2rem;display:block;margin-bottom:8px;}

/* Zona lista */
.zona-lista{max-height:240px;overflow-y:auto;}
.zona-item{display:flex;align-items:center;gap:6px;padding:4px 6px;border-radius:4px;
    font-size:.76rem;cursor:pointer;border:1px solid transparent;margin-bottom:2px;}
.zona-item:hover{background:#f0f4ff;}
.zona-item.ativo{background:#e8f0fe;border-color:#0d6efd;}
.zi-cor{width:10px;height:10px;border-radius:2px;flex-shrink:0;}
.zi-nome{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

.legenda-dot{display:inline-block;width:11px;height:11px;border-radius:2px;
    border:2px solid;vertical-align:middle;margin-right:3px;}
#toastPlanta{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:240px;display:none;
    padding:11px 16px;border-radius:6px;font-size:.875rem;font-weight:600;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastPlanta.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastPlanta.erro   {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

/* ── Editor de imagem ── */
.img-editor-wrap{background:#222;border-radius:6px;overflow:hidden;max-height:420px;
    display:flex;align-items:center;justify-content:center;}
.img-editor-wrap img{max-width:100%;display:block;}
.edit-toolbar{display:flex;gap:6px;flex-wrap:wrap;padding:10px 0 2px;}
.btn-edit{padding:5px 12px;border:1px solid #ced4da;border-radius:5px;background:#fff;
    font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:4px;color:#343a40;
    transition:background .12s;}
.btn-edit:hover{background:#e9ecef;}
.btn-edit.ativo{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.crop-ratio-btns{display:flex;gap:4px;flex-wrap:wrap;}
.btn-ratio{padding:3px 9px;border:1px solid #ced4da;border-radius:4px;background:#fff;
    font-size:.72rem;cursor:pointer;color:#495057;}
.btn-ratio:hover{background:#e9ecef;}
.btn-ratio.ativo{background:#6c757d;color:#fff;border-color:#6c757d;}
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-map mr-2"></i>Editor de Planta</h4>
    <div style="display:flex;gap:8px">
        <a href="plantaViewer.php" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="fas fa-eye mr-1"></i>Ver ao vivo
        </a>
        <button class="btn btn-success btn-sm" onclick="salvarTudo()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div>

<!-- Legenda -->
<div class="mb-2" style="font-size:.73rem;display:flex;gap:12px;flex-wrap:wrap">
    <span><span class="legenda-dot" style="background:rgba(46,125,50,.2);border-color:#2e7d32"></span>Porta IoT</span>
    <span><span class="legenda-dot" style="background:rgba(21,101,192,.2);border-color:#1565c0"></span>AR IoT</span>
    <span><span class="legenda-dot" style="background:rgba(106,27,154,.2);border-color:#6a1b9a"></span>Porta + AR</span>
    <span><span class="legenda-dot" style="background:rgba(13,110,253,.12);border-color:#0d6efd"></span>Sem IoT</span>
    <span class="ml-auto text-muted">Clique+arraste para criar zona &nbsp;|&nbsp; Duplo clique para editar &nbsp;|&nbsp; ↘ para redimensionar</span>
</div>

<div class="editor-wrap">
    <!-- Sidebar -->
    <div class="editor-sidebar">
        <div class="sb-section">
            <h6><i class="fas fa-image mr-1"></i>Imagem do piso ativo</h6>
            <input type="file" id="inputImagem" accept="image/*" style="display:none" onchange="abrirEditor(this)">
            <button class="btn btn-outline-primary btn-sm btn-block mb-1"
                    onclick="document.getElementById('inputImagem').click()">
                <i class="fas fa-upload mr-1"></i><span id="btnUploadTxt">Enviar imagem</span>
            </button>
            <button class="btn btn-outline-secondary btn-sm btn-block" id="btnEditarImg"
                    onclick="editarImagemAtual()" style="display:none">
                <i class="fas fa-crop-alt mr-1"></i>Editar imagem atual
            </button>
        </div>
        <div class="sb-section">
            <h6><i class="fas fa-layer-group mr-1"></i>Pisos</h6>
            <div id="pisoListaSb" style="max-height:150px;overflow-y:auto;margin-bottom:6px"></div>
            <button class="btn btn-outline-secondary btn-sm btn-block" onclick="adicionarPiso()">
                <i class="fas fa-plus mr-1"></i>Novo piso
            </button>
        </div>
        <div class="sb-section">
            <h6><i class="fas fa-vector-square mr-1"></i>Zonas do piso
                <span id="zonaCount" class="badge badge-secondary ml-1">0</span>
            </h6>
            <div class="zona-lista" id="zonaLista"></div>
            <button class="btn btn-outline-danger btn-sm btn-block mt-2"
                    id="btnExcluir" onclick="excluirZona()" disabled>
                <i class="fas fa-trash mr-1"></i>Excluir zona
            </button>
        </div>
    </div>

    <!-- Área do canvas -->
    <div style="flex:1;min-width:0">
        <!-- Abas de piso -->
        <div class="piso-tabs" id="pisoTabs"></div>
        <div class="canvas-outer">
            <div class="editor-canvas-wrap" id="canvasWrap">
                <div id="semImagem" class="canvas-hint">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>Envie a imagem deste piso para começar</p>
                </div>
                <img id="plantaImg" src="" alt="Planta" style="display:none">
                <div id="zonaLayer"></div>
            </div>
        </div>
    </div>
</div>

</div></div>

<!-- Modal editor de imagem -->
<div class="modal fade" id="modalEditorImg" tabindex="-1" data-backdrop="static">
<div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header py-2">
        <h5 class="modal-title" style="font-size:.95rem">
            <i class="fas fa-crop-alt mr-2"></i>Editar imagem
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body pb-2">
        <!-- Toolbar de edição -->
        <div class="edit-toolbar">
            <button class="btn-edit" onclick="cropper.rotate(-90)" title="Girar 90° esquerda">
                <i class="fas fa-undo"></i> 90°
            </button>
            <button class="btn-edit" onclick="cropper.rotate(90)" title="Girar 90° direita">
                <i class="fas fa-redo"></i> 90°
            </button>
            <button class="btn-edit" onclick="cropper.rotate(180)" title="Girar 180°">
                <i class="fas fa-sync"></i> 180°
            </button>
            <button class="btn-edit" onclick="cropper.scaleX(-cropper.getData().scaleX||1)" title="Espelhar horizontal">
                <i class="fas fa-arrows-alt-h"></i>
            </button>
            <button class="btn-edit" onclick="cropper.scaleY(-cropper.getData().scaleY||1)" title="Espelhar vertical">
                <i class="fas fa-arrows-alt-v"></i>
            </button>
            <button class="btn-edit" onclick="cropper.zoom(0.1)"><i class="fas fa-search-plus"></i></button>
            <button class="btn-edit" onclick="cropper.zoom(-0.1)"><i class="fas fa-search-minus"></i></button>
            <button class="btn-edit" onclick="cropper.reset()" title="Resetar">
                <i class="fas fa-times"></i> Reset
            </button>
            <div style="border-left:1px solid #dee2e6;margin:0 4px"></div>
            <span style="font-size:.75rem;color:#6c757d;align-self:center">Proporção:</span>
            <div class="crop-ratio-btns">
                <button class="btn-ratio ativo" id="ratioLivre" onclick="setRatio(NaN,this)">Livre</button>
                <button class="btn-ratio" onclick="setRatio(16/9,this)">16:9</button>
                <button class="btn-ratio" onclick="setRatio(4/3,this)">4:3</button>
                <button class="btn-ratio" onclick="setRatio(1,this)">1:1</button>
                <button class="btn-ratio" onclick="setRatio(3/2,this)">3:2</button>
            </div>
        </div>
        <!-- Canvas do cropper -->
        <div class="img-editor-wrap">
            <img id="cropperImg" src="" alt="Editar imagem">
        </div>
        <small class="text-muted d-block mt-1">
            Arraste para mover a área de corte. Arraste as bordas para redimensionar.
            Se não quiser cortar, basta clicar em <strong>Usar imagem</strong>.
        </small>
    </div>
    <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="usarSemCorte()">
            <i class="fas fa-image mr-1"></i>Usar sem corte
        </button>
        <button class="btn btn-primary btn-sm" onclick="aplicarCorte()">
            <i class="fas fa-crop-alt mr-1"></i>Aplicar e enviar
        </button>
    </div>
</div></div>
</div>

<!-- Modal zona -->
<div class="modal fade" id="modalZona" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-vector-square mr-2"></i>Configurar zona</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="form-group mb-2">
            <label style="font-size:.78rem;font-weight:600">Rótulo (opcional)</label>
            <input type="text" id="zonaLabel" class="form-control form-control-sm"
                   placeholder="Ex: Lab 01, Sala 203...">
        </div>
        <div class="form-group mb-0">
            <label style="font-size:.78rem;font-weight:600">Ambiente vinculado</label>
            <select id="zonaAmbiente" class="form-control form-control-sm">
                <option value="">— Nenhum —</option>
                <?php foreach($ambientes as $a):
                    $iot = ($a['macPorta']?'🚪':'').($a['macArCondicionado']?'❄️':'');
                ?>
                <option value="<?php echo $a['idLaboratorio']; ?>">
                    <?php echo htmlspecialchars($a['nome'].($a['descricao']?' — '.$a['descricao']:'').($iot?' '.$iot:'')); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="confirmarZona()">
            <i class="fas fa-check mr-1"></i>Aplicar
        </button>
    </div>
</div></div>
</div>

<!-- Modal renomear piso -->
<div class="modal fade" id="modalPiso" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" style="font-size:.95rem">Renomear piso</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="text" id="pisoNomeInput" class="form-control form-control-sm"
               placeholder="Ex: Térreo, 1º Andar...">
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="confirmarRenamePiso()">Salvar</button>
    </div>
</div></div>
</div>

<div id="toastPlanta"></div>
<script src="../js/menu.js"></script>
<script>
/* ═══ DADOS ═══ */
var _ambientes  = <?php echo $ambientesJson; ?>;
var _pisos      = <?php echo $pisosJson; ?>;   // [{id,nome,imagem,zonas:[]}]
var _pisoAtivo  = 0;   // índice em _pisos
var _sel        = null; // índice da zona selecionada
var _drag       = null;
var _resize     = null;
var _draw       = null;
var _renameIdx  = null;

/* Mapa ambiente */
var _ambMap = {};
_ambientes.forEach(function(a){ _ambMap[a.idLaboratorio] = a; });

/* ── Garante pelo menos 1 piso ── */
if(_pisos.length === 0){
    _pisos.push({id: Date.now(), nome:'Piso 1', imagem:null, zonas:[]});
}

/* ═══ PISOS ═══ */
function renderPisos(){
    // Abas no topo
    var tabs = document.getElementById('pisoTabs');
    tabs.innerHTML = '';
    _pisos.forEach(function(p, i){
        var tab = document.createElement('div');
        tab.className = 'piso-tab' + (i===_pisoAtivo?' ativo':'');
        tab.innerHTML = '<span ondblclick="renamePiso('+i+')" style="cursor:text">'+esc(p.nome)+'</span>'
            + '<span class="tab-del" onclick="excluirPiso('+i+')" title="Excluir piso">✕</span>';
        tab.onclick = function(e){ if(!e.target.classList.contains('tab-del')) trocarPiso(i); };
        tabs.appendChild(tab);
    });
    var add = document.createElement('button');
    add.className='btn-add-piso'; add.innerHTML='<i class="fas fa-plus"></i>';
    add.onclick = adicionarPiso;
    tabs.appendChild(add);

    // Lista na sidebar
    var sb = document.getElementById('pisoListaSb');
    sb.innerHTML = '';
    _pisos.forEach(function(p, i){
        var d = document.createElement('div');
        d.style.cssText='display:flex;align-items:center;gap:6px;padding:4px 6px;border-radius:4px;cursor:pointer;font-size:.78rem;'
            +(i===_pisoAtivo?'background:#e8f0fe;font-weight:700;':'');
        d.innerHTML = '<i class="fas fa-layer-group" style="color:#6c757d;font-size:.7rem"></i>'
            + esc(p.nome);
        d.onclick = function(){ trocarPiso(i); };
        sb.appendChild(d);
    });

    renderCanvas();
}

function trocarPiso(i){
    _sel = null;
    _pisoAtivo = i;
    renderPisos();
}

function adicionarPiso(){
    _pisos.push({id:Date.now(), nome:'Piso '+ (_pisos.length+1), imagem:null, zonas:[]});
    trocarPiso(_pisos.length-1);
}

function excluirPiso(i){
    if(_pisos.length<=1){ mostrarToast('Deve existir ao menos 1 piso.','erro'); return; }
    if(!confirm('Excluir o piso "'+_pisos[i].nome+'" e todas as suas zonas?')) return;
    _pisos.splice(i,1);
    if(_pisoAtivo >= _pisos.length) _pisoAtivo = _pisos.length-1;
    _sel = null;
    renderPisos();
}

function renamePiso(i){
    _renameIdx = i;
    document.getElementById('pisoNomeInput').value = _pisos[i].nome;
    $('#modalPiso').modal('show');
    setTimeout(function(){ document.getElementById('pisoNomeInput').select(); },300);
}
function confirmarRenamePiso(){
    var v = document.getElementById('pisoNomeInput').value.trim();
    if(!v) return;
    _pisos[_renameIdx].nome = v;
    $('#modalPiso').modal('hide');
    renderPisos();
}

/* ═══ CANVAS ═══ */
function pisoAtual(){ return _pisos[_pisoAtivo]; }
function zonasAtual(){ return pisoAtual().zonas; }

function corZona(z){
    if(!z.idLaboratorio) return '';
    var a = _ambMap[z.idLaboratorio];
    if(!a) return '';
    var porta = !!a.macPorta, ar = !!a.macArCondicionado;
    if(porta&&ar) return 'tem-ambos';
    if(porta)     return 'tem-porta';
    if(ar)        return 'tem-ar';
    return '';
}

function renderCanvas(){
    var piso   = pisoAtual();
    var zonas  = piso.zonas || [];
    var img    = document.getElementById('plantaImg');
    var hint   = document.getElementById('semImagem');
    var btn    = document.getElementById('btnUploadTxt');

    if(piso.imagem){
        img.src = piso.imagem; img.style.display='block';
        hint.style.display='none';
        btn.textContent='Trocar imagem';
        var btnEditar=document.getElementById('btnEditarImg');
        if(btnEditar) btnEditar.style.display='';
    } else {
        img.style.display='none';
        hint.style.display='';
        btn.textContent='Enviar imagem';
        var btnEditar=document.getElementById('btnEditarImg');
        if(btnEditar) btnEditar.style.display='none';
    }

    // Zonas
    var layer = document.getElementById('zonaLayer');
    layer.innerHTML='';
    var lista = document.getElementById('zonaLista');
    lista.innerHTML='';
    document.getElementById('zonaCount').textContent = zonas.length;
    document.getElementById('btnExcluir').disabled   = (_sel===null);

    zonas.forEach(function(z,idx){
        var div = document.createElement('div');
        div.className='zona '+corZona(z)+(idx===_sel?' selecionada':'');
        div.style.cssText='left:'+z.x+'%;top:'+z.y+'%;width:'+z.w+'%;height:'+z.h+'%';
        div.dataset.idx=idx;
        var lbl=document.createElement('div'); lbl.className='zona-label';
        lbl.textContent=z.label||(_ambMap[z.idLaboratorio]?_ambMap[z.idLaboratorio].nome:'#'+(idx+1));
        var rz=document.createElement('div'); rz.className='zona-resize'; rz.dataset.idx=idx;
        div.appendChild(lbl); div.appendChild(rz);
        layer.appendChild(div);

        // Sidebar item
        var item=document.createElement('div');
        item.className='zona-item'+(idx===_sel?' ativo':'');
        item.dataset.idx=idx;
        var cor=document.createElement('span'); cor.className='zi-cor';
        cor.style.cssText=div.classList.contains('tem-ambos')?'background:#6a1b9a':
                          div.classList.contains('tem-porta')?'background:#2e7d32':
                          div.classList.contains('tem-ar')   ?'background:#1565c0':'background:#0d6efd';
        var nm=document.createElement('span'); nm.className='zi-nome';
        nm.textContent=lbl.textContent;
        var btnE=document.createElement('button');
        btnE.style.cssText='border:none;background:none;font-size:.68rem;color:#6c757d;cursor:pointer;padding:0 2px';
        btnE.innerHTML='<i class="fas fa-pen"></i>';
        btnE.onclick=function(e){e.stopPropagation();selecionarZona(parseInt(item.dataset.idx));abrirModalZona();};
        item.appendChild(cor);item.appendChild(nm);item.appendChild(btnE);
        item.onclick=function(){selecionarZona(parseInt(this.dataset.idx));};
        lista.appendChild(item);
    });
}

function selecionarZona(idx){
    _sel=idx; renderCanvas();
}

/* ── Eventos do canvas ── */
var cw=document.getElementById('canvasWrap');

cw.addEventListener('mousedown',function(e){
    if(e.target.closest('.zona')) return;
    if(!pisoAtual().imagem) return;
    var r=cw.getBoundingClientRect();
    _draw={x0:(e.clientX-r.left)/r.width*100,y0:(e.clientY-r.top)/r.height*100};
    e.preventDefault();
});

document.getElementById('zonaLayer').addEventListener('mousedown',function(e){
    var zonaEl=e.target.closest('.zona');
    if(!zonaEl) return;
    var idx=parseInt(zonaEl.dataset.idx);
    if(e.target.classList.contains('zona-resize')){
        var z=zonasAtual()[idx], r=cw.getBoundingClientRect();
        _resize={idx,ox:e.clientX,oy:e.clientY,ow:z.w,oh:z.h,cw:r.width,ch:r.height};
        selecionarZona(idx); e.preventDefault(); e.stopPropagation(); return;
    }
    var z=zonasAtual()[idx], r=cw.getBoundingClientRect();
    _drag={idx,ox:e.clientX-(z.x/100*r.width),oy:e.clientY-(z.y/100*r.height),cw:r.width,ch:r.height};
    selecionarZona(idx); e.preventDefault(); e.stopPropagation();
});

document.addEventListener('mousemove',function(e){
    var r=cw.getBoundingClientRect();
    if(_draw){
        var x=(e.clientX-r.left)/r.width*100, y=(e.clientY-r.top)/r.height*100;
        var layer=document.getElementById('zonaLayer');
        var prev=layer.querySelector('.zona-preview');
        if(!prev){prev=document.createElement('div');prev.className='zona zona-preview';layer.appendChild(prev);}
        var x0=Math.min(_draw.x0,x),y0=Math.min(_draw.y0,y),w=Math.abs(x-_draw.x0),h=Math.abs(y-_draw.y0);
        prev.style.cssText='left:'+x0+'%;top:'+y0+'%;width:'+w+'%;height:'+h+'%;pointer-events:none;opacity:.5';
    }
    if(_drag){
        var z=zonasAtual()[_drag.idx];
        z.x=Math.max(0,Math.min(100-z.w,(e.clientX-_drag.ox)/_drag.cw*100));
        z.y=Math.max(0,Math.min(100-z.h,(e.clientY-_drag.oy)/_drag.ch*100));
        renderCanvas();
    }
    if(_resize){
        var z=zonasAtual()[_resize.idx];
        z.w=Math.max(3,_resize.ow+(e.clientX-_resize.ox)/_resize.cw*100);
        z.h=Math.max(2,_resize.oh+(e.clientY-_resize.oy)/_resize.ch*100);
        if(z.x+z.w>100) z.w=100-z.x;
        if(z.y+z.h>100) z.h=100-z.y;
        renderCanvas();
    }
});

document.addEventListener('mouseup',function(e){
    if(_draw){
        var r=cw.getBoundingClientRect();
        var x=(e.clientX-r.left)/r.width*100,y=(e.clientY-r.top)/r.height*100;
        var x0=Math.min(_draw.x0,x),y0=Math.min(_draw.y0,y),w=Math.abs(x-_draw.x0),h=Math.abs(y-_draw.y0);
        _draw=null;
        var prev=document.querySelector('.zona-preview'); if(prev) prev.remove();
        if(w<2||h<1) return;
        zonasAtual().push({id:Date.now(),x:x0,y:y0,w:w,h:h,label:'',idLaboratorio:null});
        _sel=zonasAtual().length-1;
        renderCanvas();
        abrirModalZona();
    }
    _drag=null; _resize=null;
});

document.getElementById('zonaLayer').addEventListener('dblclick',function(e){
    var el=e.target.closest('.zona'); if(!el) return;
    selecionarZona(parseInt(el.dataset.idx)); abrirModalZona();
});

/* ── Modal zona ── */
function abrirModalZona(){
    if(_sel===null) return;
    var z=zonasAtual()[_sel];
    document.getElementById('zonaLabel').value    = z.label||'';
    document.getElementById('zonaAmbiente').value = z.idLaboratorio||'';
    $('#modalZona').modal('show');
}
function confirmarZona(){
    if(_sel===null) return;
    var z=zonasAtual()[_sel];
    z.label        = document.getElementById('zonaLabel').value.trim();
    z.idLaboratorio= parseInt(document.getElementById('zonaAmbiente').value)||null;
    $('#modalZona').modal('hide');
    renderCanvas();
}
function excluirZona(){
    if(_sel===null) return;
    if(!confirm('Excluir esta zona?')) return;
    zonasAtual().splice(_sel,1); _sel=null; renderCanvas();
}

/* ── Editor de imagem com Cropper.js ── */
var cropper = null;
var _arquivoOriginal = null;

function editarImagemAtual(){
    /* Carrega a imagem atual do piso no Cropper sem pedir novo arquivo */
    var piso = pisoAtual();
    if(!piso.imagem){ mostrarToast('Nenhuma imagem carregada.','erro'); return; }
    var img = document.getElementById('cropperImg');
    // Remove query string de cache (?v=...) para evitar problemas de CORS local
    img.src = piso.imagem.split('?')[0] + '?nocache=' + Date.now();
    img.crossOrigin = 'anonymous';
    _arquivoOriginal = null; // indica que veio da edição, não de upload
    if(cropper){ cropper.destroy(); cropper=null; }
    $('#modalEditorImg').modal('show');
    $('#modalEditorImg').one('shown.bs.modal', function(){
        cropper = new Cropper(img, {
            viewMode: 1, dragMode: 'move', autoCropArea: 1,
            restore: false, guides: true, center: true,
            highlight: false, cropBoxMovable: true, cropBoxResizable: true,
            toggleDragModeOnDblclick: true,
        });
    });
}

function abrirEditor(input){
    if(!input.files||!input.files[0]) return;
    _arquivoOriginal = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e){
        var img = document.getElementById('cropperImg');
        img.src = e.target.result;
        // Destrói cropper anterior se existir
        if(cropper){ cropper.destroy(); cropper=null; }
        $('#modalEditorImg').modal('show');
        // Inicializa Cropper após o modal abrir
        $('#modalEditorImg').one('shown.bs.modal', function(){
            cropper = new Cropper(img, {
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: true,
            });
        });
    };
    reader.readAsDataURL(_arquivoOriginal);
    // Limpa o input para permitir reenvio do mesmo arquivo
    input.value='';
}

function setRatio(ratio, btn){
    if(cropper) cropper.setAspectRatio(ratio);
    document.querySelectorAll('.btn-ratio').forEach(function(b){ b.classList.remove('ativo'); });
    if(btn) btn.classList.add('ativo');
}

function usarSemCorte(){
    $('#modalEditorImg').modal('hide');
    if(_arquivoOriginal){
        // Veio de upload novo — envia sem corte
        enviarArquivo(_arquivoOriginal);
    }
    // Veio de "editar imagem atual" sem arquivo — apenas fecha
}

function aplicarCorte(){
    if(!cropper) return;
    // Gera canvas recortado/rotacionado
    var canvas = cropper.getCroppedCanvas({
        maxWidth:  4096,
        maxHeight: 4096,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });
    $('#modalEditorImg').modal('hide');
    // Converte canvas para Blob e envia
    canvas.toBlob(function(blob){
        var ext = (_arquivoOriginal.name.match(/\.([^.]+)$/) || ['','jpg'])[1].toLowerCase();
        if(ext==='jpg') ext='jpeg';
        var file = new File([blob], 'planta.'+ext, {type:'image/'+ext});
        enviarArquivo(file);
    }, 'image/'+( (_arquivoOriginal.type || 'image/jpeg').split('/')[1] || 'jpeg' ), 0.92);
}

function enviarArquivo(file){
    mostrarToast('Enviando imagem...','sucesso');
    var fd=new FormData();
    fd.append('acao','upload_fundo');
    fd.append('piso_id', pisoAtual().id);
    fd.append('imagem', file);
    fetch('plantaEditor.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            pisoAtual().imagem=res.url;
            mostrarToast('Imagem salva!','sucesso');
            renderCanvas();
        })
        .catch(function(){mostrarToast('Erro no upload.','erro');});
}

/* ── Salvar tudo ── */
function salvarTudo(){
    var fd=new FormData();
    fd.append('acao','salvar_planta');
    fd.append('pisos',JSON.stringify(_pisos));
    fetch('plantaEditor.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){mostrarToast(res.ok?'Planta salva!':res.msg||'Erro.',res.ok?'sucesso':'erro');})
        .catch(function(){mostrarToast('Erro ao salvar.','erro');});
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
var _tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastPlanta');
    el.textContent=msg;el.className=tipo==='sucesso'?'sucesso':'erro';el.style.display='block';
    if(_tt)clearTimeout(_tt);
    _tt=setTimeout(function(){el.style.display='none';},3000);
}

renderPisos();
</script>
</body>
</html>