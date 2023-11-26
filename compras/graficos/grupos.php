<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    header('Content-Type: application/json');
    
    include('../conexao.php');

    $dados = array();

    $busca = $conn->prepare("SELECT COUNT(grupo) as quantidade, grupo FROM itens INNER JOIN produtos ON itens.codigoProduto = produtos.codigo
    GROUP BY produtos.grupo");

    $busca->execute();

    $buscaDado = $busca->fetchAll();
    foreach($buscaDado as $buscaDado) {
        $dados[] = $buscaDado;
    }

    echo json_encode($dados);
?>