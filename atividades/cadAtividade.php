<?php
    session_start();
    
    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../index.php');    
    }
    
    include('conexaoatv.php');
    
    $turma = $_POST['turma'];
    $nome = $_POST['nome'];
    $data = $_POST['data'];
    if(isset($_POST['pontuada'])) {
        $pontuada = 1;
    } else {
        $pontuada = 0;
    }
    if($pontuada==1) {
        $valor = $_POST['valor'];
    } else {
        $valor = 0;
    }    
    $disciplina = $_POST['disciplina'];
    $docente = $_SESSION['user'];

    try {
        $cadAtv = $conn->prepare("INSERT INTO atividades (nome,turma,dataEntrega,pontuada,valor,docente,disciplina) VALUES ('$nome','$turma','$data','$pontuada','$valor','$docente','$disciplina')");
        $cadAtv->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    header('location:professor.php');
?>