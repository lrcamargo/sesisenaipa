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
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Câmera</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/camera.css">
        
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
                <div class="area">
                    <div class="webCamera">
                        <video autoplay="true" id="webCamera">
                        </video>
                    </div>
                    <div class="form">
                        <form target="POST" style="float:left;">
                            <textarea  type="text" id="baseImg" name="baseImg" style="display:none"></textarea>
                            Turma: <div id="turma" class="turma" name="turma"></div>
                            <br/>
                            <div> 
                                <a href="#modalCreate" class="createFolder info-bg-green" name="createFolder" id="createFolder"><i class="fas fa-plus-square"></i> Criar</a>
                            </div>
                            <br/>
                            Aluno: 
                            <br/>
                            <input type="text" id="nomeArquivo" name="nomeimagem" class="nomeArquivo">
                            <br/>
                            <br/>
                            <a href="#" class="takeSnapshot info-bg-faux" onclick="takeSnapShot()"><i class="fas fa-camera-retro"></i> Tirar foto</a>
                            <br/>
                            <br/>
                            <a href="#" class="delete info-bg-purple" name="delete" id="delete"><i class="far fa-trash-alt"></i> Deletar</a>   
                            <br/>
                            <br/>
                            Turno:
                            <br/>
                            <div>
                                <input type="radio" id="manha" name="turno" value="manha" style="float:left;width:20px;height:20px"><label for="manha" style="float:left">Manhã</label>
                                <br/>
                                <input type="radio" id="tarde" name="turno" value="tarde" style="float:left;width:20px;height:20px"><label for="tarde" style="float:left">Tarde</label>
                                <br/>
                                <input type="radio" id="noite" name="turno" value="noite" style="float:left;width:20px;height:20px"><label for="noite" style="float:left">Noite</label>
                                <br/>
                                <input type="radio" id="integral" name="turno" value="integral" style="float:left;width:20px;height:20px"><label for="integral" style="float:left">Integral</label>
                            </div>
                            <br/>
                            <!--<div>
                                <br/>
                                <input type="radio" id="nova" name="data" value="nova" style="float:left;width:20px;height:20px"><label for="tarde" style="float:left">Data nova</label>
                            </div>-->
                            <br/>
                            <a href="#" class="gerar info-bg-deepgreen" onclick="teste();"><i class="far fa-id-card"></i> Gerar</a>   
                        </form>
                    </div>
                
                <div>
                    <img id="imagemConvertida" class="imagemConvertida"/>
                    <p id="caminhoImagem" class="caminhoImagem" style="display: none;"><a href="" target="_blank" style="display: none;"></a></p>
                </div>
            </div>
        <div>
        <!--Fim wrapper-->

        <div id="modalCreate" class="modalDialog">
            <div>
                <a href="#close" title="Close" class="close">X</a>
                <div class="modal-content">
						<div class="modal-header">
							<h4 class="modal-title"><span id="change-title">Criar pasta</span></h4>
						</div>
						<div class="modal-body">
							<p>Coloque o nome da pasta:
							<input type="text" name="folder_name" id="folder_name" class="form-control"/></p>
							<br/>
							<input type="hidden" name="action" id="action"/>
							<input type="hidden" name="old_name" id="old_name"/>
							<input type="button" name="folder_button" id="folder_button" class="createFolder info-bg-green modalCreate" value="Criar"/>
					    </div>
                </div>
            </div>
        </div>

        <!--Scripts-->
        <script>
            function teste() {
                var turma = document.getElementById("listaTurma");
                var nome = document.getElementById("nomeArquivo");
                var turno = document.querySelector('input[name="turno"]:checked').value;
                /*if (document.getElementById("nova").checked) {
                    var data = "true";
                } else {
                    var data = "false";
                }*/
                var data = "false";
                var url = "importNow.php?codT=" + turma.value + "&nome=" + nome.value + "&turno=" + turno + "&data=" + data;
                console.log(url);
                window.open(url);
            };
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
		<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
        <script type="text/javascript" src="../js/camera.js"></script>
    </body>
</html>