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
            SET nome=?, usuario=?, email=?, senha=?,
                perfil=?, registro=?, primeiro_login=1
            WHERE id=?
        ");
        $stmt->execute([$nome, $usuarioNovo, $email, $senhaHash, $perfil, $registro, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET nome=?, usuario=?, email=?,
                perfil=?, registro=?
            WHERE id=?
        ");
        $stmt->execute([$nome, $usuarioNovo, $email, $perfil, $registro, $id]);
    }

    /* ── E-MAIL APENAS SE SENHA FOI ALTERADA ── */

    if($trocouSenha){
        $emailDestino = !empty($email) ? $email : $emailAtual;
        if($emailDestino){
            $link     = "http://172.16.95.254/trocarSenha.php";
            $msg      = "Sua senha no sistema foi redefinida por um administrador.\n";
            $msg     .= "No próximo login você será solicitado a criar uma nova senha.\n";
            $msg     .= "Acesse: {$link}\n";
            mail($emailDestino, "Senha redefinida", $msg);
        }
    }

    header("Location: editarUsuario.php?id={$id}&msg=ok");

} catch(PDOException $e){
    error_log("[editUser] ".$e->getMessage());
    header("Location: editarUsuario.php?id={$id}&msg=erro_db");
}
exit;
?>