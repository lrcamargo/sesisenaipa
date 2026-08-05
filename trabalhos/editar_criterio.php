<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "ID inválido."; exit; }

$stmt = $pdo->prepare("SELECT * FROM Criterios WHERE id = ?");
$stmt->execute([$id]);
$criterio = $stmt->fetch();

if ($_POST) {
    $descricao = $_POST['descricao'];
    $pontuacao = $_POST['pontuacao_maxima'];

    $pdo->prepare("UPDATE Criterios SET descricao = ?, pontuacao_maxima = ? WHERE id = ?")
        ->execute([$descricao, $pontuacao, $id]);

    header('Location: ranking.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Critério</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<a href="ranking.php" class="btn btn-secondary mb-3">← Voltar</a>
<h2>Editar Critério</h2>
<form method="post">
    <div class="mb-3">
        <label>Descrição</label>
        <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($criterio['descricao']) ?>" required>
    </div>
    <div class="mb-3">
        <label>Pontuação máxima</label>
        <input type="number" name="pontuacao_maxima" class="form-control" value="<?= htmlspecialchars($criterio['pontuacao_maxima']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
</body>
</html>