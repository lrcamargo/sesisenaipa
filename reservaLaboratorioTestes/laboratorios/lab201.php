<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    include("../../conexaosec.php");
    session_start();
    
    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../../index.php');    
    }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Laboratório 201</title>
        
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
        <link rel="stylesheet" href="../../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../../css/telefone.css">
        <link rel='stylesheet' href='../../fullcalendar/main.min.css'/>
        
        <script src='../../fullcalendar/main.min.js'></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>   
        
        <!-- colocar em script a parte -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'pt-br',
                    timeZone: 'local',
                    initialView: 'dayGridMonth',
                    validRange: function() {
                        var max = new Date();
                        max.setDate(max.getDate() + 30); 
                        console.log(max);
                        return {end:  max}
                    },
                    dayMaxEventRows: true,
                    eventDisplay: 'block',
                    duration: { month: 2 },
                    events: { url: '../listareservas.php?lab=1',
                        failure: function() {
                            alert('Houve um erro ao buscar os eventos!');
                        }},
                    dateClick: function(info) {
                        var today = new Date();
                        var date = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2, '0')+'-'+String(today.getDate()).padStart(2, '0');
                        if(info.dateStr<date) {
                            alert("Data escolhida: "+ info.dateStr + "\nOps, ainda não temos um DeLorean, então você não pode voltar no tempo para fazer essa reserva.\nEscolha outra data.");
                        } else {
                            let array = info.dateStr.split("-");
                            let dataSel = `${array[2]}-${array[1]}-${array[0]}`;
                            $("#reservaModal #data").text(dataSel);
                            document.querySelector('input[name=dataInp]').setAttribute('value',info.dateStr);
                            $("#reservaModal").modal();
                        }
                    },
                    eventClick: function(info) {
                        $("#dadosModal #dadosModalLabel").text(info.event.title);
                        let starting = info.event.start.toString();
                        let inicio = starting.split(" ");
                        $("#dadosModal #inicio").text(inicio[4]);
                        let ending = info.event.end.toString();
                        let fim = ending.split(" ");
                        $("#dadosModal #fim").text(fim[4]);
                        if(info.event.extendedProps.status == 0) {
                            $("#dadosModal #status").text("Aguardando aprovação");    
                        } else if (info.event.extendedProps.status == 1) {
                            $("#dadosModal #status").text("Aprovado"); 
                        } 
                        $("#dadosModal").modal();
                    }
                });
                calendar.render();
            });
            
            </script>
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
                        <li><a href="../../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
                    </ul>
                </div>
            </div>
        <!-- Fim Cabeçalho -->
        <!--Inicio sidebar-->
            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('../../menu.php'); ?>
                </div>
            </div>
        <!--Fim sidebar-->
        <!--Inicio conteúdo-->
            <div class="main-container">
                <div id='calendar'></div>
            </div>
        <!--Fim wrapper-->
        <!--Modal Cadastro-->
        <div class="modal fade" id="reservaModal" tabindex="-1" aria-labelledby="reservaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservaModalLabel">Solicitar Reserva</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../solicita.php">
                <b>Data</b>:<br/><span id="data" name="data"></span><br/><br/><input type="hidden" name="dataInp" value=""></input>
                <b>Turno:</b>
                <div>
                    <input type="radio" id="manha" name="turno" value="manha" style="float:left;width:20px;height:20px"><label for="manha" style="float:left">Manhã</label>
                    <input type="radio" id="tarde" name="turno" value="tarde" style="float:left;width:20px;height:20px;margin-left:5px"><label for="tarde" style="float:left">Tarde</label>
                    <input type="radio" id="noite" name="turno" value="noite" style="float:left;width:20px;height:20px;margin-left:5px"><label for="noite" style="float:left">Noite</label>
                </div><br/><br/>
                <b>A reserva ocupará todo o turno ou apenas um período do dia?</b><br/>
                <input type="radio" name="periodo" value="parcial" onChange="horarios()">Só um período
                <input type="radio" name="periodo" value="todo" onChange="horarios()">Todo o turno
                <br/>
                <b><span name="hInicio" style="display:none">Início: </span></b><input id="horainicio" name="horainicio" type="time" style="display:none">
                <b><span name="hFim" style="display:none">Fim: </span></b><input id="horafim" name="horafim" type="time" style="display:none"> <br/>
                <b>Turma:</b><br/>
                <select name="turma">
                    <?php
                        try {
                            $buscaTurmas = $conn->prepare("SELECT id,descricao FROM niveis");
                            $buscaTurmas->execute();

                            $turmas = $buscaTurmas->fetchAll();
                                        
                            foreach($turmas as $turmas) {
                                echo "<option value='".$turmas['descricao']."'>".$turmas['descricao']."</option>";
                            }
                        } catch(PDOException $e) {
                            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                        }
                    ?>
                </select>
                    <?php
                        if($nivel == '4' || $nivel == '3' || $nivel == '9') {
                            echo "<br/>";
                            echo "<br/>";
                            echo "<b>Solicitante:</b><br/>";
                            echo "<select name='solicitante'>";
                            include("../conexaounidade.php");
                                try {
                                    $buscaSolicitante = $conn->prepare("SELECT * FROM funcionarios");
                                    $buscaSolicitante->execute();

                                    $solicitante = $buscaSolicitante->fetchAll();
                                                   
                                    foreach($solicitante as $solicitante) {
                                        echo "<option value='".$solicitante['usuario']."'>".$solicitante['nome']."</option>";
                                    }
                                } catch(PDOException $e) {
                                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                                }
                            echo "</select>";
                        } else {
                            echo "<input type='hidden' name='solicitante' value=<?php echo ".$logado."; ?></input>";
                        }
                    ?>
                <input type="hidden" name="lab" value="201a"></input>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button submit" class="btn btn-primary">Solicitar</button>
            </div>
            </form>
            </div>
        </div>
        </div>
        <!--Modal Lista Info-->
        <div class="modal fade" id="dadosModal" tabindex="-1" aria-labelledby="dadosModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" name="titulo" id="dadosModalLabel"></h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        
                        <b>Horário de início</b>:<br/><span id="inicio" name="inicio"></span><br/><br/>
                        <b>Horário de término</b>:<br/><span id="fim" name="fim"></span><br/><br/>
                        <b>Status</b>:<br/><span id="status" name="status"></span><br/><br/>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function horarios() {
                const box = document.querySelector('input[value=parcial]');
                if(box.checked==true) {
                    document.querySelector('span[name=hInicio]').style.display = "block";
                    document.querySelector('span[name=hFim]').style.display = "block";
                    document.querySelector('input[name=horainicio]').style.display = "block";
                    document.querySelector('input[name=horafim]').style.display = "block";
                } else {
                    document.querySelector('span[name=hInicio]').style.display = "none";
                    document.querySelector('span[name=hFim]').style.display = "none";
                    document.querySelector('input[name=horainicio]').style.display = "none";
                    document.querySelector('input[name=horafim]').style.display = "none";
                }
            }
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>