<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include("../../conexao.php");
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../../index.php');
    exit;
}

$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];
?>

<html>

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Biblioteca</title>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="../../css/telefone.css">
<link rel='stylesheet' href='../../fullcalendar/main.min.css'/>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src='../../fullcalendar/main.min.js'></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function() {

var calendarEl = document.getElementById('calendar');

var calendar = new FullCalendar.Calendar(calendarEl, {

locale: 'pt-br',
timeZone: 'local',
initialView: 'dayGridMonth',

validRange: function() {

var max = new Date();
max.setDate(max.getDate() + 30);

return { end: max }

},

dayMaxEventRows: true,
eventDisplay: 'block',

events: {
url: '../listareservas.php?lab=8',
failure: function(){
alert('Erro ao buscar eventos');
}
},

dateClick: function(info){

var today = new Date();
var date = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');

if(info.dateStr < date){

alert("Não é possível reservar datas passadas.");

}else{

let array = info.dateStr.split("-");
let dataSel = `${array[2]}-${array[1]}-${array[0]}`;

$("#reservaModal #data").text(dataSel);

document.querySelector('input[name=dataInp]').setAttribute('value',info.dateStr);

$("#reservaModal").modal();

}

},

eventClick: function(info){

$("#dadosModal #dadosModalLabel").text(info.event.title);

let starting = info.event.start.toString();
let inicio = starting.split(" ");

$("#dadosModal #inicio").text(inicio[4]);

let ending = info.event.end.toString();
let fim = ending.split(" ");

$("#dadosModal #fim").text(fim[4]);

if(info.event.extendedProps.status == 0){
$("#dadosModal #status").text("Aguardando aprovação");
}

if(info.event.extendedProps.status == 1){
$("#dadosModal #status").text("Aprovado");
}

$("#dadosModal").modal();

}

});

calendar.render();

});

</script>

</head>

<body>

<div class="wrapper">

<div class="header" style="z-index:99">

<div class="header-menu">

<div class="title">
<img src="../../img/logo_white.svg">
</div>

<div class="sidebar-btn">
<i class="fas fa-bars"></i>
</div>

<ul>

<li>
<a href="#" class="user"><?php echo $logado; ?></a>
</li>

<li>
<a href="../../sair.php" class="logout">
<i class="fas fa-power-off"></i>
</a>
</li>

</ul>

</div>
</div>

<div class="sidebar">

<div class="sidebar-menu">
<?php include_once('../../menu.php'); ?>
</div>

</div>

<div class="main-container">
<div id='calendar'></div>
</div>

<!-- MODAL RESERVA -->

<div class="modal fade" id="reservaModal">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">Solicitar Reserva</h5>

<button type="button" class="btn-close" data-dismiss="modal"></button>

</div>

<div class="modal-body">

<form method="POST" action="../solicita.php">

<b>Data:</b><br>
<span id="data"></span>
<input type="hidden" name="dataInp">

<br><br>

<b>Turno:</b>

<br>

<input type="radio" name="turno" value="manha"> Manhã
<input type="radio" name="turno" value="tarde"> Tarde
<input type="radio" name="turno" value="noite"> Noite

<br><br>

<b>Período:</b><br>

<input type="radio" name="periodo" value="parcial" onChange="horarios()"> Só um período

<input type="radio" name="periodo" value="todo" onChange="horarios()"> Todo o turno

<br><br>

<span name="hInicio" style="display:none"><b>Início</b></span>
<input id="horainicio" name="horainicio" type="time" style="display:none">

<br>

<span name="hFim" style="display:none"><b>Fim</b></span>
<input id="horafim" name="horafim" type="time" style="display:none">

<br><br>

<b>Turma:</b><br>

<select name="turma">

<?php

try{

$stmt = $pdo->prepare("SELECT nome FROM turmas WHERE ativo = 1 ORDER BY nome");

$stmt->execute();

$turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($turmas as $turma){

echo "<option value='".$turma['nome']."'>".$turma['nome']."</option>";

}

}catch(PDOException $e){

die("Erro banco ".$e->getMessage());

}

?>

</select>

<?php

if(in_array($nivel, [3,4,9]) || $logado == 'administrator' || $nivel == 'admin') {

echo "<br><br><b>Solicitante:</b><br>";

echo "<select name='solicitante'>";

$stmt = $pdo->prepare("SELECT id, nome FROM usuarios ORDER BY nome");
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($users as $u){

echo "<option value='".$u['id']."'>".$u['nome']."</option>";

}

echo "</select>";

}else{

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmt->execute([$logado]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<input type='hidden' name='solicitante' value='".$user['id']."'>";

}


?>

<input type="hidden" name="lab" value="106b">

</div>

<div class="modal-footer">

<button type="button" class="btn btn-secondary" data-dismiss="modal">
Cancelar
</button>

<button type="submit" class="btn btn-primary">
Solicitar
</button>

</div>

</form>

</div>

</div>

</div>

<!-- MODAL DADOS -->

<div class="modal fade" id="dadosModal">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 id="dadosModalLabel"></h5>

<button type="button" class="btn-close" data-dismiss="modal"></button>

</div>

<div class="modal-body">

<b>Início:</b><br>
<span id="inicio"></span>

<br><br>

<b>Fim:</b><br>
<span id="fim"></span>

<br><br>

<b>Status:</b><br>
<span id="status"></span>

</div>

<div class="modal-footer">

<button type="button" class="btn btn-secondary" data-dismiss="modal">
Fechar
</button>

</div>

</div>

</div>

</div>

<script>

function horarios(){

const box = document.querySelector('input[value=parcial]');

if(box.checked){

document.querySelector('span[name=hInicio]').style.display="block";
document.querySelector('span[name=hFim]').style.display="block";
document.querySelector('input[name=horainicio]').style.display="block";
document.querySelector('input[name=horafim]').style.display="block";

}else{

document.querySelector('span[name=hInicio]').style.display="none";
document.querySelector('span[name=hFim]').style.display="none";
document.querySelector('input[name=horainicio]').style.display="none";
document.querySelector('input[name=horafim]').style.display="none";

}

}

</script>

</body>
</html>