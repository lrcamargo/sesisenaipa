<?php
/*
 * cadastroFeriasInstrutor.php
 * Cadastro de períodos de férias/ausência de instrutores.
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

    if($_POST['acao']==='salvar'){
        $id      = intval($_POST['id'] ?? 0); // 0 = novo
        $idInst  = intval($_POST['idInstrutor'] ?? 0);
        $inicio  = trim($_POST['dataInicio'] ?? '');
        $fim     = trim($_POST['dataFim']    ?? '');
        $desc    = trim($_POST['descricao']  ?? 'Férias');
        if(!$idInst || !$inicio || !$fim){
            echo json_encode(['ok'=>false,'msg'=>'Preencha todos os campos.']); exit;
        }
        if($fim < $inicio){
            echo json_encode(['ok'=>false,'msg'=>'Data fim deve ser maior que início.']); exit;
        }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE instrutor_ferias SET idInstrutor=?,dataInicio=?,dataFim=?,descricao=? WHERE id=?")
                    ->execute([$idInst,$inicio,$fim,$desc,$id]);
                echo json_encode(['ok'=>true,'id'=>$id]);
            } else {
                $pdo->prepare("INSERT INTO instrutor_ferias (idInstrutor,dataInicio,dataFim,descricao) VALUES (?,?,?,?)")
                    ->execute([$idInst,$inicio,$fim,$desc]);
                echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
            }
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
        }
        exit;
    }

    if($_POST['acao']==='excluir'){
        $id=intval($_POST['id']??0);
        try {
            $pdo->prepare("DELETE FROM instrutor_ferias WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    if($_POST['acao']==='carregar'){
        $id=$intval($_POST['id']??0);
        $st=$pdo->prepare("SELECT f.id,f.idInstrutor,f.dataInicio,f.dataFim,f.descricao FROM instrutor_ferias f WHERE f.id=?");
        $st->execute([$id]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row){ echo json_encode(['ok'=>false,'msg'=>'Não encontrado.']); exit; }
        echo json_encode(['ok'=>true,'data'=>$row]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$instrutores = $pdo->query(
    "SELECT id, nome FROM usuarios WHERE perfil='Instrutor' ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$registros = $pdo->query("
    SELECT f.id, f.idInstrutor, f.dataInicio, f.dataFim, f.descricao, f.criado_em,
           u.nome AS nomeInstrutor
    FROM instrutor_ferias f
    JOIN usuarios u ON u.id = f.idInstrutor
    ORDER BY f.dataInicio DESC, u.nome
")->fetchAll(PDO::FETCH_ASSOC);

$porInstrutor = [];
foreach($registros as $r) $porInstrutor[$r['nomeInstrutor']][] = $r;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Férias de Instrutores</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.inst-group{background:#fff;border:1px solid #dee2e6;border-radius:8px;margin-bottom:10px;overflow:hidden;}
.inst-header{padding:10px 14px;background:#f8f9fa;border-bottom:1px solid #dee2e6;
    font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:8px;}
.inst-header i{color:#0d6efd;}
.ferias-row{display:flex;align-items:center;justify-content:space-between;
    padding:8px 14px;border-bottom:1px solid #f5f5f5;font-size:.83rem;}
.ferias-row:last-child{border:none;}
.ferias-periodo{font-weight:600;color:#212529;}
.ferias-desc{color:#6c757d;font-size:.78rem;}
.ferias-dias{background:#e3f2fd;color:#0d47a1;border-radius:4px;padding:1px 7px;font-size:.72rem;font-weight:700;}
#toastFI{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastFI.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastFI.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
    <h4 class="mb-0"><i class="fas fa-plane-departure mr-2"></i>Férias de Instrutores</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirNovo()">
        <i class="fas fa-plus mr-1"></i>Novo período
    </button>
</div>

<p class="text-muted" style="font-size:.83rem">
    <i class="fas fa-info-circle mr-1"></i>
    Instrutores em férias não aparecem como disponíveis no Painel de Docentes
    e geram alerta se tiverem aula agendada no período.
</p>

<?php if(empty($registros)): ?>
<div class="text-muted text-center py-5">
    <i class="fas fa-plane-departure" style="font-size:2rem;display:block;margin-bottom:8px"></i>
    Nenhum período de férias cadastrado.
</div>
<?php else: ?>
<?php foreach($porInstrutor as $nomeInst => $feriasInst): ?>
<div class="inst-group">
    <div class="inst-header">
        <i class="fas fa-user-tie"></i>
        <?php echo htmlspecialchars(mb_strtoupper($nomeInst)); ?>
        <span class="badge badge-secondary ml-auto"><?php echo count($feriasInst); ?></span>
    </div>
    <?php foreach($feriasInst as $f):
        $di = date('d/m/Y', strtotime($f['dataInicio']));
        $df = date('d/m/Y', strtotime($f['dataFim']));
        $dias = (strtotime($f['dataFim'])-strtotime($f['dataInicio']))/86400+1;
        $hoje = date('Y-m-d');
        $ativo = $f['dataInicio']<=$hoje && $f['dataFim']>=$hoje;
    ?>
    <div class="ferias-row" id="frow-<?php echo $f['id']; ?>">
        <div>
            <div class="ferias-periodo">
                <?php if($ativo): ?><span style="color:#2e7d32">● </span><?php endif; ?>
                <?php echo $di; ?> → <?php echo $df; ?>
                <span class="ferias-dias ml-1"><?php echo $dias; ?> dias</span>
            </div>
            <div class="ferias-desc"><?php echo htmlspecialchars($f['descricao']); ?></div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
            <button class="btn btn-outline-primary btn-sm"
                    onclick="editar(<?php echo $f['id']; ?>,<?php echo $f['idInstrutor']; ?>,'<?php echo addslashes($f['dataInicio']); ?>','<?php echo addslashes($f['dataFim']); ?>','<?php echo addslashes($f['descricao']); ?>')">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="excluir(<?php echo $f['id']; ?>)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div>

<!-- Modal novo/editar período -->
<div class="modal fade" id="modalNovaFerias" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalFITitle">
            <i class="fas fa-plane-departure mr-2"></i>Período de férias
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="fId" value="0">
        <div class="form-group mb-2">
            <label class="font-weight-bold" style="font-size:.82rem">Instrutor</label>
            <select id="fInstId" class="form-control form-control-sm">
                <option value="">— Selecione —</option>
                <?php foreach($instrutores as $i): ?>
                <option value="<?php echo $i['id']; ?>">
                    <?php echo htmlspecialchars(mb_strtoupper($i['nome'])); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-2">
            <label class="font-weight-bold" style="font-size:.82rem">Descrição</label>
            <input type="text" id="fDesc" class="form-control form-control-sm"
                   value="Férias" placeholder="Ex: Férias, Licença médica, Congresso...">
        </div>
        <div class="form-row">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Data início</label>
                <input type="date" id="fInicio" class="form-control form-control-sm">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Data fim</label>
                <input type="date" id="fFim" class="form-control form-control-sm">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvar()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastFI"></div>
<script src="../js/menu.js"></script>
<script>
function abrirNovo(){
    document.getElementById('fId').value='0';
    document.getElementById('fInstId').value='';
    document.getElementById('fDesc').value='Férias';
    document.getElementById('fInicio').value='';
    document.getElementById('fFim').value='';
    document.getElementById('modalFITitle').innerHTML='<i class="fas fa-plane-departure mr-2"></i>Novo período de férias';
    $('#modalNovaFerias').modal('show');
}

function editar(id, idInst, inicio, fim, desc){
    document.getElementById('fId').value=id;
    document.getElementById('fInstId').value=idInst;
    document.getElementById('fDesc').value=desc;
    document.getElementById('fInicio').value=inicio;
    document.getElementById('fFim').value=fim;
    document.getElementById('modalFITitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar período de férias';
    $('#modalNovaFerias').modal('show');
}

function salvar(){
    var id    = document.getElementById('fId').value;
    var idI   = document.getElementById('fInstId').value;
    var desc  = document.getElementById('fDesc').value.trim()||'Férias';
    var inicio= document.getElementById('fInicio').value;
    var fim   = document.getElementById('fFim').value;
    if(!idI||!inicio||!fim){ alert('Preencha todos os campos.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar'); fd.append('id',id);
    fd.append('idInstrutor',idI); fd.append('descricao',desc);
    fd.append('dataInicio',inicio); fd.append('dataFim',fim);
    fetch('cadastroFeriasInstrutor.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            toast('Salvo!','ok');
            $('#modalNovaFerias').modal('hide');
            setTimeout(function(){location.reload();},700);
        }).catch(function(){toast('Erro.','err');});
}

function excluir(id){
    if(!confirm('Excluir este período?')) return;
    var fd=new FormData(); fd.append('acao','excluir'); fd.append('id',id);
    fetch('cadastroFeriasInstrutor.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            var el=document.getElementById('frow-'+id); if(el) el.remove();
            toast('Removido.','ok');
        }).catch(function(){toast('Erro.','err');});
}

var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toastFI');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>