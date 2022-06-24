<?php
    include("conexao.php");
    
    $lab = $_GET['lab'];

    function rand_color() {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    try {
        $buscareserva = $conn->prepare("SELECT id,data,LEFT(RTRIM(CONVERT(TIME, horarioInicio)), 8) AS horarioInicio, LEFT(RTRIM(CONVERT(TIME, horarioFim)), 8) AS horarioFim,solicitante,laboratorio,turma,aprovado FROM reservas WHERE laboratorio = '$lab'");
        $buscareserva->execute();
        
        $reservas = [];

        $buscaRes = $buscareserva->fetchAll(PDO::FETCH_ASSOC);
            foreach ($buscaRes as $buscaRes) {
                if($buscaRes['aprovado'] == 0 || $buscaRes['aprovado'] == 1) {
                    $id = $buscaRes['id'];
                $data = $buscaRes['data'];
                $horaInicio = $buscaRes['horarioInicio'];
                $horaFim = $buscaRes['horarioFim'];
                $solicitante = $buscaRes['solicitante'];
                $turma = $buscaRes['turma'];
                $aprovado = $buscaRes['aprovado'];

                if($aprovado == 0) {
                    $color = "gray";
                    $textColor = "black";
                } else {
                    $color = rand_color();
                    $textColor = "black";
                }
                $reservas[] = [
                    'title' => $solicitante . " " . $turma,
                    'color' => $color,
                    'status' => $aprovado,
                    'backgroundColor' => $color,
                    'start' => $data."T".$horaInicio,
                    'end' => $data."T".$horaFim,
                    'textColor' => $textColor,
                ];
                }
            } 
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }

    echo json_encode($reservas);
?>