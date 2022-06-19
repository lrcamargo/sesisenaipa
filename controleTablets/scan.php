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
        
        <title>Controle de Entrega Tablets</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        
        <!--script câmera interno-->
        <script src="https://cdn.rawgit.com/serratus/quaggaJS/0420d5e0/dist/quagga.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/webcomponentsjs/2.4.3/webcomponents-bundle.js"></script>
        <!--mudar local - arquivo externo-->
        <style>
            canvas.drawing, canvas.drawingBuffer {
                position: absolute;
                left: 0;
                top: 0;
            }
        </style>
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
                <div>
                    
                </div>
                <br/>
                <div id="scanner-container" style="float:left"></div>
                <input type="button" id="btn" value="Iniciar/parar scan" />
                    <input type="button" id="submit" value="Finalizar entrega" />
                    <!-- Include the image-diff library -->
                <script src="https://cdn.rawgit.com/serratus/quaggaJS/0420d5e0/dist/quagga.min.js"></script>

            
            <div style="float:rigth">
                <ul id="lista" style="float:rigth"></ul>
            </div>

            </div>
        <!--Fim wrapper-->
        <!-- script transformar em externo -->
        <script>
            var _scannerIsRunning = false;
            var lista = [];
            var posicao = 0;

            function startScanner() {
                Quagga.init({
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.querySelector('#scanner-container'),
                        constraints: {
                            width: 480,
                            height: 320,
                            facingMode: "environment"
                        },
                    },
                    decoder: {
                        readers: [
                            "i2of5_reader"
                        ],
                        debug: {
                            showCanvas: true,
                            showPatches: true,
                            showFoundPatches: true,
                            showSkeleton: true,
                            showLabels: true,
                            showPatchLabels: true,
                            showRemainingPatchLabels: true,
                            boxFromPatches: {
                                showTransformed: true,
                                showTransformedBox: true,
                                showBB: true
                            }
                        }
                    },

                }, function (err) {
                    if (err) {
                        console.log(err);
                        return
                    }

                    console.log("Initialization finished. Ready to start");
                    Quagga.start();

                    // Set flag to is running
                    _scannerIsRunning = true;
                });

                Quagga.onProcessed(function (result) {
                    var drawingCtx = Quagga.canvas.ctx.overlay,
                    drawingCanvas = Quagga.canvas.dom.overlay;

                    if (result) {
                        if (result.boxes) {
                            drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
                            result.boxes.filter(function (box) {
                                return box !== result.box;
                            }).forEach(function (box) {
                                Quagga.ImageDebug.drawPath(box, { x: 0, y: 1 }, drawingCtx, { color: "green", lineWidth: 2 });
                            });
                        }

                        if (result.box) {
                            Quagga.ImageDebug.drawPath(result.box, { x: 0, y: 1 }, drawingCtx, { color: "#00F", lineWidth: 2 });
                        }

                        if (result.codeResult && result.codeResult.code) {
                            Quagga.ImageDebug.drawPath(result.line, { x: 'x', y: 'y' }, drawingCtx, { color: 'red', lineWidth: 3 });
                        }
                    }
                });

                Quagga.onDetected(function (result) {
                    console.log("Barcode detected and processed : [" + result.codeResult.code + "]", result);
                    if(result.codeResult.code.substring(0,3) == '000') {
                        //teste valor vetor
                        if(result.codeResult.code == lista[posicao-1]) {
                            console.log("Do not record");
                        } else {
                            posicao=posicao+1;
                            lista.push(result.codeResult.code);
                            console.log(lista);
                            if(posicao%2==0) {
                                $("#lista").append('<li> Tablet:'+ result.codeResult.code + '</li>');
                            } else {
                                $("#lista").append('<li> Aluno:'+ result.codeResult.code + '</li>');
                            }
                            
                        }
                    }                    
                });
            }


            // Start/stop scanner
            document.getElementById("btn").addEventListener("click", function () {
                if (_scannerIsRunning) {
                    Quagga.stop();
                } else {
                    startScanner();
                }
            }, false);

            //salva tablets
            
            document.getElementById("submit").addEventListener("click", function () {
                var jsonString = JSON.stringify(lista);
                $.ajax({
                    type: "POST",
                    url: "regTablets.php",
                    data: {data: jsonString},
                    success: function(response){
                        var jsonData = JSON.parse(response);
                        //alert(jsonData);
                        console.log(jsonData);
                        console.log(response);
                    }
                });
            }, false);
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
    </body>
</html>