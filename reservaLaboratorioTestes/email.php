<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require '../vendor/autoload.php';

    $nivel = $_SESSION['group'];

    $funcao = $_GET['cod'];
    $busca = $_GET['id'];

    include('conexaoteste.php');

    try{
        $buscaDados = $conn->prepare("SELECT * FROM reservalabs WHERE id = ".$busca);
        $buscaDados->execute();
            
        $dados = $buscaDados->fetchAll();
        foreach($dados as $dados) {
            $usuario = $dados['solicitante'];
            $lab = $dados['laboratorio'];
            $data = $dados['data'];            
        }
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }

    //renomeia lab
    if($lab == 1) {
        $laboratorio = '201A';
    } else if($lab == 2) {
        $laboratorio = '202A';
    } else if($lab == 3) {
        $laboratorio = '203A';
    } else if($lab == 4) {
        $laboratorio = '101B';
    } else if($lab == 5) {
        $laboratorio = '103b';
    } else if($lab == 6) {
        $laboratorio = '104b';
    } else if($lab == 7) {
        $laboratorio = '105b';
    } else if($lab == 8) {
        $laboratorio = '106b';
    } else if($lab == 9) {
        $laboratorio = '107b';
    } else if($lab == 10) {
        $laboratorio = '101a';
    } else if($lab == 11) {
        $laboratorio = 'cnc';
    } else if($lab == 12) {
        $laboratorio = 'lego';
    } 
    /*$usuario = $_GET['sol'];
    $lab = $_GET['lab'];
    $data = $_GET['dt'];
    $idRes = $_GET['idres'];*/
    //separar em arquivo de função
    //busca usuario

    //enviaEmail($usuario,$rcpt,$sub,$msg)
    function enviaEmail($usuario,$rcpt,$sub,$msg) {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sesisenaipa@gmail.com';
            $mail->Password   = 'ajqcbzizwwdkhyxk';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
    
            $mail->setFrom('sesisenaipa@gmail.com', 'Sistema Reservas');
            //$mail->addAddress(str_replace( "'", "", $usuario)."@fiemg.com.br", $rcpt);
            $mail->addAddress($usuario."@fiemg.com.br", $rcpt);
    
            $mail->isHTML(true);
            $mail->Subject = $sub;
            $mail->Body    = $msg;

            $mail->send();
            
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        if($nivel == '4' || $nivel == '3' || $nivel == '9') {
            header('location:supervisao.php');
        } else {
            header('location:principal.php');
        }
    }

    if($funcao == 1) { //solicitação - solicitante e supervisao
        include('conexaounidade.php');
        try {
            $buscaNome = $conn->prepare("SELECT * FROM funcionarios WHERE RTRIM(usuario) ='".$usuario."'");
            $buscaNome->execute();

            $nome = $buscaNome->fetchAll();

            foreach($nome as $nome) {
                $rcpt = $nome['nome'];
            }
        } catch(PDOException $e) {
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
        $subSol = "Solicitação de Reserva de Laboratório";
        $msgSol = "Olá, " . $rcpt . "! <br/>Sua solicitação de reserva do laboratório ".strtoupper($laboratorio)." para o dia ".$data." foi realizada com sucesso. <br/> Aguarde aprovação da solicitação pela supervisão.
        Você pode conferir mais detalhes sobre a sua reserva na tela inicial do sistema de reservas.<br/><br/><br/>Atenção: este é um e-mail automático. Por favor não responda.";
        enviaEmail($usuario,$rcpt,$subSol,$msgSol);
        $subSup = "Nova Solicitação de Reserva";
        $msgSup = "Olá! <br/>Uma nova solicitação de laboratório foi feita e está pendente de aprovação.";
        /*$sup1 = 'asrocha';
        $sup2 = 'frgoncalves';
        $sup3 = 'iprata';
        $sup4 = 'ascustodio';
        $sup5 = 'cleide.souza';
        $sup6 = 'ivina';*/
        $sup7 = 'lrcamargo';
        /*enviaEmail($sup1,$rcpt,$subSup,$msgSup);
        enviaEmail($sup2,$rcpt,$subSup,$msgSup);
        enviaEmail($sup3,$rcpt,$subSup,$msgSup);
        enviaEmail($sup4,$rcpt,$subSup,$msgSup);
        enviaEmail($sup5,$rcpt,$subSup,$msgSup);
        enviaEmail($sup6,$rcpt,$subSup,$msgSup);*/
        enviaEmail($sup7,$rcpt,$subSup,$msgSup);
    } else if($funcao == 2) {
        include('conexaounidade.php');
        try {
            $buscaNome = $conn->prepare("SELECT * FROM funcionarios WHERE RTRIM(usuario) ='".$usuario."'");
            $buscaNome->execute();

            $nome = $buscaNome->fetchAll();

            foreach($nome as $nome) {
                $rcpt = $nome['nome'];
            }
        } catch(PDOException $e) {
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
        $sub = "Alteração de status";
        $msg = "Olá, " . $rcpt . "!<br/>O estado da solicitação ".$busca." foi alterado pela supervisão. <br/> De acordo com as demandas a reserva pode ter 
        sofrido alterações, então confira atentamente os detalhes da sua reserva antes do uso. <br/>Em caso de dúvidas ou alguma necessidade específica procurar a supervisão para maiores esclarecimentos.<br/><br/>Atenção: este é um e-mail automático. Por favor não responda.";
        enviaEmail($usuario,$rcpt,$sub,$msg);

        if($nivel == '4' || $nivel == '3' || $nivel == '9') {
            header('location:supervisao.php');
        } else {
            header('location:principal.php');
        }
    } else if($funcao=3) {
        
    }
        //function redireciona() {
            
        //}
?>