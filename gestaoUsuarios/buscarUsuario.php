<?php

require_once('../conexao.php');

$registro = $_GET['registro'] ?? '';

$sql = "SELECT nome, usuario, perfil 
        FROM usuarios 
        WHERE registro = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$registro]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user){

    echo json_encode($user);

}else{

    echo json_encode(["erro"=>true]);

}