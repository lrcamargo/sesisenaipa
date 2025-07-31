<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "ID inválido."; exit; }

// Busca dados do avaliador
$stmt = sqlsrv_query($conn, "SELECT * FROM Avaliadores WHERE id=?", array($id));
$avaliador = sqlsrv_fetch_array($stmt);

// Busca todos os trabalhos ordenados
$res_trabalhos = sqlsrv_query($conn, "SELECT id, titulo FROM Trabalhos ORDER BY titulo");

// Busca trabalhos já vinculados
$res_vinc = sqlsrv_query($conn, "SELECT trabalho_id FROM AvaliadorTrabalho WHERE avaliador_id=?", array($id));
$vinculados = [];
while($v = sqlsrv_fetch_array($res_vinc)) {
    $vinculados[] = $v['trabalho_id'];
}

// Se foi salvo com sucesso, exibe mensagem
$msg = '';
if ($_POST) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $trabalhos = $_POST['trabalhos'] ?? [];

    sqlsrv_query($conn, "UPDATE Avaliadores SET nome=?, email=? WHERE id=?", array($nome, $email, $id));
    if (!empty($_POST['nova_senha'])) {
        sqlsrv_query($conn, "UPDATE Avaliadores SET senha=? WHERE id=?", array($_POST['nova_senha'], $id));
    }
    sqlsrv_query($conn, "DELETE FROM AvaliadorTrabalho WHERE avaliador_id=?", array($id));
    foreach ($trabalhos as $trabalho_id) {
        sqlsrv_query($conn, "INSERT INTO AvaliadorTrabalho (avaliador_id, trabalho_id) VALUES (?, ?)", array($id, $trabalho_id));
    }
    $msg = "Salvo com sucesso!";
    // Recarrega os vinculados após salvar
    $res_vinc = sqlsrv_query($conn, "SELECT trabalho_id FROM AvaliadorTrabalho WHERE avaliador_id=?", array($id));
    $vinculados = [];
    while($v = sqlsrv_fetch_array($res_vinc)) {
        $vinculados[] = $v['trabalho_id'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Avaliador</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .colunas-trabalhos { columns: 2; /* ou 3 para mais colunas */ }
    </style>
</head>
<body class="container mt-5">
<a href="ranking.php" class="btn btn-secondary mb-3">← Voltar</a>
<h2>Editar Avaliador</h2>

<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="post">
    <div class="mb-3">
        <label>Nome</label>
        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($avaliador['nome']) ?>" required>
    </div>
    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($avaliador['email']) ?>" required>
    </div>
    <h5 class="mt-3">Redefinir senha (opcional):</h5>
    <div class="mb-3">
        <input type="password" name="nova_senha" class="form-control" placeholder="Nova senha (deixe em branco para não alterar)">
    </div>
    <h5>Trabalhos que ele pode avaliar:</h5>

    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="selecionarTodos" onclick="toggleAll(this)">
        <label class="form-check-label fw-bold">Selecionar todos</label>
    </div>

    <div class="colunas-trabalhos">
    <?php
    sqlsrv_fetch($res_trabalhos, SQLSRV_FETCH_ASSOC); // Reset cursor
    sqlsrv_execute($res_trabalhos);
    while($t = sqlsrv_fetch_array($res_trabalhos)) { ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="trabalhos[]" value="<?= $t['id'] ?>"
            <?= in_array($t['id'], $vinculados) ? 'checked' : '' ?>>
            <label class="form-check-label"><?= htmlspecialchars($t['titulo']) ?></label>
        </div>
    <?php } ?>
    </div>

    <button type="submit" class="btn btn-primary mt-3">Salvar</button>
</form>

<script>
function toggleAll(source) {
    checkboxes = document.querySelectorAll('input[name="trabalhos[]"]');
    for (var i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
</body>
</html>