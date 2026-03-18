<?php

session_start();
require_once "conexao.php";

if(!isset($_SESSION['id'])){
    header("Location:index.php");
    exit;
}

$senha1 = $_POST['senha1'];
$senha2 = $_POST['senha2'];

if($senha1 != $senha2){
    die("Senhas não conferem.");
}

$senhaHash = password_hash($senha1, PASSWORD_DEFAULT);

$sql = "UPDATE usuarios 
        SET senha=?, primeiro_login=0
        WHERE id=?";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $senhaHash,
    $_SESSION['id']
]);

header("Location: main.php");
exit;