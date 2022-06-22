<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    include('../conexaosec.php');
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
        
        <title>Reserva de Laboratório - principal</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>

        <style>
            .labs {
                margin-top: 10%;
                margin-left: -55%;
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
                            <li><a href="laboratorios/lab201.php">201A</a><br/></li>
                            <li><a href="laboratorios/lab202.php">202A</a><br/></li>
                            <li><a href="laboratorios/lab203.php">203A</a><br/></li>
                            <li><a href="#">101A</a><br/></li>
                            <li><a href="#">101B</a><br/></li>
                            <li><a href="#">103B</a><br/></li>
                            <li><a href="#">104B</a><br/></li>
                            <li><a href="#">105B</a><br/></li>
                            <li><a href="#">106B</a><br/></li>
                            <li><a href="#">107B</a><br/></li>
                        </ul>     
                    </div>
                    <div class="card lista">
                        <h3 style="text-align:center;width:100%">Minhas Reservas</h3>
                        <br/>
                        <table class="atv-lista" style="border: none !important;text-align:center">
                            <thead>
                                <tr>
                                    <th class="id">#</th>
                                    <th class="data">Data</th>
                                    <th class="disc">Laboratório</th>
                                    <th class="edit">Início</th>
                                    <th class="fin">Fim</th>
                                    <th class="apv">Aprovado</th>
                                </tr>
                            </thead>
                        <?php
                            include("conexaoteste.php");
                            try {
                                $buscaReservas = $conn->prepare("SELECT id,data,LEFT(RTRIM(CONVERT(TIME, horarioInicio)), 8) AS horarioInicio, 
                                LEFT(RTRIM(CONVERT(TIME, horarioFim)), 8) AS horarioFim,solicitante,laboratorio,turma,aprovado FROM reservalabs
                                WHERE solicitante = '$logado' ORDER BY data,horarioInicio");
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
                                        }                                        
                                        echo "<td>" . $buscaReserva['horarioInicio'] . "</td>";
                                        echo "<td>" . $buscaReserva['horarioFim'] . "</td>";
                                        if($buscaReserva['aprovado'] == 0) {
                                            echo "<td>Aguardando</td>";
                                        } else if($buscaReserva['aprovado'] == 1) {
                                            echo "<td>Sim</td>";
                                        } else if($buscaReserva['aprovado'] == 2) {
                                            echo "<td>Não</td>";
                                        }                                       
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
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>