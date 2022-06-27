<?php
    include("conexao.php");
    $id = $_GET['id'];
    $status = $_GET['status'];

    try {
        $aprovacao = $conn->prepare("UPDATE reservas SET aprovado = $status WHERE id = $id");
        $aprovacao->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    //email?cod=2&idres=$id;
    header('location:email.php?cod=2&id=$id');
?>