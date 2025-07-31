<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    session_start();
    
    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:index.php');    
    }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];
    if((!isset ($_SESSION['obs']) == true)) {
        $obs = 0;   
    } else {
        $obs = $_SESSION['obs'];
    }
    

    include('functions.php');
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Tela principal</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
    </head>
    <body>
        <!-- Início Wrapper -->
        <div class="wrapper">
        <!-- Início Cabeçalho -->
            <div class="header">
                <div class="header-menu">
                    <div class="title"><img src="../img/logo_white.svg"></div>
                    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
                    <ul>
                        <li><a href="#" class="user"><?php echo $logado; ?></a></li>
                        <li><a href="sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
                    </ul>
                </div>
            </div>
        <!-- Fim Cabeçalho -->
        <!--Inicio sidebar-->
            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('menu.php'); ?>
                </div>
            </div>
        <!--Fim sidebar-->
        <!--Inicio conteúdo-->
            <div class="main-container">
                <?php 
                    if($nivel == 0) {
                        if($obs == 1) {
                            include('resetPassContainer.php');
                        } else {
                            include('alunoContainer.php');
                        }
                    }
                    else if ($nivel == 1) {

                    } else if ($nivel == 2) {
                        //include('./atividades/professor.php');
                    }  else if ($nivel == 3) {
                        include('supTecnicaContainer.php');
                    }  else if ($nivel == 4) {
                        include('supPedContainer.php');
                    }  else if ($nivel == 5) {
                        
                    }  else if ($nivel == 6) {
                        include('gerContainer.php');
                    }  else if ($nivel == 7) {
                        include('secContainer.php');
                    }  else if ($nivel == 8) {
                        
                    }  else if ($nivel == 9) {
                        include('adminContainer.php');
                    } else if($nivel == 0) {
                        
                    }
                ?>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>