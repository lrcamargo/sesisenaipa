<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

$permGerenciar = in_array($nivelNorm,[
    'admin','administrator','sup tecnica','sup pedagogica','gerencia'
]);
if(!$permGerenciar){ header('location:../index.php'); exit; }

/* ── Bitmask ── */
const DIAS_BIT   = ['seg'=>1,'ter'=>2,'qua'=>4,'qui'=>8,'sex'=>16,'sab'=>32];
const DIAS_LABEL = ['seg'=>'Seg','ter'=>'Ter','qua'=>'Qua','qui'=>'Qui','sex'=>'Sex','sab'=>'Sáb'];

function diasParaTexto(int $mask): string {
    $out = [];
    foreach(DIAS_BIT as $d => $b){ if(($mask & $b) > 0) $out[] = DIAS_LABEL[$d]; }
    return implode(' ', $out) ?: '—';
}

function tipoTurma(string $cod): string {
    if(preg_match('/^EM-/i',$cod))  return 'EM';
    if(preg_match('/^EF-/i',$cod))  return 'EF';
    if(preg_match('/^APP-/i',$cod)) return 'APP';
    if(preg_match('/^HT-/i',$cod))  return 'HT';
    if(preg_match('/^AI-/i',$cod))  return 'AI';
    return 'TEC';
}

/* ── Salas ── */
$stmtSalas = $pdo->prepare("SELECT idLaboratorio, nome FROM laboratorios WHERE temSala=1 ORDER BY nome");
$stmtSalas->execute();
$salas = $stmtSalas->fetchAll(PDO::FETCH_ASSOC);

