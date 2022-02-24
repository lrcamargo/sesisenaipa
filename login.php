<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    session_start();

    $usuario = $_POST['user'];
    $senha = $_POST['pass'];
    $valor = 0;
    
    //substr($usuario,0,1) == '0'
    if (is_numeric($usuario[0])) {
        acessoExterno($usuario,$senha);
    } else {
        //Endereco do servidor AD IP ou nome
        $servidor_AD = "192.168.254.2";
        //Domínio
        $dominio = "sesisenai.br";

        // Conexão com servidor AD. 
        $ad = ldap_connect($servidor_AD)
        or die("Não foi possível realizar conexão com o servidor. Contacte o administrador do sistema.");

        // Versao do protocolo       
        ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
        // Usar as referencias do servidor AD, neste caso nao
        ldap_set_option($ad, LDAP_OPT_REFERRALS, 0);

        //CHECA SE USUÁRIO EXISTE
        // Bind to the directory server.
        $bd = ldap_bind($ad, $usuario."@".$dominio, $senha )
        or die("Usuário ou senha incorretos.");   

        function valida_inst() {

            global $usuario, $ad;

            $dn = "ou=Instrutores,ou=Docentes,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);

            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }

        function valida_prof() {

            global $usuario, $ad;

            $dn = "ou=Professores,ou=Docentes,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);


            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }
        function valida_sup() {

            global $usuario, $ad;

            $dn = "ou=Supervisao,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);


            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }

        function valida_biblio() {

            global $usuario, $ad;

            $dn = "ou=Biblioteca,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);


            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }

        function valida_ger() {

            global $usuario, $ad;

            $dn = "ou=Gerencia,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);


            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }

        function valida_sec() {

            global $usuario, $ad;

            $dn = "ou=Secretaria,ou=Educacao,dc=sesisenai,dc=br";
            $filter = "(samAccountName=".$usuario.")";
            $attrs = array("memberOf");

            $result = ldap_search($ad, $dn, $filter, $attrs);
            $entries = ldap_get_entries($ad, $result);


            if(isset($entries)){
                foreach($entries as $grps){
                    if(empty($entries[0])) {
                        return $valor = 0;
                    } else {
                        return $valor = 1;
                    } 
                } 
            }
        }

        function valida_adm() {

                global $usuario, $ad;

                $dn = "ou=Administrativo,ou=Educacao,dc=sesisenai,dc=br";
                $filter = "(samAccountName=".$usuario.")";
                $attrs = array("memberOf");

                $result = ldap_search($ad, $dn, $filter, $attrs);
                $entries = ldap_get_entries($ad, $result);


                if(isset($entries)){
                    foreach($entries as $grps){
                        if(empty($entries[0])) {
                            return $valor = 0;
                        } else {
                            return $valor = 1;
                        } 
                    } 
                }
            }

        function logToFile($msg) 
        {  
        $fd = fopen('useLog.txt', "a") or die("Unable to open file!");
        $str = "[" . date("Y/m/d H:i:s", mktime()) . "] " . $msg;
        fwrite($fd, $str . "\n"); 
        fclose($fd); 
        } 

        ////CHECA OU DO USUARIO
        if( $bd ){
            if(valida_inst() == 1) {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "1";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php");
            } else if(valida_prof() == 1) {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "2";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.html");
            } else if(valida_sup() == 1) {
                if($usuario == 'thiago') {
                    $_SESSION['sLogin'] = "1";
                    $_SESSION['user'] = $usuario;
                    $_SESSION['group'] = "3";
                    $log = 'Usuário '.$usuario.' logado.';
                    logToFile($log);
                    header("location: main.php"); 
                } else {
                    $_SESSION['sLogin'] = "1";
                    $_SESSION['user'] = $usuario;
                    $_SESSION['group'] = "4";
                    $log = 'Usuário '.$usuario.' logado.';
                    logToFile($log);
                    header("location: main.php");
                }
            } else if(valida_biblio() == 1){
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "5";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php");
            } else if(valida_ger() == 1) {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "6";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php");
            } else if(valida_sec() == 1) {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "7";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php");
            } else if(valida_adm() == 1) {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "8";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php");
            } else if($usuario == 'administrator') {
                $_SESSION['sLogin'] = "1";
                $_SESSION['user'] = $usuario;
                $_SESSION['group'] = "9";
                $log = 'Usuário '.$usuario.' logado.';
                logToFile($log);
                header("location: main.php"); 
            } else {
                session_unset();
                session_destroy();
                $log = 'Tentativa de login do usuário '.$usuario.' falhou.';
                logToFile($log);
                echo "Não pode logar";
            }
            //header("Location: construcao.html");
        }else{
            echo "Nao Conectado no servidor";
        }

        //Fecha conexao
        ldap_unbind($ad);
    }

    function acessoExterno($usuario,$senha) {
        require("conexaosec.php");
        try {
            $buscaUser = $conn->prepare("SELECT TOP 1 * FROM dbo.pessoas WHERE n_identificador = ".$usuario);
                                    
            $buscaUser->execute();

            $listaUser = $buscaUser->fetch();
                     
            if(is_array($listaUser)) {
                if($listaUser['web_senha'] == $senha) {
                    $_SESSION['sLogin'] = "0";
                    $_SESSION['user'] = $listaUser['nome'];
                    $_SESSION['id'] = $listaUser['id'];
                    $_SESSION['ra'] = $listaUser['n_identificador'];
                    $_SESSION['codNivel'] = $listaUser['nivel_id'];
                    $_SESSION['obs'] = $listaUser['obs'];
                    $_SESSION['group'] = "0";
                    header("location: main.php"); 
                } else {
                    echo "Não Pode Logar";
                }     
            } else {
                echo "Não Pode Logar";
            }      
        } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
    }
?>