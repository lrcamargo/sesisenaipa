<!DOCTYPE html>
<?php
/*
 * cadastroFeriados.php
 * Cadastro de feriados nacionais, municipais e recessos escolares.
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:../index.php'); exit;
}
$logado = $_SESSION['user'];

/* ── POST JSON ── */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');
    $acao = trim($_POST['acao']);

    if($acao === 'salvar'){
        $id    = intval($_POST['id'] ?? 0);
        $data  = trim($_POST['data']  ?? '');
        $nome  = trim($_POST['nome']  ?? '');
        $tipo  = trim($_POST['tipo']  ?? '');
        $turmas = array_filter(array_map('trim', (array)($_POST['turmas'] ?? [])));

        if(!$data || !$nome || !in_array($tipo,['nacional','municipal','recesso'])){
            echo json_encode(['ok'=>false,'msg'=>'Dados obrigatórios ausentes.']); exit;
        }
        try{
            if($id){
                $pdo->prepare("UPDATE feriados SET data=?,nome=?,tipo=? WHERE id=?")
                    ->execute([$data,$nome,$tipo,$id]);
                // Recria turmas vinculadas
                $pdo->prepare("DELETE FROM feriado_turmas WHERE idFeriado=?")->execute([$id]);
            } else {
                $pdo->prepare("INSERT INTO feriados (data,nome,tipo) VALUES (?,?,?)")
                    ->execute([$data,$nome,$tipo]);
                $id = intval($pdo->lastInsertId());
            }
            if($tipo === 'recesso' && !empty($turmas)){
                $stmt = $pdo->prepare("INSERT INTO feriado_turmas (idFeriado,codigoTurma) VALUES (?,?)");
                foreach($turmas as $cod){
                    $cod = strtoupper($cod);
                    if($cod) $stmt->execute([$id,$cod]);
                }
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
        }
        exit;
    }

    if($acao === 'excluir'){
        $id = intval($_POST['id'] ?? 0);
        if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        try{
            $pdo->prepare("DELETE FROM feriados WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao excluir.']);
        }
        exit;
    }

    if($acao === 'carregar'){
        $id = intval($_POST['id'] ?? 0);
        if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $stmt = $pdo->prepare("
            SELECT f.*, GROUP_CONCAT(ft.codigoTurma ORDER BY ft.codigoTurma SEPARATOR ',') AS turmas
            FROM feriados f
            LEFT JOIN feriado_turmas ft ON ft.idFeriado = f.id
            WHERE f.id = ? GROUP BY f.id
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row){ echo json_encode(['ok'=>false,'msg'=>'Não encontrado.']); exit; }
        echo json_encode([
            'ok'=>true,
            'id'=>$row['id'], 'data'=>$row['data'], 'nome'=>$row['nome'], 'tipo'=>$row['tipo'],
            'turmas'=> $row['turmas'] ? explode(',',$row['turmas']) : [],
        ]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$stmt = $pdo->query("
    SELECT f.*, COUNT(ft.id) AS qtdTurmas
    FROM feriados f
    LEFT JOIN feriado_turmas ft ON ft.idFeriado = f.id
    GROUP BY f.id ORDER BY f.data ASC
");
$feriados = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Contadores */
$cntNac = count(array_filter($feriados, fn($f)=>$f['tipo']==='nacional'));
$cntMun = count(array_filter($feriados, fn($f)=>$f['tipo']==='municipal'));
$cntRec = count(array_filter($feriados, fn($f)=>$f['tipo']==='recesso'));

/* Turmas da API */
$turmasAPI = [];
$ch = curl_init('http://172.16.95.253:3002/backapi/Turmas?dataInicio='.date('Y-m-d'));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,
    CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($httpCode>=200 && $httpCode<300 && $resp){
    $dec = json_decode($resp,true);
    if(is_array($dec)) foreach($dec as $t) if(isset($t['nome'])) $turmasAPI[]=$t['nome'];
    sort($turmasAPI);
}

$tipoLabels = ['nacional'=>'Nacional','municipal'=>'Municipal','recesso'=>'Recesso'];
$tipoCores  = ['nacional'=>'danger','municipal'=>'warning','recesso'=>'info'];
$diasSem    = ['Sunday'=>'Dom','Monday'=>'Seg','Tuesday'=>'Ter',
               'Wednesday'=>'Qua','Thursday'=>'Qui','Friday'=>'Sex','Saturday'=>'Sáb'];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feriados e Recessos</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
/* Contadores */
.cnt-box{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.cnt-item{display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:8px;font-size:.88rem;font-weight:700;}
.cnt-item.nacional{background:#f8d7da;color:#721c24;}
.cnt-item.municipal{background:#fff3cd;color:#856404;}
.cnt-item.recesso{background:#d1ecf1;color:#0c5460;}
.cnt-item .num{font-size:1.4rem;font-weight:800;line-height:1;}

/* Checkboxes de turmas */
.turmas-check-grid{display:flex;flex-direction:column;gap:2px;max-height:220px;overflow-y:auto;
    border:1px solid #ced4da;border-radius:4px;padding:6px;background:#fff;}
.turma-ck-item{display:flex;align-items:center;gap:6px;padding:3px 6px;border-radius:3px;cursor:pointer;font-size:.82rem;}
.turma-ck-item:hover{background:#f0f4ff;}
.turma-ck-item input{cursor:pointer;}
.turma-ck-item.oculto{display:none;}
.turmas-selecionadas{font-size:.78rem;color:#0c5460;margin-top:4px;}

/* Toast */
#toastFer{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;display:none;
    padding:12px 18px;border-radius:6px;font-size:.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastFer.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastFer.erro{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
    <h4 class="mb-0"><i class="fas fa-calendar-times mr-2"></i>Feriados e Recessos</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirModal()">
        <i class="fas fa-plus mr-1"></i>Novo
    </button>
</div>

<!-- Contadores -->
<div class="cnt-box">
    <div class="cnt-item nacional">
        <span class="num"><?php echo $cntNac; ?></span>
        <span><i class="fas fa-flag mr-1"></i>Nacionais</span>
    </div>
    <div class="cnt-item municipal">
        <span class="num"><?php echo $cntMun; ?></span>
        <span><i class="fas fa-map-marker-alt mr-1"></i>Municipais</span>
    </div>
    <div class="cnt-item recesso">
        <span class="num"><?php echo $cntRec; ?></span>
        <span><i class="fas fa-pause-circle mr-1"></i>Recessos</span>
    </div>
</div>

<table class="table table-bordered table-hover bg-white" style="font-size:.85rem">
    <thead class="thead-dark">
        <tr>
            <th style="width:110px">Data</th>
            <th style="width:50px">Dia</th>
            <th>Nome</th>
            <th style="width:110px">Tipo</th>
            <th style="width:120px">Turmas afetadas</th>
            <th style="width:90px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($feriados as $f):
        $cor    = $tipoCores[$f['tipo']] ?? 'secondary';
        $diaSem = $diasSem[date('l',strtotime($f['data']))] ?? '';
        $ehHoje = ($f['data'] === date('Y-m-d'));
    ?>
    <tr id="row-f-<?php echo $f['id']; ?>" <?php echo $ehHoje?"class='table-warning'":''; ?>>
        <td><?php echo date('d/m/Y',strtotime($f['data'])); ?></td>
        <td><?php echo $diaSem; ?></td>
        <td><?php echo htmlspecialchars($f['nome']); ?></td>
        <td><span class="badge badge-<?php echo $cor; ?>" style="font-size:.75rem;padding:3px 8px">
            <?php echo $tipoLabels[$f['tipo']]; ?>
        </span></td>
        <td>
            <?php if($f['tipo']==='recesso'): ?>
                <?php if($f['qtdTurmas']>0): ?>
                    <span class="badge badge-light border"><?php echo $f['qtdTurmas']; ?> turma(s)</span>
                <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem">Todas</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="text-muted" style="font-size:.75rem">Todas</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-primary mr-1"
                    onclick="editarFeriado(<?php echo $f['id']; ?>)" title="Editar">
                <i class="fas fa-pen"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="excluirFeriado(<?php echo $f['id']; ?>,'<?php echo addslashes($f['nome']); ?>')"
                    title="Excluir">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($feriados)): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum feriado cadastrado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="alert alert-info py-2" style="font-size:.82rem">
    <i class="fas fa-info-circle mr-1"></i>
    <strong>Nacionais e municipais</strong> aplicam-se a todas as turmas.
    <strong>Recessos</strong> sem turmas selecionadas aplicam-se a todas; com turmas, apenas às selecionadas.
</div>

</div></div>

<!-- Modal novo/editar feriado -->
<div class="modal fade" id="modalFeriado" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalFeriadoTitulo">
            <i class="fas fa-calendar-plus mr-2"></i>Novo feriado / recesso
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="fId">

        <div class="form-group">
            <label class="font-weight-bold">Data</label>
            <input type="date" id="fData" class="form-control form-control-sm">
        </div>
        <div class="form-group">
            <label class="font-weight-bold">Nome / Descrição</label>
            <input type="text" id="fNome" class="form-control form-control-sm"
                   placeholder="Ex: Recesso Semestral, Aniversário da cidade...">
        </div>
        <div class="form-group">
            <label class="font-weight-bold">Tipo</label>
            <select id="fTipo" class="form-control form-control-sm" onchange="tipoChanged()">
                <option value="nacional">Nacional — aplica a todas as turmas</option>
                <option value="municipal">Municipal — aplica a todas as turmas</option>
                <option value="recesso">Recesso — pode ser por turma</option>
            </select>
        </div>

        <!-- Seção turmas (só para recesso) -->
        <div id="secaoTurmas" style="display:none">
            <label class="font-weight-bold">Turmas afetadas pelo recesso</label>
            <small class="text-muted d-block mb-1">
                Deixe sem selecionar para aplicar a todas as turmas.
            </small>
            <input type="text" id="buscaTurmaF" class="form-control form-control-sm mb-1"
                   placeholder="🔍 Filtrar turmas..." oninput="filtrarTurmas()">
            <div class="turmas-check-grid" id="turmasCheckGrid">
                <?php foreach($turmasAPI as $t): ?>
                <label class="turma-ck-item" data-turma="<?php echo htmlspecialchars($t); ?>">
                    <input type="checkbox" value="<?php echo htmlspecialchars($t); ?>">
                    <?php echo htmlspecialchars($t); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="turmas-selecionadas" id="turmasSelecionadasInfo">
                Nenhuma turma selecionada — recesso aplicado a todas.
            </div>
            <div class="mt-1">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="selecionarTodasTurmas(false)">Desmarcar tudo</button>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="salvarFeriado()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastFer"></div>
<script src="../js/menu.js"></script>
<script>
function tipoChanged(){
    var tipo=document.getElementById('fTipo').value;
    document.getElementById('secaoTurmas').style.display=tipo==='recesso'?'':'none';
}

function filtrarTurmas(){
    var q=document.getElementById('buscaTurmaF').value.toLowerCase();
    document.querySelectorAll('#turmasCheckGrid .turma-ck-item').forEach(function(el){
        el.classList.toggle('oculto', q && !el.dataset.turma.toLowerCase().includes(q));
    });
}

function atualizarContadorTurmas(){
    var sel=document.querySelectorAll('#turmasCheckGrid input:checked').length;
    var info=document.getElementById('turmasSelecionadasInfo');
    info.textContent = sel===0
        ? 'Nenhuma turma selecionada — recesso aplicado a todas.'
        : sel+' turma(s) selecionada(s).';
}

document.getElementById('turmasCheckGrid').addEventListener('change', atualizarContadorTurmas);

function selecionarTodasTurmas(marcar){
    document.querySelectorAll('#turmasCheckGrid input').forEach(function(cb){ cb.checked=marcar; });
    atualizarContadorTurmas();
}

function abrirModal(){
    document.getElementById('fId').value='';
    document.getElementById('fData').value='';
    document.getElementById('fNome').value='';
    document.getElementById('fTipo').value='nacional';
    document.getElementById('secaoTurmas').style.display='none';
    document.getElementById('buscaTurmaF').value='';
    filtrarTurmas();
    selecionarTodasTurmas(false);
    document.getElementById('modalFeriadoTitulo').innerHTML=
        '<i class="fas fa-calendar-plus mr-2"></i>Novo feriado / recesso';
    $('#modalFeriado').modal('show');
}

function editarFeriado(id){
    var fd=new FormData(); fd.append('acao','carregar'); fd.append('id',id);
    fetch('cadastroFeriados.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            document.getElementById('fId').value=res.id;
            document.getElementById('fData').value=res.data;
            document.getElementById('fNome').value=res.nome;
            document.getElementById('fTipo').value=res.tipo;
            document.getElementById('modalFeriadoTitulo').innerHTML=
                '<i class="fas fa-pen mr-2"></i>Editar feriado / recesso';

            // Reset turmas
            selecionarTodasTurmas(false);
            document.getElementById('buscaTurmaF').value='';
            filtrarTurmas();

            if(res.tipo==='recesso'){
                document.getElementById('secaoTurmas').style.display='';
                // Marca as turmas vinculadas
                res.turmas.forEach(function(cod){
                    var cb=document.querySelector('#turmasCheckGrid input[value="'+cod+'"]');
                    if(cb) cb.checked=true;
                });
                atualizarContadorTurmas();
            } else {
                document.getElementById('secaoTurmas').style.display='none';
            }
            $('#modalFeriado').modal('show');
        })
        .catch(function(){mostrarToast('Erro de comunicação.','erro');});
}

function salvarFeriado(){
    var id    = document.getElementById('fId').value;
    var data  = document.getElementById('fData').value;
    var nome  = document.getElementById('fNome').value.trim();
    var tipo  = document.getElementById('fTipo').value;
    if(!data||!nome){mostrarToast('Preencha data e nome.','erro');return;}

    var fd=new FormData();
    fd.append('acao','salvar');
    if(id) fd.append('id',id);
    fd.append('data',data); fd.append('nome',nome); fd.append('tipo',tipo);

    if(tipo==='recesso'){
        document.querySelectorAll('#turmasCheckGrid input:checked').forEach(function(cb){
            fd.append('turmas[]',cb.value);
        });
    }

    fetch('cadastroFeriados.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            $('#modalFeriado').modal('hide');
            mostrarToast('Salvo!','sucesso');
            setTimeout(function(){location.reload();},600);
        })
        .catch(function(){mostrarToast('Erro de comunicação.','erro');});
}

function excluirFeriado(id,nome){
    if(!confirm('Excluir "'+nome+'"?')) return;
    var fd=new FormData(); fd.append('acao','excluir'); fd.append('id',id);
    fetch('cadastroFeriados.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){mostrarToast(res.msg||'Erro.','erro');return;}
            var el=document.getElementById('row-f-'+id);
            if(el) el.remove();
            mostrarToast('Removido.','sucesso');
        })
        .catch(function(){mostrarToast('Erro.','erro');});
}

var tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastFer');
    el.textContent=msg; el.className=tipo==='sucesso'?'sucesso':'erro'; el.style.display='block';
    if(tt) clearTimeout(tt); tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>