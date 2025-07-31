<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    sqlsrv_query($conn, "DELETE FROM Criterios WHERE id=?", array($id));
}
header('Location: ranking.php');
exit;
?>
