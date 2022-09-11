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
    $id = $_GET['id'];
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
                        <li><a href="sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
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
                <h3>Detalhes da Solicitação</h3>
                <br/>
                <?php
                    if($logado == 'administrator' || $logado == 'jlboari') {
                        echo "<b>Aprovação: </b>";
                        echo "<td align='center'><a href='aprovaCompra.php?id=".$id."&status=1'>Aprovar</a> || <a href='aprovaCompra.php?id=".$id."&status=2'>Reprovar</a>";
                        echo "<br/>";
                        echo "<b>Definir unidade para todos os produtos: </b>";
                        echo "<td align='center'><a href='unidadeTotal.php?id=".$id."&status=1'>SESI</a> || <a href='unidadeTotal.php?id=".$id."&status=2'>SENAI</a>";
                        echo "<br/>";
                        echo "<b>Definir centro de custo para todos os produtos: </b>";
                        echo "<br/>";
                        echo "<i>SESI: </i><td align='center'><a href='ccTotal.php?id=".$id."&cc=2201'>2201</a> || <a href='ccTotal.php?id=".$id."&cc=3111'>3111</a> || <a href='ccTotal.php?id=".$id."&cc=3112'>3112</a> || <a href='ccTotal.php?id=".$id."&cc=3147'>3147</a> || <a href='ccTotal.php?id=".$id."&cc=3158'>3158</a> || <a href='ccTotal.php?id=".$id."&cc=3163'>3163</a> || <a href='ccTotal.php?id=".$id."&cc=3168'>3168</a> || <a href='ccTotal.php?id=".$id."&cc=3172'>3172</a> || <a href='ccTotal.php?id=".$id."&cc=5310'>5310</a> || <a href='ccTotal.php?id=".$id."&cc=5316'>5316</a>";
                        echo "<br/>";
                        echo "<i>SENAI: </i><td align='center'><a href='ccTotal.php?id=".$id."&cc=2201'>2201</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3172'>3172</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3182'>3182</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3192'>3192</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3484'>3484</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3614'>3614</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3804'>3804</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=3865'>3865</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=5306'>5306</a> || <td align='center'><a href='ccTotal.php?id=".$id."&cc=5316'>5316</a>";
                        echo "<br/>";
                        echo "<br/>";
                    } else if($logado == 'ritaveloso' || $logado == 'scleidi') {
                        echo "<a href='statusCompra.php?id=".$id."&status=1'>Realizando Orçamento</a> || <a href='statusCompra.php?id=".$id."&status=2'>Aguardando Informações</a> || <a href='statusCompra.php?id=".$id."&status=3'>Realizando Compra</a> || <a href='statusCompra.php?id=".$id."&status=3'>Aguardando Entrega</a> || <a href='statusCompra.php?id=".$id."&status=5'>Finalizado</a>";
                        echo "<br/>";
                        echo "<br/>";
                    }
                ?>
                <table id="tabela" class="tabela display stripe order-column">
                    <thead>
                        <th>Codigo do Produto</th>
                        <th>Nome</th>
                        <th>Descricao</th>
                        <th>Un. Medida</th>
                        <th>Quant.</th>
                        <th>Aplicacao</th>
                        <th>Unidade</th>
                        <th>CC</th>
                        <th>Editar</th>
                    </thead>
                    <tbody>
                        <?php
                            try {
                                $buscaSolicitacao = $conn->prepare("SELECT * FROM itens INNER JOIN produtos ON codigoProduto = codigo WHERE codSolicitacao = $id ");
                                $buscaSolicitacao->execute();
                        
                                $solicitacao = $buscaSolicitacao->fetchAll();
                                               
                                foreach($solicitacao as $solicitacao) {
                                    echo "<tr>";
                                        echo "<td>".$solicitacao['codigoProduto']."</td>";
                                        echo "<td>".$solicitacao['nome']."</td>";
                                        echo "<td>".$solicitacao['descricao']."</td>";
                                        echo "<td>".$solicitacao['unMedida']."</td>";
                                        echo "<td>".$solicitacao['quantidade']."</td>";
                                        echo "<td>".$solicitacao['aplicacao']."</td>";
                                        if($solicitacao['unidade'] == 1) {
                                            echo "<td>SESI</td>";
                                        } else if($solicitacao['unidade'] == 2) {
                                            echo "<td>SENAI</td>";
                                        }
                                        echo "<td align='center'>".$solicitacao['cc']."</td>";
                                        echo "<td align='center'><a href='editaProduto.php?id=".$solicitacao['idItem']."'><i class='fas fa-edit'></i></a></td>";
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
                $('#tabela').DataTable();
            } );
        </script>
    </body>
</html>