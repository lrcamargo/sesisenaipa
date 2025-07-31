<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    sqlsrv_query($conn, "DELETE FROM Notas WHERE avaliacao_id IN (SELECT id FROM Avaliacoes WHERE trabalho_id=?)", array($id));
    sqlsrv_query($conn, "DELETE FROM Avaliacoes WHERE trabalho_id=?", array($id));
    sqlsrv_query($conn, "DELETE FROM AvaliadorTrabalho WHERE trabalho_id=?", array($id));
    sqlsrv_query($conn, "DELETE FROM Membros WHERE trabalho_id=?", array($id));
    sqlsrv_query($conn, "DELETE FROM Trabalhos WHERE id=?", array($id));
}

header('Location: ranking.php');
exit;
?>