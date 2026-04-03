<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
include('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){
    unset($_SESSION['sLogin'], $_SESSION['user'], $_SESSION['group']);
    header('location:../index.php'); exit;
}
$logado        = $_SESSION['user'];
$nivelNorm     = strtolower(str_replace('.', '', $_SESSION['group']));
$permGerenciar = in_array($nivelNorm, ['admin','administrator','sup tecnica','gerencia']);
$msgs = [
    'criado'         => ['tipo'=>'success','texto'=>'Ambiente cadastrado com sucesso.'],
    'editado'        => ['tipo'=>'success','texto'=>'Ambiente atualizado com sucesso.'],
    'excluido'       => ['tipo'=>'success','texto'=>'Ambiente excluído com sucesso.'],
    'erro_excluir'   => ['tipo'=>'danger', 'texto'=>'Não foi possível excluir: existem reservas vinculadas a este ambiente.'],
    'erro_db'        => ['tipo'=>'danger', 'texto'=>'Erro ao processar. Tente novamente.'],
    'erro_vazio'     => ['tipo'=>'danger', 'texto'=>'Preencha todos os campos obrigatórios.'],
    'erro_permissao' => ['tipo'=>'danger', 'texto'=>'Você não tem permissão para esta ação.'],
    'nao_encontrado' => ['tipo'=>'danger', 'texto'=>'Ambiente não encontrado.'],
];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestão de Ambientes</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
/* Badges de função */
.badge-reserva { background:#d4edda; color:#155724; font-size:.75rem; padding:2px 8px; border-radius:10px; font-weight:600; white-space:nowrap; }
.badge-estoque { background:#fff3cd; color:#856404; font-size:.75rem; padding:2px 8px; border-radius:10px; font-weight:600; white-space:nowrap; }
.badge-sala    { background:#ede7f6; color:#4527a0; font-size:.75rem; padding:2px 8px; border-radius:10px; font-weight:600; white-space:nowrap; }
.funcoes-cell  { display:flex; flex-wrap:wrap; gap:4px; }
/* Botões de ação */
.btn-acao {
    display:inline-flex; align-items:center; gap:4px;
    padding:4px 10px; border:none; border-radius:4px;
    font-size:.82rem; font-weight:600; cursor:pointer;
    transition:filter .15s ease; white-space:nowrap;
}
.btn-acao:hover  { filter:brightness(.88); }
.btn-acao:active { filter:brightness(.78); }
.btn-editar  { background:#007bff; color:#fff; }
.btn-excluir { background:#dc3545; color:#fff; }
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

<div class="sidebar">
    <div class="sidebar-menu"><?php include_once('../menu.php'); ?></div>
</div>

<div class="main-container">

    <?php
    if(isset($_GET['msg'])){
        $cod = $_GET['msg'];
        if(isset($msgs[$cod])){
            $m = $msgs[$cod];
            echo "<div class='alert alert-{$m['tipo']} text-center'>{$m['texto']}</div>";
        }
    }
    ?>

    Gestão de Ambientes
    <br><br>

    <?php if($permGerenciar){ ?>
    <button class="btn btn-primary mb-3"
            onclick="location.href='cadastrarAmbiente.php'">
        <i class="fas fa-plus-circle mr-1"></i> Cadastrar Ambiente
    </button>
    <?php } ?>

    <table id="ambientes" class="display" style="width:100%">
        <thead>
            <tr>
                <th></th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Capacidade</th>
                <th>Funções</th>
                <th>Porta IoT</th>
                <th>Ar IoT</th>
                <?php if($permGerenciar){ ?><th>Ações</th><?php } ?>
            </tr>
        </thead>
    </table>

</div>
</div>

<script src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="../js/menu.js"></script>

<script>
var permGerenciar = <?php echo $permGerenciar ? 'true' : 'false'; ?>;
var icSim = '<i class="fas fa-check text-success"></i>';
var icNao = '<i class="fas fa-times text-muted"></i>';

var colunas = [
    { className:'details-control', orderable:false, data:null, defaultContent:'' },
    { data:'nome' },
    { data:'descricao' },
    {
        data:'capacidade',
        render: function(d){ return d ? d + ' pessoas' : '—'; }
    },
    {
        // Coluna de funções — mostra os badges ativos
        data: null,
        orderable: false,
        render: function(data, type, row){
            var badges = '';
            if(row.temReserva == 1) badges += '<span class="badge-reserva"><i class="fas fa-calendar-check mr-1"></i>Reserva</span> ';
            if(row.temEstoque == 1) badges += '<span class="badge-estoque"><i class="fas fa-boxes mr-1"></i>Estoque</span> ';
            if(row.temSala    == 1) badges += '<span class="badge-sala"><i class="fas fa-chalkboard mr-1"></i>Sala</span> ';
            return badges
                ? '<div class="funcoes-cell">' + badges + '</div>'
                : '<span class="text-muted">—</span>';
        }
    },
    { data:'temPorta',          render: function(d){ return d == 1 ? icSim : icNao; } },
    { data:'temArCondicionado', render: function(d){ return d == 1 ? icSim : icNao; } },
];

if(permGerenciar){
    colunas.push({
        data: null,
        orderable: false,
        render: function(data, type, row){
            return '<button class="btn-acao btn-editar mr-1" '
                 + 'onclick="location.href=\'editarAmbiente.php?id=' + row.idLaboratorio + '\'">'
                 + '<i class="fas fa-pen"></i> Editar'
                 + '</button>'
                 + '<button class="btn-acao btn-excluir" '
                 + 'onclick="confirmarExclusao(' + row.idLaboratorio + ', \'' + escHtml(row.nome) + '\')">'
                 + '<i class="fas fa-trash-alt"></i> Excluir'
                 + '</button>';
        }
    });
}

new DataTable('#ambientes', {
    responsive:  true,
    processing:  true,
    serverSide:  true,
    ajax: { url:'dadosAmbientes.php', type:'POST' },
    columns: colunas,
    order: [[1,'asc']],
    language: {
        sEmptyTable:    "Nenhum ambiente cadastrado",
        sInfo:          "Mostrando _START_ até _END_ de _TOTAL_ ambientes",
        sInfoEmpty:     "Mostrando 0 até 0 de 0 ambientes",
        sInfoFiltered:  "(filtrado de _MAX_ no total)",
        sLengthMenu:    "_MENU_ por página",
        sLoadingRecords:"Carregando...",
        sProcessing:    "Processando...",
        sZeroRecords:   "Nenhum resultado encontrado",
        sSearch:        "Pesquisar",
        oPaginate:{ sNext:"Próximo", sPrevious:"Anterior", sFirst:"Primeiro", sLast:"Último" }
    }
});

$('#ambientes tbody').on('click', 'td.details-control', function(){
    var tr  = $(this).closest('tr');
    var row = new DataTable('#ambientes').row(tr);
    if(row.child.isShown()){
        row.child.hide(); tr.removeClass('shown');
    } else {
        var d = row.data();
        row.child(
            '<div style="padding:8px 16px">'
          + '<strong>Descrição:</strong> ' + escHtml(d.descricao || '—')
          + '</div>'
        ).show();
        tr.addClass('shown');
    }
});

function confirmarExclusao(id, nome){
    if(confirm('Excluir o ambiente "' + nome + '"?\n\nAmbientes com reservas vinculadas não podem ser excluídos.')){
        location.href = 'excluirAmbiente.php?id=' + id;
    }
}

function escHtml(str){
    if(!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>