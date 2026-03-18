<?php

require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $registro = $_POST['registro'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $usuario = $_POST['user'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? '';

    // senha criptografada
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $status = 1;

    try {

        $sql = "INSERT INTO usuarios 
                (registro, nome, email, usuario, senha, perfil, status, primeiro_login)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $registro,
            $nome,
            $email,
            $usuario,
            $senhaHash,
            $perfil,
            $status,
            1
        ]);

        header("Location: index.php?msg=sucesso");
        exit;

    } catch (PDOException $e) {

        echo "Erro ao cadastrar usuário: " . $e->getMessage();

    }

}
?>