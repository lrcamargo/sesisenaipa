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

    include("conexao.php");
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Lista de Compras</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/compras.css">
        <link rel="stylesheet" href="//cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="https://cdn.jsdelivr.net/npm/table-to-json@1.0.0/lib/jquery.tabletojson.min.js" integrity="sha256-H8xrCe0tZFi/C2CgxkmiGksqVaxhW0PFcUKZJZo1yNU=" crossorigin="anonymous"></script>
        <script src="//cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
        <script src="//cdn.datatables.net/plug-ins/1.12.1/i18n/pt-BR.json"></script>
        
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
                <h3>Lista de Compras</h3>
                <br/>
                <table id="tabela" class="tabela display stripe order-column">
                    <thead>
                        <th>Codigo</th>
                        <th>Solicitante</th>
                        <th>Aprovação</th>
                        <th>Status</th>
                        <th>Data/Hora</th>
                        <th>Editar</th>
                    </thead>
                    <tbody>
                        <?php
                            try {
                                $buscaSolicitacao = $conn->prepare("SELECT * FROM solicitacao ORDER BY idSolicitacao DESC");
                                $buscaSolicitacao->execute();
                        
                                $solicitacao = $buscaSolicitacao->fetchAll();
                                               
                                foreach($solicitacao as $solicitacao) {
                                    echo "<tr>";
                                        echo "<td>".$solicitacao['idSolicitacao']."</td>";
                                        echo "<td>".$solicitacao['solicitante']."</td>";
                                        if($solicitacao['aprovado'] == 0) {
                                            echo "<td>Aguardando aprovação</td>";
                                        } else if($solicitacao['aprovado'] == 1) {
                                            echo "<td>Aprovado</td>";
                                        } else if($solicitacao['aprovado'] == 2) {
                                            echo "<td>Reprovado</td>";
                                        }
                                        if($solicitacao['status'] == 0) {
                                            echo "<td>Aguardando aprovação</td>";
                                        } else if($solicitacao['status'] == 1) {
                                            echo "<td>Realizando orçamento</td>";
                                        } else if($solicitacao['status'] == 2) {
                                            echo "<td>Aguardando informações</td>";
                                        } else if($solicitacao['status'] == 3) {
                                            echo "<td>Realizando Compra</td>";
                                        } else if($solicitacao['status'] == 4) {
                                            echo "<td>Aguardando entrega</td>";
                                        } else if($solicitacao['status'] == 5) {
                                            echo "<td>Finalizado</td>";
                                        } 
                                        echo "<td>".$solicitacao['dataHora']."</td>";
                                        echo "<td align='center'><a href='detalhes.php?id=".$solicitacao['idSolicitacao']."'><i class='fas fa-edit'></i></a></td>";
                                    echo "</tr>";
                                }
                            } catch(PDOException $e) {
                                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                            }
                        ?>
                    </tbody>
                </table>           
            </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
        <script>
            $(document).ready( function () {
                $('#tabela').DataTable( {
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.10.20/i18n/Portuguese.json'
                    }
                });
            } );
        </script>
    </body>
</html>