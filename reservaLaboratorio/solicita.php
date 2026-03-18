<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../../index.php');
    exit;
}

include("../conexao.php");

$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];

$data = $_POST["dataInp"];
$periodo = $_POST["periodo"];
$hInicio = $_POST["horainicio"] ?? "";
$hFim = $_POST["horafim"] ?? "";
$turma = $_POST["turma"];
$descricao = $_POST["descricao"] ?? "";
$lab = $_POST["lab"];
$solicitante = $_POST["solicitante"];
$tipo = $_POST["tipo"] ?? "aula";

/* TURNO */

if(isset($_POST["turno"])){

$turno = $_POST["turno"];

if($periodo == "todo"){

if($turno == "manha"){
$hInicio="07:00";
$hFim="12:20";
}

if($turno == "tarde"){
$hInicio="13:00";
$hFim="17:30";
}

if($turno == "noite"){
$hInicio="18:00";
$hFim="22:30";
}

if($turno == "dia"){
$hInicio="06:00";
$hFim="23:00";
}

}

}

/* VALIDAÇÕES */

if($periodo == "parcial"){
if(empty($hInicio) || empty($hFim)){
header("Location: laboratorios.php?lab=".$lab."&erro=1");
exit;
}
}

if($periodo == "todo" && empty($_POST["turno"])){
header("Location: laboratorios.php?lab=".$lab."&erro=2");
exit;
}

/* VERIFICA CONFLITO */

$stmt = $pdo->prepare("

SELECT COUNT(*) 
FROM reservas 
WHERE laboratorio = ?
AND data = ?
AND ((? < horarioFim) AND (? > horarioInicio))

");

$stmt->execute([$lab,$data,$hInicio,$hFim]);

$conflito = $stmt->fetchColumn();

if($conflito > 0){
header("Location: laboratorios.php?lab=".$lab."&erro=3");
exit;
}

/* INSERE */

try{

$stmt = $pdo->prepare("

INSERT INTO reservas
(data,horarioInicio,horarioFim,solicitante,laboratorio,turma,aprovado,descricao,tipo)
VALUES (?,?,?,?,?,?,0,?,?)

");

$stmt->execute([

$data,
$hInicio,
$hFim,
$solicitante,
$lab,
$turma,
$descricao,
$tipo

]);

header("Location: laboratorios.php?lab=".$lab."&ok=1");

}catch(PDOException $e){

header("Location: laboratorios.php?lab=".$lab."&erro=4");

}
?>