<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    //ini_set('display_errors', 1);
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

    //$attributes = array("displayname", "mail", "samaccountname"); 
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Teste Links - Tela Professor</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
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
                Listar links
                <br/>
                <?php

                $servidor_AD = "192.168.254.2";
                $dominio = "sesisenai.br";

                // Conexão com servidor AD. 
                $ad = ldap_connect($servidor_AD)
                or die("Não foi possível realizar conexão com o servidor. Contacte o administrador do sistema.");

                // Versao do protocolo       
                ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
                // Usar as referencias do servidor AD, neste caso nao
                ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);

                $usuario = "administrator";
                $senha = "SenaiP@";
                $bd = ldap_bind($ad, $usuario."@".$dominio, $senha )
                or die("Usuário ou senha incorretos.");

                function busca_doc() {

                    global $logado, $ad;
            
                    $dn = "OU=Docentes,OU=Educacao,dc=sesisenai,dc=br";
                    $filter = "(&(objectCategory=person))";
                    $attrs = array("givenname");
            
                    $result = ldap_search($ad, $dn, $filter, $attrs);
                    $entries = ldap_get_entries($ad, $result);
                    $i = 0;
                    foreach($entries as $entry) {
                        $teste = explode("=",$entry["dn"]);
                        $i=$i+1;
                        $result = explode(",",$teste[1]);
                        //echo $result[0];
                        echo "<br/>";
                        echo "<a href='#modalCreate' class='getLinks info-bg-green get_data' name='getLinks' id='getLinks'>".$result[0]."</a>";
                        echo "<br/>";
                    }
                    echo "<br/>";
                    echo "<a href='#modalCreate' class='getLinks info-bg-green get_data' name='getLinks' id='getLinks'>Administrator</a>";
                }                    
                function busca_link() {
                    require("conexaoTeste.php");
                    try {
                        $buscaLink = $conn->prepare("SELECT * FROM linksAulas");
                                                
                        $buscaLink->execute();
                
                        $buscaLinks = $buscaLink->fetchAll();
                        foreach ($buscaLinks as $buscaLinks) {
                            echo "<a href='".$buscaLinks['link']."'>".$buscaLinks['nome']."</a>";
                            echo "<br/>";
                        }
                        
                    } catch (PDOException $e) {
                            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
                    }
                }
                busca_doc();

                
                
                ?>
                
            </div>
        </div>
        <!--Fim wrapper-->
        <div id="modalCreate" class="modalDialog">
            <div>
                <a href="#close" title="Close" class="close">X</a>
                <div class="modal-content">
						<div class="modal-header">
							<h4 class="modal-title"><span id="change-title">Botao</span></h4>
						</div>
						<div class="modal-body">
                        
					    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).on("click", ".get_data", function () {
                var user = this.innerHTML
                console.log(teste);
            })
        </script>
        <script type="text/javascript" src="../js/menu.js"></script>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
        <script type="text/javascript" src="../js/camera.js"></script>
    </body>
</html>