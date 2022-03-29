<?php
    include("conexaosec.php");
    session_start();

    $newPass = $_POST['nova'];
    $registro = $_POST['ra'];

    try {
        $alteraSenha = $conn->prepare("UPDATE pessoas SET web_senha = '$newPass', obs = '1' WHERE n_identificador = ".$registro);
        $alteraSenha->execute();

        echo "Senha do aluno " . $registro . " alterada.";
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
?>