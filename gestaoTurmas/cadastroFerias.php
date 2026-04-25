<?php
/*
 * cadastroFerias.php
 * Cadastro de períodos de férias de turmas.
 * Férias suprimem alertas de "sem docente" no painelDocentes.
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
        $inicio = trim($_POST['dataInicio'] ?? '');
        $fim    = trim($_POST['dataFim']    ?? '');
        $desc   = trim($_POST['descricao']  ?? 'Férias');
        $turmas = json_decode($_POST['turmas'] ?? '[]', true) ?: [];
        if(!$inicio||!$fim||empty($turmas)){
            echo json_encode(['ok'=>false,'msg'=>'Preencha datas e selecione pelo menos uma turma.']); exit;
        }
        if($fim < $inicio){
            echo json_encode(['ok'=>false,'msg'=>'Data fim deve ser maior que data início.']); exit;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO turma_ferias (dataInicio,dataFim,descricao) VALUES (?,?,?)")
                ->execute([$inicio,$fim,$desc]);
            $idF = $pdo->lastInsertId();
            $stT = $pdo->prepare("INSERT INTO turma_ferias_turmas (idFerias,codigoTurma) VALUES (?,?)");
            foreach($turmas as $t){ if(trim($t)) $stT->execute([$idF,trim($t)]); }
            $pdo->commit();
            echo json_encode(['ok'=>true,'id'=>$idF]);
        } catch(PDOException $e){
            $pdo->rollBack();
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
        }
        exit;
    }

    if($_POST['acao']==='excluir'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM turma_ferias WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao excluir.']);
        }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$ferias = $pdo->query("
    SELECT f.id, f.dataInicio, f.dataFim, f.descricao, f.criado_em,
           GROUP_CONCAT(ft.codigoTurma ORDER BY ft.codigoTurma SEPARATOR ',') AS turmas
    FROM turma_ferias f
    LEFT JOIN turma_ferias_turmas ft ON ft.idFerias = f.id
    GROUP BY f.id
    ORDER BY f.dataInicio DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Turmas disponíveis para seleção — da API + banco
$turmasDisponiveis = [];
$ch = curl_init('http://172.16.95.253:3002/backapi/Turmas?dataInicio='.date('Y-m-d'));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>3]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($httpCode>=200 && $httpCode<300 && $resp){
    $dec = json_decode($resp,true);
    if(is_array($dec)) foreach($dec as $t) if(isset($t['nome'])) $turmasDisponiveis[] = $t['nome'];
}
// Adiciona turmas com vínculo que podem não estar na API
$stTurmas = $pdo->query("
    SELECT DISTINCT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma FROM turma_sala
    UNION
    SELECT DISTINCT CONVERT(codigoTurma USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma FROM turma_sala_externa
    UNION
    SELECT DISTINCT CONVERT(codigo USING utf8mb4) COLLATE utf8mb4_general_ci AS codigoTurma FROM app_turmas WHERE ativo=1
");
foreach($stTurmas->fetchAll(PDO::FETCH_COLUMN) as $t) if(!in_array($t,$turmasDisponiveis)) $turmasDisponiveis[] = $t;
sort($turmasDisponiveis);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Férias das Turmas</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.ferias-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:14px 16px;margin-bottom:10px;}
.ferias-periodo{font-size:1rem;font-weight:700;color:#212529;}
.ferias-desc{font-size:.82rem;color:#6c757d;margin-top:2px;}
.ferias-turmas{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;}
.badge-turma{display:inline-block;background:#e3f2fd;color:#0d47a1;border-radius:4px;
    padding:2px 8px;font-size:.72rem;font-weight:600;}
.turma-check-grid{display:flex;flex-wrap:wrap;gap:6px;max-height:260px;overflow-y:auto;
    border:1px solid #dee2e6;border-radius:6px;padding:10px;}
.turma-check-item{display:flex;align-items:center;gap:4px;
    background:#f8f9fa;border-radius:4px;padding:3px 8px;font-size:.78rem;cursor:pointer;}
.turma-check-item input{cursor:pointer;}
.turma-check-item.selecionado{background:#e3f2fd;color:#0d47a1;font-weight:600;}
.filtro-turma{width:100%;margin-bottom:8px;padding:5px 10px;border:1px solid #ced4da;
    border-radius:4px;font-size:.82rem;}
#toastF{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastF.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastF.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
    <h4 class="mb-0"><i class="fas fa-umbrella-beach mr-2"></i>Férias das Turmas</h4>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNovaFerias">
        <i class="fas fa-plus mr-1"></i>Novo período
    </button>
</div>

<?php if(empty($ferias)): ?>
<div class="text-muted text-center py-5">
    <i class="fas fa-umbrella-beach" style="font-size:2rem;display:block;margin-bottom:8px"></i>
    Nenhum período de férias cadastrado.
</div>
<?php else: ?>
<?php foreach($ferias as $f):
    $turmasArr = $f['turmas'] ? explode(',', $f['turmas']) : [];
    $di = date('d/m/Y', strtotime($f['dataInicio']));
    $df = date('d/m/Y', strtotime($f['dataFim']));
    $dias = (strtotime($f['dataFim'])-strtotime($f['dataInicio']))/86400+1;
?>
<div class="ferias-card" id="fcard-<?php echo $f['id']; ?>">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="ferias-periodo">
                <i class="fas fa-calendar-alt mr-1 text-primary"></i>
                <?php echo $di; ?> → <?php echo $df; ?>
                <span class="badge badge-light ml-1" style="font-size:.72rem"><?php echo $dias; ?> dias</span>
            </div>
            <div class="ferias-desc"><?php echo htmlspecialchars($f['descricao']); ?></div>
            <div class="ferias-turmas">
                <?php foreach($turmasArr as $t): ?>
                <span class="badge-turma"><?php echo htmlspecialchars($t); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="btn btn-outline-danger btn-sm" onclick="excluir(<?php echo $f['id']; ?>)">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div>

<!-- Modal novo período -->
<div class="modal fade" id="modalNovaFerias" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-umbrella-beach mr-2"></i>Novo período de férias</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Descrição</label>
                <input type="text" id="fDesc" class="form-control form-control-sm"
                       value="Férias" placeholder="Ex: Férias de julho, Recesso fim de ano...">
            </div>
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Data início</label>
                <input type="date" id="fInicio" class="form-control form-control-sm">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Data fim</label>
                <input type="date" id="fFim" class="form-control form-control-sm">
            </div>
        </div>
        <label class="font-weight-bold" style="font-size:.82rem">
            Turmas afetadas
            <span id="selCount" class="badge badge-primary ml-1">0</span>
        </label>
        <div class="d-flex gap-2 mb-2" style="gap:6px">
            <input type="text" class="filtro-turma" id="filtroTurma"
                   placeholder="Filtrar turmas..." oninput="filtrarTurmas()">
            <button class="btn btn-outline-secondary btn-sm" onclick="selecionarTodas(true)">Todas</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="selecionarTodas(false)">Nenhuma</button>
        </div>
        <div class="turma-check-grid" id="turmaCheckGrid">
            <?php foreach($turmasDisponiveis as $t): ?>
            <label class="turma-check-item" onclick="toggleItem(this)">
                <input type="checkbox" value="<?php echo htmlspecialchars($t); ?>"
                       onchange="atualizarCount()">
                <?php echo htmlspecialchars($t); ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarFerias()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastF"></div>
<script src="../js/menu.js"></script>
<script>
function toggleItem(lbl){
    lbl.classList.toggle('selecionado', lbl.querySelector('input').checked);
    atualizarCount();
}
function atualizarCount(){
    var n=document.querySelectorAll('#turmaCheckGrid input:checked').length;
    document.getElementById('selCount').textContent=n;
}
function filtrarTurmas(){
    var q=document.getElementById('filtroTurma').value.toLowerCase();
    document.querySelectorAll('#turmaCheckGrid .turma-check-item').forEach(function(l){
        l.style.display=l.textContent.trim().toLowerCase().includes(q)?'':'none';
    });
}
function selecionarTodas(sel){
    document.querySelectorAll('#turmaCheckGrid input').forEach(function(i){
        i.checked=sel;
        i.closest('.turma-check-item').classList.toggle('selecionado',sel);
    });
    atualizarCount();
}
function salvarFerias(){
    var inicio=document.getElementById('fInicio').value;
    var fim   =document.getElementById('fFim').value;
    var desc  =document.getElementById('fDesc').value.trim()||'Férias';
    var turmas=[]; document.querySelectorAll('#turmaCheckGrid input:checked').forEach(function(i){turmas.push(i.value);});
    if(!inicio||!fim||!turmas.length){alert('Preencha as datas e selecione ao menos uma turma.');return;}
    var fd=new FormData(); fd.append('acao','salvar');
    fd.append('dataInicio',inicio); fd.append('dataFim',fim);
    fd.append('descricao',desc); fd.append('turmas',JSON.stringify(turmas));
    fetch('cadastroFerias.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            toast('Período salvo!','ok');
            $('#modalNovaFerias').modal('hide');
            setTimeout(function(){location.reload();},700);
        }).catch(function(){toast('Erro.','err');});
}
function excluir(id){
    if(!confirm('Excluir este período de férias?')) return;
    var fd=new FormData(); fd.append('acao','excluir'); fd.append('id',id);
    fetch('cadastroFerias.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            var el=document.getElementById('fcard-'+id); if(el) el.remove();
            toast('Removido.','ok');
        }).catch(function(){toast('Erro.','err');});
}
var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toastF');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>