<?php

require_once('../conexao.php');

$registro = $_POST['registro'];
$nome = $_POST['nome'];
$usuarioNovo = $_POST['user'];
$senha = $_POST['senha'];
$perfil = $_POST['perfil'];

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

/* BUSCA USUÁRIO ATUAL */

$sql = "SELECT usuario,email FROM usuarios WHERE registro=?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$registro]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user){
    die("Usuário não encontrado");
}

$usuarioAntigo = $user['usuario'];
$email = $user['email'];

/* ATUALIZA */

$sql = "UPDATE usuarios
        SET nome=?,
            usuario=?,
            senha=?,
            perfil=?,
            primeiro_login=1
        WHERE registro=?";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $nome,
    $usuarioNovo,
    $senhaHash,
    $perfil,
    $registro
]);

/* SE USUÁRIO FOI ALTERADO */

if($usuarioNovo != $usuarioAntigo){

$link = "http://100.98.98.249/trocarSenha.php";

$mensagem = "

Seu usuário no sistema foi alterado.

Novo usuário: $usuarioNovo

Para criar uma nova senha acesse:

$link

";

mail($email,"Alteração de usuário",$mensagem);

}

header("Location: editarUsuario.php?ok=1");
exit;