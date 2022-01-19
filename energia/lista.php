<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    include('conexao.php');

    $dados = array();

    $busca = $conn->prepare("SELECT TOP 1 * FROM consumoenergia ORDER BY ID DESC");

    $busca->execute();

    $buscaDado = $busca->fetchAll();
    foreach($buscaDado as $buscaDado) {
        $dados = $buscaDado;
    }

    echo json_encode($dados);
?>