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
$nivel = $_SESSION['group'];

include("../conexao.php");

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

<style>

.lab-container{

display:flex;
flex-wrap:wrap;
gap:10px;
justify-content:center;
margin-bottom:25px;

}

.lab-btn{

background:#2c3e50;
color:white;
padding:10px 18px;
border-radius:8px;
text-decoration:none;
font-size:15px;
transition:0.2s;

}

.lab-btn:hover{

background:#34495e;
color:white;
text-decoration:none;

}

.reserva-table{

overflow-x:auto;

}

</style>

</head>

<body>

<div class="wrapper">

<div class="header">

<div class="header-menu">

<div class="title">
<img src="../img/logo_white.svg">
</div>

<div class="sidebar-btn">
<i class="fas fa-bars"></i>
</div>

<ul>

<li>
<a href="#" class="user"><?php echo $logado; ?>
</a>
</li>

<li>
<a href="../sair.php" class="logout">
<i class="fas fa-power-off"></i>
</a>
</li>

</ul>

</div>

</div>

<div class="sidebar">
<div class="sidebar-menu">
<?php include_once('../menu.php'); ?>
</div>
</div>

<div class="main-container">

<h3 style="text-align:center;margin-bottom:20px;">
Ambientes
</h3>

<div class="lab-container">

<?php

$stmt = $pdo->prepare("SELECT idLaboratorio,nome FROM laboratorios ORDER BY nome");
$stmt->execute();

$labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($labs as $lab){

echo "<a class='lab-btn' href='laboratorios.php?lab=".$lab['idLaboratorio']."'>".$lab['nome']."</a>";

}

?>

</div>

<div class="card">

<h3 style="text-align:center">
Minhas Reservas
</h3>

<br>

<div class="reserva-table">

<table class="table table-striped">

<thead>

<tr>

<th>#</th>
<th>Data</th>
<th>Ambiente</th>
<th>Início</th>
<th>Fim</th>
<th>Solicitante</th>
<th>Status</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php

try{

$sql = "SELECT 

reservas.idReserva,
reservas.data,
reservas.horarioInicio,
reservas.horarioFim,
reservas.aprovado,

usuarios.nome AS solicitante,
laboratorios.nome AS laboratorio

FROM reservas

JOIN usuarios
ON usuarios.id = reservas.solicitante

JOIN laboratorios
ON laboratorios.idLaboratorio = reservas.laboratorio

WHERE reservas.data >= CURDATE()

ORDER BY reservas.data,reservas.horarioInicio";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($dados as $r){

echo "<tr>";

echo "<td>".$r['idReserva']."</td>";

echo "<td>".date("d/m/Y",strtotime($r['data']))."</td>";

echo "<td>".$r['laboratorio']."</td>";

echo "<td>".$r['horarioInicio']."</td>";

echo "<td>".$r['horarioFim']."</td>";

echo "<td>".$r['solicitante']."</td>";

if($r['aprovado'] == 0){

echo "<td style='color:orange'>Aguardando</td>";

}else if($r['aprovado'] == 1){

echo "<td style='color:green'>Aprovado</td>";

}else{

echo "<td style='color:red'>Reprovado</td>";

}

echo "<td>

<a href='statusReserva.php?id=".$r['idReserva']."&status=1'>

<i class='fas fa-check-circle'></i>

</a>

&nbsp;

<a href='statusReserva.php?id=".$r['idReserva']."&status=2'>

<i class='fas fa-times-circle'></i>

</a>

&nbsp;

<a href='editaReserva.php?id=".$r['idReserva']."'>

<i class='fas fa-pen-square'></i>

</a>

</td>";

echo "</tr>";

}

}catch(PDOException $e){

echo "Erro: ".$e->getMessage();

}

?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script src="../js/menu.js"></script>

</body>
</html>