<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    session_start();

    if(isset($_SESSION['sLogin'])){
        header("Location: main.php");
        exit;
    }
?>

<html>
    <head>
        <meta charset="UTF-8">
        <meta name="author" content="Letícia Rosa Camargo">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Página Inicial - SESI/SENAI Orlando Chiarini</title>

        <link rel="stylesheet" type="text/css" href="css/login.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">

        <script src="https://kit.fontawesome.com/a81368914c.js"></script>
    </head>
    <body>
        <img class="fundo" src="img/fundo.png">
        <div class="container">
            <div class="img">
                <img src="img/lateral.png">
            </div>
            <div class="login-container">
                <form action="login.php" method="POST">
                    <img class="logo" src="img/logo.svg">
                    <div class="input-div user">
                        <div class="i"><i class="fas fa-user"></i></div>
                        <div>
                            <h5>Usuário</h5>
                            <input class="input" type="text" name="user">
                        </div>
                    </div>
                    <div class="input-div pass">
                        <div class="i"><i class="fas fa-lock"></i></div>
                        <div>
                            <h5>Senha</h5>
                            <input class="input" type="password" name="pass">
                        </div>
                    </div>
                    <input type="submit" class="btn" value="Login">
                </form>
            </div>
        </div>
        <script type="text/javascript" src="js/login.js"></script>
    </body>
</html>
