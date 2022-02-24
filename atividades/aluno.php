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
    $codnivel = $_SESSION['codNivel'];
    $ra = $_SESSION['ra'];
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Alunos</title>
        
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
                Atividades para entregar:
                <br/>
                <?php
                    include("conexaoteste.php");
                    try {
                        $buscaAtividades = $conn->prepare("SELECT *, FORMAT(dataEntrega,'dd-MM-yyyy') as dataFormat FROM dbo.atividades WHERE turma = $codnivel ORDER BY dataEntrega DESC");
                        $buscaAtividades->execute();
                        
                        $buscaAtividade = $buscaAtividades->fetchAll();
                        foreach ($buscaAtividade as $buscaAtividade) {
                            if($buscaAtividade['dataEntrega'] >= date("Y-m-d")) {
                                echo "<li><span class='standdot'></span>".$buscaAtividade['nome']." - " .$buscaAtividade['dataFormat']." - ".$buscaAtividade['disciplina']."</li>";
                            } else {
                                try {
                                    $buscaEntrega = $conn->prepare("SELECT * FROM dbo.status WHERE idAtividade = ".$buscaAtividade['idAtividade']." AND idAlunos = " .$ra);
                                    $buscaEntrega->execute();

                                    $retorno = $buscaEntrega->fetchAll();
                                    if(count($retorno) > 0) {
                                        echo "<li><span class='offdot'></span>".$buscaAtividade['nome']." - " .$buscaAtividade['dataFormat']." - ".$buscaAtividade['disciplina']."</li>";
                                    } else {
                                        echo "<li><span class='ondot'></span>".$buscaAtividade['nome']." - " .$buscaAtividade['dataFormat']." - ".$buscaAtividade['disciplina']."</li>";
                                    }
                                } catch(PDOException $e) {
                                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                                }
                            }
                            //echo $buscaAtividade['disciplina'];
                            echo "</br>";
                        }
                    } catch(PDOException $e) {
                        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                    }
                ?>


                
            </div>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>