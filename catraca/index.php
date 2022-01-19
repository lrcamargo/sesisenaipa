<!DOCTYPE HTML>
<?php
    session_start();

    $acao = $_GET['action'];

    if((!isset ($_SESSION['sLogin']) == true))
    {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        header('location:../index.php');
        }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];

    include ("conexao.php");

?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>AcessoNet </title>
        <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
        <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
        <link href="../css/login.css" rel="stylesheet">
        <link href="../css/admin.css" rel="stylesheet">

        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/js/bootstrap.min.js"></script>
        <script src="//code.jquery.com/jquery-1.11.1.min.js"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.dataTables.min.css">
        
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
                    <?php
                        include("../menu.php");
                    ?>
                </ul>
            </div>
        </nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <!-- Page Heading -->
                <div class="row" id="main" >
                   <div class="col-sm-12 col-md-12 well" id="content">
                        <h2>Alterar Data Final</h2>
                        <div id="response">
                            <?php
                                if($acao == 1) {
                                    echo "<div class='alert alert-success' role='alert'>";
                                    echo "Data final alterada com sucesso.";
                                    echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                                    echo "<span aria-hidden='true'>&times;</span>";
                                    echo "</button>";
                                    echo "</div>";
                                } else if($acao == 2) {
                                    echo "<div class='alert alert-danger' role='alert'>";
                                    echo "Erro ao alterar data.";
                                    echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                                    echo "<span aria-hidden='true'>&times;</span>";
                                    echo "</button>";
                                    echo "</div>";
                                }    
                            ?>
                        </div>
                        <br/>
                        <form class="col-sm-2 col-md-2" method="post" action="altera.php">
                            <select class="form-control" name="turma" id="turma" onchange="myFunction()">
                            <option value="" disabled selected>Turma</option>
                                <?php
                                    try {
                                        $query = $conn->prepare("SELECT * FROM niveis");
                                                                
                                        $query->execute();
                                
                                        $niveisData = $query->fetchAll();
                                        foreach ($niveisData as $query) {
                                            echo "<option value='".$query['id']."'>".$query['descricao']."</option>";
                                        }
                                        
                                    } catch (PDOException $e) {
                                            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
                                    }
                                ?>
                            </select>
                            <br/>
                            <br/>
                            <input type="date" id="dataFim" class="form-control" name="dataFim">
                            <br/>
                            <button class="btn btn-primary" type="submit">Alterar</button>
                            <br/>
                        </form>
                       <br/>
                        
                    </div>
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /#page-wrapper -->
    </div><!-- /#wrapper -->  
        

    <script>
        function myFunction() {
            var id = document.getElementById("turma").value;
            if($.trim(id) != '') {
                $.post("search.php", {id: id}, function(data) {
                    $("input#dataFim").val(data);
                });
            }
        }
    </script>
    

    </body>
    
</html>