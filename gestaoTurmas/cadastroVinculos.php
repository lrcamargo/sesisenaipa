<!DOCTYPE html>
<?php
/*
 * cadastroVinculos.php
 * Vincula o código SESI (ex: EM-1A-O-25) ao código da planilha Excel (ex: HT-MET-01-M-25-13310).
 * Usado no painel de docentes para cruzar inconsistências sem falsos positivos.
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:../index.php'); exit;
}

$logado = $_SESSION['user'];

/* ── POST ── */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    $acao = trim($_POST['acao']);

    if($acao === 'salvar'){
        $sistema = strtoupper(trim($_POST['codigoSistema'] ?? ''));
        $excel   = strtoupper(trim($_POST['codigoExcel']   ?? ''));
        $obs     = trim($_POST['observacao'] ?? '');
        if(!$sistema || !$excel){
            echo json_encode(['ok'=>false,'msg'=>'Ambos os códigos são obrigatórios.']); exit;
        }
        try{
            $pdo->prepare("
                INSERT INTO turma_codigos_alt (codigoSistema, codigoExcel, observacao)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE codigoExcel=VALUES(codigoExcel), observacao=VALUES(observacao)
            ")->execute([$sistema,$excel,$obs]);
            echo json_encode(['ok'=>true,'id'=>intval($pdo->lastInsertId())]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
        }
        exit;
    }

    if($acao === 'excluir'){
        $id = intval($_POST['id'] ?? 0);
        if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        try{
            $pdo->prepare("DELETE FROM turma_codigos_alt WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao excluir.']);
        }
        exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Lista vínculos ── */
$vinculos = $pdo->query("SELECT * FROM turma_codigos_alt ORDER BY codigoSistema")->fetchAll(PDO::FETCH_ASSOC);

/* ── Turmas da API para sugerir ── */
$turmasAPI = []; $apiDisp = false;
$ch = curl_init('http://172.16.95.253:3002/backapi/Turmas?dataInicio='.date('Y-m-d'));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,
    CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($httpCode>=200 && $httpCode<300 && $resp){
    $dec = json_decode($resp,true);
    if(is_array($dec)) foreach($dec as $t) if(isset($t['nome'])) $turmasAPI[]=$t['nome'];
    sort($turmasAPI); $apiDisp = true;
}
$turmasEM = array_filter($turmasAPI, fn($t) => preg_match('/^EM-/i',$t));
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vínculos de Código de Turma</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.seta{font-size:1.2rem;color:#6c757d;padding:0 8px;}
#toastVinc{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;display:none;
    padding:12px 18px;border-radius:6px;font-size:.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastVinc.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastVinc.erro{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
    <h4 class="mb-0"><i class="fas fa-link mr-2"></i>Vínculos de Código de Turma</h4>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalVinculo">
        <i class="fas fa-plus mr-1"></i>Novo vínculo
    </button>
</div>

<div class="alert alert-info py-2 mb-3" style="font-size:.82rem">
    <i class="fas fa-info-circle mr-1"></i>
    Vincule o código SESI (usado no sistema e nas salas) com o código da planilha Excel (usado nos horários).
    O painel de docentes usa esse vínculo para verificar inconsistências sem gerar falsos positivos.
</div>

<table class="table table-bordered table-hover bg-white" style="font-size:.85rem">
    <thead class="thead-dark">
        <tr>
            <th>Código SESI (sistema)</th>
            <th style="width:40px"></th>
            <th>Código Excel (planilha)</th>
            <th>Observação</th>
            <th style="width:60px"></th>
        </tr>
    </thead>
    <tbody id="tabelaVinculos">
    <?php foreach($vinculos as $v): ?>
    <tr id="row-v-<?php echo $v['id']; ?>">
        <td><code><?php echo htmlspecialchars($v['codigoSistema']); ?></code></td>
        <td class="text-center seta">⇄</td>
        <td><code><?php echo htmlspecialchars($v['codigoExcel']); ?></code></td>
        <td class="text-muted" style="font-size:.78rem"><?php echo htmlspecialchars($v['observacao']); ?></td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-danger"
                    onclick="excluirVinculo(<?php echo $v['id']; ?>,'<?php echo addslashes($v['codigoSistema']); ?>')">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($vinculos)): ?>
    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum vínculo cadastrado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

</div></div>

<!-- Modal novo vínculo -->
<div class="modal fade" id="modalVinculo" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-link mr-2"></i>Novo vínculo de código</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="form-group">
            <label class="font-weight-bold">Código SESI — sistema e salas</label>
            <?php if(!empty($turmasEM)): ?>
            <select id="vSistema" class="form-control form-control-sm">
                <option value="">— Selecione ou digite abaixo —</option>
                <?php foreach($turmasEM as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">ou</small>
            <?php endif; ?>
            <input type="text" id="vSistemaManual" class="form-control form-control-sm mt-1"
                   placeholder="Ex: EM-1A-O-25" style="text-transform:uppercase"
                   oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group">
            <label class="font-weight-bold">Código Excel — planilha de horários</label>
            <input type="text" id="vExcel" class="form-control form-control-sm"
                   placeholder="Ex: HT-MET-01-M-25-13310" style="text-transform:uppercase"
                   oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold">Observação (opcional)</label>
            <input type="text" id="vObs" class="form-control form-control-sm"
                   placeholder="Ex: Mecatrônica 1° ano 2025" maxlength="200">
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="salvarVinculo()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastVinc"></div>
<script src="../js/menu.js"></script>
<script>
function salvarVinculo(){
    // Usa o select se preenchido, senão usa o campo manual
    var selEl = document.getElementById('vSistema');
    var manEl = document.getElementById('vSistemaManual');
    var sistema = (selEl && selEl.value) ? selEl.value : (manEl ? manEl.value.trim() : '');
    var excel   = document.getElementById('vExcel').value.trim();
    var obs     = document.getElementById('vObs').value.trim();

    if(!sistema || !excel){ mostrarToast('Preencha ambos os códigos.','erro'); return; }

    var fd=new FormData();
    fd.append('acao','salvar');
    fd.append('codigoSistema',sistema);
    fd.append('codigoExcel',excel);
    fd.append('observacao',obs);

    fetch('cadastroVinculos.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            $('#modalVinculo').modal('hide');
            mostrarToast('Vínculo salvo!','sucesso');
            setTimeout(function(){location.reload();},600);
        })
        .catch(function(){mostrarToast('Erro de comunicação.','erro');});
}

function excluirVinculo(id, cod){
    if(!confirm('Excluir vínculo de "'+cod+'"?')) return;
    var fd=new FormData(); fd.append('acao','excluir'); fd.append('id',id);
    fetch('cadastroVinculos.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            var el=document.getElementById('row-v-'+id);
            if(el) el.remove();
            mostrarToast('Vínculo removido.','sucesso');
        })
        .catch(function(){mostrarToast('Erro.','erro');});
}

var tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastVinc');
    el.textContent=msg; el.className=tipo==='sucesso'?'sucesso':'erro'; el.style.display='block';
    if(tt) clearTimeout(tt); tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>