<!DOCTYPE html>
<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$logado = $_SESSION['user'];
$nivel  = $_SESSION['group'];

include("../conexao.php");

$nivelNorm = strtolower(str_replace('.', '', $nivel));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$usuarioLogado = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $usuarioLogado['id'] ?? 0;

/* MENSAGENS */
$mensagens = [
    'aprovada'            => ['tipo'=>'success','texto'=>'Reserva aprovada com sucesso.'],
    'reprovada'           => ['tipo'=>'warning','texto'=>'Reserva reprovada. O horário foi liberado.'],
    'cancelada'           => ['tipo'=>'success','texto'=>'Reserva cancelada e removida com sucesso.'],
    'erro_permissao'      => ['tipo'=>'danger', 'texto'=>'Você não tem permissão para esta ação.'],
    'erro_nao_encontrada' => ['tipo'=>'danger', 'texto'=>'Reserva não encontrada.'],
    'erro_db'             => ['tipo'=>'danger', 'texto'=>'Erro ao processar. Tente novamente.'],
    'erro_param'          => ['tipo'=>'danger', 'texto'=>'Requisição inválida.'],
];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reserva de Laboratórios</title>

<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

<style>
.lab-container{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    margin-bottom:30px;
}

/* Botão do laboratório — nome em destaque + descrição menor abaixo */
.lab-btn{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#1e3c72,#2a5298);
    color:white;
    padding:16px 14px;
    border-radius:10px;
    font-weight:600;
    text-align:center;
    text-decoration:none;
    box-shadow:0 4px 8px rgba(0,0,0,.2);
    transition:.25s;
    min-height:80px;
}
.lab-btn:hover{
    transform:translateY(-3px);
    box-shadow:0 6px 14px rgba(0,0,0,.25);
    color:white;
    text-decoration:none;
}
.lab-btn .lab-nome{
    font-size:0.85rem;
    font-weight:600;
    opacity:.85;
    line-height:1.2;
}
.lab-btn .lab-desc{
    font-size:1rem;
    font-weight:700;
    margin-top:5px;
    line-height:1.3;
}

.reserva-table{ overflow-x:auto; }

