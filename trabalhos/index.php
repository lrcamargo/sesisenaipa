<?php
session_start();
include 'includes/db.php';

// Definir senha do gestor (troque para algo seguro depois)
$senhaGestor = '1234';

if ($_POST) {
    $usuario = $_POST['login'];
    $senha = $_POST['senha'];

    if ($usuario === 'gestor' && $senha === $senhaGestor) {
        // Login gestor
        $_SESSION['is_gestor'] = true;
        header('Location: ranking.php');
        exit;
    } else {
        // Login avaliador (por nome ou e-mail)
        $sql = "SELECT id, senha_hash FROM Avaliadores WHERE email=? OR nome=?";
        $stmt = sqlsrv_query($conn, $sql, array($usuario, $usuario));
        if ($stmt && ($row = sqlsrv_fetch_array($stmt))) {
            if (password_verify($senha, $row['senha_hash'])) {
                $_SESSION['avaliador_id'] = $row['id'];
                header('Location: avaliacao.php');
                exit;
            }
        }
        $erro = "Usuário ou senha inválidos";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(to right, rgb(22,65,148), rgb(127,175,211));
            color: white;
        }
        .card {
            max-width: 400px;
            margin: auto;
            margin-top: 10%;
            padding: 2rem;
            border-radius: 1rem;
            background-color: rgba(255,255,255,0.9);
            color: #333;
        }
        .logo {
            display: block;
            margin: 0 auto 1rem auto;
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>
<div class="card text-center">
    <img src="imagens/logo.png" class="logo" alt="Logo Empresa">
    <h3 class="mb-4">Acesso ao Sistema</h3>
    <?php if($msg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <input type="text" name="login" class="form-control" placeholder="Nome ou E-mail" required>
        </div>
        <div class="mb-3">
            <input type="password" name="senha" class="form-control" placeholder="Senha" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
</div>
</body>
</html>
