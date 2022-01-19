<!DOCTYPE HTML>
<?php

session_start();

if((!isset ($_SESSION['sLogin']) == true))
{
    unset($_SESSION['sLogin']);
    unset($_SESSION['user']);
    header('location:../index.php');
    }
 
$logado = $_SESSION['user'];

include("conexao.php");
$horario = $_POST['horario'];

if(isset($_POST['seg'])) {
    $segunda = 1;
} else {
    $segunda = 0;
}
if(isset($_POST['ter'])) {
    $terca = 1;
} else {
    $terca = 0;
}
if(isset($_POST['qua'])) {
    $quarta = 1;
} else {
    $quarta = 0;
}
if(isset($_POST['qui'])) {
    $quinta = 1;
} else {
    $quinta = 0;
}
if(isset($_POST['sex'])) {
    $sexta = 1;
} else {
    $sexta = 0;
}
if(isset($_POST['sab'])) {
    $sabado = 1;
} else {
    $sabado = 0;
} if(isset($_POST['dom'])) {
    $domingo = 1;
} else {
    $domingo = 0;
}
if(!empty($_POST['duracao'])) {
    $duracao = $_POST['duracao'];
} else {
    $duracao = 3;
}

if(isset($_POST['fir']) && isset($_POST['sec'])) {
    $sirene = 3;
} else if(isset($_POST['fir']) && !isset($_POST['sec'])){
    $sirene = 1;
} else if(!isset($_POST['fir']) && isset($_POST['sec'])){
    $sirene = 2;
} else {
    $sirene = 3;
}

$hora = substr($horario, -5, -3);
$minuto = substr($horario, -2);

?>

<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Horários Sirene - Editar </title>
        <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
        <link href="../css/login.css" rel="stylesheet">
        <link href="../css/admin.css" rel="stylesheet">

        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/js/bootstrap.min.js"></script>
        <script src="//code.jquery.com/jquery-1.11.1.min.js"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
        
    </head>

    <body>
        <div id="throbber" style="display:none; min-height:120px;"></div>
    <div id="noty-holder"></div>
    <div id="wrapper">
        <!-- Navegação -->
        <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="../admin.php">
                    <img src="../img/logo.jpg" width="400px" alt="Logo" style="padding:10px">
                </a>
            </div>
            <!-- Top Menu Items -->
            <ul class="nav navbar-right top-nav">
                <li><a href="#" data-placement="bottom" data-toggle="tooltip" href="#" data-original-title="Stats"><i class="fa fa-bar-chart-o"></i>
                    </a>
                </li>            
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $logado ?><b class="fa fa-angle-down"></b></a>
                    <ul class="dropdown-menu">
                        <li><a href="#"><i class="fa fa-fw fa-user"></i> Edit Profile</a></li>
                        <li><a href="#"><i class="fa fa-fw fa-cog"></i> Change Password</a></li>
                        <li class="divider"></li>
                        <li><a href="sair.php"><i class="fa fa-fw fa-power-off"></i> Sair</a></li>
                    </ul>
                </li>
            </ul>
            <!-- Sidebar Menu Items - These collapse to the responsive navigation menu on small screens -->
            <div class="collapse navbar-collapse navbar-ex1-collapse" style="color:white">
                <ul class="nav navbar-nav side-nav">
                    <!--<li>
                        <a href="#" data-toggle="collapse" data-target="#submenu-1" style="color:white"><i class="fa fa-fw fa-globe"></i>  Bloqueio de Internet</a>
                    <ul id="submenu-1" class="collapse">
                        <li><a href="../bloqueionet/bloqueio.php"><i class="fa fa-angle-double-right"></i> Bloqueio</a></li>
                        <li><a href="../registros.php"><i class="fa fa-angle-double-right"></i> Registro de Acesso</a></li>
                    </ul>
                    </li>-->
                    <li>
                        <a href="#" data-toggle="collapse" data-target="#submenu-2" style="color:white"><i class="fa fa-fw fa-bell"></i>  Sirene</a>
                    </li>
                    <li>
                        <a href="configuracoes.php" data-toggle="collapse" data-target="#submenu-2" style="color:white"><i class="fa fa-fw fa-cog"></i>  Configurar Arduino</a>
                    </li>
                    <!--<li>
                        <a href="../controleinteressados/cursos.php" style="color:white"><i class="fa fa-fw fa-group"></i> Controle de Interessados</a>
                    </li>
                    <li>
                        <a href="../AuditPrinter/dashboard.php" style="color:white"><i class="fa fa-fw fa fa-print"></i> AuditPrinter</a>
                    </li>
                    -->
                </ul>
            </div>
        </nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <!-- Page Heading -->
                <div class="row" id="main" >
                   <div class="col-sm-12 col-md-12 well" id="content">
                        <h2>Horários da Sirene</h2>
                            <?php
                               try{        
                                $sql = "INSERT INTO horarioSirene (hora, minuto, segunda, terca, quarta, quinta, sexta, sabado, domingo, duracao, sirene) VALUES ('$hora', '$minuto', '$segunda', '$terca', '$quarta', '$quinta', '$sexta', '$sabado', '$domingo', '$duracao', '$sirene')";
                                $conn->exec($sql);
                            } catch(PDOException $e) {
                                echo $sql . "<br>" . $e->getMessage();
                            }
                            $conn = null;

                            ?>
                       Horário salvo.
                        <a href=index.php><button type="button" class="btn btn-danger">Voltar</button></a>
                    </div>
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /#page-wrapper -->
    </div><!-- /#wrapper -->  

    </body>
    
</html>