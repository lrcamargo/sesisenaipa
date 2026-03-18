<?php
session_start();
require_once "conexao.php";

if(!isset($_SESSION['id'])){
    header("Location: index.php");
    exit;
}

$logado = $_SESSION['user'] ?? "";
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Trocar Senha</title>

<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<style>

.senha-container{
width:100%;
display:flex;
justify-content:center;
margin-top:120px;
}

.senha-card{
background:white;
padding:40px;
border-radius:8px;
width:400px;
box-shadow:0 0 10px rgba(0,0,0,0.1);
}

.senha-card h3{
text-align:center;
margin-bottom:25px;
}

.senha-card input{
width:100%;
padding:10px;
margin-bottom:15px;
border:1px solid #ccc;
border-radius:4px;
}

.senha-card button{
width:100%;
padding:12px;
background:#2c7be5;
border:none;
color:white;
border-radius:4px;
cursor:pointer;
font-weight:bold;
}

.senha-card button:hover{
background:#1a5fd0;
}

</style>

</head>

<body>

<div class="wrapper">

<!-- Cabeçalho -->
<div class="header">
    <div class="header-menu">
        <div class="title"><img src="img/logo_white.svg"></div>

        <ul>
            <li><a href="#" class="user"><?php echo $logado; ?></a></li>
            <li><a href="sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
        </ul>
    </div>
</div>

<!-- Conteúdo -->
<div class="main-container">

<div class="senha-container">

<div class="senha-card">

<h3>Troque sua senha</h3>

<form action="salvarNovaSenha.php" method="POST">

<input type="password" name="senha1" placeholder="Nova senha" required>

<input type="password" name="senha2" placeholder="Repita a nova senha" required>

<button type="submit">Alterar Senha</button>

</form>

</div>

</div>

</div>

</div>

</body>
</html>