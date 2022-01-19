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

    if((!isset($_GET['acao']))) {
        $acao = 0;
    } else {
        $acao = $_GET['acao'];
    }

    include('functions.php');
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Telefones</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        
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
                        <li><a href="#" class="logout"><i class="fas fa-power-off"></i></a></li>
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
            <a href="http://192.168.254.14/" target="_blank" rel="noopener noreferrer"><button type="button" class="btn btn-purple"><i class="fas fa-external-link-alt"></i>></i> Abrir central PABX</button></a>
            <a href="resetAta.php"><button type="button" class="btn btn-blue"><i class="fas fa-sync-alt"></i> Resetar ATAs</button></a>
            <?php
                
                if ($acao == 1){
                    echo "<div style='color: green;'>Enviado sinal de reboot para os equipamentos com sucesso.</div>";
                }
            ?>
            <br/><br/>
            <div class="card-container">
                <div class="card">
                    <h3>Troncos</h3>
                    <span class="lista">
                        <ul>
                            <?php 
                                $troncos = array();
                                $troncos = troncosStatus();
                                foreach($troncos as $troncos) {
                                    echo "<li><span class='ondot'></span>" . $troncos . "</li>";
                                }
                            ?>
                        </ul>
                    </span>
                </div>
                    
                <div class="card lista">
                    
                    <h3>Ramais</h3>
                    <span class="lista">
                    <ul>
                        <?php 
                            $ramais = array();
                            $ramais = ramaisStatus();
                            
                            foreach($ramais["online"] as $online) {
                                echo "<li><span class='ondot'></span>" . $online . "</li>";
                            }

                            foreach($ramais["offline"] as $offline) {
                                if($offline != "701") {
                                    if($offline != "702") {
                                        echo "<li><span class='offdot'></span>" . $offline . "</li>";
                                    }
                                }
                            }
                            
                        ?>
                    </ul>
                    </span>
                </div>
            </div>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>