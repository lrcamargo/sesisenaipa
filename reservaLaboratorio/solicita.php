<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    session_start();

    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../../index.php');    
    }

    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];

    include("conexao.php");

    $data = $_POST["dataInp"];

    if($nivel == 9 || $nivel == 4 || $nivel == 3) {
        if (isset($_POST['turno'])) {
            if(is_array($_POST['turno'])) {
                $opcoes_selecionadas = $_POST['turno'];

                foreach ($opcoes_selecionadas as $opcao) {
                    echo "Opção selecionada: " . htmlspecialchars($opcao) . "<br>";
                }
            } else {
                $turnoD = $_POST["turno"];
            }  
        }} else {
            $turnoD = $_POST["turno"];
        }

    $periodo = $_POST["periodo"];
    $hInicio = $_POST["horainicio"];
    $hFim = $_POST["horafim"];
    $turma = $_POST["turma"];
    $solicitante = $_POST["solicitante"];
    $descricao = $_POST['descricao'] ?? '';
    $laboratorio = $_POST["lab"];

    if($periodo == "todo") {
        if($nivel == '4' || $nivel == '3' || $nivel == '9') {
            if(!is_null($opcoes_selecionadas)) {
                    if($opcoes_selecionadas[0] == "manha" && empty($opcoes_selecionadas[1])) {
                        $hInicio = "07:00";
                        $hFim = "12:20";
                    } else if($opcoes_selecionadas[0] == "tarde" && empty($opcoes_selecionadas[1])){
                        $hInicio = "13:00";
                        $hFim = "17:30";
                    } else if($opcoes_selecionadas[0] == "noite" && empty($opcoes_selecionadas[1])){
                        $hInicio = "18:00";
                        $hFim = "22:30";
                    } else if($opcoes_selecionadas[0] == "manha" && $opcoes_selecionadas[1] == "tarde" && empty($opcoes_selecionadas[2])) {
                        $hInicio = "07:00";
                        $hFim = "17:30";
                    } else if($opcoes_selecionadas[0] == "tarde" && $opcoes_selecionadas[1] == "noite" && empty($opcoes_selecionadas[2])) {
                        $hInicio = "13:00";
                        $hFim = "22:30";
                    } else if($opcoes_selecionadas[0] == "manha" && $opcoes_selecionadas[1] == "tarde" && $opcoes_selecionadas[2] == "noite") {
                        $hInicio = "07:00";
                        $hFim = "22:30";
                    } else if($opcoes_selecionadas[0] == "manha" && $opcoes_selecionadas[1] == "noite" && empty($opcoes_selecionadas[2])) {
                        $hInicio = "07:00";
                        $hFim = "22:30";
                    }
                } else {
                    if($turnoD == "manha") {
                        $hInicio = "07:00";
                        $hFim = "12:20";
                    } else if($turnoD=="tarde"){
                        $hInicio = "13:00";
                        $hFim = "17:30";
                    } else {
                        $hInicio = "18:00";
                        $hFim = "22:30";
                    }
                }
            } else {
                if($turnoD == "manha") {
                    $hInicio = "07:00";
                    $hFim = "12:20";
                } else if($turnoD=="tarde"){
                    $hInicio = "13:00";
                    $hFim = "17:30";
                } else {
                    $hInicio = "18:00";
                    $hFim = "22:30";
                }
            }
        }     
    
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
    } else if($laboratorio == "lego") {
        $lab = 12;
    } else if($laboratorio == 'tornearia') {
        $lab = 13;
    } else if($laboratorio == 'ferramentaria') {
        $laboratorio = 14;
    } else if($laboratorio == 'manutenção') {
        $lab = 15;
    } else if($laboratorio == 'solda') {
        $lab = 16;
    } else if($laboratorio == "teatro") {
        $lab = 17;
    } else if($laboratorio == "biblioteca") {
        $lab = 18;
    }
    
    //echo "INSERT INTO reservas (data,horarioInicio,horarioFim,solicitante,laboratorio,turma,aprovado,descricao) VALUES ('$data','$hInicio','$hFim','$solicitante','$lab','$turma',0,'$descricao')";
    try {
        $cadreserva = $conn->prepare("INSERT INTO reservas (data,horarioInicio,horarioFim,solicitante,laboratorio,turma,aprovado,descricao) VALUES ('$data','$hInicio','$hFim','$solicitante','$lab','$turma',0,'$descricao')");
        $cadreserva->execute();

        $buscaReserv = $conn->prepare("SELECT TOP 1 id FROM reservas ORDER BY id DESC");
        $buscaReserv->execute();
        
        $idRs = $buscaReserv->fetchAll();
        foreach($idRs as $idRs) {
            $id = $idRs['id'];
        }
        $url="email.php?cod=1&id=$id";
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    //solicitante, laboratorio, data
    header("Location: $url");
    
?>
