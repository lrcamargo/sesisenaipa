<?php
/*
 * cadastroCursos.php
 * Cadastro de cursos técnicos e suas Unidades Curriculares (UCs).
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    header('location:../index.php'); exit;
}

/* ══════════════════════════════
   POST AJAX
══════════════════════════════ */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    /* ── Cursos ── */
    if($_POST['acao']==='salvar_curso'){
        $id   = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $cod  = trim($_POST['codigo'] ?? '') ?: null;
        $desc = trim($_POST['descricao'] ?? '') ?: null;
        if(!$nome){ echo json_encode(['ok'=>false,'msg'=>'Nome obrigatório.']); exit; }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE cursos_tecnicos SET nome=?,codigo=?,descricao=? WHERE id=?")
                    ->execute([$nome,$cod,$desc,$id]);
            } else {
                $pdo->prepare("INSERT INTO cursos_tecnicos (nome,codigo,descricao) VALUES (?,?,?)")
                    ->execute([$nome,$cod,$desc]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro (código duplicado?).']);
        }
        exit;
    }

    if($_POST['acao']==='excluir_curso'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE cursos_tecnicos SET ativo=0 WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* ── UCs ── */
    if($_POST['acao']==='salvar_uc'){
        $id       = intval($_POST['id']           ?? 0);
        $idCurso  = intval($_POST['idCurso']      ?? 0);
        $nome     = trim($_POST['nome']           ?? '');
        $cod      = trim($_POST['codigo']         ?? '') ?: null;
        $ch       = floatval($_POST['cargaHoraria']  ?? 0);
        $prat     = floatval($_POST['horasPraticas'] ?? 0);
        $ead      = floatval($_POST['horasEad']      ?? 0);
        $obs      = trim($_POST['observacao']     ?? '') ?: null;
        $ordem    = intval($_POST['ordem']        ?? 0);
        if(!$idCurso || !$nome){
            echo json_encode(['ok'=>false,'msg'=>'Curso e nome são obrigatórios.']); exit;
        }
        if($prat > $ch){
            echo json_encode(['ok'=>false,'msg'=>'Horas práticas não podem exceder a carga horária total.']); exit;
        }
        if($ead > $ch || ($prat+$ead) > $ch){
            echo json_encode(['ok'=>false,'msg'=>'Horas EAD + práticas não podem exceder a carga horária total.']); exit;
        }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE curso_ucs SET nome=?,codigo=?,cargaHoraria=?,horasPraticas=?,horasEad=?,observacao=?,ordem=? WHERE id=?")
                    ->execute([$nome,$cod,$ch,$prat,$ead,$obs,$ordem,$id]);
            } else {
                $pdo->prepare("INSERT INTO curso_ucs (idCurso,nome,codigo,cargaHoraria,horasPraticas,horasEad,observacao,ordem) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$idCurso,$nome,$cod,$ch,$prat,$ead,$obs,$ordem]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar UC.']);
        }
        exit;
    }

    if($_POST['acao']==='excluir_uc'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE curso_ucs SET ativo=0 WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    if($_POST['acao']==='reordenar_ucs'){
        $ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
        try {
            $st = $pdo->prepare("UPDATE curso_ucs SET ordem=? WHERE id=?");
            foreach($ids as $i => $ucId) $st->execute([$i+1, intval($ucId)]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false]); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ══════════════════════════════
   Dados
══════════════════════════════ */
$cursos = $pdo->query("
    SELECT c.id, c.nome, c.codigo, c.descricao,
           COUNT(u.id) AS totalUCs,
           SUM(u.cargaHoraria) AS totalHoras
    FROM cursos_tecnicos c
    LEFT JOIN curso_ucs u ON u.idCurso=c.id AND u.ativo=1
    WHERE c.ativo=1
    GROUP BY c.id
    ORDER BY c.nome
")->fetchAll(PDO::FETCH_ASSOC);

$idCursoSel = intval($_GET['curso'] ?? ($cursos[0]['id'] ?? 0));

$ucs = [];
if($idCursoSel){
    $st = $pdo->prepare("
        SELECT id, nome, codigo, cargaHoraria, horasPraticas, horasEad, observacao, ordem
        FROM curso_ucs
        WHERE idCurso=? AND ativo=1
        ORDER BY ordem, nome
    ");
    $st->execute([$idCursoSel]);
    $ucs = $st->fetchAll(PDO::FETCH_ASSOC);
}

$cursoSelNome = '';
$cursoSelCod  = '';
foreach($cursos as $c){
    if($c['id']==$idCursoSel){ $cursoSelNome=$c['nome']; $cursoSelCod=$c['codigo']; }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cursos Técnicos</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.curso-list{display:flex;flex-direction:column;gap:6px;margin-bottom:20px;}
.curso-btn{display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;border-radius:8px;border:2px solid #dee2e6;background:#fff;
    font-size:.83rem;font-weight:600;cursor:pointer;color:#495057;text-decoration:none;
    transition:all .15s;}
.curso-btn:hover{border-color:#0d6efd;color:#0d6efd;text-decoration:none;}
.curso-btn.ativo{border-color:#0d6efd;background:#0d6efd;color:#fff;}
.curso-btn.ativo .curso-meta{color:#cfe2ff;}
.curso-meta{font-size:.72rem;font-weight:400;opacity:.8;}
.uc-table th{background:#343a40;color:#fff;font-size:.8rem;white-space:nowrap;}
.uc-table td{font-size:.83rem;vertical-align:middle;}
.badge-ch{background:#e3f2fd;color:#0d47a1;border-radius:4px;padding:2px 8px;
    font-size:.72rem;font-weight:700;}
.badge-prat{background:#e8f5e9;color:#1b5e20;border-radius:4px;padding:2px 8px;
    font-size:.72rem;font-weight:700;}
.drag-handle{cursor:grab;color:#adb5bd;padding:0 6px;}
.drag-handle:active{cursor:grabbing;}
.uc-row.dragging{opacity:.5;background:#f0f4ff!important;}
.uc-row.drag-over{border-top:3px solid #0d6efd;}
.empty-state{text-align:center;padding:48px 20px;color:#adb5bd;}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:10px;}
.resumo-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.resumo-pill{padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:700;
    display:flex;align-items:center;gap:6px;}
#toast{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toast.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toast.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-graduation-cap mr-2"></i>Cursos Técnicos</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirNovoCurso()">
        <i class="fas fa-plus mr-1"></i>Novo Curso
    </button>
</div>

<div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">

    <!-- Lista de cursos (coluna esquerda) -->
    <div style="width:280px;flex-shrink:0">
        <div style="font-size:.72rem;font-weight:700;color:#6c757d;text-transform:uppercase;
             letter-spacing:.05em;margin-bottom:8px">Cursos cadastrados</div>
        <?php if(empty($cursos)): ?>
        <div class="text-muted text-center py-4" style="font-size:.83rem">
            Nenhum curso cadastrado ainda.
        </div>
        <?php else: ?>
        <div class="curso-list">
        <?php foreach($cursos as $c):
            $ativo = $c['id']==$idCursoSel;
        ?>
        <a href="?curso=<?php echo $c['id']; ?>"
           class="curso-btn <?php echo $ativo?'ativo':''; ?>">
            <div>
                <div><?php echo htmlspecialchars($c['nome']); ?></div>
                <?php if($c['codigo']): ?>
                <div class="curso-meta"><?php echo htmlspecialchars($c['codigo']); ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div class="curso-meta"><?php echo $c['totalUCs']; ?> UCs</div>
                <div class="curso-meta"><?php echo number_format($c['totalHoras'],0,',','.'); ?>h</div>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- UCs do curso selecionado (coluna direita) -->
    <div style="flex:1;min-width:300px">
    <?php if($idCursoSel): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($cursoSelNome); ?></h5>
                <?php if($cursoSelCod): ?>
                <small class="text-muted"><?php echo htmlspecialchars($cursoSelCod); ?></small>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:6px">
                <button class="btn btn-outline-secondary btn-sm"
                        onclick="editarCurso(<?php echo $idCursoSel; ?>)"
                        title="Editar curso">
                    <i class="fas fa-edit mr-1"></i>Editar
                </button>
                <button class="btn btn-outline-danger btn-sm"
                        onclick="excluirCurso(<?php echo $idCursoSel; ?>)"
                        title="Excluir curso">
                    <i class="fas fa-trash"></i>
                </button>
                <button class="btn btn-primary btn-sm" onclick="abrirNovaUC()">
                    <i class="fas fa-plus mr-1"></i>Nova UC
                </button>
            </div>
        </div>

        <?php if(!empty($ucs)):
            $totalH = array_sum(array_column($ucs,'cargaHoraria'));
            $totalP = array_sum(array_column($ucs,'horasPraticas'));
        ?>
        <div class="resumo-bar">
            <div class="resumo-pill" style="background:#e3f2fd;color:#0d47a1">
                <i class="fas fa-book"></i><?php echo count($ucs); ?> UCs
            </div>
            <div class="resumo-pill" style="background:#fff3e0;color:#e65100">
                <i class="fas fa-clock"></i><?php echo number_format($totalH,1,',','.'); ?>h totais
            </div>
            <?php if($totalP > 0): ?>
            <div class="resumo-pill" style="background:#e8f5e9;color:#1b5e20">
                <i class="fas fa-flask"></i><?php echo number_format($totalP,1,',','.'); ?>h práticas
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
        <table class="table table-sm table-bordered uc-table bg-white" id="ucTable">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th>UC / Disciplina</th>
                    <th style="width:80px">Código</th>
                    <th style="width:100px">C.H. Total</th>
                    <th style="width:110px">H. Práticas</th>
                    <th style="width:100px">H. EAD</th>
                    <th style="width:80px" class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody id="ucTbody">
            <?php foreach($ucs as $uc): ?>
            <tr class="uc-row" data-id="<?php echo $uc['id']; ?>" draggable="true">
                <td class="drag-handle text-center">
                    <i class="fas fa-grip-vertical"></i>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($uc['nome']); ?></strong>
                    <?php if($uc['observacao']): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($uc['observacao']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-muted" style="font-size:.75rem">
                    <?php echo htmlspecialchars($uc['codigo'] ?? '—'); ?>
                </td>
                <td>
                    <span class="badge-ch">
                        <?php echo number_format($uc['cargaHoraria'],1,',','.'); ?>h
                    </span>
                </td>
                <td>
                    <?php if($uc['horasPraticas'] > 0): ?>
                    <span class="badge-prat">
                        <i class="fas fa-flask mr-1"></i><?php echo number_format($uc['horasPraticas'],1,',','.'); ?>h
                    </span>
                    <small class="text-muted ml-1">
                        (<?php echo round($uc['horasPraticas']/$uc['cargaHoraria']*100); ?>%)
                    </small>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($uc['horasEad'] > 0): ?>
                    <span style="background:#e3f2fd;color:#0d47a1;border-radius:4px;padding:2px 8px;font-size:.72rem;font-weight:700">
                        <i class="fas fa-laptop mr-1"></i><?php echo number_format($uc['horasEad'],1,',','.'); ?>h
                    </span>
                    <small class="text-muted ml-1">
                        (<?php echo round($uc['horasEad']/$uc['cargaHoraria']*100); ?>%)
                    </small>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <button class="btn btn-outline-primary btn-sm"
                            onclick="editarUC(<?php echo $uc['id']; ?>,
                                '<?php echo addslashes($uc['nome']); ?>',
                                '<?php echo addslashes($uc['codigo'] ?? ''); ?>',
                                <?php echo $uc['cargaHoraria']; ?>,
                                <?php echo $uc['horasPraticas']; ?>,
                                <?php echo $uc['horasEad']; ?>,
                                '<?php echo addslashes($uc['observacao'] ?? ''); ?>',
                                <?php echo $uc['ordem']; ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm ml-1"
                            onclick="excluirUC(<?php echo $uc['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            Nenhuma UC cadastrada neste curso.<br>
            <button class="btn btn-primary btn-sm mt-3" onclick="abrirNovaUC()">
                <i class="fas fa-plus mr-1"></i>Cadastrar primeira UC
            </button>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-graduation-cap"></i>
        Selecione um curso ao lado ou cadastre um novo.
    </div>
    <?php endif; ?>
    </div><!-- /coluna direita -->

</div><!-- /flex container -->

</div></div>

<!-- Modal Curso -->
<div class="modal fade" id="modalCurso" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalCursoTitle">
            <i class="fas fa-graduation-cap mr-2"></i>Curso Técnico
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="cId" value="0">
        <div class="form-group mb-3">
            <label class="font-weight-bold" style="font-size:.82rem">
                Nome do Curso <span class="text-danger">*</span>
            </label>
            <input type="text" id="cNome" class="form-control form-control-sm"
                   placeholder="Ex: Técnico em Eletrotécnica">
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Código</label>
                <input type="text" id="cCodigo" class="form-control form-control-sm"
                       placeholder="Ex: ELET-2024">
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold" style="font-size:.82rem">Descrição</label>
            <textarea id="cDesc" class="form-control form-control-sm" rows="2"
                      placeholder="Observações gerais sobre o curso..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarCurso()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<!-- Modal UC -->
<div class="modal fade" id="modalUC" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalUCTitle">
            <i class="fas fa-book mr-2"></i>Unidade Curricular
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="uId" value="0">
        <input type="hidden" id="uOrdem" value="0">
        <div class="form-group mb-3">
            <label class="font-weight-bold" style="font-size:.82rem">
                Nome da UC <span class="text-danger">*</span>
            </label>
            <input type="text" id="uNome" class="form-control form-control-sm"
                   placeholder="Ex: Instalações Elétricas Industriais">
        </div>
        <div class="form-row mb-3">
            <div class="col-5">
                <label class="font-weight-bold" style="font-size:.82rem">Código</label>
                <input type="text" id="uCodigo" class="form-control form-control-sm"
                       placeholder="Ex: UC-01">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Carga Horária Total (h) <span class="text-danger">*</span>
                </label>
                <input type="number" id="uCH" class="form-control form-control-sm"
                       min="0" step="0.5" placeholder="Ex: 60"
                       oninput="validarPratica()">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Horas Práticas (h)
                    <i class="fas fa-info-circle text-muted ml-1"
                       title="Subconjunto da carga horária total"></i>
                </label>
                <input type="number" id="uPrat" class="form-control form-control-sm"
                       min="0" step="0.5" placeholder="Ex: 20"
                       oninput="validarPratica()">
                <small id="uPratInfo" class="text-muted"></small>
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Horas EAD (h)
                    <i class="fas fa-info-circle text-muted ml-1"
                       title="Subconjunto da carga horária total — opcional"></i>
                </label>
                <input type="number" id="uEad" class="form-control form-control-sm"
                       min="0" step="0.5" placeholder="Ex: 10"
                       oninput="validarPratica()">
                <small id="uEadInfo" class="text-muted"></small>
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold" style="font-size:.82rem">Observação</label>
            <textarea id="uObs" class="form-control form-control-sm" rows="2"
                      placeholder="Conteúdo, ementa, observações..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarUC()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toast"></div>
<script src="../js/menu.js"></script>
<script>
var _idCursoSel = <?php echo $idCursoSel ?: 0; ?>;

/* ── Validação horas práticas ── */
function validarPratica(){
    var ch   = parseFloat(document.getElementById('uCH').value)   || 0;
    var prat = parseFloat(document.getElementById('uPrat').value)  || 0;
    var ead  = parseFloat(document.getElementById('uEad').value)   || 0;
    var info = document.getElementById('uPratInfo');
    var infoEad = document.getElementById('uEadInfo');
    if(ch > 0 && prat > 0){
        var pct = Math.round(prat/ch*100);
        info.textContent = pct + '% da carga total';
        info.style.color = prat > ch ? '#dc3545' : '#6c757d';
    } else { info.textContent = ''; }
    if(ch > 0 && ead > 0){
        var pctE = Math.round(ead/ch*100);
        infoEad.textContent = pctE + '% da carga total';
        infoEad.style.color = ead > ch ? '#dc3545' : '#6c757d';
    } else { infoEad.textContent = ''; }
    // Avisa se prát+EAD > total
    if(ch > 0 && (prat+ead) > ch){
        info.textContent = '⚠️ Prático + EAD excedem o total!';
        info.style.color = '#dc3545';
    }
}

/* ── Curso ── */
function abrirNovoCurso(){
    document.getElementById('cId').value='0';
    document.getElementById('cNome').value='';
    document.getElementById('cCodigo').value='';
    document.getElementById('cDesc').value='';
    document.getElementById('modalCursoTitle').innerHTML='<i class="fas fa-plus mr-2"></i>Novo Curso Técnico';
    $('#modalCurso').modal('show');
}
function editarCurso(id){
    // Busca dados do curso atual via PHP já no DOM
    <?php foreach($cursos as $c): ?>
    if(id==<?php echo $c['id']; ?>){
        document.getElementById('cId').value=<?php echo $c['id']; ?>;
        document.getElementById('cNome').value=<?php echo json_encode($c['nome']); ?>;
        document.getElementById('cCodigo').value=<?php echo json_encode($c['codigo'] ?? ''); ?>;
        document.getElementById('cDesc').value=<?php echo json_encode($c['descricao'] ?? ''); ?>;
    }
    <?php endforeach; ?>
    document.getElementById('modalCursoTitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar Curso';
    $('#modalCurso').modal('show');
}
function salvarCurso(){
    var id   = document.getElementById('cId').value;
    var nome = document.getElementById('cNome').value.trim();
    var cod  = document.getElementById('cCodigo').value.trim();
    var desc = document.getElementById('cDesc').value.trim();
    if(!nome){ alert('Nome obrigatório.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_curso'); fd.append('id',id);
    fd.append('nome',nome); fd.append('codigo',cod); fd.append('descricao',desc);
    fetch('cadastroCursos.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Salvo!','ok');
            $('#modalCurso').modal('hide');
            setTimeout(()=>location.href='?curso='+res.id, 600);
        }).catch(()=>toast('Erro.','err'));
}
function excluirCurso(id){
    if(!confirm('Excluir este curso e todas as suas UCs?')) return;
    var fd=new FormData(); fd.append('acao','excluir_curso'); fd.append('id',id);
    fetch('cadastroCursos.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Excluído.','ok');
            setTimeout(()=>location.href='cadastroCursos.php', 600);
        }).catch(()=>toast('Erro.','err'));
}

/* ── UC ── */
function abrirNovaUC(){
    document.getElementById('uId').value='0';
    document.getElementById('uNome').value='';
    document.getElementById('uCodigo').value='';
    document.getElementById('uCH').value='';
    document.getElementById('uPrat').value='';
    document.getElementById('uEad').value='';
    document.getElementById('uObs').value='';
    document.getElementById('uPratInfo').textContent='';
    var n=document.querySelectorAll('#ucTbody tr').length;
    document.getElementById('uOrdem').value=n+1;
    document.getElementById('modalUCTitle').innerHTML='<i class="fas fa-plus mr-2"></i>Nova Unidade Curricular';
    $('#modalUC').modal('show');
}
function editarUC(id,nome,cod,ch,prat,ead,obs,ordem){
    document.getElementById('uId').value=id;
    document.getElementById('uNome').value=nome;
    document.getElementById('uCodigo').value=cod;
    document.getElementById('uCH').value=ch;
    document.getElementById('uPrat').value=prat||'';
    document.getElementById('uEad').value=ead||'';
    document.getElementById('uObs').value=obs;
    document.getElementById('uOrdem').value=ordem;
    validarPratica();
    document.getElementById('modalUCTitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar UC';
    $('#modalUC').modal('show');
}
function salvarUC(){
    var id   = document.getElementById('uId').value;
    var nome = document.getElementById('uNome').value.trim();
    var cod  = document.getElementById('uCodigo').value.trim();
    var ch   = parseFloat(document.getElementById('uCH').value)||0;
    var prat = parseFloat(document.getElementById('uPrat').value)||0;
    var ead  = parseFloat(document.getElementById('uEad').value)||0;
    var obs  = document.getElementById('uObs').value.trim();
    var ord  = document.getElementById('uOrdem').value;
    if(!nome||ch<=0){ alert('Nome e carga horária são obrigatórios.'); return; }
    if(prat>ch){ alert('Horas práticas não podem exceder a carga horária total.'); return; }
    if(ead>ch){ alert('Horas EAD não podem exceder a carga horária total.'); return; }
    if((prat+ead)>ch){ alert('Horas práticas + EAD não podem exceder a carga horária total.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_uc'); fd.append('id',id);
    fd.append('idCurso',_idCursoSel); fd.append('nome',nome);
    fd.append('codigo',cod); fd.append('cargaHoraria',ch);
    fd.append('horasPraticas',prat); fd.append('horasEad',ead); fd.append('observacao',obs);
    fd.append('ordem',ord);
    fetch('cadastroCursos.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('UC salva!','ok');
            $('#modalUC').modal('hide');
            setTimeout(()=>location.reload(), 600);
        }).catch(()=>toast('Erro.','err'));
}
function excluirUC(id){
    if(!confirm('Excluir esta UC?')) return;
    var fd=new FormData(); fd.append('acao','excluir_uc'); fd.append('id',id);
    fetch('cadastroCursos.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            var el=document.querySelector('tr.uc-row[data-id="'+id+'"]');
            if(el) el.remove();
            toast('Removida.','ok');
        }).catch(()=>toast('Erro.','err'));
}

/* ── Drag & Drop reordenação ── */
var _dragSrc = null;
document.querySelectorAll('.uc-row').forEach(function(row){
    row.addEventListener('dragstart',function(e){
        _dragSrc=this; this.classList.add('dragging');
        e.dataTransfer.effectAllowed='move';
    });
    row.addEventListener('dragend',function(){
        this.classList.remove('dragging');
        document.querySelectorAll('.uc-row').forEach(r=>r.classList.remove('drag-over'));
        // Salva nova ordem
        var ids=[]; document.querySelectorAll('.uc-row').forEach(r=>ids.push(r.dataset.id));
        var fd=new FormData(); fd.append('acao','reordenar_ucs'); fd.append('ids',JSON.stringify(ids));
        fetch('cadastroCursos.php',{method:'POST',body:fd});
    });
    row.addEventListener('dragover',function(e){
        e.preventDefault(); e.dataTransfer.dropEffect='move';
        document.querySelectorAll('.uc-row').forEach(r=>r.classList.remove('drag-over'));
        if(this!==_dragSrc) this.classList.add('drag-over');
    });
    row.addEventListener('drop',function(e){
        e.preventDefault();
        if(_dragSrc && _dragSrc!==this){
            var tbody=document.getElementById('ucTbody');
            var rows=[...tbody.querySelectorAll('tr.uc-row')];
            var fromIdx=rows.indexOf(_dragSrc);
            var toIdx=rows.indexOf(this);
            if(fromIdx<toIdx) tbody.insertBefore(_dragSrc,this.nextSibling);
            else tbody.insertBefore(_dragSrc,this);
        }
    });
});

/* ── Toast ── */
var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toast');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(()=>el.style.display='none',3000);
}
</script>
</body>
</html>