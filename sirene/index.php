<!DOCTYPE HTML>
<?php
  
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];

    $acao = $_GET['acao'];

?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Horários Sirene </title>
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
                        <h2>Horários da Sirene</h2>
                        <table id="registros" class="table table-responsive table-striped table-hover" style="text-align:center;">
                        <thead class="thead-dark">
                            <tr>
                                <th>Hora</th>
                                <th>Minuto</th>
                                <th>Segunda</th>
                                <th>Terça</th>
                                <th>Quarta</th>
                                <th>Quinta</th>
                                <th>Sexta</th>
                                <th>Sábado</th>
                                <th>Domingo</th>
                                <th>Duração</th>
                                <th>Sirene</th>
                                <th>Editar</th>
                                <th>Apagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            include("conexao.php");
                        
                            try{
                                $sql = $conn->prepare("SELECT * from dbo.horarioSirene ORDER BY hora, minuto");
                                $sql->execute();
                                
                                $horarios = $sql->fetchAll();
                                foreach($horarios as $sql) {
                                    echo '<tr>';
                                    echo '<td>'.$sql['hora'].'</td>';
                                    echo '<td>'.$sql['minuto'].'</td>';
                                    if($sql['segunda'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['terca'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['quarta'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['quinta'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['sexta'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['sabado'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    if($sql['domingo'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i></td>";
                                    }
                                    
                                    echo "<td>".$sql['duracao']."</td>";
                                    
                                    //echo "<td>".$sql['sirene']."</td>";
                                    if($sql['sirene'] == 1) {
                                        echo "<td><i class='fa fa-check' style='color:green;'></i><i class='fa fa-times' style='color:red;'></i></td>";
                                    } else if($sql['sirene'] == 2) {
                                        echo "<td><i class='fa fa-times' style='color:red;'></i><i class='fa fa-check' style='color:green;'></i></td>";
                                    } else if($sql['sirene'] == 3) {
                                        echo "<td><i class='fa fa-check' style='color:green;'><i class='fa fa-check' style='color:green;'></i></td>";
                                    }
                                
                                    echo "<td><button type='button' class='btn btn-primary view_data' data-toggle='modal' data-target='#editaHorario' data-id='".$sql['idHorario']."' data-hora = '".$sql['hora']."' data-minuto='".$sql['minuto']."' data-segunda='".$sql['segunda']."' data-terca='".$sql['terca']."' data-quarta='".$sql['quarta']."' data-quinta='".$sql['quinta']."' data-sexta='".$sql['sexta']."' data-sabado='".$sql['sabado']."' data-domingo='".$sql['domingo']."' data-duracao='".$sql['duracao']."' data-sirene='".$sql['sirene']."'><i class='fa fa-edit'></i></button></td>";
                                    echo "<td><a href='excluir.php?id=".$sql['idHorario']."'><button type='button' class='btn btn-danger'><i class='fa fa-trash'></i></button></a></td>";
                                    echo '</tr>';
                                    
                                }
                            } catch(PDOException $e) {
                                die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
                            }
                        ?>
                        </tbody>
                       </table>
                        
                        <br/>
                       <?php 
                            if ($acao == 1){
                                echo "<div style='color: green;'>Enviado para sirene.</div>";
                            } else if($acao == 2) {
                                echo "<div style='color:green;'>Horário editado.</div>";
                            } else if($acao == 3) {
                                echo "<div style='color:green;'>Horário excluído.</div>";
                            } else if($acao == 4) {
                                echo "<div style='color:green;'>Horário do servidor enviado para a sirene.</div>";
                            } else if($acao == 5) {
                                echo "<div style='color:red;'>Não foi possível conectar ao dispositivo.</div>";
                            }
                        ?>
                       <br/>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target=#addHorario>Adicionar Horário</button>
                       <a href="enviar.php"><button type="button" class="btn btn-danger">Enviar para sirene</button></a>
                       <a href="lista.php"><button type="button" class="btn btn-dark">Exibir</button></a>
                       <a href="hora.php"><button type="button" class="btn btn-warning">Hora</button></a>
                    </div>
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /#page-wrapper -->
    </div><!-- /#wrapper -->  
        <!--Modal Adicionar Horário-->
        <div id="addHorario" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Adicionar Horário</h4>
                    </div>
                    <form method="post" action="adicionar.php">
                        <div class="modal-body">
                        <b>Horário: </b><input type="time" name="horario" class="form-control">
                        <br/>
                        <b>Dia da semana: </b>
                        <br/>
                            <input type=checkbox name="seg" class="form-check-input"> Segunda
                            <br/>
                            <input type=checkbox name="ter" class="form-check-input"> Terça
                            <br/>
                            <input type=checkbox name="qua" class="form-check-input"> Quarta
                            <br/>
                            <input type=checkbox name="qui" class="form-check-input"> Quinta
                            <br/>
                            <input type=checkbox name="sex" class="form-check-input"> Sexta
                            <br/>
                            <input type=checkbox name="sab" class="form-check-input"> Sábado
                            <br/>
                            <input type="checkbox" name="dom" class="form-check-input"> Domingo
                            <br/>
                            <br/>
                        <b>Duração: </b><input type="text" name="duracao" class="form-control">
                        <br/>
                        <b>Sirene: </b>
                        <br/>
                            <input type=checkbox name="fir" class="form-check-input"> 1º andar
                            <br/>
                            <input type=checkbox name="sec" class="form-check-input"> 2º andar
                            <br/>    
                            
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Enviar</button>
                        </div>
                    </form>
                </div> 
            </div>            
        </div>
        <!--Modal Editar Horário-->
        <div id="editaHorario" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Editar Horário</h4>
                    </div>
                    <form method="post" action="editar.php">
                        <div class="modal-body">
                            <div class="form-inline" style="width:100%; margin-left:auto; margin-right:auto">
                                <b>Horário: </b>
                                <input type="text" name="hora" id="hora" class="form-control" style="width:50px"><b> : </b><input type="text" name="minuto" id="minuto" class="form-control" style="width:50px">
                            </div>
                        <br/>
                        <b>Dia da semana: </b>
                        <br/>
                            <input type=checkbox name="seg" id="seg" class="form-check-input"> Segunda
                            <br/>
                            <input type=checkbox name="ter" id="ter" class="form-check-input"> Terça
                            <br/>
                            <input type=checkbox name="qua" id="qua" class="form-check-input"> Quarta
                            <br/>
                            <input type=checkbox name="qui" id="qui" class="form-check-input"> Quinta
                            <br/>
                            <input type=checkbox name="sex" id="sex" class="form-check-input"> Sexta
                            <br/>
                            <input type=checkbox name="sab" id="sab" class="form-check-input"> Sábado
                            <br/>
                            <input type=checkbox name="dom" id="dom" class="form-check-input"> Domingo
                            <br/>
                            <br/>
                            <b>Duração: </b><input type="text" name="duracao" class="form-control" id="duracao">
                            <input type=hidden name="id" id="id">
                            <br/>
                            <b>Sirene: </b>
                            <br/>
                            <input type=checkbox name="fir" id="fir" class="form-check-input"> 1º andar
                            <br/>
                            <input type=checkbox name="sec" id="sec" class="form-check-input"> 2º andar
                            <br/>  
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Enviar</button>
                        </div>
                    </form>
                </div> 
            </div>            
        </div>   
      
        <script>
            $(document).on("click", ".view_data", function () {
                 var idHorario = $(this).data('id');
                 var segunda = $(this).data('segunda');
                 var terca = $(this).data('terca');
                 var quarta = $(this).data('quarta');
                 var quinta = $(this).data('quinta');
                 var sexta = $(this).data('sexta');
                 var sabado = $(this).data('sabado');
                 var domingo = $(this).data('domingo');
                 var hora = $(this).data('hora');
                 var minuto = $(this).data('minuto');
                 var duracao = $(this).data('duracao');
                 var sirene = $(this).data('sirene');
                 $(".modal-body #id").val( idHorario ); 
                 $(".modal-body #hora").val( hora ); 
                 $(".modal-body #minuto").val( minuto ); 
                 if(segunda == 1) {
                     $(".modal-body #seg").attr( 'checked', true )
                 } else {
                     $(".modal-body #seg").attr( 'checked', false )
                 }
                 if(terca == 1) {
                     $(".modal-body #ter").attr( 'checked', true )
                 } else {
                     $(".modal-body #ter").attr( 'checked', false )
                 }
                 if(quarta == 1) {
                     $(".modal-body #qua").attr( 'checked', true )
                 } else {
                     $(".modal-body #qua").attr( 'checked', false )
                 }
                 if(quinta == 1) {
                     $(".modal-body #qui").attr( 'checked', true )
                 } else {
                     $(".modal-body #qui").attr( 'checked', false )
                 }
                 if(sexta == 1) {
                     $(".modal-body #sex").attr( 'checked', true )
                 } else {
                     $(".modal-body #sex").attr( 'checked', false )
                 }
                 if(sabado == 1) {
                     $(".modal-body #sab").attr( 'checked', true )
                 } else {
                     $(".modal-body #sab").attr( 'checked', false )
                 }
                if(domingo == 1) {
                     $(".modal-body #dom").attr( 'checked', true )
                 } else {
                     $(".modal-body #dom").attr( 'checked', false )
                 }
                 
                 $(".modal-body #duracao").val( duracao ); 
                
                if(sirene == 1) {
                     $(".modal-body #fir").attr( 'checked', true )
                     $(".modal-body #sec").attr( 'checked', false )
                 } else if(sirene == 2) {
                     $(".modal-body #fir").attr( 'checked', false )
                     $(".modal-body #sec").attr( 'checked', true )
                 } else if(sirene == 3) {
                     $(".modal-body #fir").attr( 'checked', true )
                     $(".modal-body #sec").attr( 'checked', true )
                 }
            });
        </script>
    </body>
    
</html>