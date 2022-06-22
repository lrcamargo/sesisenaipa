<?php
    include("conexaoteste.php");

    $id = $_POST['id'];
    $data = $_POST["dataInp"];
    $turno = $_POST["turno"];
    $periodo = $_POST["periodo"];
    $hInicio = $_POST["horainicio"];
    $hFim = $_POST["horafim"];
    $turma = $_POST["turma"];
    $solicitante = $_POST["solicitante"];
    $laboratorio = $_POST["lab"];

    if($periodo == "todo") {
        if($turno == "manha") {
            $hInicio = "07:00";
            $hFim = "12:20";
        } else if($turno=="tarde"){
            $hInicio = "13:00";
            $hFim = "17:30";
        } else {
            $hInicio = "18:00";
            $hFim = "22:30";
        }
    }
    //checar com Aline e Anderson oficina mecânica - solda, ajustagem,manutencao...
    if($laboratorio == "201a") {
        $lab = 1;
    } else if($laboratorio == "202a") {
        $lab = 2;
    } else if($laboratorio == "203a") {
        $lab = 3;
    } else if($laboratorio == "101b") {
        $lab = 4;
    } else if($laboratorio == "103b") {
        $lab = 5;
    } else if($laboratorio == "104b") {
        $lab = 6;
    } else if($laboratorio == "105b") {
        $lab = 7;
    } else if($laboratorio == "106b") {
        $lab = 8;
    } else if($laboratorio == "107b") {
        $lab = 9;
    } else if($laboratorio == "101a") {
        $lab = 10;
    } else if($laboratorio == "cnc") {
        $lab = 11;
    }
    try {
        $cadreserva = $conn->prepare("UPDATE reservalabs SET data = '$data', horarioInicio = '$hInicio', horarioFim = '$hFim', laboratorio = '$lab', turma = '$turma' WHERE id = '$id'");
        $cadreserva->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    header('location:supervisao.php');
?>
