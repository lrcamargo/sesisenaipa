<?php
    include("conexao.php");

    $id = $_POST['turma'];
    $data = $_POST['dataFim'];
    $hora = " 00:00:00";
    $novaData = $data.$hora;
    
    try {
        $query = $conn->prepare("UPDATE pessoas SET validade_data_fim = '$novaData' WHERE nivel_id = $id");

        $numRows = $query->execute();
    
        if($numRows > 0) {
            header("Location: index.php?action=1");
        } else {
            header("Location: index.php?action=2");
        }
        
    } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
    }
?>
