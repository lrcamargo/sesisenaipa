<?php
error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
include('../conexao.php');

if ($pdo === false) {
    echo "Erro na conexão. Verifique o arquivo conexaosec.php.";
    die(print_r(sqlsrv_errors(), true));
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];
    $status = 0; 
    
    try {
        $ativa = $pdo->prepare("UPDATE usuarios SET status = '$status'  WHERE id = '$userId'");
        $ativa->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    header('location:index.php');
}
?>