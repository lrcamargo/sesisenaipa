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

    include("conexao.php");
    
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
        <link rel="stylesheet" href="http://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="http://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>

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
                <table id="dados" class="display cell-border stripe display compact" style="width:80%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tensão R/N</th>
                            <th>Tensão S/N</th>
                            <th>Tensão T/N</th>
                            <th>Tensão R/S</th>
                            <th>Tensão S/T</th>
                            <th>Tensão T/R</th>
                            <th>I1</th>
                            <th>I2</th>
                            <th>I3</th>
                            <th>Soma Correntes</th>
                            <th>P1</th>
                            <th>P2</th>
                            <th>P3</th>
                            <th>PT</th>
                            <th>Q1</th>
                            <th>Q2</th>
                            <th>Q3</th>
                            <th>QT</th>
                            <th>S1</th>
                            <th>S2</th>
                            <th>S3</th>
                            <th>ST</th>
                            <th>COS 1</th>
                            <th>COS 2</th>
                            <th>COS 3</th>
                            <th>COS T</th>
                            <th>FREQUENCIA</th>
                            <th>CONS WH</th>
                            <th>FORN WH</th>
                            <th>CONS VARH</th>
                            <th>FORN VARH</th>
                            <th>ANG 1</th>
                            <th>ANG 2</th>
                            <th>ANG 3</th>
                            <th>ANG T</th>
                            <th>Data Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            try {
                                $busca = $conn -> prepare("SELECT * FROM dbo.consumoenergia ORDER BY id DESC");
                                $busca -> execute();

                                $buscaDados = $busca->fetchAll();
                                foreach($buscaDados as $buscaDados) {
                                    echo "<tr>"; 
                                        echo "<td>".$buscaDados["id"]."</td>";
                                        echo "<td>".$buscaDados["tensaol1neutro"]."</td>"; 
                                        echo "<td>".$buscaDados["tensaol2neutro"]."</td>";
                                        echo "<td>".$buscaDados["tensaol3neutro"]."</td>";
                                        echo "<td>".$buscaDados["tensaol1l2"]."</td>";
                                        echo "<td>".$buscaDados["tensaol2l3"]."</td>";
                                        echo "<td>".$buscaDados["tensaol3l1"]."</td>";
                                        echo "<td>".$buscaDados["correntei1"]."</td>";
                                        echo "<td>".$buscaDados["correntei2"]."</td>";
                                        echo "<td>".$buscaDados["correntei3"]."</td>";
                                        echo "<td>".$buscaDados["somacorrentes"]."</td>";
                                        echo "<td>".$buscaDados["potativap1"]."</td>";
                                        echo "<td>".$buscaDados["potativap2"]."</td>";
                                        echo "<td>".$buscaDados["potativap3"]."</td>";
                                        echo "<td>".$buscaDados["potativatotal"]."</td>";
                                        echo "<td>".$buscaDados["potreativaq1"]."</td>";
                                        echo "<td>".$buscaDados["potreativaq2"]."</td>";
                                        echo "<td>".$buscaDados["potreativaq3"]."</td>";
                                        echo "<td>".$buscaDados["potreativatotal"]."</td>";
                                        echo "<td>".$buscaDados["potaparentes1"]."</td>";
                                        echo "<td>".$buscaDados["potaparentes2"]."</td>";
                                        echo "<td>".$buscaDados["potaparentes3"]."</td>";
                                        echo "<td>".$buscaDados["potaparentetotalst"]."</td>";
                                        echo "<td>".$buscaDados["cosphi1"]."</td>";
                                        echo "<td>".$buscaDados["cosphi2"]."</td>";
                                        echo "<td>".$buscaDados["cosphi3"]."</td>";
                                        echo "<td>".$buscaDados["cosphit"]."</td>";
                                        echo "<td>".$buscaDados["frequencia"]."</td>";
                                        echo "<td>".$buscaDados["consumidawh"]."</td>";
                                        echo "<td>".$buscaDados["fornecidawh"]."</td>";
                                        echo "<td>".$buscaDados["consumidavarh"]."</td>";
                                        echo "<td>".$buscaDados["fornecidavarh"]."</td>";
                                        echo "<td>".$buscaDados["angulophi1"]."</td>";
                                        echo "<td>".$buscaDados["angulophi2"]."</td>";
                                        echo "<td>".$buscaDados["angulophi3"]."</td>";
                                        echo "<td>".$buscaDados["angulophit"]."</td>";
                                        echo "<td>".$buscaDados["dataHora"]."</td>";
                                    echo "</tr>";
                                }
                            } catch (PDOException $e) {
                                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!--Fim wrapper-->
        <script>
            $(document).ready( function () {
                $('#dados').DataTable({
                    dom: 'Bfrtip',
                    "responsive": true,
                    lengthMenu: [10, 20, 50, 200, 400],
                    "autoWidth": true,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.10.20/i18n/Portuguese.json"
                    }, "buttons": [
                            'excelHtml5',
                            'pdfHtml5'
                        ]                        
                });
            } );
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>