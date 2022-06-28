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
        
        <title>Reserva de Laboratório</title>
        
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>   
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        
        <style>
            .labs {
                margin-top: 15%;
                margin-left: -85%;
                text-align: center;
            }

            .labs li:before {
                display: inline-block;
                margin-left: -1.3em; /* same as padding-left set on li */
                width: 1.3em; /* same as padding-left set on li */
            }
        </style>
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
                <div class="card-container" style="grid-template-columns: 30% 70% !important;">
                    <div class="card">
                        <h3 class="atv-titulo">Laboratórios</h3>
                        <br/>
                        <br/>
                        <ul class="labs">
                            <li><a href="laboratorios/lab201.php">201A - Informática</a><br/></li>
                            <li><a href="laboratorios/lab202.php">202A - Informática</a><br/></li>
                            <li><a href="laboratorios/lab203.php">203A - Informática</a><br/></li>
                            <li><a href="laboratorios/lab101a.php">101A - Química</a><br/></li>
                            <li><a href="laboratorios/lab102a.php">102A - Robótica Lego</a><br/></li>
                            <li><a href="laboratorios/lab101b.php">101B - Robótica Industrial</a><br/></li>
                            <li><a href="laboratorios/lab103b.php">103B - Eletrohidropneumática</a><br/></li>
                            <li><a href="laboratorios/lab104b.php">104B - Elétrica Predial</a><br/></li>
                            <li><a href="laboratorios/lab105b.php">105B - Elétrica Industrial</a><br/></li>
                            <li><a href="laboratorios/lab106b.php">106B - Eletrônica</a><br/></li>
                            <li><a href="laboratorios/lab107b.php">107B - SENAI LAB</a><br/></li>
                            <li><a href="laboratorios/lab101c.php">101C - Usinagem</a><br/></li>
                            <li><a href="laboratorios/lab103c.php">103C - Solda</a><br/></li>
                            <li><a href="laboratorios/lab104c.php">104C - Manutenção</a><br/></li>
                            <li><a href="laboratorios/lab105c.php">105C - Ferramentaria</a><br/></li>
                            <li><a href="laboratorios/lab106c.php">106C - CNC</a><br/></li>
                        </ul>                        
                    </div>
                    <div class="card lista">
                        <h3 style="text-align:center;width:100%">Reservas</h3>
                        <br/>
                        <table class="atv-lista" style="border: none !important;text-align:center">
                            <thead>
                                <tr>
                                    <th class="id">#</th>
                                    <th class="data">Data</th>
                                    <th class="disc">Laboratório</th>
                                    <th class="edit">Início</th>
                                    <th class="fin">Fim</th>
                                    <th class="fin">Solicitante</th>
                                    <th class="apv">Aprovado</th>
                                    <th class="apv">Status</th>
                                    <th class="apv">Editar</th>
                                </tr>
                            </thead>
                        <?php
                            include("conexao.php");
                            try {
                                $buscaReservas = $conn->prepare("SELECT id,data,LEFT(RTRIM(CONVERT(TIME, horarioInicio)), 8) AS horarioInicio, 
                                LEFT(RTRIM(CONVERT(TIME, horarioFim)), 8) AS horarioFim,solicitante,laboratorio,turma,aprovado FROM reservas
                                WHERE data >= GETDATE() ORDER BY data,horarioInicio");
                                $buscaReservas->execute();
                                
                                $buscaReserva = $buscaReservas->fetchAll();
                                
                                foreach ($buscaReserva as $buscaReserva) {
                                    echo "<tr>";
                                        echo "<td>" . $buscaReserva['id'] . "</td>";
                                        echo "<td>".date("d-m-Y",strtotime($buscaReserva['data']))."</td>";
                                        if($buscaReserva['laboratorio'] == 1) {
                                            echo "<td>201A</td>";
                                        } else if($buscaReserva['laboratorio'] == 2) {
                                            echo "<td>202A</td>";
                                        } else if($buscaReserva['laboratorio'] == 3) {
                                            echo "<td>203A</td>";
                                        } else if($buscaReserva['laboratorio'] == 4) {
                                            echo "<td>101B</td>";
                                        } else if($buscaReserva['laboratorio'] == 5) {
                                            echo "<td>103B</td>";
                                        } else if($buscaReserva['laboratorio'] == 6) {
                                            echo "<td>104B</td>";
                                        } else if($buscaReserva['laboratorio'] == 7) {
                                            echo "<td>105B</td>";
                                        } else if($buscaReserva['laboratorio'] == 8) {
                                            echo "<td>106B</td>";
                                        } else if($buscaReserva['laboratorio'] == 9) {
                                            echo "<td>107B</td>";
                                        } else if($buscaReserva['laboratorio'] == 10) {
                                            echo "<td>101A</td>";
                                        } else if($buscaReserva['laboratorio'] == 11) {
                                            echo "<td>CNC</td>";
                                        } else if($buscaReserva['laboratorio'] == 12) {
                                            echo "<td>LEGO</td>";
                                        }                                        
                                        echo "<td>" . $buscaReserva['horarioInicio'] . "</td>";
                                        echo "<td>" . $buscaReserva['horarioFim'] . "</td>";
                                        echo "<td>" . $buscaReserva['solicitante'] . "</td>";
                                        if($buscaReserva['aprovado'] == 0) {
                                            echo "<td>Aguardando</td>";
                                        } else if($buscaReserva['aprovado'] == 1) {
                                            echo "<td>Sim</td>";
                                        } else if($buscaReserva['aprovado'] == 2) {
                                            echo "<td>Não</td>";
                                        } 
                                        echo "<td align='center'><a href='statusReserva.php?id=".$buscaReserva['id']."&status=1'><i class='fas fa-check-circle'></i></a>  <a href='statusReserva.php?id=".$buscaReserva['id']."&status=2'><i class='fas fa-times-circle'></i></a></td>";
                                        echo "<td align='center'><a href='editaReserva.php?id=".$buscaReserva['id']."'><i class='fas fa-pen-square'></i></a></td>";
                                    echo "</tr>";
                                }
                            } catch(PDOException $e) {
                                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                            }
                        ?>
                        </table>
                        </br>
                    </div>                    
                </div>
            </div>
        <!--Fim wrapper-->
       
        </div>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>