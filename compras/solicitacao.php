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
    if((!isset ($_SESSION['obs']) == true)) {
        $obs = 0;   
    } else {
        $obs = $_SESSION['obs'];
    }

    include("conexao.php");
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Compras</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/compras.css">
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <script src="https://cdn.jsdelivr.net/npm/table-to-json@1.0.0/lib/jquery.tabletojson.min.js" integrity="sha256-H8xrCe0tZFi/C2CgxkmiGksqVaxhW0PFcUKZJZo1yNU=" crossorigin="anonymous"></script>
        
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
                <h3>Solicitação de Compras</h3>
                <br/>
                <div class="produtos">
                    <div class="select-box">
                        <div class="options-container">

                            <?php
                                try {
                                    $buscaProd = $conn->prepare("SELECT * FROM produtos");
                                    $buscaProd->execute();
    
                                    $produtos = $buscaProd->fetchAll();
                                                       
                                    foreach($produtos as $produtos) {
                                        echo "<div class='option'>";
                                            echo "<input type='radio' class='radio' id='".$produtos['codigo']."' name='itens' data-cod='".$produtos['codigo']."' 
                                            data-nome='".$produtos['nome']."' data-descricao='".$produtos['descricao']."' data-unmedida='".$produtos['unMedida']."' 
                                            data-regpreco='".$produtos['regPreco']."' data-gisu='".$produtos['gisu']."'/>
                                            <label for='".$produtos['codigo']."'>'".$produtos['codigo']."-".$produtos['nome']."-".$produtos['descricao']."</label>";
                                        echo "</div>";
                                    }
                                } catch(PDOException $e) {
                                    die("Erro ao conectar ao banco de dados :" . $e->getMessage());
                                }
                                ?>
                        </div>
                        <div class="selected">
                            Selecione o produto
                        </div>

                        <div class="search-box">
                            <input type="text" placeholder="Digite para pesquisar..."/>
                        </div>
                    </div>
                    <br/>
                    <b>Unidade de Medida:</b>
                    <input type="text" name='uni'>
                    <br/>
                    <b>Quantidade:</b><input type="number" name='quant'>
                    <br/>
                    <b>Aplicação:</b><input type="text" name='aplic'>
                    <button id="incrementCount" class="counter-button">
                        <span class="icon-button-icon-content">
                            <i class ="fas fa-plus-square"></i></a>
                            <span class="icon-button-text-content">Adicionar</span>
                        </span>
        
                    </button>
                <br/>
                <br/>
                <table id="tabela" class="tabela">
                    <thead>
                        <th>Codigo</th>
                        <th>Nome</th>
                        <th>Descricao</th>
                        <th>Un. Medida</th>
                        <th>Reg. Preço</th>
                        <th>GISU</th>
                        <th>Quantidade</th>
                        <th>Aplicação</th>
                    </thead>
                    <tbody>
                    </tbody>
                </table>       
                <button id="incrementCount" class="criar">
                        <span class="icon-button-icon-content">
                            <i class ="fas fa-plus-square"></i></a>
                            <span class="icon-button-text-content">Criar Pedido</span>
                        </span>
        
                    </button>       
            </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="../js/menu.js"></script>
        <script>
            function unidade() {
                const box = document.querySelector('input[value=parcial]');
            }
        </script>
        <script>
            const selected = document.querySelector(".selected");
            const optionsContainer = document.querySelector(".options-container");
            const searchBox = document.querySelector(".search-box input");

            const optionsList = document.querySelectorAll(".option");

            selected.addEventListener("click", () => {
            optionsContainer.classList.toggle("active");

            searchBox.value = "";
            filterList("");

            if (optionsContainer.classList.contains("active")) {
                searchBox.focus();
            }
            });

            optionsList.forEach(o => {
            o.addEventListener("click", () => {
                selected.innerHTML = o.querySelector("label").innerHTML;
                optionsContainer.classList.remove("active");
            });
            });

            searchBox.addEventListener("keyup", function(e) {
            filterList(e.target.value);
            });

            const filterList = searchTerm => {
            searchTerm = searchTerm.toLowerCase();
            optionsList.forEach(option => {
                let label = option.firstElementChild.nextElementSibling.innerText.toLowerCase();
                if (label.indexOf(searchTerm) != -1) {
                option.style.display = "block";
                } else {
                option.style.display = "none";
                }
            });
            };
        </script>
        <script>
            $(document).ready(function() { 
                $('.options-container').change(function() {
                    var unidade = document.querySelector('input[name="itens"]:checked').getAttribute('data-unmedida');
                    document.querySelector('input[name="uni"]').setAttribute('value',unidade);
                    document.querySelector('input[name="quant"]').setAttribute('value',"");
                    document.querySelector('input[name="aplic"]').setAttribute('value',"");
                    console.log(unidade);                    
                });
            });
            $(document).ready(function() { 
                $('button.counter-button').click(function() { 
                    var codigo = document.querySelector('input[name="itens"]:checked').getAttribute('data-cod');
                    var nome = document.querySelector('input[name="itens"]:checked').getAttribute('data-nome');
                    var descricao = document.querySelector('input[name="itens"]:checked').getAttribute('data-descricao');
                    var unidade = document.querySelector('input[name="itens"]:checked').getAttribute('data-unmedida');
                    var regpreco = document.querySelector('input[name="itens"]:checked').getAttribute('data-regpreco');
                    var gisu = document.querySelector('input[name="itens"]:checked').getAttribute('data-gisu');
                    var quanti = document.querySelector('input[name="quant"]').value;
                    var aplic = document.querySelector('input[name="aplic"]').value;

                    var rows = "";
                    
                        if(regpreco == 'S') {
                            rows += "<tr style='color:red !important'><td>" + codigo + "</td><td>" + nome + "</td><td>" + descricao + "</td><td>" + unidade + "</td><td>" + regpreco + "</td><td>" + gisu + "</td><td>" + quanti + "</td><td>" + aplic + "</td></tr>";
                            $(rows).appendTo("#tabela tbody");
                            
                        } else {
                            rows += "<tr><td>" + codigo + "</td><td>" + nome + "</td><td>" + descricao + "</td><td>" + unidade + "</td><td>" + regpreco + "</td><td>" + gisu + "</td><td>" + quanti + "</td><td>" + aplic + "</td></tr>";
                            $(rows).appendTo("#tabela tbody");
                            
                        }
                    });
                });
        </script>
        <script type="text/javascript">
            $(document).ready(function() { 
                $('button.criar').click( function() {
                var table = $('#tabela').tableToJSON();

                $.ajax({
                    type : "POST",  //type of method
                    url  : "solicitar.php",
                    data : { table },// passing the values
                    success: function(res){
                        console.log(res);  
                        if(res.status === 'sucess') {
                            console.log("ok");
                        }    
                    }
                });
                });
    });
        </script>
    </body>
</html>