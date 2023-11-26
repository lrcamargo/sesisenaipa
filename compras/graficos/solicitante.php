<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    header('Content-Type: application/json');
    
    include('../conexao.php');

    $dados = array();

    $busca = $conn->prepare("SELECT  COUNT(solicitacao.idSolicitacao) as quantidade, solicitante
    FROM solicitacao WHERE EXISTS (SELECT * FROM solicitacao WHERE dataHora >= DATEADD(DAY, -90, GETDATE())) 
    GROUP BY solicitante");

    $busca->execute();

    $buscaDado = $busca->fetchAll();
    foreach($buscaDado as $buscaDado) {
        $dados[] = $buscaDado;
    }

    echo json_encode($dados);
?>