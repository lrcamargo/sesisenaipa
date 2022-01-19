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
        header('location:../index.php');    
    }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Monitoramento de Energia</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>

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
                        <li><a href="#" class="logout"><i class="fas fa-power-off"></i></a></li>
                    </ul>
                </div>
            </div>
        <!-- Fim Cabeçalho -->
        <!--Inicio sidebar-->
            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('../menu.php'); ?>
                </div>
            </div>
        <!--Fim sidebar-->
        <!--Inicio conteúdo-->
            <div class="main-container">
                <div class="buttondiv" style="float: right">
                    <?php
                        if($logado == 'administrator') {
                            echo "<a href='../energia/tabela.php'><button type='button' class='btn-bootstrap btn-bootstrap-blue'><i class='fas fa-table'></i> Visualizar em Tabela</button></a>";
                        }
                    ?>
                </div>
                <br/>
                <div style="width:50%; float: left">
                    <canvas id="cosphimulti"></canvas>
                </div>
                <div style="width:50%; float: right">
                    <canvas id="potativamulti"></canvas>
                </div>
                <br/><br/>
                <div style="width:50%; float: left">
                    <canvas id="potreatmulti"></canvas>
                </div>
                <div style="width:50%; float: right">
                    <canvas id="frequencia"></canvas>
                </div>
                <br/><br/>
                <div style="width:50%; float: left">
                    <canvas id="angulo"></canvas>
                </div>
            </div>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
        <script src="../js/energiadia.js"></script>
    </body>
</html>