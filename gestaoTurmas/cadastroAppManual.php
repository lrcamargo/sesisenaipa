<?php
/*
 * cadastroAppManual.php
 * Cadastro de turmas de Aperfeiçoamento (APP) com horário manual.
 * Cada turma pode ter até 3 turnos (manhã/tarde/noite), cada um com instrutor e sala.
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
        $id     = intval($_POST['id'] ?? 0);
        $codigo = strtoupper(trim($_POST['codigo']    ?? ''));
        $nome   = trim($_POST['nome']       ?? '');
        $inicio = trim($_POST['dataInicio'] ?? '');
        $fim    = trim($_POST['dataFim']    ?? '');
        $dias   = intval($_POST['diasSemana'] ?? 31);
        $obs    = trim($_POST['observacao'] ?? '');
        $turnos = json_decode($_POST['turnos'] ?? '[]', true) ?: [];

        if(!$codigo||!$nome||!$inicio||!$fim){
            echo json_encode(['ok'=>false,'msg'=>'Preencha todos os campos obrigatórios.']); exit;
        }
        if(!preg_match('/^APP-/i', $codigo)){
            echo json_encode(['ok'=>false,'msg'=>'O código deve começar com APP-.']); exit;
        }
        if($fim < $inicio){
            echo json_encode(['ok'=>false,'msg'=>'Data fim deve ser maior que início.']); exit;
        }
        try {
            $pdo->beginTransaction();
            if($id){
                $pdo->prepare("UPDATE app_turmas SET codigo=?,nome=?,dataInicio=?,dataFim=?,diasSemana=?,observacao=? WHERE id=?")
                    ->execute([$codigo,$nome,$inicio,$fim,$dias,$obs,$id]);
                $pdo->prepare("DELETE FROM app_turma_turnos WHERE idTurma=?")->execute([$id]);
            } else {
                $pdo->prepare("INSERT INTO app_turmas (codigo,nome,dataInicio,dataFim,diasSemana,observacao) VALUES (?,?,?,?,?,?)")
                    ->execute([$codigo,$nome,$inicio,$fim,$dias,$obs]);
                $id = intval($pdo->lastInsertId());
            }
            // Salva turnos
            $stT = $pdo->prepare("INSERT INTO app_turma_turnos (idTurma,turno,horarioInicio,horarioFim,instrutor,idLaboratorio) VALUES (?,?,?,?,?,?)");
            foreach($turnos as $t){
                if(empty($t['instrutor'])||empty($t['horarioInicio'])||empty($t['horarioFim'])) continue;
                $idLab = $t['idLaboratorio'] ?? null;
            // 'EXTERNO' = in company, não tem ID numérico → null na FK
            if($idLab === 'EXTERNO') $idLab = null;
            else $idLab = $idLab ? intval($idLab) : null;
            $stT->execute([$id,$t['turno'],$t['horarioInicio'],$t['horarioFim'],
                               $t['instrutor'],$idLab]);
            }
            $pdo->commit();
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            $pdo->rollBack();
            echo json_encode(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
        }
        exit;
    }

    if($_POST['acao']==='excluir'){
        $id=intval($_POST['id']??0);
        try {
            $pdo->prepare("DELETE FROM app_turmas WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    if($_POST['acao']==='toggle_ativo'){
        $id=intval($_POST['id']??0); $ativo=intval($_POST['ativo']??1);
        try {
            $pdo->prepare("UPDATE app_turmas SET ativo=? WHERE id=?")->execute([$ativo,$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false]); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$turmas = $pdo->query("
    SELECT t.*, GROUP_CONCAT(
        CONCAT(tt.turno,'|',tt.horarioInicio,'|',tt.horarioFim,'|',tt.instrutor,'|',COALESCE(tt.idLaboratorio,''),'|',COALESCE(l.nome,''))
        ORDER BY FIELD(tt.turno,'manha','tarde','noite')
        SEPARATOR ';;'
    ) AS turnos_raw
    FROM app_turmas t
    LEFT JOIN app_turma_turnos tt ON tt.idTurma = t.id
    LEFT JOIN laboratorios l ON l.idLaboratorio = tt.idLaboratorio
    GROUP BY t.id
    ORDER BY t.dataInicio DESC
")->fetchAll(PDO::FETCH_ASSOC);

$instrutores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil='Instrutor' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$labs        = $pdo->query("SELECT idLaboratorio, nome FROM laboratorios WHERE temSala=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

const DIAS_BIT   = ['seg'=>1,'ter'=>2,'qua'=>4,'qui'=>8,'sex'=>16,'sab'=>32];
const DIAS_LABEL = ['seg'=>'Seg','ter'=>'Ter','qua'=>'Qua','qui'=>'Qui','sex'=>'Sex','sab'=>'Sáb'];
function diasTexto(int $mask): string {
    $out=[];
    foreach(DIAS_BIT as $d=>$b) if($mask&$b) $out[]=DIAS_LABEL[$d];
    return implode(' ',$out)?:'—';
}
$turnosConfig=['manha'=>['label'=>'Manhã','icone'=>'fa-sun'],
               'tarde'=>['label'=>'Tarde','icone'=>'fa-cloud-sun'],
               'noite'=>['label'=>'Noite','icone'=>'fa-moon']];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Turmas APP Manuais</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.app-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:14px 16px;margin-bottom:10px;
    border-left:4px solid #e65100;}
.app-card.inativo{opacity:.6;border-left-color:#aaa;}
.app-codigo{font-size:.8rem;font-family:monospace;background:#fff3e0;color:#e65100;
    border-radius:4px;padding:2px 8px;font-weight:700;}
.app-nome{font-size:1rem;font-weight:700;color:#212529;margin-top:3px;}
.app-periodo{font-size:.8rem;color:#6c757d;}
.turno-bloco{background:#f8f9fa;border-radius:6px;padding:10px;margin-top:6px;}
.turno-bloco-header{font-size:.78rem;font-weight:700;color:#495057;margin-bottom:6px;}
.turno-bloco-row{font-size:.78rem;color:#555;display:flex;gap:12px;flex-wrap:wrap;}

/* Modal turnos */
.turno-section{border:1px solid #dee2e6;border-radius:8px;padding:12px;margin-bottom:10px;}
.turno-section.ativo{border-color:#0d6efd;background:#f0f4ff;}
.turno-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:0;}
.turno-toggle input[type=checkbox]{cursor:pointer;}
.turno-fields{display:none;margin-top:10px;}
.turno-fields.visivel{display:block;}

.dias-grid{display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;}
.dia-btn{padding:5px 11px;border-radius:4px;border:1px solid #ced4da;background:#fff;
    font-size:.82rem;font-weight:700;cursor:pointer;color:#495057;transition:all .12s;}
.dia-btn.sel{background:#0d6efd;color:#fff;border-color:#0d6efd;}

#toastApp{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastApp.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastApp.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
    <h4 class="mb-0"><i class="fas fa-chalkboard mr-2"></i>Turmas APP Manuais</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirModal()">
        <i class="fas fa-plus mr-1"></i>Nova turma APP
    </button>
</div>

<?php if(empty($turmas)): ?>
<div class="text-muted text-center py-5">
    <i class="fas fa-chalkboard" style="font-size:2rem;display:block;margin-bottom:8px"></i>
    Nenhuma turma APP manual cadastrada.
</div>
<?php else: ?>
<?php foreach($turmas as $t):
    $turnos = [];
    if($t['turnos_raw']){
        foreach(explode(';;',$t['turnos_raw']) as $tr){
            $p=explode('|',$tr);
            if(count($p)>=4) $turnos[]=['turno'=>$p[0],'hi'=>$p[1],'hf'=>$p[2],'inst'=>$p[3],'idLab'=>$p[4]??'','nomeLab'=>$p[5]??''];
        }
    }
    $di=date('d/m/Y',strtotime($t['dataInicio']));
    $df=date('d/m/Y',strtotime($t['dataFim']));
?>
<div class="app-card <?php echo $t['ativo']?'':'inativo'; ?>" id="acard-<?php echo $t['id']; ?>">
    <div class="d-flex justify-content-between align-items-start">
        <div style="flex:1">
            <span class="app-codigo"><?php echo htmlspecialchars($t['codigo']); ?></span>
            <div class="app-nome"><?php echo htmlspecialchars($t['nome']); ?></div>
            <div class="app-periodo">
                <i class="fas fa-calendar-alt mr-1"></i><?php echo $di; ?> → <?php echo $df; ?>
                &nbsp;|&nbsp;<i class="fas fa-calendar-week mr-1"></i><?php echo diasTexto($t['diasSemana']); ?>
            </div>
            <?php if($t['observacao']): ?>
            <div style="font-size:.75rem;color:#888;margin-top:2px"><?php echo htmlspecialchars($t['observacao']); ?></div>
            <?php endif; ?>
            <?php foreach($turnos as $tr): $tc=$turnosConfig[$tr['turno']]??['label'=>$tr['turno'],'icone'=>'fa-clock']; ?>
            <div class="turno-bloco">
                <div class="turno-bloco-header"><i class="fas <?php echo $tc['icone']; ?> mr-1"></i><?php echo $tc['label']; ?></div>
                <div class="turno-bloco-row">
                    <span><i class="fas fa-clock mr-1"></i><?php echo substr($tr['hi'],0,5).' → '.substr($tr['hf'],0,5); ?></span>
                    <span><i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($tr['inst']); ?></span>
                    <?php if($tr['nomeLab']): ?><span><i class="fas fa-door-open mr-1"></i><?php echo htmlspecialchars($tr['nomeLab']); ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:5px;margin-left:10px">
            <button class="btn btn-outline-secondary btn-sm" onclick="abrirModal(<?php echo $t['id']; ?>)" title="Editar">
                <i class="fas fa-pen"></i>
            </button>
            <button class="btn btn-outline-<?php echo $t['ativo']?'warning':'success'; ?> btn-sm"
                    onclick="toggleAtivo(<?php echo $t['id']; ?>,<?php echo $t['ativo']?0:1; ?>)"
                    title="<?php echo $t['ativo']?'Desativar':'Ativar'; ?>">
                <i class="fas fa-<?php echo $t['ativo']?'pause':'play'; ?>"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="excluir(<?php echo $t['id']; ?>)" title="Excluir">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div></div>

<!-- Modal novo/editar turma APP -->
<div class="modal fade" id="modalApp" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalAppTitulo"><i class="fas fa-chalkboard mr-2"></i>Nova turma APP</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="appId">
        <div class="form-row mb-2">
            <div class="col-md-8">
                <label class="font-weight-bold" style="font-size:.8rem">Nome do curso</label>
                <input type="text" id="appNome" class="form-control form-control-sm"
                       placeholder="Ex: Informática Básica" oninput="gerarCodigo()">
            </div>
            <div class="col-md-4">
                <label class="font-weight-bold" style="font-size:.8rem">
                    Código <small class="text-muted">(gerado automaticamente)</small>
                </label>
                <input type="text" id="appCodigo" class="form-control form-control-sm"
                       placeholder="APP-..." style="text-transform:uppercase;background:#f8f9fa"
                       oninput="this.value=this.value.toUpperCase()">
            </div>
        </div>
        <div class="form-row mb-2">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.8rem">Data início</label>
                <input type="date" id="appInicio" class="form-control form-control-sm">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.8rem">Data fim</label>
                <input type="date" id="appFim" class="form-control form-control-sm">
            </div>
        </div>
        <div class="mb-3">
            <label class="font-weight-bold" style="font-size:.8rem">Dias da semana</label>
            <div class="dias-grid" id="diasGrid">
                <?php foreach(DIAS_BIT as $d=>$b): ?>
                <button type="button" class="dia-btn <?php echo $b<=16?'sel':''; ?>"
                        data-bit="<?php echo $b; ?>"><?php echo DIAS_LABEL[$d]; ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mb-2">
            <label class="font-weight-bold" style="font-size:.8rem">Observação</label>
            <input type="text" id="appObs" class="form-control form-control-sm" placeholder="Opcional">
        </div>
        <hr>
        <h6 style="font-size:.85rem;font-weight:700;margin-bottom:10px">
            <i class="fas fa-clock mr-1"></i>Turnos e instrutores
        </h6>
        <?php foreach($turnosConfig as $turnoKey=>$tConf): ?>
        <div class="turno-section" id="tsec-<?php echo $turnoKey; ?>">
            <label class="turno-toggle">
                <input type="checkbox" id="tchk-<?php echo $turnoKey; ?>"
                       onchange="toggleTurnoFields('<?php echo $turnoKey; ?>')">
                <i class="fas <?php echo $tConf['icone']; ?> mr-1"></i>
                <strong><?php echo $tConf['label']; ?></strong>
            </label>
            <div class="turno-fields" id="tfields-<?php echo $turnoKey; ?>">
                <div class="form-row mt-2">
                    <div class="col-md-3">
                        <label style="font-size:.75rem;font-weight:600">Início</label>
                        <input type="time" id="thi-<?php echo $turnoKey; ?>" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label style="font-size:.75rem;font-weight:600">Fim</label>
                        <input type="time" id="thf-<?php echo $turnoKey; ?>" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label style="font-size:.75rem;font-weight:600">Instrutor</label>
                        <select id="tinst-<?php echo $turnoKey; ?>" class="form-control form-control-sm">
                            <option value="">— Selecione —</option>
                            <?php foreach($instrutores as $inst): ?>
                            <option value="<?php echo htmlspecialchars($inst['nome']); ?>">
                                <?php echo htmlspecialchars(mb_strtoupper($inst['nome'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group mt-2 mb-0">
                    <label style="font-size:.75rem;font-weight:600">Laboratório/Sala (opcional)</label>
                    <select id="tlab-<?php echo $turnoKey; ?>" class="form-control form-control-sm">
                        <option value="">— Nenhum —</option>
                        <option value="EXTERNO">📍 Externo / In Company</option>
                        <?php foreach($labs as $l): ?>
                        <option value="<?php echo $l['idLaboratorio']; ?>">
                            <?php echo htmlspecialchars($l['nome']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarApp()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastApp"></div>
<script src="../js/menu.js"></script>
<script>
function gerarCodigo(){
    var nome = document.getElementById('appNome').value.trim();
    // Gera APP-NOME-DO-CURSO removendo acentos e caracteres especiais
    var slug = nome.toUpperCase()
        .normalize('NFD').replace(/[̀-ͯ]/g,'') // remove acentos
        .replace(/[^A-Z0-9\s]/g,'')
        .trim().replace(/\s+/g,'-');
    document.getElementById('appCodigo').value = slug ? 'APP-'+slug : 'APP-';
}

var _instrutores = <?php echo json_encode(array_map(fn($i)=>['id'=>$i['id'],'nome'=>mb_strtoupper($i['nome'])],$instrutores),JSON_HEX_TAG|JSON_UNESCAPED_UNICODE); ?>;
var _turmasData  = <?php echo json_encode(array_map(function($t){
    $turnos=[];
    if($t['turnos_raw']) foreach(explode(';;',$t['turnos_raw']) as $tr){
        $p=explode('|',$tr);
        if(count($p)>=4) $turnos[]=compact('p');
    }
    return ['id'=>$t['id'],'codigo'=>$t['codigo'],'nome'=>$t['nome'],
            'dataInicio'=>$t['dataInicio'],'dataFim'=>$t['dataFim'],
            'diasSemana'=>$t['diasSemana'],'observacao'=>$t['observacao']??'',
            'turnos_raw'=>$t['turnos_raw']??''];
},$turmas), JSON_HEX_TAG|JSON_UNESCAPED_UNICODE); ?>;

/* Dias bitmask */
document.getElementById('diasGrid').addEventListener('click',function(e){
    var btn=e.target.closest('.dia-btn'); if(!btn) return;
    btn.classList.toggle('sel');
});
function calcDias(){
    var m=0;
    document.querySelectorAll('#diasGrid .dia-btn.sel').forEach(function(b){m|=parseInt(b.dataset.bit);});
    return m;
}
function setDias(mask){
    document.querySelectorAll('#diasGrid .dia-btn').forEach(function(b){
        b.classList.toggle('sel',(mask&parseInt(b.dataset.bit))>0);
    });
}

function toggleTurnoFields(turno){
    var chk=document.getElementById('tchk-'+turno);
    var fields=document.getElementById('tfields-'+turno);
    var sec=document.getElementById('tsec-'+turno);
    fields.classList.toggle('visivel',chk.checked);
    sec.classList.toggle('ativo',chk.checked);
}

function abrirModal(id){
    // Reset
    document.getElementById('appId').value='';
    document.getElementById('appCodigo').value='APP-';
    document.getElementById('appNome').value='';
    document.getElementById('appInicio').value='';
    document.getElementById('appFim').value='';
    document.getElementById('appObs').value='';
    setDias(31);
    ['manha','tarde','noite'].forEach(function(t){
        document.getElementById('tchk-'+t).checked=false;
        toggleTurnoFields(t);
        document.getElementById('thi-'+t).value='';
        document.getElementById('thf-'+t).value='';
        document.getElementById('tinst-'+t).value='';
        document.getElementById('tlab-'+t).value='';
    });
    document.getElementById('modalAppTitulo').innerHTML='<i class="fas fa-chalkboard mr-2"></i>Nova turma APP';

    if(id){
        var td=_turmasData.find(function(t){return t.id===id;});
        if(!td) return;
        document.getElementById('appId').value=id;
        document.getElementById('appCodigo').value=td.codigo;
        document.getElementById('appNome').value=td.nome;
        document.getElementById('appInicio').value=td.dataInicio;
        document.getElementById('appFim').value=td.dataFim;
        document.getElementById('appObs').value=td.observacao||'';
        setDias(td.diasSemana);
        document.getElementById('modalAppTitulo').innerHTML='<i class="fas fa-pen mr-2"></i>Editar turma APP';
        // Preenche turnos
        if(td.turnos_raw){
            td.turnos_raw.split(';;').forEach(function(tr){
                var p=tr.split('|');
                if(p.length<4) return;
                var turno=p[0];
                document.getElementById('tchk-'+turno).checked=true;
                toggleTurnoFields(turno);
                document.getElementById('thi-'+turno).value=p[1];
                document.getElementById('thf-'+turno).value=p[2];
                document.getElementById('tinst-'+turno).value=p[3];
                if(p[4]) document.getElementById('tlab-'+turno).value=p[4];
            });
        }
    }
    $('#modalApp').modal('show');
}

function salvarApp(){
    var id    =document.getElementById('appId').value;
    var codigo=document.getElementById('appCodigo').value.trim();
    var nome  =document.getElementById('appNome').value.trim();
    var inicio=document.getElementById('appInicio').value;
    var fim   =document.getElementById('appFim').value;
    var obs   =document.getElementById('appObs').value.trim();
    var dias  =calcDias();
    if(!codigo||!nome||!inicio||!fim){alert('Preencha os campos obrigatórios.');return;}
    if(!/^APP-/i.test(codigo)){alert('O código deve começar com APP-.');return;}

    var turnos=[];
    ['manha','tarde','noite'].forEach(function(t){
        if(!document.getElementById('tchk-'+t).checked) return;
        turnos.push({
            turno:t,
            horarioInicio:document.getElementById('thi-'+t).value,
            horarioFim:   document.getElementById('thf-'+t).value,
            instrutor:    document.getElementById('tinst-'+t).value,
            idLaboratorio:document.getElementById('tlab-'+t).value||null
        });
    });
    if(!turnos.length){alert('Adicione pelo menos um turno.');return;}

    var fd=new FormData(); fd.append('acao','salvar');
    if(id) fd.append('id',id);
    fd.append('codigo',codigo); fd.append('nome',nome);
    fd.append('dataInicio',inicio); fd.append('dataFim',fim);
    fd.append('diasSemana',dias); fd.append('observacao',obs);
    fd.append('turnos',JSON.stringify(turnos));
    fetch('cadastroAppManual.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            toast('Salvo!','ok');
            $('#modalApp').modal('hide');
            setTimeout(function(){location.reload();},700);
        }).catch(function(){toast('Erro.','err');});
}

function excluir(id){
    if(!confirm('Excluir esta turma APP?')) return;
    var fd=new FormData(); fd.append('acao','excluir'); fd.append('id',id);
    fetch('cadastroAppManual.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){toast(res.msg||'Erro.','err');return;}
            var el=document.getElementById('acard-'+id); if(el) el.remove();
            toast('Removido.','ok');
        }).catch(function(){toast('Erro.','err');});
}

function toggleAtivo(id,ativo){
    var fd=new FormData(); fd.append('acao','toggle_ativo'); fd.append('id',id); fd.append('ativo',ativo);
    fetch('cadastroAppManual.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){ if(res.ok) location.reload(); })
        .catch(function(){});
}

var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toastApp');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>