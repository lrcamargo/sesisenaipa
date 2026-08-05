<?php
session_start();
if (!isset($_SESSION['avaliador_id'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

$avaliador_id = $_SESSION['avaliador_id'];

// Buscar todos os trabalhos que o avaliador pode avaliar
$stmt = $pdo->prepare("
    SELECT t.id, t.titulo, t.turma
    FROM Trabalhos t
    JOIN AvaliadorTrabalho at ON at.trabalho_id = t.id
    WHERE at.avaliador_id = ?
");
$stmt->execute([$avaliador_id]);
$trabalhos = $stmt->fetchAll();

// Buscar avaliações já feitas
$stmtAval = $pdo->prepare("SELECT trabalho_id FROM Avaliacoes WHERE avaliador_id = ?");
$stmtAval->execute([$avaliador_id]);
$avaliados = $stmtAval->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Escolher Trabalho para Avaliar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="container py-4">

<div class="text-center mb-4">
    <img src="imagens/logo.png" alt="Logo da Empresa" style="max-width: 200px; height: auto;">
</div>

<h2 class="text-principal text-center mb-4">Selecione um trabalho para avaliar</h2>

<div class="row g-3">
<?php foreach ($trabalhos as $row):
    $ja_avaliado = in_array($row['id'], $avaliados);
?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 <?= $ja_avaliado ? 'border-success' : '' ?>" style="<?= $ja_avaliado ? 'background-color: #d4edda;' : '' ?>">
            <div class="card-body d-flex flex-column justify-content-between">
                <h5 class="card-title text-principal"><?= htmlspecialchars($row['titulo']) ?></h5>
                <p class="card-text text-muted">Turma: <?= htmlspecialchars($row['turma']) ?></p>
                <a href="avaliar.php?trabalho_id=<?= $row['id'] ?>" class="btn btn-principal mt-auto"><?= $ja_avaliado ? "Reavaliar" : "Avaliar" ?>
                </a>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>