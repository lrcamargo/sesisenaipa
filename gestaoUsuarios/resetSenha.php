<?php
require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$nivel     = $_SESSION['group'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

$permReset = in_array($nivelNorm, [
    'admin', 'administrator', 'sup tecnica', 'gerencia', 'sup pedagogica'
]);

if(!$permReset){
    header('location:index.php?msg=erro_permissao');
    exit;
}

$id = intval($_GET['id'] ?? 0);

if(!$id){
    header('location:index.php?msg=erro_param');
    exit;
}

/* ── BUSCA O USUÁRIO ── */

$stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$usuario){
    header('location:index.php?msg=nao_encontrado');
    exit;
}

/* ── DEFINE SENHA PADRÃO + MARCA PRIMEIRO LOGIN ── */

// Senha padrão: 12345678
$senhaHash = password_hash('12345678', PASSWORD_DEFAULT);

try{
    $pdo->prepare("
        UPDATE usuarios
        SET senha = ?, primeiro_login = 1
        WHERE id = ?
    ")->execute([$senhaHash, $id]);

} catch(PDOException $e){
    error_log("[resetSenha] ".$e->getMessage());
    header('location:index.php?msg=erro_db');
    exit;
}


header('location:index.php?msg=senha_resetada');
exit;
?>