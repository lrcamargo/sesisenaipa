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
    $idAtividade = $_GET['id'];
    $codTurma = $_GET['codturma'];
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Atividades - Professor</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        
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
                <?php echo $idAtividade; ?>
                Entrega do aluno
                <br/>
                <form method="POST" action="cadEntrega.php">
                <?php
                    include("../conexaosec.php");
                    try {
                        $buscaAlunos = $conn->prepare("SELECT * FROM dbo.pessoas WHERE nivel_id = '$codTurma' ORDER BY nome");
                        $buscaAlunos->execute();
                        
                        $buscaAluno = $buscaAlunos->fetchAll();
                        foreach ($buscaAluno as $buscaAluno) {
                            echo $buscaAluno['n_identificador'];
                            echo "-";
                            echo $buscaAluno['nome'];
                            echo "-";
                            echo "<input type='checkbox' name='ra[]' value='".$buscaAluno['n_identificador']."'></input><br/>";
                            echo "</br>";
                        }
                    } catch(PDOException $e) {
                        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                    }
                ?>
                
                    <input type="hidden" name="idAtividade" value=<?php echo $idAtividade; ?>></input>
                    <input type="submit" value="Registrar" name="submit"></input>
                </form>  
                <br/>
            </div>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>