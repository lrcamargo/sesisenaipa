<!DOCTYPE HTML>
<?php
session_start();

if((!isset ($_SESSION['sLogin']) == true))
{
    unset($_SESSION['sLogin']);
    unset($_SESSION['user']);
    header('location:../index.php');
    }
 
$logado = $_SESSION['user'];

include('conexao.php');
$id = $_POST['id'];
$hora = $_POST['hora'];
$minuto = $_POST['minuto'];

if(isset($_POST['seg'])) {
    $segunda = 1;
} else {
    $segunda = 0;
}
if(isset($_POST['ter'])) {
    $terca = 1;
} else {
    $terca = 0;
}
if(isset($_POST['qua'])) {
    $quarta = 1;
} else {
    $quarta = 0;
}
if(isset($_POST['qui'])) {
    $quinta = 1;
} else {
    $quinta = 0;
}
if(isset($_POST['sex'])) {
    $sexta = 1;
} else {
    $sexta = 0;
}
if(isset($_POST['sab'])) {
    $sabado = 1;
} else {
    $sabado = 0;
}
if(isset($_POST['dom'])) {
    $domingo = 1;
} else {
    $domingo = 0;
}
if(!empty($_POST['duracao'])) {
    $duracao = $_POST['duracao'];
} else {
    $duracao = 3;
}

if(isset($_POST['fir']) && isset($_POST['sec'])) {
    $sirene = 3;
} else if(isset($_POST['fir']) && !isset($_POST['sec'])){
    $sirene = 1;
} else if(!isset($_POST['fir']) && isset($_POST['sec'])){
    $sirene = 2;
} 

try{        
    $sql = "UPDATE horarioSirene SET hora = '$hora', minuto = '$minuto', segunda = '$segunda', terca = '$terca', quarta = '$quarta', quinta = '$quinta', sexta = '$sexta', sabado = '$sabado', domingo = '$domingo', duracao = '$duracao', sirene = '$sirene' WHERE idHorario = '$id'";
                                
    $conn->exec($sql);
} catch(PDOException $e) {
    echo $sql . "<br>" . $e->getMessage();
}
$conn = null;

header("location:index.php?acao=2");

?>
