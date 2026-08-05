<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    // Limpa tudo que referencia esse avaliador antes de excluir
    $pdo->prepare("DELETE FROM Notas WHERE avaliacao_id IN (SELECT id FROM (SELECT id FROM Avaliacoes WHERE avaliador_id = ?) AS a)")->execute([$id]);
    $pdo->prepare("DELETE FROM Avaliacoes WHERE avaliador_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM AvaliadorTrabalho WHERE avaliador_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM Avaliadores WHERE id = ?")->execute([$id]);
}
header('Location: ranking.php');
exit;