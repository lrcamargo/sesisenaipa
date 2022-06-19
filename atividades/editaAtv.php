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
    $idAtividade = $_GET['id'];
    $turma;
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
                <h3 align="center">Editar Atividade</h3>
                <br/>
                <?php
                    include('conexaoatv.php');
                    try {
                        $buscaAtv = $conn->prepare("SELECT * FROM dbo.atividades WHERE idAtividade = $idAtividade");
                        $buscaAtv->execute();

                        $atividades = $buscaAtv->fetchAll();

                        foreach($atividades as $atividades) {
                            $turma = $atividades["turma"];
                            $nome = $atividades["nome"];
                            $data = $atividades["dataEntrega"];
                            $pontuada = $atividades["pontuada"];
                            $valor = $atividades["valor"];
                            $disciplina = $atividades["disciplina"];
                        }
                    } catch(PDOException $e) {
                        die("Erro ao conectar ao banco de dados: ".$e->getMessage());
                    }
                ?>
                <form method="POST" action="editAtv.php">
                    Turma: 
                        <select name="turma">
                            <?php
                                include('../conexaosec.php');
                                try {
                                    $buscaTurmas = $conn->prepare("SELECT id,descricao FROM niveis WHERE SUBSTRING(descricao,10,5) = '11775' AND SUBSTRING(descricao,16,2) = FORMAT(getdate(), 'yy') OR SUBSTRING(descricao,12,5) = '11775' AND SUBSTRING(descricao,18,2) = FORMAT(getdate(), 'yy')");
                                    $buscaTurmas->execute();

                                    $turmas = $buscaTurmas->fetchAll();
                                        
                                    foreach($turmas as $turmas) {
                                        if($turmas['id'] != $turma) {
                                            echo "<option value='".$turmas['id']."'>".$turmas['descricao']."</option>";
                                        } else {
                                            echo "<option selected value='".$turmas['id']."'>".$turmas['descricao']."</option>";
                                        }
                                    }
                                } catch(PDOException $e) {
                                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                                }
                            ?>
                        </select>
                        <br/>
                        Nome: <input type="text" name="nome" value="<?php echo $nome; ?>"></input><br/>
                        Data de Entrega: <input type="date" name="data" value="<?php echo $data; ?>"></input><br/>
                        Disciplina: <input type="text" name="disciplina" value="<?php echo $disciplina; ?>"></input><br/>
                        Pontuada?
                            <?php
                                if($pontuada == 0) {
                                    echo "<input type='checkbox' name='pontuada' onChange='pontos()'></input>  Valor: <input type='text' name='valor' disabled></input><br/>";
                                } else {
                                    echo "<input type='checkbox' name='pontuada' onChange='pontos()' checked></input>  Valor: <input type='text' name='valor' value='".$valor."'></input><br/>";
                                }
                            ?>
                         
                        <input type="hidden" name="docente" value=<?php echo $logado; ?>></input>
                        <input type="hidden" name="id" value=<?php echo $idAtividade; ?>></input>
                        <br/>
                        <input class='btn btn-green' type="submit" value="Editar" style="margin-right:20%"></input>
                        <br/>
                        <br/>
                </form>
                <br/>
            </div>
        </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>