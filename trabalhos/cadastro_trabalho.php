<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$msg = '';
if ($_POST) {
    $titulo = $_POST['titulo'];
    $turma = $_POST['turma'];
    $area = trim($_POST['area'] ?? '') ?: null;
    $membros = explode(',', $_POST['membros']);

    try {
        $stmt = $pdo->prepare("INSERT INTO Trabalhos (titulo, turma, area) VALUES (?, ?, ?)");
        $stmt->execute([$titulo, $turma, $area]);
        $trabalho_id = $pdo->lastInsertId();

        $stmtM = $pdo->prepare("INSERT INTO Membros (trabalho_id, nome) VALUES (?, ?)");
        foreach ($membros as $m) {
            $nome = trim($m);
            if ($nome !== '') {
                $stmtM->execute([$trabalho_id, $nome]);
            }
        }
        $msg = "<div class='alert alert-success mt-3'>Trabalho cadastrado com sucesso!</div>";
    } catch (PDOException $e) {
        $msg = "<div class='alert alert-danger mt-3'>Erro ao cadastrar.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cadastro de Trabalhos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="container mt-5">

<h2>Cadastro de Trabalho</h2>

<form method="post">
    <div class="mb-3">
        <label>Título do Trabalho</label>
        <input type="text" name="titulo" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Turma</label>
        <input type="text" name="turma" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Área</label>
        <input type="text" name="area" class="form-control" placeholder="Ex: Robótica, Sustentabilidade... (usado no ranking por área)">
    </div>
    <div class="mb-3">
        <label>Membros (separados por vírgula)</label>
        <textarea name="membros" class="form-control" required></textarea>
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