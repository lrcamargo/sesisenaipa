<?php include 'includes/db.php'; ?>
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
        <label>Membros (separados por vírgula)</label>
        <textarea name="membros" class="form-control" required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar</button>
</form>

<?php
if ($_POST) {
    $titulo = $_POST['titulo'];
    $turma = $_POST['turma'];
    $membros = explode(',', $_POST['membros']);

    $sql = "INSERT INTO Trabalhos (titulo, turma) OUTPUT INSERTED.id VALUES (?, ?)";
    $params = array($titulo, $turma);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && sqlsrv_fetch($stmt)) {
        $trabalho_id = sqlsrv_get_field($stmt, 0);
        foreach ($membros as $m) {
            $nome = trim($m);
            sqlsrv_query($conn, "INSERT INTO Membros (trabalho_id, nome) VALUES (?, ?)", array($trabalho_id, $nome));
        }
        echo "<div class='alert alert-success mt-3'>Trabalho cadastrado com sucesso!</div>";
    } else {
        echo "<div class='alert alert-danger mt-3'>Erro ao cadastrar.</div>";
    }
}
?>
</br>
<a href="ranking.php" class="btn btn-secondary mb-3">
  <i class="bi bi-arrow-left"></i> Voltar para área do gestor
</a>
</body>
</html>
