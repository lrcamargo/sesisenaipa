<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    include("conexao.php");
    session_start();
    
    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../../index.php');    
    }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];
    $idReserva = $_GET['id'];
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Laboratório 201</title>
        
        <link rel="stylesheet" href="../../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../../css/telefone.css">
        <link rel='stylesheet' href='../../fullcalendar/main.min.css'/>
        
        <script src='../../fullcalendar/main.min.js'></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        
    </head>
    <body>
        <!-- Início Wrapper -->
        <div class="wrapper">
        <!-- Início Cabeçalho -->
        <!-- Colocar index no css -->
            <div class="header" style="z-index:99">
                <div class="header-menu">
                    <div class="title"><img src="../../img/logo_white.svg"></div>
                    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
                    <ul>
                        <li><a href="#" class="user"><?php echo $logado; ?></a></li>
                        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
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
                <h3>Editar Reserva</h3>
                <form method="POST" action="edit.php">
                    <?php
                        //include("conexaoteste.php");
                        try {
                            $buscaReservas = $conn->prepare("SELECT id,data,LEFT(RTRIM(CONVERT(TIME, horarioInicio)), 8) AS horarioInicio, 
                            LEFT(RTRIM(CONVERT(TIME, horarioFim)), 8) AS horarioFim,solicitante,laboratorio,turma,aprovado FROM reservas
                            WHERE id = $idReserva");
                            $buscaReservas->execute();
                                
                            $buscaReserva = $buscaReservas->fetchAll();
                                
                            foreach ($buscaReserva as $buscaReserva) {
                                $data = date("d-m-Y",strtotime($buscaReserva['data']));
                                $laboratorio = $buscaReserva['laboratorio'];      
                                $hInicio = $buscaReserva['horarioInicio'];
                                $hFim = $buscaReserva['horarioFim'];
                                $turma = $buscaReserva['turma'];
                                $solicitante = $buscaReserva['solicitante'];
                                $status = $buscaReserva['aprovado'];
                                $laboratorio = $buscaReserva['laboratorio'];
                                }
                            } catch(PDOException $e) {
                                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                            }
                            echo "<b>Data</b>: <input type='date' name='dataInp' value='".$buscaReserva['data']."'><br/>";
                            echo "<b>Turno: </b>";
                            echo "<div>";
                            if($hFim <= '13:00') {
                                echo "<input type='radio' id='manha' name='turno' value='manha' style='float:left;width:20px;height:20px' checked><label for='manha' style='float:left'> Manhã</label>";
                                echo "<input type='radio' id='tarde' name='turno' value='tarde' style='float:left;width:20px;height:20px;margin-left: 5px'><label for='tarde' style='float:left'> Tarde</label>";
                                echo "<input type='radio' id='noite' name='turno' value='noite' style='float:left;width:20px;height:20px;margin-left: 5px'><label for='noite' style='float:left'> Noite</label>";
                                echo "</div><br/><br/>";
                            } else if($hFim > '13:00' & $hFim <= '18:00' ) {
                                echo "<input type='radio' id='manha' name='turno' value='manha' style='float:left;width:20px;height:20px'><label for='manha' style='float:left'> Manhã</label>";
                                echo "<input type='radio' id='tarde' name='turno' value='tarde' style='float:left;width:20px;height:20px;margin-left: 5px' checked><label for='tarde' style='float:left'> Tarde</label>";
                                echo "<input type='radio' id='noite' name='turno' value='noite' style='float:left;width:20px;height:20px;margin-left: 5px'><label for='noite' style='float:left'> Noite</label>";
                                echo "</div><br/><br/>";
                            } else if($hFim > '18:00') {
                                echo "<input type='radio' id='manha' name='turno' value='manha' style='float:left;width:20px;height:20px'><label for='manha' style='float:left'> Manhã</label>";
                                echo "<input type='radio' id='tarde' name='turno' value='tarde' style='float:left;width:20px;height:20px;margin-left: 5px'><label for='tarde' style='float:left'> Tarde</label>";
                                echo "<input type='radio' id='noite' name='turno' value='noite' style='float:left;width:20px;height:20px;margin-left: 5px' checked><label for='noite' style='float:left'> Noite</label>";
                                echo "</div><br/>";
                            }
                            echo "<b>A reserva ocupará todo o turno ou apenas um período do dia?</b><br/>";
                            if($hInicio == "07:00:00" && $hFim == "12:20:00" || $hInicio == "13:00:00" && $hFim == "17:30:00" || $hInicio == "18:00:00" && $hFim == "22:30:00") {
                                echo "<input type='radio' name='periodo' value='parcial' onChange='horarios()'>Só um período  ";
                                echo "<input type='radio' name='periodo' value='todo' onChange='horarios()' checked>Todo o turno";
                                echo "<b><span name='hInicio' style='display:none'>Início: </span></b><input id='horainicio' name='horainicio' type='time' style='display:none' value='".$hInicio."'>";
                                echo "<b><span name='hFim' style='display:none'>Fim: </span></b><input id='horafim' name='horafim' type='time' style='display:none' value='".$hFim."'> <br/>";
                            } else {
                                echo "<input type='radio' name='periodo' value='parcial' onChange='horarios()' checked>Só um período  ";
                                echo "<input type='radio' name='periodo' value='todo' onChange='horarios()'>Todo o turno";
                                echo "<br/>";
                                echo "<b><span name='hInicio' style='display:block'>Início: </span></b><input id='horainicio' name='horainicio' type='time' style='display:block' value='".$hInicio."'>";
                                echo "<b><span name='hFim' style='display:block'>Fim: </span></b><input id='horafim' name='horafim' type='time' style='display:block' value='".$hFim."'> <br/>";
                            }
                            echo "<br/>";
                            echo "<b>Turma: </b><br/>";
                            echo "<select name='turma'>";
                                include("../conexaosec.php");
                                try {
                                    $buscaTurmas = $conn->prepare("SELECT id,descricao FROM niveis");
                                    $buscaTurmas->execute();

                                    $turmas = $buscaTurmas->fetchAll();
                                                
                                    foreach($turmas as $turmas) {
                                        if($turmas['descricao'] == $turma) {
                                            echo "<option value='".$turmas['descricao']."' selected>".$turmas['descricao']."</option>";
                                        } else {
                                            echo "<option value='".$turmas['descricao']."'>".$turmas['descricao']."</option>";
                                        }
                                        
                                    }
                                } catch(PDOException $e) {
                                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                                }
                        echo "</select>";
                        echo "<br/>";
                            echo "<b>Solicitante: </b><span id='solicitante'>".$solicitante."</span><br/>";
                            if($laboratorio == 1) {
                                echo "<b>Laboratório: </b>";
                                echo "<select name='lab'>";
                                    echo "<option value='201a' selected>201A</option>";
                                    echo "<option value='202a'>202A</option>";
                                    echo "<option value='203a'>203A</option>";
                                echo "</select>";
                            } else if($laboratorio == 2) {
                                echo "<b>Laboratório: </b>";
                                echo "<select name='lab'>";
                                    echo "<option value='201a'>201A</option>";
                                    echo "<option value='202a' selected>202A</option>";
                                    echo "<option value='203a'>203A</option>";
                                echo "</select>";
                            } else if($laboratorio == 3) {
                                echo "<b>Laboratório: </b>";
                                echo "<select name='lab'>";
                                    echo "<option value='201a'>201A</option>";
                                    echo "<option value='202a'>202A</option>";
                                    echo "<option value='203a' selected>203A</option>";
                                echo "</select>";
                            }
                            
                        ?>
                        <br/>
                        <input type="hidden" name='id' value="<?php echo $idReserva;?>">
                    <input class='btn btn-green' type="submit" value="Editar" style="margin-right:20%"></input>
                </form>
                
            </div>
        <!--Fim wrapper-->
        </div>
        </div>
        
        <script>
            function horarios() {
                const box = document.querySelector('input[value=parcial]');
                if(box.checked==true) {
                    console.log("teste");
                    document.querySelector('span[name=hInicio]').style.display = "block";
                    document.querySelector('span[name=hFim]').style.display = "block";
                    document.querySelector('input[name=horainicio]').style.display = "block";
                    document.querySelector('input[name=horafim]').style.display = "block";
                } else {
                    console.log("teste2");
                    document.querySelector('span[name=hInicio]').style.display = "none";
                    document.querySelector('span[name=hFim]').style.display = "none";
                    document.querySelector('input[name=horainicio]').style.display = "none";
                    document.querySelector('input[name=horafim]').style.display = "none";
                }
            }
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>