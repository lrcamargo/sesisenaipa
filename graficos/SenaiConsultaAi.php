<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);

    require("../conexaosec.php");    

    $dataTotal = [];

    try {
        $buscaSenai = $conn->prepare("SELECT nivel_id, descricao, COUNT(pessoas.id) AS TOTAL FROM dbo.pessoas
        INNER JOIN niveis ON nivel_id = niveis.id WHERE estado = 0 AND SUBSTRING(descricao, 1, 2) = 'AI'
        GROUP BY nivel_ID, estado, descricao");
                            
        $buscaSenai->execute();

        $buscaAlunos = $buscaSenai->fetchAll(PDO::FETCH_ASSOC);
        echo $result = json_encode($buscaAlunos);
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }

?>