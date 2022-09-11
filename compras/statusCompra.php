<?php
    include("conexao.php");
    $id = $_GET['id'];
    $status = $_GET['status'];

    try {
        $aprovacao = $conn->prepare("UPDATE solicitacao SET status = $status WHERE idSolicitacao = $id");
        $aprovacao->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    header("Location: lista.php");
?>