<?php

include("../conexao.php");

$lab = $_GET['lab'] ?? '';

function rand_color(){
    return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

try{

    $sql = "SELECT 
reservas.idReserva,
reservas.data,
reservas.horarioInicio,
reservas.horarioFim,
usuarios.nome AS solicitante,
reservas.laboratorio,
reservas.turma,
reservas.aprovado,
reservas.descricao
FROM reservas
JOIN usuarios 
ON usuarios.id = reservas.solicitante
WHERE reservas.laboratorio = :lab";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":lab",$lab);
    $stmt->execute();

    $reservas = [];

    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($dados as $r){

        if($r['tipo'] == "evento"){

    $color = "#8e44ad"; // evento
    $textColor = "white";

}else{

    if($r['aprovado'] == 0){

        $color = "#808080"; // cinza
        $textColor = "white";

    }else{

        $color = rand_color(); // aprovado
        $textColor = "black";

    }}
        $nome = explode(" ", $r['solicitante']);
        $solicitanteCurto = $nome[0];

        if(isset($nome[1])){
            $solicitanteCurto .= " ".$nome[1];
        }

        $reservas[] = [
            /*'title' => $r['solicitante']." ".$r['turma'],
            'color' => $color,
            'status' => $r['aprovado'],
            'descricao' => $r['descricao'],
            'backgroundColor' => $color,
            'start' => $r['data']."T".$r['horarioInicio'],
            'end' => $r['data']."T".$r['horarioFim'],
            'textColor' => $textColor*/
            'id' => $r['idReserva'],
            'title' => $solicitanteCurto." - ".$r['turma'],

'start' => $r['data']."T".$r['horarioInicio'],
'end' => $r['data']."T".$r['horarioFim'],

'backgroundColor' => $color,
'textColor' => $textColor,

'extendedProps' => [

    'solicitante' => $r['solicitante'],
    'turma' => $r['turma'],
    'descricao' => $r['descricao'],
    'status' => $r['aprovado'],
    'tipo' => $r['tipo']

]


        ];

    }

    echo json_encode($reservas);

}catch(PDOException $e){

    echo json_encode([
        "erro" => $e->getMessage()
    ]);

}

?>