<?php
    include("conexaoTeste.php");
    $link = $_POST["link"];
    $nome = $_POST["nome"];
    $logado = $_POST["docente"];

    echo $link;

    try {
        $insere = $conn->prepare("INSERT INTO linksAulas (nome,link,docente) VALUES ('$nome','$link','$logado')");
        
        $insere->execute();

    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
?>