<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    require("../conexaosec.php");    

    $countSesi = 0;
    $dataTotal = [];
    $sexto = 0;
    $setimo = 0;
    $oitavo = 0;
    $primeiro = 0;
    $segundo = 0;
    $terceiro = 0;

    try {
        $buscaTurma = $conn->prepare("SELECT COUNT(*), n.descricao from dbo.pessoas AS p INNER JOIN dbo.niveis AS n
        ON p.nivel_id = n.id 
        WHERE n.descricao LIKE 'E%' AND validade_data_fim > GETDATE() AND estado = 0 
        GROUP BY n.id, n.descricao");
        
        $buscaTurma->execute();

        $buscaT = $buscaTurma -> fetchAll();
        foreach($buscaT as $buscaT) {
            array_push($dataTotal, $buscaT);
        }
        
        echo json_encode($dataTotal);

        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
?>








SELECT COUNT(*), n.descricao from dbo.pessoas AS p INNER JOIN dbo.niveis AS n
ON p.nivel_id = n.id
WHERE n.descricao LIKE 'E%' AND validade_data_fim > GETDATE() AND estado = 0 
GROUP BY n.id, n.descricao