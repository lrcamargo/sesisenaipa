<?php
    include("conexao.php");
    $id = $_GET['id'];
    $cc = $_GET['cc'];

    try {
        $aprovacao = $conn->prepare("UPDATE itens SET cc = $cc WHERE codSolicitacao = $id");
        $aprovacao->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    header("Location: lista.php");
?>