<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    header('Content-Type: application/json');
    
    include('conexao.php');

    $dados = array();

    $busca = $conn->prepare("SELECT  *, convert(varchar(5), dataHora, 108) AS hora FROM consumoenergia WHERE dataHora >= DATEADD(day, -1, GETDATE()) ORDER BY id DESC");

    $busca->execute();

    $buscaDado = $busca->fetchAll();
    foreach($buscaDado as $buscaDado) {
        $dados[] = $buscaDado;
        //array_push($dados,$buscaDado);
    }

    echo json_encode($dados);
?>