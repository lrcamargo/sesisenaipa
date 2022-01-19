<!DOCTYPE html>
<?php
session_start();

if((!isset ($_SESSION['sLogin']) == true))
{
    unset($_SESSION['sLogin']);
    unset($_SESSION['user']);
    header('location:index.php');
    }
 
$logado = $_SESSION['user'];

$acao = $_GET['acao'];
?>

<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    <link href="../css/admin.css" rel="stylesheet">
      
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/js/bootstrap.min.js"></script>
    <script src="//code.jquery.com/jquery-1.11.1.min.js"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
    <style>
        .textIp {
            display: inline;
            width: 6%;
        }  
        .ipAtual {
            display: inline;
            width: 15%;
        }
        .portaAtual {
            display: inline;
            width: 7%;
        }
      
    </style>
    <title>Página Inicial - Administrador</title>
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
                <a class="navbar-brand" href="http://cijulenlinea.ucr.ac.cr/dev-users/">
                    <img src="../img/logo.jpg" width="400px" alt="Logo" style="padding:10px">
                </a>
            </div>
            <!-- Top Menu Items -->
            <ul class="nav navbar-right top-nav">        
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $logado ?><b class="fa fa-angle-down"></b></a>
                    <ul class="dropdown-menu">
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
                        <a href="index.php" data-toggle="collapse" data-target="#submenu-2" style="color:white"><i class="fa fa-fw fa-bell"></i>  Sirene</a>
                    </li>
                    <li>
                        <a href="configuracoes.php" data-toggle="collapse" data-target="#submenu-2" style="color:white"><i class="fa fa-fw fa-cog"></i>  Configurar Arduino</a>
                    </li>
                    
                </ul>
            </div>
        </nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <!-- Page Heading -->
                <div class="row" id="main" >
                    <!--<div class="col-sm-12 col-md-12 well" id="content">
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#novoCurso"><i class="fa fa-plus-square"></i> Novo Equipamento</button>
                    </div>
                    -->                
                    Alterar configurações de endereçamento:
                    <br>
                    <?php 
                            if ($acao == 1){
                                echo "<div style='color: green;'>IP Alterado</div>";
                            } else if($acao == 2) {
                                echo "<div style='color:red;'>Erro ao alterar IP.</div>";
                            } 
                    ?>
                    <form method="post" action="config.php">
                    IP Atual: <input type="text" class="form-control ipAtual" name="ipAtual">
                    Porta Atual: <input type="text" class="form-control portaAtual" name="portaAtual" maxlength="4">
                    <br/>
                    <br/>
                    Novo IP: <input type="text" class="form-control textIp" name="novo1" maxlength="3"> . <input type="text" class="form-control textIp" name="novo2" maxlength="3"> . <input type="text" class="form-control textIp" name="novo3" maxlength="3"> . <input type="text" class="form-control textIp" name="novo4" maxlength="3">
                    <br/>
                    <br/>
                    Máscara: <input type="text" class="form-control textIp" name="mascara1" maxlength="3"> . <input type="text" class="form-control textIp" name="mascara2" maxlength="3"> . <input type="text" class="form-control textIp" name="mascara3" maxlength="3"> . <input type="text" class="form-control textIp" name="mascara4" maxlength="3">
                    <br/>
                    <br/>
                    Gateway: <input type="text" class="form-control textIp" name="gtw1" maxlength="3"> . <input type="text" class="form-control textIp" name="gtw2" maxlength="3"> . <input type="text" class="form-control textIp" name="gtw3" maxlength="3"> . <input type="text" class="form-control textIp" name="gtw4" maxlength="3">
                    <br/>
                    <br/>
                    End.DNS: <input type="text" class="form-control textIp" name="dns1" maxlength="3"> . <input type="text" class="form-control textIp" name="dns2" maxlength="3"> . <input type="text" class="form-control textIp" name="dns3" maxlength="3"> . <input type="text" class="form-control textIp" name="dns4" maxlength="3">
                    <br/>
                    <br/>
                    Porta: <input type="text" class="form-control portaAtual" name="porta" maxlength="4">
                    <br/>
                    <br/>
                    <button type="submit" class="btn btn-success">Enviar</button>
                    </form>
                    
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /#page-wrapper -->
    </div><!-- /#wrapper -->  

  </body>
</html>