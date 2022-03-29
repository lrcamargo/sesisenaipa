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
    $idAtv = $_GET['id'];
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Atividades - Professor</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        <link rel="stylesheet" href="../css/atividades.css">
        
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
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
                        <?php
                            include("conexaoatv.php");
                            try {
                                $buscaAtividades = $conn->prepare("SELECT * FROM dbo.status WHERE idAtividade = ".$idAtv);
                                $buscaAtividades->execute();
                                
                                $buscaAtividade = $buscaAtividades->fetchAll();
                                
                                foreach ($buscaAtividade as $buscaAtividade) {
                                    include('../conexaosec.php');
                                    $buscaAluno = $conn->prepare("SELECT n_identificador,nome FROM dbo.pessoas WHERE n_identificador = ".$buscaAtividade['idAlunos']);
                                    $buscaAluno->execute();
                                    $buscaAlunos = $buscaAluno->fetchAll();

                                    foreach ($buscaAlunos as $buscaAlunos) {
                                        echo "<tr>";
                                            echo "<td>" . $buscaAlunos['nome'] . "</td>";
                                        echo "</tr>";
                                    }
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