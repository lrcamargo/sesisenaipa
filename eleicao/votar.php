<?php
    include('conexao.php');
    $cand = $_GET['num'];
    $sql = "UPDATE candidatos SET votos = votos + 1 = WHERE numeroCandidato = '$cand'";
    $result = $conn->query($sql);
?>