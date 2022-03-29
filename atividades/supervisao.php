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
                    <div class="card lista">
                        <h3 style="text-align:center;width:100%">Todas Atividades</h3>
                        <table class="atv-lista-sup" style="border: none !important">
                            <thead>
                                <tr>
                                    <th class="idsup">#</th>
                                    <th class="datasup">Data</th>
                                    <th class="discsup">Disciplina</th>
                                    <th class="profsup">Professor</th>
                                    <th class="editsup">Editar</th>
                                </tr>
                            </thead>
                        <?php
                            include("conexaoatv.php");
                            try {
                                $buscaAtividades = $conn->prepare("SELECT * FROM dbo.atividades ORDER BY dataEntrega DESC");
                                $buscaAtividades->execute();
                                
                                $buscaAtividade = $buscaAtividades->fetchAll();
                                
                                foreach ($buscaAtividade as $buscaAtividade) {
                                    echo "<tr>";
                                        echo "<td>" . $buscaAtividade['idAtividade'] . "</td>";
                                        echo "<td>".date("d-m-Y",strtotime($buscaAtividade['dataEntrega']))."</td>";
                                        echo "<td>".$buscaAtividade['disciplina']."</td>";
                                        echo "<td>".$buscaAtividade['docente']."</td>";
                                        echo "<td align='center'><a href='listaAtvTotal.php?id=".$buscaAtividade['idAtividade']."'><i class='fas fa-edit'></i></a></td>";
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
        <script>
            function pontos() {
                const box = document.querySelector('input[name=pontuada]');
                if(box.checked==true) {
                    document.querySelector('input[name=valor]').disabled = false;
                } else {
                    document.querySelector('input[name=valor]').disabled = true;
                }
            }
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>