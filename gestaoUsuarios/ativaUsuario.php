<?php
// Inclua o arquivo de conexão
include('../conexao.php');

if ($conn === false) {
    echo "Erro na conexão. Verifique o arquivo conexaosec.php.";
    die(print_r(sqlsrv_errors(), true));
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $userId = $_GET['id'];
    $status = 1; 
    
    try {
        $ativa = $conn->prepare("UPDATE funcionarios SET status = '$status'  WHERE id = '$userId'");
        $ativa->execute();
        
    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    header('location:index.php');
}
?>