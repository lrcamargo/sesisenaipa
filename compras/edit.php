<?php
    include("conexao.php");

    $id = $_POST['id'];
    $quantidade = $_POST["quant"];
    $aplicacao = $_POST["aplic"];
    $unidade = $_POST["unidade"];
    $cc = $_POST["cc"];

    try {
        $upItem = $conn->prepare("UPDATE itens SET quantidade = '$quantidade', aplicacao = '$aplicacao', unidade = '$unidade', cc = '$cc' WHERE idItem = '$id'");
        $upItem->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    header('location:lista.php');
?>
