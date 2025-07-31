<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    sqlsrv_query($conn, "DELETE FROM Avaliadores WHERE id=?", array($id));
    // Se quiser, também remover os vínculos:
    // sqlsrv_query($conn, "DELETE FROM AvaliadorTrabalho WHERE avaliador_id=?", array($id));
}
header('Location: ranking.php');
exit;
?>
