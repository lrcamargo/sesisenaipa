<?php
session_start();
if (!isset($_SESSION['is_gestor'])) { header('Location: index.php'); exit; }
include 'includes/db.php';

$modo = ($_GET['modo'] ?? 'geral') === 'area' ? 'area' : 'geral';

$medalha = ['#FFD700', '#C0C0C0', '#CD7F32']; // ouro, prata, bronze

$top3Geral = [];
$porArea = [];

if ($modo === 'geral') {
    $sql = "SELECT t.id, t.titulo, t.turma, t.area, SUM(n.nota) AS total
            FROM Trabalhos t
            JOIN Avaliacoes a ON a.trabalho_id = t.id
            JOIN Notas n ON n.avaliacao_id = a.id
            GROUP BY t.id, t.titulo, t.turma, t.area
            ORDER BY total DESC
            LIMIT 3";
    $top3Geral = $pdo->query($sql)->fetchAll();
} else {
    // Top 3 dentro de cada área, usando ROW_NUMBER (MySQL 8+ / MariaDB 10.2+)
    $sql = "SELECT id, titulo, turma, area, total, posicao FROM (
                SELECT t.id, t.titulo, t.turma, t.area, SUM(n.nota) AS total,
                       ROW_NUMBER() OVER (PARTITION BY t.area ORDER BY SUM(n.nota) DESC) AS posicao
                FROM Trabalhos t
                JOIN Avaliacoes a ON a.trabalho_id = t.id
                JOIN Notas n ON n.avaliacao_id = a.id
                GROUP BY t.id, t.titulo, t.turma, t.area
            ) x
            WHERE posicao <= 3
            ORDER BY area, posicao";
    $linhas = $pdo->query($sql)->fetchAll();
    foreach ($linhas as $row) {
        $chaveArea = $row['area'] ?? 'Sem área definida';
        $porArea[$chaveArea][] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pódio - Top 3</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body { background: linear-gradient(to bottom, rgb(22,65,148), rgb(127,175,211)); min-height: 100vh; }
        .top-bar { background: rgba(255,255,255,.95); }
        .podio-area-titulo {
            color: #fff; text-align: center; font-weight: 700; margin: 30px 0 16px;
            text-shadow: 0 2px 4px rgba(0,0,0,.3);
        }
        .podio-wrapper { display: flex; align-items: flex-end; justify-content: center; gap: 16px; flex-wrap: wrap; margin-bottom: 30px; }
        .podio-card {
            background: #fff; border-radius: 12px; padding: 20px; text-align: center;
            width: 220px; box-shadow: 0 6px 18px rgba(0,0,0,.25);
        }
        .podio-card.posicao-1 { order: 2; transform: scale(1.08); }
        .podio-card.posicao-2 { order: 1; }
        .podio-card.posicao-3 { order: 3; }
        .podio-medalha {
            width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 10px; font-weight: 800; font-size: 1.3rem; color: #fff;
        }
        .podio-titulo { font-weight: 700; font-size: 1.05rem; margin-bottom: 4px; }
        .podio-turma { font-size: .82rem; color: #6c757d; }
        .podio-pontos { font-size: 1.3rem; font-weight: 800; color: rgb(22,65,148); margin-top: 8px; }
        .sem-dados-podio { color: #fff; text-align: center; padding: 40px; font-size: 1.1rem; }
    </style>
</head>
<body>
<div class="top-bar py-3 mb-4 shadow-sm">
    <div class="container d-flex justify-content-between align-items-center flex-wrap" style="gap:12px">
        <h3 class="mb-0 text-principal"><i class="bi bi-trophy-fill"></i> Pódio</h3>
        <div class="btn-group">
            <a href="podio.php?modo=geral" class="btn btn-<?= $modo === 'geral' ? 'principal' : 'outline-secondary' ?>">Geral</a>
            <a href="podio.php?modo=area" class="btn btn-<?= $modo === 'area' ? 'principal' : 'outline-secondary' ?>">Por área</a>
        </div>
        <a href="ranking.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
</div>

<div class="container">

<?php
function renderPodio(array $trabalhos, array $medalha): void {
    if (empty($trabalhos)) {
        echo '<div class="sem-dados-podio">Ainda não há notas suficientes para montar o pódio.</div>';
        return;
    }
    echo '<div class="podio-wrapper">';
    foreach ($trabalhos as $i => $t) {
        $pos = $i + 1;
        $cor = $medalha[$i] ?? '#adb5bd';
        echo '<div class="podio-card posicao-' . $pos . '">';
        echo '<div class="podio-medalha" style="background:' . $cor . '">' . $pos . 'º</div>';
        echo '<div class="podio-titulo">' . htmlspecialchars($t['titulo']) . '</div>';
        echo '<div class="podio-turma">Turma: ' . htmlspecialchars($t['turma']) . '</div>';
        echo '<div class="podio-pontos">' . htmlspecialchars((string) $t['total']) . ' pts</div>';
        echo '</div>';
    }
    echo '</div>';
}

if ($modo === 'geral') {
    renderPodio($top3Geral, $medalha);
} else {
    if (empty($porArea)) {
        echo '<div class="sem-dados-podio">Ainda não há notas suficientes para montar o pódio.</div>';
    } else {
        foreach ($porArea as $area => $trabalhosDaArea) {
            echo '<div class="podio-area-titulo"><i class="bi bi-tag-fill"></i> ' . htmlspecialchars($area) . '</div>';
            renderPodio($trabalhosDaArea, $medalha);
        }
    }
}
?>

</div>
</body>
</html>