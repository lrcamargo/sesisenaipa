<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ranking Detalhado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<h2>Notas detalhadas</h2>
<table class="table">
<tr>
    <th>Trabalho</th><th>Avaliador</th><th>Critério</th><th>Nota</th>
</tr>
<?php
$sql = "SELECT t.titulo, a.nome AS avaliador, c.descricao, n.nota
        FROM Notas n
        JOIN Avaliacoes av ON n.avaliacao_id = av.id
        JOIN Trabalhos t ON av.trabalho_id = t.id
        JOIN Avaliadores a ON av.avaliador_id = a.id
        JOIN Criterios c ON n.criterio_id = c.id
        ORDER BY t.titulo, a.nome, c.descricao";
$stmt = $pdo->query($sql);
foreach ($stmt as $row) {
    echo "<tr>
            <td>" . htmlspecialchars($row['titulo']) . "</td>
            <td>" . htmlspecialchars($row['avaliador']) . "</td>
            <td>" . htmlspecialchars($row['descricao']) . "</td>
            <td>{$row['nota']}</td>
          </tr>";
}
?>
</table>
<a href="ranking.php">Voltar ao ranking</a>
</body>
</html>