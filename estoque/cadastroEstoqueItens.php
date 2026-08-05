<?php
/*
 * cadastroEstoqueItens.php
 * Cadastro de itens de estoque por laboratório/ambiente.
 * Apenas ambientes com temEstoque=1 aparecem.
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    header('location:../index.php'); exit;
}

/* ── POST AJAX ── */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    /* Salvar/editar item */
    if($_POST['acao']==='salvar_item'){
        $id       = intval($_POST['id']            ?? 0);
        $idLab    = intval($_POST['idLaboratorio']  ?? 0);
        $desc     = trim($_POST['descricao']        ?? '');
        $idUn     = intval($_POST['idUnidade']      ?? 0);
        $minimo   = floatval($_POST['estoqueMinimo']?? 0);
        if(!$idLab || !$desc || !$idUn){
            echo json_encode(['ok'=>false,'msg'=>'Preencha todos os campos obrigatórios.']); exit;
        }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE estoque_itens SET idLaboratorio=?,descricao=?,idUnidade=?,estoqueMinimo=? WHERE id=?")
                    ->execute([$idLab,$desc,$idUn,$minimo,$id]);
            } else {
                $pdo->prepare("INSERT INTO estoque_itens (idLaboratorio,descricao,idUnidade,estoqueMinimo) VALUES (?,?,?,?)")
                    ->execute([$idLab,$desc,$idUn,$minimo]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar: '.$e->getMessage()]);
        }
        exit;
    }

    /* Excluir item */
    if($_POST['acao']==='excluir_item'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE estoque_itens SET ativo=0 WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* Salvar/editar unidade */
    if($_POST['acao']==='salvar_unidade'){
        $id   = intval($_POST['id']          ?? 0);
        $nome = trim($_POST['nome']          ?? '');
        $abv  = trim($_POST['abreviacao']    ?? '');
        if(!$nome || !$abv){
            echo json_encode(['ok'=>false,'msg'=>'Nome e abreviação são obrigatórios.']); exit;
        }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE estoque_unidades SET nome=?,abreviacao=? WHERE id=?")
                    ->execute([$nome,$abv,$id]);
            } else {
                $pdo->prepare("INSERT INTO estoque_unidades (nome,abreviacao) VALUES (?,?)")
                    ->execute([$nome,$abv]);
                $id = $pdo->lastInsertId();
            }
            // Retorna lista atualizada
            $uns = $pdo->query("SELECT id,nome,abreviacao FROM estoque_unidades ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok'=>true,'id'=>$id,'unidades'=>$uns]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro (unidade já existe?).']);
        }
        exit;
    }

    /* Excluir unidade */
    if($_POST['acao']==='excluir_unidade'){
        $id = intval($_POST['id'] ?? 0);
        try {
            // Verifica se está em uso
            $uso = $pdo->prepare("SELECT COUNT(*) FROM estoque_itens WHERE idUnidade=? AND ativo=1");
            $uso->execute([$id]);
            if($uso->fetchColumn() > 0){
                echo json_encode(['ok'=>false,'msg'=>'Unidade em uso por itens ativos.']); exit;
            }
            $pdo->prepare("DELETE FROM estoque_unidades WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$laboratorios = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temEstoque=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$unidades = $pdo->query(
    "SELECT id, nome, abreviacao FROM estoque_unidades ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

// Laboratório selecionado
$idLabSel = intval($_GET['lab'] ?? ($laboratorios[0]['idLaboratorio'] ?? 0));

// Itens do laboratório selecionado
$itens = [];
if($idLabSel){
    $st = $pdo->prepare("
        SELECT i.id, i.descricao, i.estoqueMinimo, i.ativo,
               u.nome AS unidadeNome, u.abreviacao AS unidadeAbv, u.id AS idUnidade
        FROM estoque_itens i
        JOIN estoque_unidades u ON u.id = i.idUnidade
        WHERE i.idLaboratorio = ? AND i.ativo = 1
        ORDER BY i.descricao
    ");
    $st->execute([$idLabSel]);
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);
    if(isset($_GET['debug'])){ echo '<pre>IDs retornados: '; print_r(array_column($itens,'id')); exit; }
}

$labSelNome = '';
foreach($laboratorios as $l) if($l['idLaboratorio']==$idLabSel) $labSelNome=$l['nome'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro de Itens — Estoque</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.lab-selector{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
.lab-btn{padding:7px 16px;border-radius:6px;border:2px solid #dee2e6;background:#fff;
    font-size:.83rem;font-weight:600;cursor:pointer;color:#495057;text-decoration:none;transition:all .15s;}
.lab-btn:hover{border-color:#0d6efd;color:#0d6efd;text-decoration:none;}
.lab-btn.ativo{border-color:#0d6efd;background:#0d6efd;color:#fff;}
.item-table th{background:#343a40;color:#fff;font-size:.8rem;white-space:nowrap;}
.item-table td{font-size:.83rem;vertical-align:middle;}
.badge-unidade{background:#e8f5e9;color:#1b5e20;border-radius:4px;padding:2px 8px;
    font-size:.72rem;font-weight:700;}
.minimo-badge{background:#fff3e0;color:#e65100;border-radius:4px;padding:2px 8px;
    font-size:.72rem;font-weight:700;}
.unidade-row{display:flex;align-items:center;justify-content:space-between;
    padding:6px 12px;border-bottom:1px solid #f0f0f0;font-size:.83rem;}
.unidade-row:last-child{border:none;}
#toast{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toast.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toast.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.empty-state{text-align:center;padding:40px 20px;color:#adb5bd;}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:10px;}
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
    <h4 class="mb-0"><i class="fas fa-boxes mr-2"></i>Cadastro de Itens — Estoque</h4>
    <div style="display:flex;gap:8px">
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#modalUnidades">
            <i class="fas fa-ruler mr-1"></i>Gerenciar Unidades
        </button>
        <?php if($idLabSel): ?>
        <button class="btn btn-primary btn-sm" onclick="abrirNovoItem()">
            <i class="fas fa-plus mr-1"></i>Novo Item
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if(empty($laboratorios)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    Nenhum laboratório/ambiente com estoque habilitado. Acesse o cadastro de laboratórios e marque <strong>Tem Estoque</strong>.
</div>
<?php else: ?>

<!-- Seletor de ambiente -->
<div class="lab-selector">
    <?php foreach($laboratorios as $l): ?>
    <a href="?lab=<?php echo $l['idLaboratorio']; ?>"
       class="lab-btn <?php echo $l['idLaboratorio']==$idLabSel?'ativo':''; ?>">
        <i class="fas fa-flask mr-1"></i><?php echo htmlspecialchars($l['nome']); ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Tabela de itens -->
<?php if($idLabSel): ?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 text-muted">
        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($labSelNome); ?>
        <span class="badge badge-secondary ml-1"><?php echo count($itens); ?> itens</span>
    </h6>
</div>

<?php if(empty($itens)): ?>
<div class="empty-state">
    <i class="fas fa-box-open"></i>
    Nenhum item cadastrado neste ambiente.<br>
    <button class="btn btn-primary btn-sm mt-3" onclick="abrirNovoItem()">
        <i class="fas fa-plus mr-1"></i>Cadastrar primeiro item
    </button>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-bordered item-table bg-white">
    <thead>
        <tr>
            <th>Descrição</th>
            <th style="width:140px">Unidade</th>
            <th style="width:160px">Estoque Mínimo</th>
            <th style="width:90px" class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($itens as $it): ?>
    <tr id="irow-<?php echo $it['id']; ?>">
        <td><?php echo htmlspecialchars($it['descricao']); ?></td>
        <td>
            <span class="badge-unidade">
                <?php echo htmlspecialchars($it['unidadeNome']); ?>
                (<?php echo htmlspecialchars($it['unidadeAbv']); ?>)
            </span>
        </td>
        <td>
            <?php if($it['estoqueMinimo'] > 0): ?>
            <span class="minimo-badge">
                <i class="fas fa-exclamation-circle mr-1"></i>
                Mín: <?php echo number_format($it['estoqueMinimo'],2,',','.'); ?>
                <?php echo htmlspecialchars($it['unidadeAbv']); ?>
            </span>
            <?php else: ?>
            <span class="text-muted" style="font-size:.75rem">Sem mínimo</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <button class="btn btn-outline-primary btn-sm"
                    onclick="editarItem(<?php echo $it['id']; ?>,'<?php echo addslashes($it['descricao']); ?>',<?php echo $it['idUnidade']; ?>,<?php echo $it['estoqueMinimo']; ?>)">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm ml-1"
                    onclick="excluirItem(<?php echo $it['id']; ?>)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

</div></div>

<!-- Modal Novo/Editar Item -->
<div class="modal fade" id="modalItem" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalItemTitle">
            <i class="fas fa-box mr-2"></i>Item de Estoque
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="iId" value="0">
        <div class="form-group mb-3">
            <label class="font-weight-bold" style="font-size:.82rem">Descrição <span class="text-danger">*</span></label>
            <input type="text" id="iDesc" class="form-control form-control-sm"
                   placeholder="Ex: Luva de nitrila, Álcool isopropílico, Papel toalha...">
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Unidade <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <select id="iUnidade" class="form-control form-control-sm">
                        <option value="">— Selecione —</option>
                        <?php foreach($unidades as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['nome']); ?> (<?php echo htmlspecialchars($u['abreviacao']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="$('#modalItem').modal('hide');$('#modalUnidades').modal('show')"
                                title="Gerenciar unidades">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Estoque Mínimo</label>
                <input type="number" id="iMinimo" class="form-control form-control-sm"
                       min="0" step="0.01" value="0"
                       placeholder="0 = sem alerta">
                <small class="text-muted">Alerta de compra quando atingir este valor</small>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarItem()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<!-- Modal Gerenciar Unidades -->
<div class="modal fade" id="modalUnidades" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-ruler mr-2"></i>Unidades de Medida</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body" style="padding:0">
        <!-- Formulário nova unidade -->
        <div style="padding:14px 16px;border-bottom:1px solid #dee2e6;background:#f8f9fa">
            <div class="form-row align-items-end">
                <div class="col">
                    <label style="font-size:.78rem;font-weight:600">Nome</label>
                    <input type="text" id="uNome" class="form-control form-control-sm" placeholder="Ex: Caixa com 12">
                </div>
                <div class="col-4">
                    <label style="font-size:.78rem;font-weight:600">Abreviação</label>
                    <input type="text" id="uAbv" class="form-control form-control-sm" placeholder="cx/12">
                </div>
                <div class="col-auto">
                    <input type="hidden" id="uId" value="0">
                    <button class="btn btn-primary btn-sm" onclick="salvarUnidade()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Lista de unidades -->
        <div id="listaUnidades" style="max-height:300px;overflow-y:auto">
            <?php foreach($unidades as $u): ?>
            <div class="unidade-row" id="urow-<?php echo $u['id']; ?>">
                <span>
                    <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
                    <span class="text-muted ml-2"><?php echo htmlspecialchars($u['abreviacao']); ?></span>
                </span>
                <div style="display:flex;gap:4px">
                    <button class="btn btn-outline-primary btn-sm"
                            onclick="editarUnidade(<?php echo $u['id']; ?>,'<?php echo addslashes($u['nome']); ?>','<?php echo addslashes($u['abreviacao']); ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="excluirUnidade(<?php echo $u['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Fechar</button>
    </div>
</div></div>
</div>

<div id="toast"></div>
<script src="../js/menu.js"></script>
<script>
var _idLabSel = <?php echo $idLabSel ?: 0; ?>;

/* ── Item ── */
function abrirNovoItem(){
    document.getElementById('iId').value='0';
    document.getElementById('iDesc').value='';
    document.getElementById('iUnidade').value='';
    document.getElementById('iMinimo').value='0';
    document.getElementById('modalItemTitle').innerHTML='<i class="fas fa-plus mr-2"></i>Novo Item';
    $('#modalItem').modal('show');
}
function editarItem(id,desc,idUn,minimo){
    document.getElementById('iId').value=id;
    document.getElementById('iDesc').value=desc;
    document.getElementById('iUnidade').value=idUn;
    document.getElementById('iMinimo').value=minimo;
    document.getElementById('modalItemTitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar Item';
    $('#modalItem').modal('show');
}
function salvarItem(){
    var id     = document.getElementById('iId').value;
    var desc   = document.getElementById('iDesc').value.trim();
    var idUn   = document.getElementById('iUnidade').value;
    var minimo = document.getElementById('iMinimo').value||'0';
    if(!desc||!idUn){ alert('Preencha descrição e unidade.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_item'); fd.append('id',id);
    fd.append('idLaboratorio',_idLabSel); fd.append('descricao',desc);
    fd.append('idUnidade',idUn); fd.append('estoqueMinimo',minimo);
    fetch('cadastroEstoqueItens.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            toast('Salvo!','ok');
            $('#modalItem').modal('hide');
            setTimeout(function(){location.reload();},600);
        }).catch(function(){toast('Erro.','err');});
}
function excluirItem(id){
    if(!confirm('Remover este item do estoque?')) return;
    var fd=new FormData(); fd.append('acao','excluir_item'); fd.append('id',id);
    fetch('cadastroEstoqueItens.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            var el=document.getElementById('irow-'+id); if(el) el.remove();
            toast('Removido.','ok');
        }).catch(function(){toast('Erro.','err');});
}

/* ── Unidade ── */
function editarUnidade(id,nome,abv){
    document.getElementById('uId').value=id;
    document.getElementById('uNome').value=nome;
    document.getElementById('uAbv').value=abv;
}
function salvarUnidade(){
    var id   = document.getElementById('uId').value;
    var nome = document.getElementById('uNome').value.trim();
    var abv  = document.getElementById('uAbv').value.trim();
    if(!nome||!abv){ alert('Preencha nome e abreviação.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_unidade'); fd.append('id',id);
    fd.append('nome',nome); fd.append('abreviacao',abv);
    fetch('cadastroEstoqueItens.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            toast('Unidade salva!','ok');
            // Atualiza lista de unidades no modal
            var lista=document.getElementById('listaUnidades');
            lista.innerHTML='';
            res.unidades.forEach(function(u){
                lista.innerHTML+='<div class="unidade-row" id="urow-'+u.id+'">'
                    +'<span><strong>'+escHTML(u.nome)+'</strong><span class="text-muted ml-2">'+escHTML(u.abreviacao)+'</span></span>'
                    +'<div style="display:flex;gap:4px">'
                    +'<button class="btn btn-outline-primary btn-sm" onclick="editarUnidade('+u.id+',\''+escJS(u.nome)+'\',\''+escJS(u.abreviacao)+'\')"><i class="fas fa-edit"></i></button>'
                    +'<button class="btn btn-outline-danger btn-sm" onclick="excluirUnidade('+u.id+')"><i class="fas fa-trash"></i></button>'
                    +'</div></div>';
            });
            // Atualiza select de unidades no modal de item
            var sel=document.getElementById('iUnidade');
            sel.innerHTML='<option value="">— Selecione —</option>';
            res.unidades.forEach(function(u){
                sel.innerHTML+='<option value="'+u.id+'">'+escHTML(u.nome)+' ('+escHTML(u.abreviacao)+')</option>';
            });
            // Limpa form
            document.getElementById('uId').value='0';
            document.getElementById('uNome').value='';
            document.getElementById('uAbv').value='';
        }).catch(function(){toast('Erro.','err');});
}
function excluirUnidade(id){
    if(!confirm('Excluir esta unidade?')) return;
    var fd=new FormData(); fd.append('acao','excluir_unidade'); fd.append('id',id);
    fetch('cadastroEstoqueItens.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            var el=document.getElementById('urow-'+id); if(el) el.remove();
            toast('Removida.','ok');
        }).catch(function(){toast('Erro.','err');});
}

function escHTML(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escJS(s){ return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toast');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>