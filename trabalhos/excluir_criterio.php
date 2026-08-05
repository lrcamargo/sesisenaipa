<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $pdo->prepare("DELETE FROM Notas WHERE criterio_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM Criterios WHERE id = ?")->execute([$id]);
}
header('Location: ranking.php');
exit;