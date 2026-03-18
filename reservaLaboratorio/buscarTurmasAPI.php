<?php

    $data = $_GET['data'];
    $url = "http://100.98.98.250:3002/backapi/Turmas"; // endpoint da catraca
    $json = file_get_contents($url);

    $turmas = json_decode($json, true);

    $resultado = [];

    foreach($turmas as $t){
        if($data >= $t['dataInicio'] && $data <= $t['dataFim']){
            $resultado[] = $t['nome'];
        }
    }

    echo json_encode($resultado);
?>