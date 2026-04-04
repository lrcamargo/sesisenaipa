<?php
require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    header('location:gestaoTurmas.php'); exit;
}
$id = intval($_GET['id'] ?? 0);
if(!$id){ header('location:gestaoTurmas.php'); exit; }
try{
    $pdo->prepare("DELETE FROM turma_sala WHERE id = ?")->execute([$id]);
    header('location:gestaoTurmas.php?msg=excluido');
} catch(PDOException $e){
    error_log("[excluirTurma] ".$e->getMessage());
    header('location:gestaoTurmas.php?msg=erro_db');
}
exit;
?>