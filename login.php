<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

session_start();
require_once 'conexao.php';

$usuario = $_POST['user'] ?? '';
$senha = $_POST['pass'] ?? '';

$sql = "SELECT * FROM usuarios 
        WHERE usuario = ?
        AND status = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user){

    if(password_verify($senha, $user['senha'])){

        $_SESSION['sLogin'] = true;
        $_SESSION['id'] = $user['id'];
        $_SESSION['user'] = $user['nome'];
        $_SESSION['group'] = $user['perfil'];

        // VERIFICA PRIMEIRO LOGIN
        if($user['primeiro_login'] == 1){

            header("Location: trocarSenha.php");
            exit;

        }else{

            header("Location: main.php");
            exit;

        }

    }else{

        echo "Senha incorreta";

    }

}else{

    echo "Usuário não encontrado ou inativo";

}
?>