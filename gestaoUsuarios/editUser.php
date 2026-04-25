<?php
/*
 * editUser.php
 * Atualiza dados do usuário.
 * E-mail de notificação enviado APENAS quando a senha for alterada.
 * Senha é opcional — campo vazio = mantém a atual.
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$id          = intval(trim($_POST['id']       ?? 0));
$registro    = trim($_POST['registro']        ?? '');
$nome        = trim($_POST['nome']            ?? '');
$apelido        = trim($_POST['apelido']            ?? '');
$email       = trim($_POST['email']           ?? '');
$usuarioNovo = trim($_POST['user']            ?? '');
$senha       = $_POST['senha']                ?? '';
$perfil      = trim($_POST['perfil']          ?? '');

if(!$id || !$registro || !$nome || !$usuarioNovo || !$perfil){
    header("Location: editarUsuario.php?id={$id}&msg=erro_vazio");
    exit;
}

/* ── BUSCA DADOS ATUAIS ── */

$stmt = $pdo->prepare("SELECT email FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$atual = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$atual){
    header("Location: editarUsuario.php?msg=nao_encontrado");
    exit;
}

$emailAtual  = $atual['email'];
$trocouSenha = !empty(trim($senha));

/* ── UPDATE ── */

try{

    if($trocouSenha){
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET nome=?, usuario=?, apelido=?, email=?, senha=?,
                perfil=?, registro=?, primeiro_login=1
            WHERE id=?
        ");
        $stmt->execute([$nome, $usuarioNovo, $apelido, $email, $senhaHash, $perfil, $registro, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET nome=?, usuario=?, email=?, apelido=?,
                perfil=?, registro=?
            WHERE id=?
        ");
        $stmt->execute([$nome, $usuarioNovo, $email, $apelido, $perfil, $registro, $id]);
    }

    /* ── E-MAIL APENAS SE SENHA FOI ALTERADA ── */

    if($trocouSenha){
        $emailDestino = !empty($email) ? $email : $emailAtual;
        if($emailDestino){
            $link     = "http://100.98.98.249/trocarSenha.php";
            $msg      = "Sua senha no sistema foi redefinida por um administrador.\n";
            $msg     .= "No próximo login você será solicitado a criar uma nova senha.\n";
            $msg     .= "Acesse: {$link}\n";
            mail($emailDestino, "Senha redefinida", $msg);
        }
    }

    /* ── SINCRONIZA NA CATRACA (PUT /Pessoas) ── */
    // Tenta atualizar nome/registro na catraca — falha silenciosa
    $urlBuscaCat = 'http://172.16.95.253:3002/backapi/Pessoas?documento=' . urlencode($registro);
    $chBusca = curl_init($urlBuscaCat);
    curl_setopt_array($chBusca,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPGET=>true,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>4,
    ]);
    $resBusca  = curl_exec($chBusca);
    $httpBusca = curl_getinfo($chBusca, CURLINFO_HTTP_CODE);
    curl_close($chBusca);

    if($httpBusca >= 200 && $httpBusca < 300 && $resBusca){
        $dadosCat = json_decode($resBusca, true);
        // API retorna array ou objeto — normaliza para pegar o id
        if(is_array($dadosCat) && isset($dadosCat[0]['id'])){
            $idCatraca = $dadosCat[0]['id'];
        } elseif(isset($dadosCat['id'])){
            $idCatraca = $dadosCat['id'];
        } else {
            $idCatraca = null;
        }

        if($idCatraca){
            // Pessoa existe: atualiza via PUT /{id}
            $payloadPut = json_encode([
                'tipo'      => 2,
                'nome'      => $nome,
                'documento' => $registro,
                'ativo'     => true,
            ]);
            $chPut = curl_init('http://172.16.95.253:3002/backapi/Pessoas/' . $idCatraca);
            curl_setopt_array($chPut,[
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'PUT',
                CURLOPT_POSTFIELDS=>$payloadPut,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
                CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>4,
            ]);
            $httpPut = curl_getinfo($chPut, CURLINFO_HTTP_CODE);
            curl_exec($chPut); curl_close($chPut);
            error_log("[editUser] Catraca PUT id={$idCatraca} HTTP={$httpPut}");
        } else {
            // Pessoa não existe na catraca: cria via POST
            $payloadPost = json_encode([
                'tipo'      => 2,
                'nome'      => $nome,
                'documento' => $registro,
                'ativo'     => true,
            ]);
            $chPost = curl_init('http://172.16.95.253:3002/backapi/Pessoas');
            curl_setopt_array($chPost,[
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$payloadPost,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
                CURLOPT_TIMEOUT=>8, CURLOPT_CONNECTTIMEOUT=>4,
            ]);
            curl_exec($chPost); curl_close($chPost);
            error_log("[editUser] Catraca POST (novo) registro={$registro}");
        }
    } else {
        error_log("[editUser] Catraca inacessível ao atualizar registro={$registro}");
    }

    header("Location: editarUsuario.php?id={$id}&msg=ok");

} catch(PDOException $e){
    error_log("[editUser] ".$e->getMessage());
    header("Location: editarUsuario.php?id={$id}&msg=erro_db");
}
exit;
?>