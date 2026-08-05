<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "ID inválido."; exit; }

$stmt = $pdo->prepare("SELECT * FROM Trabalhos WHERE id = ?");
$stmt->execute([$id]);
$trabalho = $stmt->fetch();

if ($_POST) {
    $titulo = $_POST['titulo'];
    $turma = $_POST['turma'];
    $area = trim($_POST['area'] ?? '') ?: null;

    $pdo->prepare("UPDATE Trabalhos SET titulo = ?, turma = ?, area = ? WHERE id = ?")
        ->execute([$titulo, $turma, $area, $id]);

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
    <div class="mb-3">
        <label>Área</label>
        <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($trabalho['area'] ?? '') ?>" placeholder="Ex: Robótica, Sustentabilidade...">
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
</body>
</html>