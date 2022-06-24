<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require '../vendor/autoload.php';

    $nivel = $_SESSION['group'];

    $funcao = $_GET['cod'];
    $usuario = $_GET['sol'];
    $lab = $_GET['lab'];
    $data = $_GET['dt'];

    //separar em arquivo de função
    //busca usuario
    include('conexaounidade.php');
    try {
        $buscaNome = $conn->prepare("SELECT * FROM funcionarios WHERE RTRIM(usuario) =".$usuario."");
        $buscaNome->execute();

        $nome = $buscaNome->fetchAll();

        foreach($nome as $nome) {
            $rcpt = $nome['nome'];
        }
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }

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
            $mail->addAddress(str_replace( "'", "", $usuario)."@fiemg.com.br", $rcpt);
    
            $mail->isHTML(true);
            $mail->Subject = $sub;
            $mail->Body    = $msg;

            $mail->send();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    if($funcao == 1) { //solicitação - solicitante e supervisao
        $subSol = "Solicitação de Reserva de Laboratório";
        $msgSol = "Olá, " . $rcpt . "! <br/>Sua solicitação de reserva do laboratório ".strtoupper($lab)." para o dia ".$data." foi realizada com sucesso. <br/> Aguarde aprovação da solicitação pela supervisão.
        Você pode conferir mais detalhes sobre a sua reserva na tela inicial do sistema de reservas.<br/><br/><br/>Atenção: este é um e-mail automático. Por favor não responda.";
        enviaEmail($usuario,$rcpt,$subSol,$msgSol);
        $subSup = "Nova Solicitação de Reserva";
        $msgSup = "Olá! <br/>Uma nova solicitação de laboratório foi feita e está pendente de aprovação.";
        $sup1 = 'asrocha';
        $sup2 = 'frgoncalves';
        $sup3 = 'iprata';
        $sup4 = 'ascustodio';
        $sup5 = 'cleide.souza';
        $sup6 = 'ivina';
        $sup7 = 'thiago';
        enviaEmail($sup1,$rcpt,$subSup,$msgSup);
        enviaEmail($sup2,$rcpt,$subSup,$msgSup);
        enviaEmail($sup3,$rcpt,$subSup,$msgSup);
        enviaEmail($sup4,$rcpt,$subSup,$msgSup);
        enviaEmail($sup5,$rcpt,$subSup,$msgSup);
        enviaEmail($sup6,$rcpt,$subSup,$msgSup);
        enviaEmail($sup7,$rcpt,$subSup,$msgSup);
    }

        if($nivel == '4' || $nivel == '3' || $nivel == '9') {
            header('location:supervisao.php');
        } else {
            header('location:principal.php');
        }