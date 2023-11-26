<?php
    include('conexao.php');
    $cand = $_GET['num'];
    $sql = "SELECT * FROM candidatos WHERE numeroCandidato = $cand";
    $result = $conn->query($sql);
    $rows = array();
    $rows = $result->fetchAll();
    print json_encode($rows);
?>