<!DOCTYPE html>
<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

include("../conexao.php");
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];

$lab = $_GET['lab'] ?? 1;

$stmt = $pdo->prepare("SELECT nome FROM laboratorios WHERE idLaboratorio = ?");
$stmt->execute([$lab]);
$laboratorioInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeLaboratorio = $laboratorioInfo['nome'] ?? 'Laboratório';

/* LIMITE DE DIAS */

$limite = 30;

if($nivel == "Sup Pedagogica"){
    $limite = 60;
}

if($nivel == "Sup Tecnica" || $nivel == "admin" || $nivel == "Administrator"){
    $limite = 3650;
}

/* MENSAGENS */

$erros = [
1=>"Informe horário de início e fim",
2=>"Selecione um turno",
3=>"Já existe reserva nesse horário",
4=>"Erro ao salvar reserva"
];

$oks = [
1=>"Reserva criada com sucesso"
];

/* PERMISSÕES */

$permEvento = in_array($nivel,[
"Sup Tecnica",
"Sup Pedagogica",
"Gerencia",
"Sup Adm",
"admin"
]);

$permDiaInteiro = in_array($nivel,[
"Sup Tecnica",
"admin"
]);

?>

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Laboratórios</title>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="../../css/telefone.css">
<link rel='stylesheet' href='../../fullcalendar/main.min.css'/>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src='../../fullcalendar/main.min.js'></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

<script>

var laboratorio = <?php echo $lab; ?>;

document.addEventListener('DOMContentLoaded', function(){

var calendarEl = document.getElementById('calendar');

var calendar = new FullCalendar.Calendar(calendarEl,{

locale:'pt-br',
timeZone:'local',
eventTimeFormat:{
hour:'2-digit',
minute:'2-digit',
hour12:false
},
initialView:'dayGridMonth',

validRange:function(){

var max = new Date();
max.setDate(max.getDate() + <?php echo $limite; ?>);

return { end:max }

},

events:{
url:'listareservas.php?lab='+laboratorio
},

dateClick:function(info){

var today = new Date();
var date = today.toISOString().split('T')[0];

if(info.dateStr < date){

alert("Para reservar datas passadas você precisará de um DeLorean.");

}else{

let array = info.dateStr.split("-");
let dataSel = `${array[2]}-${array[1]}-${array[0]}`;

$("#reservaModal #data").text(dataSel);

document.querySelector('input[name=dataInp]').value = info.dateStr;

$("#reservaModal").modal();

fetch('buscarTurmasAPI.php?data='+info.dateStr)
.then(response=>response.json())
.then(data=>{

let select = document.getElementById("turmaSelect");
select.innerHTML="";

data.forEach(function(turma){

let option=document.createElement("option");
option.value=turma;
option.text=turma;

select.appendChild(option);

});

});

}

},
eventClick:function(info){

let inicio = info.event.start.toLocaleTimeString('pt-BR',{
hour:'2-digit',
minute:'2-digit',
hour12:false
});

let fim = info.event.end.toLocaleTimeString('pt-BR',{
hour:'2-digit',
minute:'2-digit',
hour12:false
});
let data = info.event.start.toLocaleDateString();

let turma = info.event.extendedProps.turma;
let solicitante = info.event.extendedProps.solicitante;
let descricao = info.event.extendedProps.descricao;
let status = info.event.extendedProps.status;

let statusTexto = status == 1 ? "Aprovado" : "Pendente";

document.getElementById("infoData").innerText = data;
document.getElementById("infoInicio").innerText = inicio;
document.getElementById("infoFim").innerText = fim;
document.getElementById("infoTurma").innerText = turma;
document.getElementById("infoSolicitante").innerText = solicitante;
document.getElementById("infoDescricao").innerText = descricao;
document.getElementById("infoStatus").innerText = statusTexto;

$("#infoReservaModal").modal();

},


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

<?php

if(isset($_GET['erro'])){
$cod=$_GET['erro'];
if(isset($erros[$cod])){
echo "<div class='alert alert-danger text-center'>".$erros[$cod]."</div>";
}
}

if(isset($_GET['ok'])){
$cod=$_GET['ok'];
if(isset($oks[$cod])){
echo "<div class='alert alert-success text-center'>".$oks[$cod]."</div>";
}
}

?>

<h3 class="mb-3">
<center><b>Ambiente:</b> <?php echo $nomeLaboratorio; ?></center>
</h3>

<div id='calendar'></div>

</div>

<div class="modal fade" id="reservaModal">

<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Solicitar Reserva</h5>
<button type="button" class="btn-close" data-dismiss="modal"></button>
</div>

<div class="modal-body">

<form method="POST" action="solicita.php">

<b>Data:</b><br>
<span id="data"></span>
<input type="hidden" name="dataInp">

<br><br>

<b>Turno:</b><br>

<input type="radio" name="turno" value="manha"> Manhã
<input type="radio" name="turno" value="tarde"> Tarde
<input type="radio" name="turno" value="noite"> Noite

<?php if($permDiaInteiro){ ?>

<input type="radio" name="turno" value="dia"> Dia inteiro

<?php } ?>

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

<?php if($permEvento){ ?>

<b>Tipo:</b><br>

<select name="tipo">
<option value="aula">Aula</option>
<option value="evento">Evento</option>
</select>

<br><br>

<b>Descrição / Observação:</b>

<textarea name="descricao" class="form-control"></textarea>

<br><br>

<?php } ?>

<b>Turma:</b><br>

<select name="turma" id="turmaSelect"></select>

<input type="hidden" name="lab" value="<?php echo $lab; ?>">

<?php

if(in_array($nivel,[3,4,9]) || $logado=='administrator' || $nivel=='admin'){

echo "<br><br><b>Solicitante:</b><br>";

echo "<select name='solicitante'>";

$stmt=$pdo->prepare("SELECT id,nome FROM usuarios ORDER BY nome");
$stmt->execute();

$users=$stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($users as $u){

echo "<option value='".$u['id']."'>".$u['nome']."</option>";

}

echo "</select>";

}else{

$stmt=$pdo->prepare("SELECT id FROM usuarios WHERE nome=?");
$stmt->execute([$logado]);

$user=$stmt->fetch(PDO::FETCH_ASSOC);

echo "<input type='hidden' name='solicitante' value='".$user['id']."'>";

}

?>

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
<div class="modal fade" id="infoReservaModal">

<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Informações da Reserva</h5>
<button type="button" class="btn-close" data-dismiss="modal"></button>
</div>

<div class="modal-body">

<b>Data:</b>
<div id="infoData"></div>

<br>

<b>Horário:</b>
<div>
<span id="infoInicio"></span> até 
<span id="infoFim"></span>
</div>

<br>

<b>Turma:</b>
<div id="infoTurma"></div>

<br>

<b>Solicitante:</b>
<div id="infoSolicitante"></div>

<br>

<b>Status:</b>
<div id="infoStatus"></div>

<br>

<b>Descrição:</b>
<div id="infoDescricao"></div>

</div>

</div>
</div>

</div>
<script>

function horarios(){

const box=document.querySelector('input[value=parcial]');

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