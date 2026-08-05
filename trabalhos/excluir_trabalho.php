<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $pdo->prepare("DELETE FROM Notas WHERE avaliacao_id IN (SELECT id FROM (SELECT id FROM Avaliacoes WHERE trabalho_id = ?) AS a)")->execute([$id]);
    $pdo->prepare("DELETE FROM Avaliacoes WHERE trabalho_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM AvaliadorTrabalho WHERE trabalho_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM Membros WHERE trabalho_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM Trabalhos WHERE id = ?")->execute([$id]);
}

header('Location: ranking.php');
exit;