.acao-aprovar  { color:#28a745; font-size:1.2rem; }
.acao-reprovar { color:#dc3545; font-size:1.2rem; }
.acao-cancelar { color:#6c757d; font-size:1.2rem; }
.acao-editar   { color:#007bff; font-size:1.2rem; }
.acao-restaurar{ color:#fd7e14; font-size:1.2rem; }
a.acao-aprovar:hover   { color:#1e7e34; }
a.acao-reprovar:hover  { color:#a71d2a; }
a.acao-cancelar:hover  { color:#343a40; }
a.acao-editar:hover    { color:#0056b3; }
a.acao-restaurar:hover { color:#c96a00; }

#secaoHistorico{ display:none; margin-top:30px; }
#secaoHistorico .card-header{ background:#fff3cd; font-weight:600; }
.badge-reprovado{ background:#dc3545; color:#fff; }
.badge-cancelado{ background:#6c757d; color:#fff; }
</style>
</head>

<body>
<div class="wrapper">

<div class="header">
<div class="header-menu">
<div class="title"><img src="../img/logo_white.svg"></div>
<div class="sidebar-btn"><i class="fas fa-bars"></i></div>
<ul>
<li><a href="#" class="user"><?php echo $logado; ?></a></li>
<li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
</ul>
</div>
</div>

<div class="sidebar">
<div class="sidebar-menu"><?php include_once('../menu.php'); ?></div>
</div>

<div class="main-container">

<?php
// Mensagem de retorno do statusReserva.php
if(isset($_GET['msg'])){
    $cod = $_GET['msg'];
    if(isset($mensagens[$cod])){
        $m = $mensagens[$cod];
        echo "<div class='alert alert-{$m['tipo']} text-center'>{$m['texto']}</div>";
    }
}
// Mensagem de restauração bem-sucedida
if(isset($_GET['restaurada'])){
    echo "<div class='alert alert-success text-center'>Reserva restaurada com sucesso. Status redefinido para <strong>Aguardando aprovação</strong>.</div>";
}
?>

<h3 style="text-align:center;margin-bottom:20px;">Ambientes</h3>

<!-- BOTÕES DOS LABORATÓRIOS — nome + descrição -->
<div class="lab-container">
<?php
// Busca nome E descrição
$stmt = $pdo->prepare("SELECT idLaboratorio, nome, descricao FROM laboratorios ORDER BY nome");
$stmt->execute();
$labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($labs as $lab){
    $desc = htmlspecialchars(trim($lab['descricao'] ?? ''));
    $nome = htmlspecialchars($lab['nome']);
    echo "<a class='lab-btn' href='laboratorios.php?lab={$lab['idLaboratorio']}'>";
    echo   "<span class='lab-nome'>{$nome}</span>";
    if($desc !== ''){
        echo "<span class='lab-desc'>{$desc}</span>";
    }
    echo "</a>";
}
?>
</div>

<!-- CARD RESERVAS ATIVAS -->
<div class="card mb-3">
<div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
        <?php echo $supervisao ? "Todas as Reservas" : "Minhas Reservas"; ?>
    </h5>
    <?php if($supervisao){ ?>
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="toggleHistorico">
        <label class="custom-control-label" for="toggleHistorico">
            Exibir reprovadas / canceladas
        </label>
    </div>
    <?php } ?>
</div>

<div class="card-body p-2">
<div class="reserva-table">
<table class="table table-striped mb-0">
<thead>
<tr>
<th>#</th><th>Data</th><th>Ambiente</th>
<th>Início</th><th>Fim</th><th>Solicitante</th>
<th>Status</th><th>Ações</th>
</tr>
</thead>
<tbody>
<?php
try{
    if($supervisao){
        $sql = "
            SELECT r.idReserva, r.data, r.horarioInicio, r.horarioFim,
                   r.aprovado, r.solicitante AS idSolicitante,
                   u.nome AS solicitante, l.nome AS laboratorio
            FROM reservas r
            JOIN usuarios u     ON u.id = r.solicitante
            JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
            WHERE r.data >= CURDATE()
            ORDER BY r.data, r.horarioInicio
        ";
        $stmt = $pdo->prepare($sql);
    } else {
        $sql = "
            SELECT r.idReserva, r.data, r.horarioInicio, r.horarioFim,
                   r.aprovado, r.solicitante AS idSolicitante,
                   u.nome AS solicitante, l.nome AS laboratorio
            FROM reservas r
            JOIN usuarios u     ON u.id = r.solicitante
            JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
            WHERE u.nome = :user AND r.data >= CURDATE()
            ORDER BY r.data, r.horarioInicio
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user', $logado);
    }
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($dados as $r){
        $ehPropria = ($idLogado == $r['idSolicitante']);
        echo "<tr>";
        echo "<td>".$r['idReserva']."</td>";
        echo "<td>".date('d/m/Y',strtotime($r['data']))."</td>";
        echo "<td>".$r['laboratorio']."</td>";
        echo "<td>".$r['horarioInicio']."</td>";
        echo "<td>".$r['horarioFim']."</td>";
        echo "<td>".$r['solicitante']."</td>";

        if($r['aprovado']==0)     echo "<td><span style='color:orange'>Aguardando</span></td>";
        elseif($r['aprovado']==1) echo "<td><span style='color:green'>Aprovado</span></td>";
        else                      echo "<td><span style='color:red'>Reprovado</span></td>";

        echo "<td class='text-nowrap'>";

        if($supervisao){
            if($r['aprovado'] != 1){
                echo "<a href='statusReserva.php?id={$r['idReserva']}&status=1'
                         class='acao-aprovar mr-2' title='Aprovar'
                         onclick=\"return confirm('Aprovar esta reserva?')\">
                         <i class='fas fa-check-circle'></i></a>";
            }
            if($ehPropria){
                // Supervisão cancelando a própria → status=3
                echo "<a href='statusReserva.php?id={$r['idReserva']}&status=3'
                         class='acao-cancelar mr-2' title='Cancelar minha reserva'
                         onclick=\"return confirm('Cancelar esta reserva? Ela será removida.')\">
                         <i class='fas fa-trash-alt'></i></a>";
            } else {
                // Supervisão reprovando reserva de outro → status=2
                echo "<a href='statusReserva.php?id={$r['idReserva']}&status=2'
                         class='acao-reprovar mr-2' title='Reprovar'
                         onclick=\"return confirm('Reprovar esta reserva? O horário será liberado e o solicitante será notificado.')\">
                         <i class='fas fa-times-circle'></i></a>";
            }
        } else {
            if($ehPropria){
                echo "<a href='statusReserva.php?id={$r['idReserva']}&status=3'
                         class='acao-cancelar mr-2' title='Cancelar reserva'
                         onclick=\"return confirm('Cancelar esta reserva?')\">
                         <i class='fas fa-trash-alt'></i></a>";
            }
        }

        if($supervisao || $ehPropria){
            echo "<a href='editaReserva.php?id={$r['idReserva']}'
                     class='acao-editar' title='Editar'>
                     <i class='fas fa-pen-square'></i></a>";
        }

        echo "</td></tr>";
    }
}catch(PDOException $e){
    echo "<tr><td colspan='8' class='text-danger text-center'>Erro: ".$e->getMessage()."</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>
</div><!-- /card reservas ativas -->

<?php if($supervisao){ ?>

<!-- SEÇÃO HISTÓRICO -->
<div id="secaoHistorico">
<div class="card">
<div class="card-header">
    <i class="fas fa-history mr-2"></i>Reservas Reprovadas / Canceladas
    <small class="text-muted ml-2">(clique em <i class="fas fa-undo"></i> para restaurar)</small>
</div>
<div class="card-body p-2">

<div id="historicoLoading" class="text-center py-3" style="display:none">
    <span class="spinner-border spinner-border-sm mr-2"></span>Carregando...
</div>

<div class="reserva-table">
<table class="table table-striped mb-0">
<thead>
<tr>
<th>#</th><th>Data</th><th>Ambiente</th>
<th>Início</th><th>Fim</th><th>Solicitante</th>
<th>Ação</th><th>Por</th><th>Em</th><th>Restaurar</th>
</tr>
</thead>
<tbody id="corpoHistorico">
<tr><td colspan="10" class="text-center text-muted">
    Ative o toggle acima para carregar o histórico.
</td></tr>
</tbody>
</table>
</div>

</div>
</div>
</div><!-- /secaoHistorico -->

<!-- MODAL CONFLITO -->
<div class="modal fade" id="modalConflito" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-warning">
    <h5 class="modal-title">
        <i class="fas fa-exclamation-triangle mr-2"></i>Conflito de Horário
    </h5>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body" id="modalConflitoTexto"></div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">
        Desistir da mudança
    </button>
    <button type="button" class="btn btn-danger" id="btnForcarRestaurar">
        <i class="fas fa-undo mr-1"></i>Cancelar reserva atual e restaurar
    </button>
</div>
</div>
</div>
</div>

<?php } ?>

</div><!-- /main-container -->
</div><!-- /wrapper -->

<script src="../js/menu.js"></script>

<?php if($supervisao){ ?>
<script>
var historicoCarregado  = false;
var idHistoricoConflito = null;

document.getElementById('toggleHistorico').addEventListener('change', function(){
    var secao = document.getElementById('secaoHistorico');
    if(this.checked){
        secao.style.display = 'block';
        if(!historicoCarregado) carregarHistorico();
    } else {
        secao.style.display = 'none';
    }
});

function carregarHistorico(){
    var loading = document.getElementById('historicoLoading');
    var corpo   = document.getElementById('corpoHistorico');
    loading.style.display = 'block';
    corpo.innerHTML = '';

    fetch('dadosHistorico.php')
        .then(function(r){ return r.json(); })
        .then(function(data){
            loading.style.display = 'none';
            historicoCarregado = true;

            if(!data || data.length === 0){
                corpo.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Nenhum registro no histórico.</td></tr>';
                return;
            }

            corpo.innerHTML = data.map(function(h){
                var badgeClass = h.acao === 'reprovado' ? 'badge-reprovado' : 'badge-cancelado';
                return '<tr>'
                    + '<td>' + h.idHistorico + '</td>'
                    + '<td>' + h.dataFmt + '</td>'
                    + '<td>' + escHtml(h.laboratorio) + '</td>'
                    + '<td>' + h.horarioInicio + '</td>'
                    + '<td>' + h.horarioFim + '</td>'
                    + '<td>' + escHtml(h.solicitante) + '</td>'
                    + '<td><span class="badge ' + badgeClass + '">' + h.acao + '</span></td>'
                    + '<td>' + escHtml(h.executadoPor) + '</td>'
                    + '<td>' + h.executadoEm + '</td>'
                    + '<td>'
                    +   '<a href="#" class="acao-restaurar" title="Restaurar esta reserva"'
                    +      ' onclick="restaurar(' + h.idHistorico + '); return false;">'
                    +      '<i class="fas fa-undo"></i>'
                    +   '</a>'
                    + '</td>'
                    + '</tr>';
            }).join('');
        })
        .catch(function(){
            loading.style.display = 'none';
            corpo.innerHTML = '<tr><td colspan="10" class="text-danger text-center">Erro ao carregar histórico.</td></tr>';
        });
}

function restaurar(idHistorico, forcar){
    forcar = forcar || 0;
    var fd = new FormData();
    fd.append('idHistorico', idHistorico);
    fd.append('forcar', forcar);

    fetch('restaurarReserva.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.ok){
                window.location.href = 'index.php?restaurada=1';
                return;
            }
            if(res.conflito){
                idHistoricoConflito = res.idHistorico;
                document.getElementById('modalConflitoTexto').innerHTML = res.info;
                $('#modalConflito').modal('show');
                return;
            }
            alert('Erro: ' + res.msg);
        })
        .catch(function(){
            alert('Erro de comunicação. Tente novamente.');
        });
}

document.getElementById('btnForcarRestaurar').addEventListener('click', function(){
    $('#modalConflito').modal('hide');
    if(idHistoricoConflito) restaurar(idHistoricoConflito, 1);
});

function escHtml(str){
    if(!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?php } ?>

</body>
</html>