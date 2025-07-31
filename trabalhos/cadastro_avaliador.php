<?php include 'includes/db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Cadastro de Avaliador</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="container mt-5">

<h2>Cadastro de Avaliador</h2>

<form method="post">
    <div class="mb-3">
        <label>Nome</label>
        <input type="text" name="nome" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Senha</label>
        <input type="password" name="senha" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Escolha trabalhos que ele deve avaliar (Ctrl+Clique para vários) ou deixe vazio para avaliar todos</label>
        <select name="trabalhos[]" multiple class="form-select">
            <?php
            $res = sqlsrv_query($conn, "SELECT id, titulo FROM Trabalhos");
            while ($row = sqlsrv_fetch_array($res)) {
                echo "<option value='{$row['id']}'>{$row['titulo']}</option>";
            }
            ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar</button>
</form>

<?php
if ($_POST) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $sql = "INSERT INTO Avaliadores (nome, email, senha_hash) OUTPUT INSERTED.id VALUES (?, ?, ?)";
    $stmt = sqlsrv_query($conn, $sql, array($nome, $email, $senha));
    if ($stmt && sqlsrv_fetch($stmt)) {
        $avaliador_id = sqlsrv_get_field($stmt, 0);
        if (!empty($_POST['trabalhos'])) {
            foreach ($_POST['trabalhos'] as $trabalho_id) {
                sqlsrv_query($conn, "INSERT INTO AvaliadorTrabalho (avaliador_id, trabalho_id) VALUES (?, ?)", array($avaliador_id, $trabalho_id));
            }
        }
        echo "<div class='alert alert-success mt-3'>Avaliador cadastrado!</div>";
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