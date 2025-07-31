<?php
session_start();
if (!isset($_SESSION['is_gestor'])) {
    header('Location: index.php');
    exit;
}
include 'includes/db.php';

// Processa zerar
if ($_POST && isset($_POST['zerar'])) {
    if (!empty($_POST['tabelas'])) {
        foreach ($_POST['tabelas'] as $tabela) {
            switch ($tabela) {
                case 'avaliacoes':
                    sqlsrv_query($conn, "DELETE FROM Notas");
                    sqlsrv_query($conn, "DELETE FROM Avaliacoes");
                    break;
                case 'trabalhos':
                    sqlsrv_query($conn, "DELETE FROM Membros");
                    sqlsrv_query($conn, "DELETE FROM Trabalhos");
                    break;
                case 'criterios':
                    sqlsrv_query($conn, "DELETE FROM Criterios");
                    break;
                case 'avaliadores':
                    sqlsrv_query($conn, "DELETE FROM AvaliadorTrabalho");
                    sqlsrv_query($conn, "DELETE FROM Avaliadores");
                    break;
            }
        }
        $msg = "Tabelas selecionadas foram zeradas com sucesso!";
    } else {
        $msg = "Nenhuma tabela selecionada.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Área do Gestor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <meta http-equiv="refresh" content="15"> <!-- atualiza a cada 15s -->
</head>
<body class="container mt-5">

<h2>Área do Gestor</h2>

<!-- Tabs Bootstrap -->
<ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="ranking-tab" data-bs-toggle="tab" data-bs-target="#ranking" type="button" role="tab">Ranking</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="cadastro-tab" data-bs-toggle="tab" data-bs-target="#cadastro" type="button" role="tab">Cadastros</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="zerar-tab" data-bs-toggle="tab" data-bs-target="#zerar" type="button" role="tab">Zerar Dados</button>
  </li>
</ul>

<div class="tab-content" id="myTabContent">
  <!-- Ranking -->
  <div class="tab-pane fade show active" id="ranking" role="tabpanel">
    <?php if (isset($msg)) echo "<div class='alert alert-info mt-2'>$msg</div>"; ?>
    <table class="table mt-3">
    <tr><th>Trabalho</th><th>Total de Pontos</th></tr>
    <?php
    $sql = "SELECT t.titulo, SUM(n.nota) as total 
            FROM Trabalhos t
            JOIN Avaliacoes a ON a.trabalho_id = t.id
            JOIN Notas n ON n.avaliacao_id = a.id
            GROUP BY t.titulo
            ORDER BY total DESC";
    $res = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($res)) {
        echo "<tr><td>{$row['titulo']}</td><td>{$row['total']}</td></tr>";
    }
    ?>
    </table>
    <a href="ranking_detalhado.php" class="btn btn-principal">Ver notas detalhadas</a>
  </div>

  <!-- Cadastros -->
  <div class="tab-pane fade" id="cadastro" role="tabpanel">
    <div class="mt-3">
      <a href="cadastro_trabalho.php" class="btn btn-secundaria me-2">Cadastrar Trabalhos</a>
      <a href="cadastro_criterio.php" class="btn btn-secundaria me-2">Cadastrar Critérios</a>
      <a href="cadastro_avaliador.php" class="btn btn-secundaria">Cadastrar Avaliadores</a>
    </div>
</BR>
    <h5>Trabalhos cadastrados</h5>
<table class="table table-sm">
<tr><th>Título</th><th>Turma</th><th>Ações</th></tr>
<?php
$res = sqlsrv_query($conn, "SELECT id, titulo, turma FROM Trabalhos");
while ($row = sqlsrv_fetch_array($res)) {
    $i=1;
    echo "<tr>
        
        <td>{$row['titulo']}</td>
        <td>{$row['turma']}</td>
        <td>
            <a href='editar_trabalho.php?id={$row['id']}' class='btn btn-sm btn-principal me-1'>Editar</a>
            <a href='excluir_trabalho.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(Tem certeza?);'>Excluir</a>
        </td>
    </tr>";
}
?>
</table>

<h5>Critérios cadastrados</h5>
<table class="table table-sm">
<tr><th>Descrição</th><th>Pontuação máxima</th><th>Ações</th></tr>
<?php
$res = sqlsrv_query($conn, "SELECT id, descricao, pontuacao_maxima FROM Criterios");
while ($row = sqlsrv_fetch_array($res)) {
    echo "<tr>
        <td>{$row['descricao']}</td>
        <td>{$row['pontuacao_maxima']}</td>
        <td>
            <a href='editar_criterio.php?id={$row['id']}' class='btn btn-sm btn-principal'>Editar</a>
            <a href='excluir_criterio.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(Tem certeza?);'>Excluir</a>
        </td>
    </tr>";
}
?>
</table>

<h5>Avaliadores cadastrados</h5>
<table class="table table-sm">
<tr><th>Nome</th><th>Email</th><th>Ações</th></tr>
<?php
$res = sqlsrv_query($conn, "SELECT id, nome, email FROM Avaliadores");
while ($row = sqlsrv_fetch_array($res)) {
    echo "<tr>
        <td>{$row['nome']}</td>
        <td>{$row['email']}</td>
        <td>
            <a href='editar_avaliador.php?id={$row['id']}' class='btn btn-sm btn-principal'>Editar</a>
            <a href='excluir_avaliador.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(Tem certeza?);'>Excluir</a>
        </td>
    </tr>";
}
?>
</table>

</div>
  </div>

  <!-- Zerar Dados -->
  <div class="tab-pane fade" id="zerar" role="tabpanel">
    <form method="post" onsubmit="return confirm('Tem certeza que deseja apagar os dados selecionados?');" class="mt-3">
        <p>Selecione o que deseja zerar:</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="tabelas[]" value="avaliacoes" id="avaliacoes">
            <label class="form-check-label" for="avaliacoes">Avaliações e Notas</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="tabelas[]" value="trabalhos" id="trabalhos">
            <label class="form-check-label" for="trabalhos">Trabalhos e Membros</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="tabelas[]" value="criterios" id="criterios">
            <label class="form-check-label" for="criterios">Critérios</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="tabelas[]" value="avaliadores" id="avaliadores">
            <label class="form-check-label" for="avaliadores">Avaliadores e permissões</label>
        </div>
        <button type="submit" name="zerar" class="btn btn-danger mt-2">Zerar selecionados</button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>