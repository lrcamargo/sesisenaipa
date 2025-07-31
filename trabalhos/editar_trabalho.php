<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "ID inválido."; exit; }

// Buscar dados atuais
$sql = "SELECT * FROM Trabalhos WHERE id=?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$trabalho = sqlsrv_fetch_array($stmt);

if ($_POST) {
    $titulo = $_POST['titulo'];
    $turma = $_POST['turma'];

    $sql = "UPDATE Trabalhos SET titulo=?, turma=? WHERE id=?";
    sqlsrv_query($conn, $sql, array($titulo, $turma, $id));

    header('Location: ranking.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Trabalho</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<a href="ranking.php" class="btn btn-secondary mb-3">← Voltar</a>
<h2>Editar Trabalho</h2>
<form method="post">
    <div class="mb-3">
        <label>Título</label>
        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($trabalho['titulo']) ?>" required>
    </div>
    <div class="mb-3">
        <label>Turma</label>
        <input type="text" name="turma" class="form-control" value="<?= htmlspecialchars($trabalho['turma']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
</body>
</html>