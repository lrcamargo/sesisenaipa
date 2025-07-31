<?php
session_start();
if (!isset($_SESSION['avaliador_id'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$avaliador_id = $_SESSION['avaliador_id'];
$trabalho_id = $_GET['trabalho_id'] ?? null;
if (!$trabalho_id) { echo "Trabalho inválido."; exit; }

// Verifica se já existe avaliação desse avaliador para esse trabalho
$sql = "SELECT id FROM Avaliacoes WHERE avaliador_id=? AND trabalho_id=?";
$stmt = sqlsrv_query($conn, $sql, array($avaliador_id, $trabalho_id));
$row = sqlsrv_fetch_array($stmt);
$avaliacao_id = $row ? $row['id'] : null;

// Busca critérios
$sql = "SELECT * FROM Criterios";
$res_criterios = sqlsrv_query($conn, $sql);

// Se for reavaliar, busca notas existentes
$notas_existentes = [];
if ($avaliacao_id) {
    $sql = "SELECT criterio_id, nota FROM Notas WHERE avaliacao_id=?";
    $res_notas = sqlsrv_query($conn, $sql, array($avaliacao_id));
    while($n = sqlsrv_fetch_array($res_notas)) {
        $notas_existentes[$n['criterio_id']] = $n['nota'];
    }
}

// Processa envio do formulário
if ($_POST) {
    if (!$avaliacao_id) {
        // Cria nova avaliação
        $sql = "INSERT INTO Avaliacoes (avaliador_id, trabalho_id, data_avaliacao) OUTPUT INSERTED.id VALUES (?, ?, GETDATE())";
        $stmt = sqlsrv_query($conn, $sql, array($avaliador_id, $trabalho_id));
        if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }
        $row = sqlsrv_fetch_array($stmt);
        $avaliacao_id = $row['id'];
        } else {
        // Remove notas antigas (para reavaliar)
        sqlsrv_query($conn, "DELETE FROM Notas WHERE avaliacao_id=?", array($avaliacao_id));
    }

    // Salva as notas novas
    foreach ($_POST['nota'] as $criterio_id => $nota) {
        $sql = "INSERT INTO Notas (avaliacao_id, criterio_id, nota) VALUES (?, ?, ?)";
        sqlsrv_query($conn, $sql, array($avaliacao_id, $criterio_id, $nota));
    }

    header("Location: avaliacao.php"); // Ou outra tela, como resumo
    exit;
}

// Busca título do trabalho
$sql = "SELECT titulo FROM Trabalhos WHERE id=?";
$res_t = sqlsrv_query($conn, $sql, array($trabalho_id));
$trabalho = sqlsrv_fetch_array($res_t);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Avaliar Trabalho</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css"> <!-- seu CSS -->
</head>
<body class="container py-4">

<div class="text-center mb-4">
    <img src="imagens/logo.png" alt="Logo" style="max-width: 200px; height: auto;">
</div>

<h2 class="text-principal text-center mb-3"><?= htmlspecialchars($trabalho['titulo']) ?></h2>
<p class="text-center text-muted mb-4"><?= $avaliacao_id ? "Você já avaliou este trabalho, pode reavaliar abaixo." : "Atribua notas aos critérios:" ?></p>

<form method="post">
<div class="row g-3 mb-4">
<?php while($c = sqlsrv_fetch_array($res_criterios)) { 
    $criterio_id = $c['id'];
    $nota_antiga = $notas_existentes[$criterio_id] ?? '';
?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($c['descricao']) ?></h5>
                <p class="card-text text-muted">Pontuação máxima: <?= $c['pontuacao_maxima'] ?></p>
                <input type="number" name="nota[<?= $criterio_id ?>]" class="form-control"
                       max="<?= $c['pontuacao_maxima'] ?>" min="0" required value="<?= htmlspecialchars($nota_antiga) ?>">
            </div>
        </div>
    </div>
<?php } ?>
</div>

<div class="text-center">
    <button type="submit" class="btn btn-principal btn-lg">Salvar Avaliação</button>
</div>
</form>

<div class="text-center mt-3">
    <a href="avaliacao.php" class="btn btn-secondary">← Voltar</a>
</div>

</body>
</html>