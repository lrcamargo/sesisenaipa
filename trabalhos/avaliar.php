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
$stmt = $pdo->prepare("SELECT id FROM Avaliacoes WHERE avaliador_id = ? AND trabalho_id = ?");
$stmt->execute([$avaliador_id, $trabalho_id]);
$row = $stmt->fetch();
$avaliacao_id = $row ? $row['id'] : null;

// Busca critérios
$criterios = $pdo->query("SELECT * FROM Criterios")->fetchAll();

// Se for reavaliar, busca notas existentes
$notas_existentes = [];
if ($avaliacao_id) {
    $stmtNotas = $pdo->prepare("SELECT criterio_id, nota FROM Notas WHERE avaliacao_id = ?");
    $stmtNotas->execute([$avaliacao_id]);
    foreach ($stmtNotas as $n) {
        $notas_existentes[$n['criterio_id']] = $n['nota'];
    }
}

// Processa envio do formulário
if ($_POST) {
    if (!$avaliacao_id) {
        // Cria nova avaliação
        $stmtIns = $pdo->prepare("INSERT INTO Avaliacoes (avaliador_id, trabalho_id, data_avaliacao) VALUES (?, ?, NOW())");
        $stmtIns->execute([$avaliador_id, $trabalho_id]);
        $avaliacao_id = $pdo->lastInsertId();
    } else {
        // Remove notas antigas (para reavaliar)
        $stmtDel = $pdo->prepare("DELETE FROM Notas WHERE avaliacao_id = ?");
        $stmtDel->execute([$avaliacao_id]);
    }

    // Salva as notas novas
    $stmtNota = $pdo->prepare("INSERT INTO Notas (avaliacao_id, criterio_id, nota) VALUES (?, ?, ?)");
    foreach ($_POST['nota'] as $criterio_id => $nota) {
        $stmtNota->execute([$avaliacao_id, $criterio_id, $nota]);
    }

    header("Location: avaliacao.php");
    exit;
}

// Busca título do trabalho
$stmtT = $pdo->prepare("SELECT titulo FROM Trabalhos WHERE id = ?");
$stmtT->execute([$trabalho_id]);
$trabalho = $stmtT->fetch();
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
<?php foreach ($criterios as $c):
    $criterio_id = $c['id'];
    $nota_antiga = $notas_existentes[$criterio_id] ?? '';
?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($c['descricao']) ?></h5>
                <p class="card-text text-muted">Pontuação máxima: <?= $c['pontuacao_maxima'] ?></p>
                <input type="number" name="nota[<?= $criterio_id ?>]" class="form-control"
                       max="<?= $c['pontuacao_maxima'] ?>" min="0" step="0.01" required value="<?= htmlspecialchars($nota_antiga) ?>">
            </div>
        </div>
    </div>
<?php endforeach; ?>
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