<?php
/*
 * calendarioAcademico.php
 * Calendário acadêmico — geração e gestão de horários de turmas.
 * Integrado com cursos_tecnicos, curso_ucs e usuarios.
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

    /* Criar/editar turma */
    if($_POST['acao']==='salvar_turma'){
        $id       = intval($_POST['id']        ?? 0);
        $nome     = trim($_POST['nome']        ?? '');
        $codigo   = trim($_POST['codigo']      ?? '') ?: null;
        $idCurso  = intval($_POST['idCurso']   ?? 0);
        $turno    = $_POST['turno']            ?? 'noite';
        $inicio   = trim($_POST['dataInicio']  ?? '');
        $fim      = trim($_POST['dataFim']     ?? '');
        if(!$nome || !$idCurso || !$inicio || !$fim){
            echo json_encode(['ok'=>false,'msg'=>'Preencha todos os campos obrigatórios.']); exit;
        }
        try {
            if($id > 0){
                $pdo->prepare("UPDATE cal_turmas SET nome=?,codigo=?,idCurso=?,turno=?,dataInicio=?,dataFim=? WHERE id=?")
                    ->execute([$nome,$codigo,$idCurso,$turno,$inicio,$fim,$id]);
            } else {
                $pdo->prepare("INSERT INTO cal_turmas (nome,codigo,idCurso,turno,dataInicio,dataFim) VALUES (?,?,?,?,?,?)")
                    ->execute([$nome,$codigo,$idCurso,$turno,$inicio,$fim]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
        }
        exit;
    }

    /* Excluir turma */
    if($_POST['acao']==='excluir_turma'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE cal_turmas SET ativo=0 WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* Salvar aula */
    if($_POST['acao']==='salvar_aula'){
        $idTurma = intval($_POST['idTurma']     ?? 0);
        $idUC    = intval($_POST['idUC']        ?? 0);
        $idInst  = intval($_POST['idInstrutor'] ?? 0) ?: null;
        $data    = trim($_POST['data']          ?? '');
        $tipo    = $_POST['tipo']               ?? 'presencial';
        $obs     = trim($_POST['observacao']    ?? '') ?: null;
        if(!$idTurma || !$idUC || !$data){
            echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
        }
        try {
            // Upsert: se já existe aula nessa data+UC, atualiza
            $pdo->prepare("
                INSERT INTO cal_aulas (idTurma,idUC,idInstrutor,data,tipo,observacao)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE idInstrutor=VALUES(idInstrutor), tipo=VALUES(tipo), observacao=VALUES(observacao)
            ")->execute([$idTurma,$idUC,$idInst,$data,$tipo,$obs]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro: '.$e->getMessage()]);
        }
        exit;
    }

    /* Excluir aula */
    if($_POST['acao']==='excluir_aula'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM cal_aulas WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* Carregar aulas de uma turma (para o calendário) */
    if($_POST['acao']==='carregar_aulas'){
        $idTurma = intval($_POST['idTurma'] ?? 0);
        $st = $pdo->prepare("
            SELECT a.id, a.data, a.tipo, a.observacao,
                   u.id AS idUC, u.nome AS nomeUC, u.cargaHoraria, u.horasPraticas, u.horasEad,
                   i.id AS idInstrutor, i.nome AS nomeInstrutor
            FROM cal_aulas a
            JOIN curso_ucs u ON u.id=a.idUC
            LEFT JOIN usuarios i ON i.id=a.idInstrutor
            WHERE a.idTurma=?
            ORDER BY a.data, u.nome
        ");
        $st->execute([$idTurma]);
        echo json_encode(['ok'=>true,'aulas'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ══════════════════════════════
   Dados
══════════════════════════════ */
$turmas = $pdo->query("
    SELECT t.*, c.nome AS nomeCurso
    FROM cal_turmas t
    JOIN cursos_tecnicos c ON c.id=t.idCurso
    WHERE t.ativo=1
    ORDER BY t.dataInicio DESC, t.nome
")->fetchAll(PDO::FETCH_ASSOC);

$cursos = $pdo->query("
    SELECT c.id, c.nome,
           COUNT(u.id) AS totalUCs,
           SUM(u.cargaHoraria) AS totalHoras
    FROM cursos_tecnicos c
    LEFT JOIN curso_ucs u ON u.idCurso=c.id AND u.ativo=1
    WHERE c.ativo=1
    GROUP BY c.id ORDER BY c.nome
")->fetchAll(PDO::FETCH_ASSOC);

$instrutores = $pdo->query(
    "SELECT id, nome FROM usuarios WHERE perfil='Instrutor' AND status=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

// Turma selecionada — sem default (tela inicial mostra cards)
$idTurmaSel = intval($_GET['turma'] ?? 0);
$turmaSel   = null;
$ucsTurma   = [];
foreach($turmas as $t) if($t['id']==$idTurmaSel){ $turmaSel=$t; break; }

if($turmaSel){
    $st = $pdo->prepare("
        SELECT id, nome, codigo, cargaHoraria, horasPraticas, horasEad, ordem
        FROM curso_ucs
        WHERE idCurso=? AND ativo=1
        ORDER BY ordem, nome
    ");
    $st->execute([$turmaSel['idCurso']]);
    $ucsTurma = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Constante de horas por dia de aula
define('HORAS_POR_DIA', 3.75);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Calendário Acadêmico</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
/* ── Layout geral ── */
.cal-layout{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.cal-sidebar{width:260px;flex-shrink:0}
.cal-main{flex:1;min-width:300px}
.cal-painel{width:280px;flex-shrink:0}

/* ── Lista de turmas ── */
.turma-btn{display:block;padding:10px 14px;border-radius:8px;border:2px solid #dee2e6;
    background:#fff;font-size:.82rem;font-weight:600;cursor:pointer;color:#495057;
    text-decoration:none;transition:all .15s;margin-bottom:6px;}
.turma-btn:hover{border-color:#0d6efd;color:#0d6efd;text-decoration:none;}
.turma-btn.ativo{border-color:#0d6efd;background:#0d6efd;color:#fff;}
.turma-btn.ativo .turma-meta{color:#cfe2ff;}
.turma-meta{font-size:.7rem;font-weight:400;opacity:.8;margin-top:2px;}

/* ── Calendário ── */
.mes-bloco{margin-bottom:32px;}
.mes-titulo{font-size:1rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;
    margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid #e3f0ff;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);border-radius:8px;
    overflow:hidden;border:1px solid #dee2e6;}
.cal-header{background:#343a40;color:#fff;text-align:center;font-size:.72rem;
    font-weight:700;padding:6px 2px;}
.cal-cell{border:1px solid #e5e7eb;min-height:90px;padding:4px;background:#fff;
    cursor:pointer;transition:background .1s;font-size:.72rem;}
.cal-cell:hover{background:#f0f7ff;}
.cal-cell.sabado{background:#e0f2fe;}
.cal-cell.sabado:hover{background:#bae6fd;}
.cal-cell.domingo{background:#fee2e2;cursor:default;}
.cal-cell.fora-periodo{background:#f8f9fa;cursor:default;opacity:.5;}
.cal-cell.tem-inconsistencia{background:#fff3cd!important;}
.cal-num{font-weight:700;color:#495057;font-size:.75rem;}
.cal-evento{margin-top:3px;padding:2px 5px;border-radius:3px;font-size:.67rem;
    cursor:pointer;line-height:1.3;position:relative;}
.cal-evento:hover{opacity:.85;}
.cal-evento.presencial{background:#c7d7f0;color:#1e3a8a;}
.cal-evento.ead{background:#f0b6de;color:#7e1d5f;}
.cal-evento.sem-instrutor{background:#fff3cd;color:#856404;border:1px solid #ffc107;}
.cal-evento .del-btn{position:absolute;right:2px;top:1px;font-size:.6rem;
    cursor:pointer;color:#666;display:none;}
.cal-evento:hover .del-btn{display:inline;}
.cal-vazio{color:#adb5bd;font-style:italic;font-size:.65rem;margin-top:4px;}

/* ── Painel progresso ── */
.prog-uc{margin-bottom:12px;}
.prog-nome{font-size:.78rem;font-weight:700;color:#212529;}
.prog-info{font-size:.7rem;color:#6c757d;display:flex;justify-content:space-between;}
.prog-bar-wrap{height:6px;background:#e9ecef;border-radius:4px;overflow:hidden;margin-top:3px;}
.prog-bar{height:100%;border-radius:4px;transition:width .3s;}
.prog-ok{background:#198754;}
.prog-alerta{background:#ffc107;}
.prog-completo{background:#0d6efd;}
.inc-badge{background:#dc3545;color:#fff;border-radius:12px;padding:2px 8px;
    font-size:.68rem;font-weight:700;margin-left:4px;}

/* ── Modal ── */
.modal-aula .modal-header{background:#343a40;color:#fff;}
.modal-aula .modal-title{font-size:.95rem;}

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
<div class="main-container" style="min-height:100vh">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Calendário Acadêmico</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirNovaTurma()">
        <i class="fas fa-plus mr-1"></i>Nova Turma
    </button>
</div>

<?php if(empty($cursos)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    Nenhum curso cadastrado. <a href="cadastroCursos.php">Cadastre um curso</a> primeiro.
</div>

<?php elseif(!$turmaSel): ?>
<!-- ── TELA INICIAL: grid de cards de turmas ── -->
<?php if(empty($turmas)): ?>
<div class="text-center py-5" style="color:#adb5bd">
    <i class="fas fa-calendar-alt" style="font-size:3rem;display:block;margin-bottom:14px"></i>
    <p style="font-size:1rem;font-weight:600">Nenhuma turma criada ainda.</p>
    <button class="btn btn-primary" onclick="abrirNovaTurma()">
        <i class="fas fa-plus mr-1"></i>Criar primeira turma
    </button>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
<?php
// Conta aulas e inconsistências por turma
$statsPorTurma = [];
$stStats = $pdo->query("
    SELECT a.idTurma,
           COUNT(a.id) AS totalAulas,
           SUM(CASE WHEN a.idInstrutor IS NULL THEN 1 ELSE 0 END) AS semInstrutor
    FROM cal_aulas a
    GROUP BY a.idTurma
");
foreach($stStats->fetchAll(PDO::FETCH_ASSOC) as $s)
    $statsPorTurma[$s['idTurma']] = $s;
foreach($turmas as $t):
    $stats = $statsPorTurma[$t['id']] ?? ['totalAulas'=>0,'semInstrutor'=>0];
    $inc   = (int)$stats['semInstrutor'];
    $aulas = (int)$stats['totalAulas'];
    // Percentual de progresso (aulas cadastradas vs dias úteis estimados)
    $diasTotais = (strtotime($t['dataFim'])-strtotime($t['dataInicio']))/86400;
    $diasUteis  = max(1, round($diasTotais * 5/7));
?>
<div class="card" style="border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);
     border:1px solid <?php echo $inc>0?'#ffc107':'#dee2e6'; ?>;overflow:hidden">
    <div style="background:<?php echo $inc>0?'#fff3cd':'#343a40'; ?>;padding:12px 16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <div style="font-weight:700;font-size:.95rem;color:<?php echo $inc>0?'#856404':'#fff'; ?>">
                    <?php echo htmlspecialchars($t['nome']); ?>
                </div>
                <?php if($t['codigo']): ?>
                <div style="font-size:.72rem;color:<?php echo $inc>0?'#a0732a':'#cfe2ff'; ?>;margin-top:2px">
                    <?php echo htmlspecialchars($t['codigo']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if($inc > 0): ?>
            <span style="background:#dc3545;color:#fff;border-radius:12px;padding:2px 10px;
                  font-size:.7rem;font-weight:700;flex-shrink:0">
                ⚠️ <?php echo $inc; ?> s/ inst.
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding:12px 16px">
        <div style="font-size:.78rem;color:#495057;margin-bottom:6px">
            <i class="fas fa-book mr-1 text-primary"></i><?php echo htmlspecialchars($t['nomeCurso']); ?>
        </div>
        <div style="font-size:.75rem;color:#6c757d;margin-bottom:6px">
            <i class="fas fa-clock mr-1"></i><?php echo ucfirst($t['turno']); ?> •
            <?php echo date('d/m/Y',strtotime($t['dataInicio'])); ?> →
            <?php echo date('d/m/Y',strtotime($t['dataFim'])); ?>
        </div>
        <div style="font-size:.72rem;color:#6c757d;margin-bottom:10px">
            <i class="fas fa-chalkboard-teacher mr-1"></i><?php echo $aulas; ?> aula(s) cadastrada(s)
        </div>
        <div style="display:flex;gap:6px">
            <a href="?turma=<?php echo $t['id']; ?>"
               class="btn btn-primary btn-sm flex-fill" style="font-size:.78rem">
                <i class="fas fa-calendar-alt mr-1"></i>Abrir Calendário
            </a>
            <button class="btn btn-outline-secondary btn-sm"
                    onclick="editarTurmaCard(<?php echo $t['id']; ?>,
                        '<?php echo addslashes($t['nome']); ?>',
                        '<?php echo addslashes($t['codigo']??''); ?>',
                        <?php echo $t['idCurso']; ?>,
                        '<?php echo $t['turno']; ?>',
                        '<?php echo $t['dataInicio']; ?>',
                        '<?php echo $t['dataFim']; ?>')"
                    title="Editar turma">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm"
                    onclick="excluirTurma(<?php echo $t['id']; ?>)"
                    title="Excluir turma">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── TELA DO CALENDÁRIO ── -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="calendarioAcademico.php" class="btn btn-outline-secondary btn-sm mr-2">
            <i class="fas fa-arrow-left mr-1"></i>Turmas
        </a>
        <strong><?php echo htmlspecialchars($turmaSel['nome']); ?></strong>
        <small class="text-muted ml-2">
            <?php echo htmlspecialchars($turmaSel['nomeCurso']); ?> •
            <?php echo ucfirst($turmaSel['turno']); ?> •
            <?php echo date('d/m/Y',strtotime($turmaSel['dataInicio'])); ?> a
            <?php echo date('d/m/Y',strtotime($turmaSel['dataFim'])); ?>
        </small>
    </div>
    <div style="display:flex;gap:6px">
        <button class="btn btn-outline-secondary btn-sm"
                onclick="editarTurma(<?php echo $turmaSel['id']; ?>)">
            <i class="fas fa-edit mr-1"></i>Editar
        </button>
        <button class="btn btn-outline-danger btn-sm"
                onclick="excluirTurma(<?php echo $turmaSel['id']; ?>)">
            <i class="fas fa-trash"></i>
        </button>
    </div>
</div>

<div class="cal-layout">
    <div class="cal-main">
        <div id="calendarioWrap">
            <div class="text-center text-muted py-5">
                <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                <small>Carregando calendário...</small>
            </div>
        </div>
    </div>
    <div class="cal-painel">
        <div class="card">
            <div class="card-header" style="background:#343a40;color:#fff;font-size:.85rem;font-weight:700;padding:10px 14px">
                <i class="fas fa-tasks mr-1"></i>Progresso das UCs
                <span id="badgeInc" class="inc-badge" style="display:none"></span>
            </div>
            <div class="card-body p-2" id="painelUCs">
                <div class="text-center text-muted py-3" style="font-size:.8rem">Carregando...</div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

</div></div>

<!-- Modal Nova/Editar Turma -->
<div class="modal fade" id="modalTurma" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header" style="background:#343a40;color:#fff">
        <h5 class="modal-title" id="modalTurmaTitle">
            <i class="fas fa-graduation-cap mr-2"></i>Nova Turma
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="tId" value="0">
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Nome da Turma <span class="text-danger">*</span>
                </label>
                <input type="text" id="tNome" class="form-control form-control-sm"
                       placeholder="Ex: TAI 2026.1">
            </div>
            <div class="col-4">
                <label class="font-weight-bold" style="font-size:.82rem">Código</label>
                <input type="text" id="tCodigo" class="form-control form-control-sm"
                       placeholder="Ex: HT-AUT-01-N-26">
            </div>
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Curso <span class="text-danger">*</span>
                </label>
                <select id="tCurso" class="form-control form-control-sm">
                    <option value="">— Selecione —</option>
                    <?php foreach($cursos as $c): ?>
                    <option value="<?php echo $c['id']; ?>">
                        <?php echo htmlspecialchars($c['nome']); ?>
                        (<?php echo $c['totalUCs']; ?> UCs / <?php echo number_format($c['totalHoras'],0,',','.'); ?>h)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3">
                <label class="font-weight-bold" style="font-size:.82rem">Turno</label>
                <select id="tTurno" class="form-control form-control-sm">
                    <option value="manha">Manhã</option>
                    <option value="tarde">Tarde</option>
                    <option value="noite" selected>Noite</option>
                </select>
            </div>
        </div>
        <div class="form-row mb-0">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Data Início <span class="text-danger">*</span>
                </label>
                <input type="date" id="tInicio" class="form-control form-control-sm">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Data Fim <span class="text-danger">*</span>
                </label>
                <input type="date" id="tFim" class="form-control form-control-sm">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarTurma()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<!-- Modal Cadastrar Aula -->
<div class="modal fade modal-aula" id="modalAula" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">
            <i class="fas fa-chalkboard-teacher mr-2"></i>
            Aula — <span id="modalAulaData"></span>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="aData" value="">
        <div class="form-group mb-3">
            <label class="font-weight-bold" style="font-size:.82rem">
                Unidade Curricular <span class="text-danger">*</span>
            </label>
            <select id="aUC" class="form-control form-control-sm" onchange="atualizarTipoUC()">
                <option value="">— Selecione —</option>
            </select>
            <small id="aUCInfo" class="text-muted"></small>
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Tipo</label>
                <select id="aTipo" class="form-control form-control-sm">
                    <option value="presencial">Presencial</option>
                    <option value="ead">EAD</option>
                </select>
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Instrutor
                    <i class="fas fa-info-circle text-muted ml-1"
                       title="Sem instrutor = inconsistência"></i>
                </label>
                <select id="aInstrutor" class="form-control form-control-sm">
                    <option value="">— Sem instrutor (inconsistência) —</option>
                    <?php foreach($instrutores as $i): ?>
                    <option value="<?php echo $i['id']; ?>">
                        <?php echo htmlspecialchars(mb_strtoupper($i['nome'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold" style="font-size:.82rem">Observação</label>
            <input type="text" id="aObs" class="form-control form-control-sm"
                   placeholder="Opcional">
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarAula()">
            <i class="fas fa-save mr-1"></i>Salvar Aula
        </button>
    </div>
</div></div>
</div>

<div id="toast"></div>
<script src="../js/menu.js"></script>
<script>
/* ── Dados do PHP ── */
var _idTurmaSel = <?php echo $idTurmaSel ?: 0; ?>;
var _turma      = <?php echo $turmaSel ? json_encode($turmaSel, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
var _ucs        = <?php echo json_encode($ucsTurma, JSON_UNESCAPED_UNICODE); ?>;
var _instrutores= <?php echo json_encode($instrutores, JSON_UNESCAPED_UNICODE); ?>;
var HORAS_POR_DIA = <?php echo HORAS_POR_DIA; ?>;

var _aulas = []; // carregadas via AJAX

/* ══════════════════════════════
   TURMA
══════════════════════════════ */
function abrirNovaTurma(){
    document.getElementById('tId').value='0';
    document.getElementById('tNome').value='';
    document.getElementById('tCodigo').value='';
    document.getElementById('tCurso').value='';
    document.getElementById('tTurno').value='noite';
    document.getElementById('tInicio').value='';
    document.getElementById('tFim').value='';
    document.getElementById('modalTurmaTitle').innerHTML='<i class="fas fa-plus mr-2"></i>Nova Turma';
    $('#modalTurma').modal('show');
}

function editarTurma(id){
    if(!_turma) return;
    preencherModalTurma(id,_turma.nome,_turma.codigo||'',_turma.idCurso,_turma.turno,_turma.dataInicio,_turma.dataFim);
}

/* Chamado a partir dos cards na tela inicial */
function editarTurmaCard(id,nome,codigo,idCurso,turno,inicio,fim){
    preencherModalTurma(id,nome,codigo,idCurso,turno,inicio,fim);
}

function preencherModalTurma(id,nome,codigo,idCurso,turno,inicio,fim){
    document.getElementById('tId').value=id;
    document.getElementById('tNome').value=nome;
    document.getElementById('tCodigo').value=codigo||'';
    document.getElementById('tCurso').value=idCurso;
    document.getElementById('tTurno').value=turno;
    document.getElementById('tInicio').value=inicio;
    document.getElementById('tFim').value=fim;
    document.getElementById('modalTurmaTitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar Turma';
    $('#modalTurma').modal('show');
}

function salvarTurma(){
    var id     = document.getElementById('tId').value;
    var nome   = document.getElementById('tNome').value.trim();
    var codigo = document.getElementById('tCodigo').value.trim();
    var curso  = document.getElementById('tCurso').value;
    var turno  = document.getElementById('tTurno').value;
    var inicio = document.getElementById('tInicio').value;
    var fim    = document.getElementById('tFim').value;
    if(!nome||!curso||!inicio||!fim){ alert('Preencha todos os campos obrigatórios.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_turma'); fd.append('id',id);
    fd.append('nome',nome); fd.append('codigo',codigo);
    fd.append('idCurso',curso); fd.append('turno',turno);
    fd.append('dataInicio',inicio); fd.append('dataFim',fim);
    fetch('calendarioAcademico.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Salvo!','ok');
            $('#modalTurma').modal('hide');
            setTimeout(()=>location.href='?turma='+res.id, 600);
        }).catch(()=>toast('Erro.','err'));
}

function excluirTurma(id){
    if(!confirm('Excluir esta turma e todas as aulas cadastradas?')) return;
    var fd=new FormData(); fd.append('acao','excluir_turma'); fd.append('id',id);
    fetch('calendarioAcademico.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Excluído.','ok');
            setTimeout(()=>location.href='calendarioAcademico.php', 600);
        }).catch(()=>toast('Erro.','err'));
}

/* ══════════════════════════════
   CALENDÁRIO
══════════════════════════════ */
function carregarAulas(){
    if(!_idTurmaSel) return;
    var fd=new FormData(); fd.append('acao','carregar_aulas'); fd.append('idTurma',_idTurmaSel);
    fetch('calendarioAcademico.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok) return;
            _aulas = res.aulas;
            gerarCalendario();
            atualizarPainel();
        });
}

function gerarCalendario(){
    if(!_turma) return;
    var wrap = document.getElementById('calendarioWrap');
    if(!wrap) return;
    wrap.innerHTML='';

    var ini  = new Date(_turma.dataInicio+'T00:00');
    var fim  = new Date(_turma.dataFim+'T00:00');
    var dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

    // Percorre mês a mês
    var atual = new Date(ini.getFullYear(), ini.getMonth(), 1);
    while(atual <= fim){
        var mesDiv = document.createElement('div');
        mesDiv.className = 'mes-bloco';

        var tit = document.createElement('div');
        tit.className = 'mes-titulo';
        tit.textContent = atual.toLocaleDateString('pt-BR',{month:'long',year:'numeric'});
        mesDiv.appendChild(tit);

        var grid = document.createElement('div');
        grid.className = 'cal-grid';

        // Cabeçalho dias
        dias.forEach(d=>{
            var h=document.createElement('div'); h.className='cal-header'; h.textContent=d;
            grid.appendChild(h);
        });

        // Células vazias antes do dia 1
        var primeiroDia = new Date(atual.getFullYear(), atual.getMonth(), 1).getDay();
        for(var i=0;i<primeiroDia;i++){
            var v=document.createElement('div'); v.className='cal-cell fora-periodo';
            grid.appendChild(v);
        }

        var ultimoDia = new Date(atual.getFullYear(), atual.getMonth()+1, 0);
        for(var d=new Date(atual); d<=ultimoDia; d.setDate(d.getDate()+1)){
            var iso = d.toISOString().slice(0,10);
            var cell = document.createElement('div');
            cell.className = 'cal-cell';

            var fora = d < ini || d > fim;
            var domingo = d.getDay()===0;
            var sabado  = d.getDay()===6;

            if(fora || domingo){ cell.classList.add('fora-periodo'); }
            else if(sabado){ cell.classList.add('sabado'); }

            var num = document.createElement('div');
            num.className = 'cal-num';
            num.textContent = d.getDate();
            cell.appendChild(num);

            if(!fora && !domingo){
                // Aulas deste dia
                var aulasHoje = _aulas.filter(a=>a.data===iso);
                if(aulasHoje.length > 0){
                    aulasHoje.forEach(function(a){
                        var ev = document.createElement('div');
                        ev.className = 'cal-evento '+(a.tipo==='ead'?'ead':'presencial');
                        if(!a.idInstrutor) ev.classList.add('sem-instrutor');
                        var nomeUCCurto = a.nomeUC.length>20 ? a.nomeUC.substring(0,18)+'…' : a.nomeUC;
                        ev.innerHTML = nomeUCCurto
                            +(a.nomeInstrutor?' — '+a.nomeInstrutor.split(' ')[0]:'<span style="color:#856404"> ⚠️ s/ instrutor</span>')
                            +'<span class="del-btn" onclick="excluirAula('+a.id+',event)">✕</span>';
                        cell.appendChild(ev);
                    });
                } else {
                    // Dia útil sem aula = inconsistência potencial
                    var vz = document.createElement('div');
                    vz.className='cal-vazio'; vz.textContent='clique p/ agendar';
                    cell.appendChild(vz);
                }
                // Click abre modal
                (function(dataISO){
                    cell.addEventListener('click', function(){ abrirModalAula(dataISO); });
                })(iso);
            }
            grid.appendChild(cell);
        }
        mesDiv.appendChild(grid);
        wrap.appendChild(mesDiv);
        atual.setMonth(atual.getMonth()+1);
    }
}

/* ══════════════════════════════
   MODAL AULA
══════════════════════════════ */
function abrirModalAula(data){
    document.getElementById('aData').value = data;
    // Formata data para exibição
    var partes = data.split('-');
    document.getElementById('modalAulaData').textContent = partes[2]+'/'+partes[1]+'/'+partes[0];
    document.getElementById('aObs').value = '';
    document.getElementById('aInstrutor').value = '';

    // Popula UCs com progresso
    var sel = document.getElementById('aUC');
    sel.innerHTML = '<option value="">— Selecione a UC —</option>';
    _ucs.forEach(function(u){
        var horasFeitas = _aulas.filter(a=>a.idUC==u.id).length * HORAS_POR_DIA;
        var restante = u.cargaHoraria - horasFeitas;
        var opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.nome + ' (' + horasFeitas.toFixed(1) + '/' + u.cargaHoraria + 'h)';
        if(restante <= 0) opt.textContent += ' ✓';
        opt.dataset.ead = u.horasEad > 0 ? '1' : '0';
        sel.appendChild(opt);
    });
    document.getElementById('aUCInfo').textContent = '';

    // Pré-seleciona se já há aula neste dia
    var aulasHoje = _aulas.filter(a=>a.data===data);
    if(aulasHoje.length > 0){
        var a = aulasHoje[0];
        sel.value = a.idUC;
        document.getElementById('aTipo').value = a.tipo;
        document.getElementById('aInstrutor').value = a.idInstrutor||'';
        document.getElementById('aObs').value = a.observacao||'';
        atualizarTipoUC();
    }
    $('#modalAula').modal('show');
}

function atualizarTipoUC(){
    var sel = document.getElementById('aUC');
    var opt = sel.options[sel.selectedIndex];
    if(!opt || !opt.value) return;
    var horasFeitas = _aulas.filter(a=>a.idUC==opt.value).length * HORAS_POR_DIA;
    var uc = _ucs.find(u=>u.id==opt.value);
    if(uc){
        var restante = uc.cargaHoraria - horasFeitas;
        document.getElementById('aUCInfo').textContent =
            restante > 0 ? restante.toFixed(1)+'h restantes' : '✓ Carga horária completa';
    }
}

function salvarAula(){
    var data   = document.getElementById('aData').value;
    var idUC   = document.getElementById('aUC').value;
    var tipo   = document.getElementById('aTipo').value;
    var idInst = document.getElementById('aInstrutor').value;
    var obs    = document.getElementById('aObs').value;
    if(!idUC){ alert('Selecione a Unidade Curricular.'); return; }
    var fd=new FormData();
    fd.append('acao','salvar_aula');
    fd.append('idTurma',_idTurmaSel);
    fd.append('idUC',idUC);
    fd.append('idInstrutor',idInst);
    fd.append('data',data);
    fd.append('tipo',tipo);
    fd.append('observacao',obs);
    fetch('calendarioAcademico.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Aula salva!','ok');
            $('#modalAula').modal('hide');
            carregarAulas(); // recarrega sem reload
        }).catch(()=>toast('Erro.','err'));
}

function excluirAula(id, evt){
    evt.stopPropagation();
    if(!confirm('Remover esta aula?')) return;
    var fd=new FormData(); fd.append('acao','excluir_aula'); fd.append('id',id);
    fetch('calendarioAcademico.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            carregarAulas();
        }).catch(()=>toast('Erro.','err'));
}

/* ══════════════════════════════
   PAINEL PROGRESSO
══════════════════════════════ */
function atualizarPainel(){
    var painel = document.getElementById('painelUCs');
    if(!painel) return;
    if(_ucs.length===0){ painel.innerHTML='<div class="text-muted text-center py-3" style="font-size:.8rem">Sem UCs cadastradas.</div>'; return; }

    var totalInc = 0;
    var html = '';
    _ucs.forEach(function(u){
        var horasFeitas = _aulas.filter(a=>a.idUC==u.id).length * HORAS_POR_DIA;
        var pct = Math.min(100, (horasFeitas/u.cargaHoraria)*100);
        var corClass = pct>=100?'prog-completo':(pct>=50?'prog-ok':'prog-alerta');
        var semInst  = _aulas.filter(a=>a.idUC==u.id && !a.idInstrutor).length;
        if(semInst>0) totalInc+=semInst;
        html += '<div class="prog-uc">'
            +'<div class="prog-nome">'+esc(u.nome)
            +(semInst>0?'<span class="inc-badge ml-1">'+semInst+' s/ inst.</span>':'')
            +'</div>'
            +'<div class="prog-info"><span>'+horasFeitas.toFixed(1)+'h / '+u.cargaHoraria+'h</span>'
            +'<span>'+Math.round(pct)+'%</span></div>'
            +'<div class="prog-bar-wrap"><div class="prog-bar '+corClass+'" style="width:'+pct+'%"></div></div>'
            +'</div>';
    });
    painel.innerHTML = html;

    // Badge de inconsistências no cabeçalho
    var badge = document.getElementById('badgeInc');
    if(badge){
        if(totalInc>0){ badge.textContent=totalInc+' inconsist.'; badge.style.display=''; }
        else badge.style.display='none';
    }
}

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toast');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(()=>el.style.display='none',3500);
}

/* Inicia ao carregar */
if(_idTurmaSel) carregarAulas();
</script>
</body>
</html>