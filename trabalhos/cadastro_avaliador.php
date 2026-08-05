<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$msg = '';
if ($_POST) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO Avaliadores (nome, email, senha_hash) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $email, $senha]);
        $avaliador_id = $pdo->lastInsertId();

        $stmtVinc = $pdo->prepare("INSERT INTO AvaliadorTrabalho (avaliador_id, trabalho_id) VALUES (?, ?)");
        if (!empty($_POST['trabalhos'])) {
            foreach ($_POST['trabalhos'] as $trabalho_id) {
                $stmtVinc->execute([$avaliador_id, $trabalho_id]);
            }
        } else {
            // Deixou vazio -> avalia todos os trabalhos cadastrados até agora
            $todos = $pdo->query("SELECT id FROM Trabalhos")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($todos as $trabalho_id) {
                $stmtVinc->execute([$avaliador_id, $trabalho_id]);
            }
        }
        $msg = "<div class='alert alert-success mt-3'>Avaliador cadastrado!</div>";
    } catch (PDOException $e) {
        $msg = "<div class='alert alert-danger mt-3'>Erro ao cadastrar.</div>";
    }
}
?>
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
            $res = $pdo->query("SELECT id, titulo FROM Trabalhos ORDER BY titulo");
            foreach ($res as $row) {
                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['titulo']) . "</option>";
            }
            ?>
        </select>
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