/* ── Vínculos — múltiplos por célula ── */
$stmtVinc = $pdo->prepare("
    SELECT ts.id, ts.codigoTurma, ts.idSala, ts.turno, ts.diasSemana,
           l.nome AS nomeSala
    FROM turma_sala ts
    LEFT JOIN laboratorios l ON l.idLaboratorio = ts.idSala
    ORDER BY ts.idSala, ts.turno, ts.codigoTurma
");
$stmtVinc->execute();
$vinculos = $stmtVinc->fetchAll(PDO::FETCH_ASSOC);

/*
 * grade[idSala][turno] = [ vínculo, vínculo, ... ]
 * Integral expande para manhã e tarde
 */
$grade = [];
foreach($salas as $s){
    $grade[$s['idLaboratorio']] = ['manha'=>[],'tarde'=>[],'noite'=>[]];
}
foreach($vinculos as $v){
    if(!isset($grade[$v['idSala']])) continue;
    $entry = [
        'id'          => $v['id'],
        'codigoTurma' => $v['codigoTurma'],
        'turno'       => $v['turno'],
        'diasSemana'  => (int)$v['diasSemana'],
        'tipo'        => tipoTurma($v['codigoTurma']),
    ];
    if($v['turno'] === 'integral'){
        $grade[$v['idSala']]['manha'][] = $entry;
        $grade[$v['idSala']]['tarde'][] = array_merge($entry, ['_integralFill'=>true]);
    } else {
        $grade[$v['idSala']][$v['turno']][] = $entry;
    }
}

/* ── Turmas da API ── */
$turmasAPI = []; $catracaDisp = false;
$ch = curl_init('http://172.16.95.253:3002/backapi/Turmas?dataInicio='.date('Y-m-d'));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
$resp = curl_exec($ch); $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($httpCode>=200 && $httpCode<300 && $resp){
    $dec = json_decode($resp,true);
    if(is_array($dec) && count($dec)>0){
        foreach($dec as $t){ if(isset($t['nome'])) $turmasAPI[]=$t['nome']; }
        sort($turmasAPI); $catracaDisp=true;
    }
}

/* Turmas que já têm pelo menos um vínculo */
$turmasComVinculo = array_unique(array_column($vinculos,'codigoTurma'));

$msgs=['salvo'=>['tipo'=>'success','texto'=>'Vínculo salvo.'],
       'removido'=>['tipo'=>'success','texto'=>'Vínculo removido.'],
       'erro_db'=>['tipo'=>'danger','texto'=>'Erro ao salvar.']];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestão de Turmas × Salas</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.wrapper .main-container{height:auto !important;min-height:calc(100vh - 70px);overflow-x:hidden;}
.gestao-wrapper{display:flex;gap:20px;align-items:flex-start;min-width:0;width:100%;box-sizing:border-box;}
.painel-turmas{width:220px;flex-shrink:0;background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:12px;position:sticky;top:80px;max-height:calc(100vh - 100px);overflow-y:auto;}
.painel-turmas h6{font-weight:700;margin-bottom:8px;font-size:.9rem;color:#495057;}
.busca-turma{width:100%;margin-bottom:8px;padding:5px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.82rem;}
.lista-turmas{display:flex;flex-direction:column;gap:4px;}

/* Chips arrastáveis */
.turma-chip{background:#e3f2fd;color:#0d47a1;border:1px solid #90caf9;border-radius:5px;padding:5px 9px;font-size:.78rem;font-weight:600;cursor:grab;user-select:none;transition:background .15s,transform .1s;}
.turma-chip:hover{background:#bbdefb;}
.turma-chip.dragging{opacity:.5;cursor:grabbing;transform:scale(1.04);}
.turma-chip[data-tipo="EM"]{background:#f3e5f5;color:#6a1b9a;border-color:#ce93d8;}
.turma-chip[data-tipo="EF"]{background:#e8f5e9;color:#1b5e20;border-color:#a5d6a7;}
.turma-chip[data-tipo="APP"]{background:#fff8e1;color:#e65100;border-color:#ffcc80;}
.turma-chip.manual{background:#fff3cd;color:#856404;border-color:#ffc107;}

/* Grade */
.grade-wrapper{flex:1;min-width:0;overflow-x:auto;}
.grade-table{width:100%;border-collapse:separate;border-spacing:4px;}
.grade-table th{background:#343a40;color:#fff;text-align:center;padding:8px 12px;border-radius:5px;font-size:.85rem;white-space:nowrap;}
.grade-table th.th-sala{background:#495057;text-align:left;min-width:140px;}
.grade-table td{vertical-align:top;min-width:160px;height:1px;}
.grade-table td > .drop-cell{height:100%;}

/* Célula droppable */
.drop-cell{min-height:72px;border:2px dashed #dee2e6;border-radius:7px;padding:4px;display:flex;flex-direction:column;gap:4px;transition:background .15s,border-color .15s;position:relative;}
.drop-cell.vazia-hint::after{content:'Arraste aqui';font-size:.72rem;color:#adb5bd;display:flex;align-items:center;justify-content:center;flex:1;pointer-events:none;}
.drop-cell.drag-over{background:#e8f5e9;border-color:#66bb6a;}
.drop-cell.drag-over-block{background:#ffebee;border-color:#ef9a9a;}

/* Chip dentro da célula */
.chip-na-grade{display:flex;flex-direction:column;gap:2px;border-radius:5px;padding:5px 7px;font-size:.76rem;font-weight:600;background:#e3f2fd;color:#0d47a1;border:1px solid #90caf9;}
.chip-na-grade[data-tipo="EM"]{background:#f3e5f5;color:#6a1b9a;border-color:#ce93d8;}
.chip-na-grade[data-tipo="EF"]{background:#e8f5e9;color:#1b5e20;border-color:#a5d6a7;}
.chip-na-grade[data-tipo="APP"]{background:#fff8e1;color:#e65100;border-color:#ffcc80;}
.chip-na-grade.integral-fill{opacity:.55;pointer-events:none;font-size:.7rem;}
.chip-turma-nome{font-weight:700;}
.chip-dias{font-size:.67rem;font-weight:400;opacity:.75;}
.chip-actions{display:flex;gap:4px;justify-content:flex-end;margin-top:2px;}
.btn-chip{background:none;border:none;cursor:pointer;padding:0;color:inherit;opacity:.6;font-size:.75rem;line-height:1;}
.btn-chip:hover{opacity:1;}

/* Modal */
.dias-grid{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
.dia-btn{padding:6px 12px;border-radius:4px;border:1px solid #ced4da;background:#fff;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .15s;color:#495057;user-select:none;}
.dia-btn:hover{background:#e9ecef;}
.dia-btn.selecionado{background:#0d6efd;color:#fff;border-color:#0d6efd;}
.dias-soma{font-size:.8rem;color:#6c757d;margin-top:6px;}
.dias-shortcuts{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;}
.dias-shortcuts button{font-size:.75rem;padding:3px 10px;border-radius:3px;border:1px solid #ced4da;background:#fff;cursor:pointer;}
.dias-shortcuts button:hover{background:#e9ecef;}

/* Toast */
#toastGestao{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:280px;display:none;padding:12px 18px;border-radius:6px;font-size:.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toastGestao.sucesso{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toastGestao.erro{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

/* Manual */
.add-manual{margin-top:10px;padding-top:10px;border-top:1px solid #dee2e6;}
.add-manual input{width:100%;padding:5px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.82rem;margin-bottom:4px;text-transform:uppercase;}
.add-manual button{width:100%;padding:5px;font-size:.78rem;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;}
.add-manual button:hover{background:#495057;}
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

<?php if(isset($_GET['msg'])&&isset($msgs[$_GET['msg']])){ $m=$msgs[$_GET['msg']]; echo "<div class='alert alert-{$m['tipo']} text-center'>{$m['texto']}</div>"; } ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-chalkboard mr-2"></i>Gestão de Turmas × Salas</h4>
    <small class="text-muted">Arraste a turma para a sala. A mesma turma pode ter turnos diferentes.</small>
</div>

<div class="gestao-wrapper">

    <!-- Painel lateral -->
    <div class="painel-turmas">
        <h6><i class="fas fa-users mr-1"></i>Turmas</h6>
        <?php if(!$catracaDisp){ ?><div class="alert alert-warning p-2 mb-2" style="font-size:.78rem"><i class="fas fa-exclamation-triangle mr-1"></i>API indisponível.</div><?php } ?>
        <input type="text" class="busca-turma" id="buscaTurma" placeholder="Filtrar...">
        <div class="lista-turmas" id="listaTurmas">
        <?php
        $turmasParaExibir = $turmasAPI;
        foreach($turmasComVinculo as $tv){ if(!in_array($tv,$turmasParaExibir)) $turmasParaExibir[]=$tv; }
        sort($turmasParaExibir);
        foreach($turmasParaExibir as $t){
            $tipo = tipoTurma($t);
            echo "<div class='turma-chip' draggable='true'
                       data-turma='".htmlspecialchars($t)."'
                       data-tipo='{$tipo}'
                       title='Arraste para uma sala'>
                       ".htmlspecialchars($t)."
                  </div>";
        }
        ?>
        </div>
        <?php if(!$catracaDisp){ ?>
        <div class="add-manual">
            <small>Adicionar manualmente:</small>
            <input type="text" id="turmaManualAdd" placeholder="Ex: HT-MET-01-M-25" maxlength="50">
            <button onclick="adicionarManual()"><i class="fas fa-plus mr-1"></i>Adicionar</button>
        </div>
        <?php } ?>
    </div>

    <!-- Grade -->
    <div class="grade-wrapper">
    <table class="grade-table">
        <thead>
            <tr>
                <th class="th-sala"><i class="fas fa-door-open mr-1"></i>Sala</th>
                <th><i class="fas fa-sun mr-1"></i>Manhã</th>
                <th><i class="fas fa-cloud-sun mr-1"></i>Tarde</th>
                <th><i class="fas fa-moon mr-1"></i>Noite</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($salas as $sala){
            $idSala = $sala['idLaboratorio'];
            echo "<tr><th class='th-sala'>".htmlspecialchars($sala['nome'])."</th>";
            foreach(['manha','tarde','noite'] as $turno){
                $chips = $grade[$idSala][$turno] ?? [];
                $temChip = count($chips) > 0;
                echo "<td><div class='drop-cell ".($temChip?'':'vazia-hint')."' data-sala='{$idSala}' data-turno='{$turno}'>";
                foreach($chips as $chip){
                    $isIF  = $chip['_integralFill'] ?? false;
                    $tipo  = $chip['tipo'];
                    $dias  = diasParaTexto($chip['diasSemana']);
                    $cls   = $isIF ? 'chip-na-grade integral-fill' : 'chip-na-grade';
                    echo "<div class='{$cls}' data-tipo='{$tipo}' data-id='{$chip['id']}'>";
                    echo "<span class='chip-turma-nome'>".htmlspecialchars($chip['codigoTurma'])."</span>";
                    echo "<span class='chip-dias'>{$dias}</span>";
                    if(!$isIF){
                        echo "<div class='chip-actions'>";
                        echo "<button class='btn-chip' onclick=\"abrirEdicao({$chip['id']})\" title='Editar'><i class='fas fa-pen'></i></button>";
                        echo "<button class='btn-chip' onclick=\"removerVinculo({$chip['id']},'".\addslashes($chip['codigoTurma'])."')\" title='Remover'><i class='fas fa-times'></i></button>";
                        echo "</div>";
                    } else {
                        echo "<span class='chip-dias'>(integral)</span>";
                    }
                    echo "</div>";
                }
                echo "</div></td>";
            }
            echo "</tr>";
        } ?>
        </tbody>
    </table>
    </div>
</div>
</div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalConfig" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalTitulo"><i class="fas fa-cog mr-2"></i>Configurar vínculo</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
    <div class="modal-body">
        <p class="mb-1"><strong>Turma:</strong> <span id="modalTurmaNome"></span></p>
        <p class="mb-2"><strong>Sala:</strong> <span id="modalSalaNome"></span></p>
        <div class="form-group mb-3">
            <label class="font-weight-bold mb-1">Turno</label>
            <select id="modalTurnoSelect" class="form-control form-control-sm">
                <option value="manha">Manhã</option>
                <option value="tarde">Tarde</option>
                <option value="noite">Noite</option>
            </select>
            <small class="text-muted">
                Turmas EM: arraste duas vezes — uma para manhã, outra para tarde com os dias corretos.
            </small>
        </div>
        <hr>
        <label class="font-weight-bold mb-1">Dias que esta turma ocorre neste turno:</label>
        <div class="dias-shortcuts">
            <button type="button" onclick="marcarMask(31)">Seg–Sex</button>
            <button type="button" onclick="marcarMask(32)">Só Sáb</button>
            <button type="button" onclick="marcarMask(63)">Todos</button>
            <button type="button" onclick="marcarMask(10)">Ter/Qui</button>
            <button type="button" onclick="marcarMask(21)">Seg/Qua/Sex</button>
            <button type="button" onclick="marcarMask(0)">Limpar</button>
        </div>
        <div class="dias-grid" id="diasGrid">
            <button type="button" class="dia-btn selecionado" data-bit="1">Seg</button>
            <button type="button" class="dia-btn selecionado" data-bit="2">Ter</button>
            <button type="button" class="dia-btn selecionado" data-bit="4">Qua</button>
            <button type="button" class="dia-btn selecionado" data-bit="8">Qui</button>
            <button type="button" class="dia-btn selecionado" data-bit="16">Sex</button>
            <button type="button" class="dia-btn"             data-bit="32">Sáb</button>
        </div>
        <div class="dias-soma" id="diasSomaInfo">Valor: <strong id="diasSomaVal">31</strong> (Seg Ter Qua Qui Sex)</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnConfirmar">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toastGestao"></div>
<script src="../js/menu.js"></script>
<script>
/* ── Bitmask ── */
var DIAS=[{bit:1,label:'Seg'},{bit:2,label:'Ter'},{bit:4,label:'Qua'},
          {bit:8,label:'Qui'},{bit:16,label:'Sex'},{bit:32,label:'Sáb'}];
function maskParaTexto(mask){
    return DIAS.filter(function(d){return (mask&d.bit)>0;}).map(function(d){return d.label;}).join(' ')||'—';
}

/* ── Dados do servidor ── */
var salaNames = <?php $sn=[]; foreach($salas as $s) $sn[$s['idLaboratorio']]=$s['nome']; echo json_encode($sn); ?>;

/* ── Estado pendente (drop ou edição) ── */
var pendente = null; // { turma, idSala, turno, tipo, cell, editId }
var chipArrastando = null;

/* ════════════════════════════════════════
   DRAG
   ════════════════════════════════════════ */
document.addEventListener('dragstart',function(e){
    var chip=e.target.closest('.turma-chip');
    if(!chip) return;
    chipArrastando=chip; chip.classList.add('dragging');
    e.dataTransfer.effectAllowed='move';
});
document.addEventListener('dragend',function(){
    if(chipArrastando) chipArrastando.classList.remove('dragging');
    chipArrastando=null;
    document.querySelectorAll('.drop-cell').forEach(function(c){ c.classList.remove('drag-over','drag-over-block'); });
});
document.addEventListener('dragover',function(e){
    var cell=e.target.closest('.drop-cell');
    if(!cell||!chipArrastando) return;
    e.preventDefault();
    document.querySelectorAll('.drop-cell').forEach(function(c){ c.classList.remove('drag-over','drag-over-block'); });
    // Sempre permite drop — validação real é no PHP via bitmask
    cell.classList.add('drag-over');
});
document.addEventListener('dragleave',function(e){
    var cell=e.target.closest('.drop-cell'); if(cell) cell.classList.remove('drag-over','drag-over-block');
});
document.addEventListener('drop',function(e){
    var cell=e.target.closest('.drop-cell');
    if(!cell||!chipArrastando) return;
    e.preventDefault(); cell.classList.remove('drag-over','drag-over-block');

    var idSala = cell.dataset.sala;
    var turno  = cell.dataset.turno;
    var turma  = chipArrastando.dataset.turma;
    var tipo   = chipArrastando.dataset.tipo;

    pendente = {turma:turma, idSala:idSala, turno:turno, tipo:tipo, cell:cell, editId:null};

    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-cog mr-2"></i>Novo vínculo';
    document.getElementById('modalTurmaNome').textContent = turma;
    document.getElementById('modalSalaNome').textContent  = salaNames[idSala]||idSala;
    // Pré-seleciona o turno da célula onde foi solto — editável
    var turnoOpcoes = ['manha','tarde','noite'];
    var turnoInicial = turnoOpcoes.includes(turno) ? turno : 'manha';
    document.getElementById('modalTurnoSelect').value = turnoInicial;
    marcarMask(31);
    $('#modalConfig').modal('show');
});

/* ════════════════════════════════════════
   EDIÇÃO de vínculo existente
   ════════════════════════════════════════ */
function abrirEdicao(id){
    // Busca chip na grade para pegar os dados
    var chip = document.querySelector('.chip-na-grade[data-id="'+id+'"]');
    if(!chip){ mostrarToast('Vínculo não encontrado.','erro'); return; }
    var cell   = chip.closest('.drop-cell');
    var turma  = chip.querySelector('.chip-turma-nome').textContent;
    var idSala = cell.dataset.sala;
    var turno  = cell.dataset.turno;
    var tipo   = chip.dataset.tipo;
    // Pega diasSemana do atributo data (adicionado na renderização)
    var mask   = parseInt(chip.dataset.dias || '31');

    pendente = {turma:turma, idSala:idSala, turno:turno, tipo:tipo, cell:cell, editId:id};

    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-pen mr-2"></i>Editar vínculo';
    document.getElementById('modalTurmaNome').textContent = turma;
    document.getElementById('modalSalaNome').textContent  = salaNames[idSala]||idSala;
    document.getElementById('modalTurnoSelect').value     = turno;
    marcarMask(mask);
    $('#modalConfig').modal('show');
}

/* ════════════════════════════════════════
   CONFIRMAR (salvar ou atualizar)
   ════════════════════════════════════════ */
document.getElementById('btnConfirmar').addEventListener('click',function(){
    if(!pendente) return;
    var mask  = calcMask();
    if(mask===0){ alert('Selecione pelo menos um dia.'); return; }
    var turno = document.getElementById('modalTurnoSelect').value;

    // Captura os dados antes de fechar o modal (hidden.bs.modal zeraria pendente)
    var snap = {
        editId  : pendente.editId,
        turma   : pendente.turma,
        idSala  : pendente.idSala,
        tipo    : pendente.tipo,
        cell    : pendente.cell,
    };
    pendente = null; // zera antes do hide para evitar double-fire

    $('#modalConfig').modal('hide');

    if(snap.editId){
        chamarAPI('atualizar', {id:snap.editId, turno:turno, diasSemana:mask}, function(res){
            if(!res.ok){ mostrarToast(res.msg||'Erro.','erro'); return; }
            var chip = document.querySelector('.chip-na-grade[data-id="'+snap.editId+'"]');
            if(chip){
                chip.querySelector('.chip-dias').textContent = maskParaTexto(mask);
                chip.dataset.dias = mask;
            }
            mostrarToast('Vínculo atualizado!','sucesso');
        });
    } else {
        chamarAPI('salvar', {
            codigoTurma: snap.turma,
            idSala:      snap.idSala,
            turno:       turno,
            diasSemana:  mask,
        }, function(res){
            if(!res.ok){ mostrarToast(res.msg||'Erro.','erro'); return; }
            adicionarChipNaCelula(snap.cell, snap.turma, snap.tipo,
                                  res.id, turno, mask, false);
            mostrarToast('Vínculo salvo!','sucesso');
        });
    }
});

$('#modalConfig').on('hidden.bs.modal',function(){ pendente=null; });

/* ════════════════════════════════════════
   REMOVER
   ════════════════════════════════════════ */
function removerVinculo(id, turma){
    if(!confirm('Remover o vínculo da turma "'+turma+'" (id '+id+')?')) return;
    chamarAPI('remover',{id:id},function(res){
        if(!res.ok){ mostrarToast(res.msg||'Erro.','erro'); return; }
        // Remove todos os chips com este id (inclusive integral-fill)
        document.querySelectorAll('.chip-na-grade[data-id="'+id+'"]').forEach(function(chip){
            var cell=chip.closest('.drop-cell');
            chip.remove();
            // Adiciona hint se célula ficou vazia
            if(cell && cell.querySelectorAll('.chip-na-grade').length===0){
                cell.classList.add('vazia-hint');
            }
        });
        mostrarToast('Vínculo removido.','sucesso');
    });
}

/* ════════════════════════════════════════
   RENDER chip na grade
   ════════════════════════════════════════ */
function adicionarChipNaCelula(cell, turma, tipo, id, turno, mask, isIF){
    cell.classList.remove('vazia-hint');
    var div=document.createElement('div');
    div.className = isIF ? 'chip-na-grade integral-fill' : 'chip-na-grade';
    div.dataset.tipo = tipo;
    div.dataset.id   = id;
    div.dataset.dias = mask;  // armazena para edição posterior

    var n=document.createElement('span'); n.className='chip-turma-nome'; n.textContent=turma; div.appendChild(n);
    var ds=document.createElement('span'); ds.className='chip-dias'; ds.textContent=maskParaTexto(mask); div.appendChild(ds);

    if(!isIF){
        var acts=document.createElement('div'); acts.className='chip-actions';
        var btnE=document.createElement('button'); btnE.className='btn-chip'; btnE.title='Editar';
        btnE.innerHTML='<i class="fas fa-pen"></i>';
        btnE.onclick=function(){ abrirEdicao(id); }; acts.appendChild(btnE);
        var btnR=document.createElement('button'); btnR.className='btn-chip'; btnR.title='Remover';
        btnR.innerHTML='<i class="fas fa-times"></i>';
        btnR.onclick=function(){ removerVinculo(id,turma); }; acts.appendChild(btnR);
        div.appendChild(acts);
    } else {
        var s=document.createElement('span'); s.className='chip-dias'; s.textContent='(integral)'; div.appendChild(s);
    }
    cell.appendChild(div);
}

/* ════════════════════════════════════════
   DIAS — botões de toggle
   ════════════════════════════════════════ */
document.getElementById('diasGrid').addEventListener('click',function(e){
    var btn = e.target.closest('.dia-btn'); if(!btn) return;
    var sel = btn.classList.toggle('selecionado');
    // Limpa style inline ao desmarcar para não sobrescrever o CSS
    if(!sel){
        btn.style.background  = '';
        btn.style.color       = '';
        btn.style.borderColor = '';
    }
    atualizarSoma();
});
function marcarMask(mask){
    document.querySelectorAll('.dia-btn').forEach(function(btn){
        var bit = parseInt(btn.dataset.bit);
        var sel = (mask & bit) > 0;
        // Usa apenas a classe — sem style inline para não conflitar com o CSS
        if(sel){
            btn.classList.add('selecionado');
        } else {
            btn.classList.remove('selecionado');
            // Garante limpeza de qualquer style inline residual
            btn.style.background  = '';
            btn.style.color       = '';
            btn.style.borderColor = '';
        }
    });
    atualizarSoma();
}
function calcMask(){ var m=0; document.querySelectorAll('.dia-btn.selecionado').forEach(function(b){ m|=parseInt(b.dataset.bit); }); return m; }
function atualizarSoma(){
    var mask=calcMask();
    var elVal  = document.getElementById('diasSomaVal');
    var elInfo = document.getElementById('diasSomaInfo');
    // Elementos só existem quando o modal está aberto
    if(elVal)  elVal.textContent = mask;
    if(elInfo) elInfo.innerHTML  = 'Valor: <strong>'+mask+'</strong> ('+maskParaTexto(mask)+')';
}

/* ════════════════════════════════════════
   API helper
   ════════════════════════════════════════ */
function chamarAPI(acao, dados, cb){
    var fd=new FormData(); fd.append('acao',acao);
    Object.keys(dados).forEach(function(k){ fd.append(k,dados[k]); });
    fetch('salvarTurma.php',{method:'POST',body:fd})
        .then(function(r){
            // Tenta parsear JSON; se falhar mostra o texto bruto para diagnóstico
            return r.text().then(function(txt){
                try{ return JSON.parse(txt); }
                catch(e){ throw new Error('Resposta inválida: '+txt.substring(0,200)); }
            });
        })
        .then(cb)
        .catch(function(e){ mostrarToast(e.message||'Erro de comunicação.','erro'); });
}

/* ════════════════════════════════════════
   FILTRO e MANUAL
   ════════════════════════════════════════ */
document.getElementById('buscaTurma').addEventListener('input',function(){
    var q=this.value.toLowerCase();
    document.querySelectorAll('#listaTurmas .turma-chip').forEach(function(c){
        c.style.display=c.dataset.turma.toLowerCase().includes(q)?'':'none';
    });
});
function adicionarManual(){
    var inp=document.getElementById('turmaManualAdd'); if(!inp) return;
    var val=inp.value.trim().toUpperCase(); if(!val){alert('Digite o código.');return;}
    var jaExiste=false;
    document.querySelectorAll('#listaTurmas .turma-chip').forEach(function(c){if(c.dataset.turma===val) jaExiste=true;});
    if(jaExiste){alert('Já está na lista.');return;}
    var tipo='TEC';
    if(/^EM-/.test(val)) tipo='EM'; else if(/^EF-/.test(val)) tipo='EF';
    else if(/^APP-/.test(val)) tipo='APP'; else if(/^HT-/.test(val)) tipo='HT';
    var chip=document.createElement('div');
    chip.className='turma-chip manual'; chip.draggable=true;
    chip.dataset.turma=val; chip.dataset.tipo=tipo; chip.textContent=val;
    document.getElementById('listaTurmas').appendChild(chip);
    inp.value=''; mostrarToast('Adicionado.','sucesso');
}

/* ════════════════════════════════════════
   AUTO-SCROLL e TOAST
   ════════════════════════════════════════ */
var ast=null,SZ=100,SV=12;
document.addEventListener('dragover',function(e){
    var y=e.clientY,h=window.innerHeight;
    if(ast){clearInterval(ast);ast=null;}
    if(y<SZ){var v=Math.round(SV*(1-y/SZ));ast=setInterval(function(){window.scrollBy(0,-v);},16);}
    else if(y>h-SZ){var v=Math.round(SV*(1-(h-y)/SZ));ast=setInterval(function(){window.scrollBy(0,v);},16);}
},false);
document.addEventListener('dragend',function(){if(ast){clearInterval(ast);ast=null;}});

var tt=null;
function mostrarToast(msg,tipo){
    var el=document.getElementById('toastGestao');
    el.textContent=msg; el.className=tipo==='sucesso'?'sucesso':'erro'; el.style.display='block';
    if(tt) clearTimeout(tt); tt=setTimeout(function(){el.style.display='none';},3500);
}
</script>
</body>
</html>