<?php
    include("conexaoteste.php");
    $id = $_GET['id'];
    $status = $_GET['status'];

    try {
        $aprovacao = $conn->prepare("UPDATE reservalabs SET aprovado = $status WHERE id = $id");
        $aprovacao->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    $url="email.php?cod=2&id=$id";
    header("Location: $url");
    //header('location:supervisao.php');
?>