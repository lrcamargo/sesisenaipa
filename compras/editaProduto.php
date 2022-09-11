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
    $idItem = $_GET['id'];
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Editar Item de Compra</title>
        
        <link rel="stylesheet" href="../../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../../css/telefone.css">
        <link rel='stylesheet' href='../../fullcalendar/main.min.css'/>
        
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
                <h3>Editar Item</h3>
                <form method="POST" action="edit.php">
                    <?php
                        try {
                            $buscaItem = $conn->prepare("SELECT * FROM itens INNER JOIN produtos ON itens.codigoProduto = produtos.codigo WHERE idItem = $idItem");
                            $buscaItem->execute();
                                
                            $buscaItens = $buscaItem->fetchAll();
                                
                            foreach ($buscaItens as $buscaItens) {
                                #$data = date("d-m-Y",strtotime($buscaReserva['data']));
                                $codigo = $buscaItens['codigoProduto'];
                                $nome = $buscaItens['nome'];
                                $quantidade = $buscaItens['quantidade'];
                                $aplicacao = $buscaItens['aplicacao'];
                                $unidade = $buscaItens['unidade'];
                                $cc = $buscaItens['cc'];
                            } 
                        } catch(PDOException $e) {
                                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                        }
                            echo "<b>Código do produto</b>: <span id='codigo'>".$codigo."</span><br/>";
                            echo "<b>Nome do produto</b>: <span id='nome'>".$nome."</span><br/>";
                            echo "<b>Quantidade</b>: <input type='text' name='quant' value='".$quantidade."'><br/>";
                            echo "<b>Aplicação</b>: <input type='text' name='aplic' value='".$aplicacao."'><br/>";
                            echo "<b>Unidade</b>:";
                            echo "<div>";
                            if($unidade == '1') {
                                echo "<input type='radio' id='sesi' name='unidade' value='1' style='float:left;width:20px;height:20px' checked><label for='sesi' style='float:left'> SESI</label>";
                                echo "<input type='radio' id='senai' name='unidade' value='2' style='float:left;width:20px;height:20px;margin-left: 5px'><label for='senai' style='float:left'> SENAI</label>";
                                echo "</div><br/><br/>";
                            } else {
                                echo "<input type='radio' id='sesi' name='unidade' value='1' style='float:left;width:20px;height:20px'><label for='sesi' style='float:left'> SESI</label>";
                                echo "<input type='radio' id='senai' name='unidade' value='2' style='float:left;width:20px;height:20px;margin-left: 5px' checked><label for='senai' style='float:left'> SENAI</label>";
                                echo "</div><br/><br/>";
                            }
                            echo "<b>Centro de Custo</b>: <input type='text' name='cc' value='".$cc."'><br/>";

                        ?>
                        <br/>
                        <input type="hidden" name='id' value="<?php echo $idItem;?>">
                    <input class='btn btn-green' type="submit" value="Editar" style="margin-right:20%"></input>
                </form>
                
            </div>
        <!--Fim wrapper-->
        </div>
        </div>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>