<?php
    include("conexaosec.php");
    session_start();

    $oldPass = $_POST['antiga'];
    $newPass = $_POST['nova'];
    $confirma = $_POST['confirma'];
    $usuario = $_POST['usuario'];

    try {
        $alteraSenha = $conn->prepare("UPDATE pessoas SET web_senha = '$newPass', obs = '' WHERE n_identificador = ".$usuario);
        $alteraSenha->execute();

        header('Location:../index.php');        
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
?>