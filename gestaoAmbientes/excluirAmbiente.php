<?php
require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:index.php?msg=erro_permissao'); exit;
}

$id = intval($_GET['id'] ?? 0);
if(!$id){ header('location:index.php?msg=nao_encontrado'); exit; }

// Verifica se há reservas vinculadas — impede exclusão para manter integridade
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE laboratorio = ?");
$stmt->execute([$id]);
if($stmt->fetchColumn() > 0){
    header('location:index.php?msg=erro_excluir'); exit;
}

try{
    $pdo->prepare("DELETE FROM laboratorios WHERE idLaboratorio = ?")
        ->execute([$id]);
    header('location:index.php?msg=excluido');
} catch(PDOException $e){
    error_log("[excluirAmbiente] ".$e->getMessage());
    header('location:index.php?msg=erro_db');
}
exit;
?>