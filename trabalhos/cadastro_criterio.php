<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$msg = '';
if ($_POST) {
    $desc = $_POST['descricao'];
    $max = $_POST['pontuacao_maxima'];
    try {
        $stmt = $pdo->prepare("INSERT INTO Criterios (descricao, pontuacao_maxima) VALUES (?, ?)");
        $stmt->execute([$desc, $max]);
        $msg = "<div class='alert alert-success mt-3'>Critério cadastrado!</div>";
    } catch (PDOException $e) {
        $msg = "<div class='alert alert-danger mt-3'>Erro ao cadastrar.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cadastro de Critérios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="container mt-5">

<h2>Cadastro de Critério</h2>

<form method="post">
    <div class="mb-3">
        <label>Descrição</label>
        <input type="text" name="descricao" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Pontuação máxima (ex: 5, 10)</label>
        <input type="number" name="pontuacao_maxima" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar</button>
</form>

<?= $msg ?>
</br>
<a href="ranking.php" class="btn btn-secondary mb-3">
  <i class="bi bi-arrow-left"></i> Voltar para área do gestor
</a>
</body>
</html>