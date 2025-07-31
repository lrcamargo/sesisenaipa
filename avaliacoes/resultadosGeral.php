<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

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

    $selectedDisciplina = $_POST["disciplina"];
    $selectedDocente = $_POST["docente"];
    $selectedAvaliacao = $_POST["avaliacao"];
    $selectedEtapa = $_POST["etapa"];
    $selectedTurma = $_POST["turma"];
    
    if(!empty($selectedDisciplina) || $selectedDisciplina === "Select") {
        $consulta = true;
    } else {
        $consulta = false;
    }

?>

<!DOCTYPE html>
<html>
<head>      
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
        
    <title>Busca Relatórios</title>

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/camera.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
    <link rel='stylesheet' src='https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css'>
    <link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.1/b-html5-2.4.1/b-print-2.4.1/datatables.min.css" rel="stylesheet">
    
    <script src='https://code.jquery.com/jquery-3.7.0.js'></script>
    <script src="https://cdn.datatables.net/v/dt/dt-1.13.6/b-2.4.1/datatables.min.js"></script>
    <script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.1/b-html5-2.4.1/b-print-2.4.1/datatables.min.js"></script>

    <!--tabela-->
    <script>
        $(document).ready(function () {
            var tbl = document.getElementById("tabela");
            tbl.style.display="none";
            var grf = document.getElementById("grafico");
            grf.style.display="none";
            $('#resultados').DataTable({
                dom: 'Blfrtip',
                    buttons: [
                        'copy', 'excel', 'pdf', 'print'
                    ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                },
            });
        });
    </script>
    
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
 
    <h2>Busca Relatórios</h2>
    
    <form id="consulta" action="" method="post" enctype="multipart/form-data">
        Escolha uma Disciplina:
        <select name="disciplina">
            <option value=''>Selecione...</option>
            <?php
                
                try {
                    $buscaDisc = $conn->prepare("SELECT * FROM disciplinas  ORDER BY discNome");
                    $buscaDisc->execute();

                    $disciplina = $buscaDisc->fetchAll();
                                
                    foreach($disciplina as $disciplina) {
                        echo "<option value='".$disciplina['idDisc']."'>".$disciplina['discNome']."</option>";
                    }
                } catch(PDOException $e) {
                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                }
            ?>
        </select>
        <br/>
        Escolha um Docente:
        <select name="docente">
            <option value=''>Selecione...</option>
            <?php
                
                try {
                    $buscaDoc = $conn->prepare("SELECT * FROM docente ORDER BY docenteNome");
                    $buscaDoc->execute();

                    $docente = $buscaDoc->fetchAll();
                                
                    foreach($docente as $docente) {
                        echo "<option value='".$docente['idDocente']."'>".$docente['docenteNome']."</option>";
                    }
                } catch(PDOException $e) {
                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                }
            ?>
        </select>
        <br/>
        Escolha uma Etapa Letiva:
        <select name="etapa">
            <option value=''>Selecione...</option>
            <option value='1'>1ª Etapa</option>
            <option value='2'>2ª Etapa</option>
            <option value='3'>3ª Etapa</option>
        </select>
        <br/>
        Escolha uma Avaliação:
        <select name="avaliacao">
            <option value=''>Selecione...</option>
            <option value='1'>Avaliação 1</option>
            <option value='2'>Avaliação 2</option>
            <option value='3'>Recuperação</option>
            <option value='4'>Avaliação da Rede - Caderno 01</option>
            <option value='5'>Avaliação da Rede - Caderno 02</option>
        </select>
        <br/>
        Escolha uma Turma:
        <select name="turma">
            <option value=''>Selecione...</option>
            <option value='1'>6º Ano</option>
            <option value='2'>7º Ano A</option>
            <option value='3'>7º Ano B</option>
            <option value='4'>8º Ano A</option>
            <option value='5'>8º Ano B</option>
            <option value='6'>9º Ano A</option>
            <option value='7'>9º Ano B</option>
            <option value='8'>1º Ano Alfa</option>
            <option value='9'>1º Ano Ômega</option>
            <option value='10'>2º Ano Alfa</option>
            <option value='11'>2º Ano Ômega</option>
            <option value='12'>3º Ano Alfa</option>
            <option value='13'>3º Ano Ômega</option>
        </select>
        <br/><br/>
        <input type="submit" value="Buscar" class='createFolder info-bg-green'>
    </form>

        <h2>Exibe dados:</h2>
        <table>
            <td>Em Tabela</td>
            <td><input type="radio" name="opcao" id="exibirTabela" value="tabela" onclick="radioButtonPressed(this)" style="float: right"> </td>
        </table>
        <!--<input type="radio" name="opcao" id="exibirGrafico" value="grafico" onclick="radioButtonPressed(this)"> Em Gráfico-->
        <br/><br/>
        <div id='tabela'>
            <table id="resultados" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>RA</th>
                            <th>Aluno</th>
                            <th>Nota</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
            <?php
                if($consulta) {
                    try {
                        $buscaNota = $conn->prepare("SELECT * FROM notas WHERE idDisc = $selectedDisciplina AND idDoc = $selectedDocente AND avaliacao = $selectedAvaliacao AND etapa = $selectedEtapa AND turma = $selectedTurma");
                        $buscaNota->execute();
            
                        $notas = $buscaNota->fetchAll();
                                    
                        foreach($notas as $notas) {
                            $idNota = $notas['idNota'];
                        }
                    } catch(PDOException $e) {
                        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                    }
                    if(!empty($idNota)) {
                        try {
                            $buscaResultados = $conn->prepare("SELECT * FROM resultados INNER JOIN alunos ON registroAluno = ra WHERE idNota = $idNota ORDER BY alunos.nomeAluno ASC");
                            $buscaResultados->execute();
                
                            $result = $buscaResultados->fetchAll();
                
                            foreach($result as $result) {
                                echo "<tr>";
                                    echo "<td>".$result['ra']."</td>";
                                    echo "<td>".$result['nomeAluno']."</td>";
                                    echo "<td>".$result['resultGrade']."</td>";
                                    echo "<td>".$result['resultPercent']."</td>";
                                echo "</tr>";
                            }
                        } catch(PDOException $e) {
                            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                        }
                    } else {
                        echo "Não localizado";
                    }
                    
                }
                
            ?>
            </table>
        </div>
        <div id='grafico'>
            <canvas id="graficoDados"></canvas>
        </div>
        <script>
            function radioButtonPressed(radio) {
                if (radio.checked) {
                    if(radio.value == 'tabela') {
                        console.log(`Radio button ${radio.value} pressed.`);
                        var tbl = document.getElementById("tabela");
                        var grf = document.getElementById("grafico");
                        tbl.style.display="block";
                        grf.style.display="none";
                    }
                    else if(radio.value == 'grafico') {
                        console.log(`Radio button ${radio.value} pressed.`);
                        var tbl = document.getElementById("tabela");
                        var grf = document.getElementById("grafico");
                        tbl.style.display="none";
                        grf.style.display="block";

                    }
                }
            }
        </script>
       </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script> 
</body>
</html>