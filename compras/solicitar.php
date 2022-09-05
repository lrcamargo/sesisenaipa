<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
if((!isset ($_SESSION['obs']) == true)) {
    $obs = 0;   
} else {
    $obs = $_SESSION['obs'];
}
    include('conexao.php');
    $solicitante = $logado;
    $dataHora = date('Y-m-d H:i:s');
    $data = $_POST['table'];
    
    try {
        $solicitacao = $conn->prepare("INSERT INTO solicitacao (solicitante,dataHora,aprovado,status) VALUES ('$solicitante','$dataHora',0,0)");
        $solicitacao->execute();

    } catch (PDOException $e){
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }

    try {
        $buscaSolicitacao = $conn->prepare("SELECT TOP(1) * FROM solicitacao ORDER BY idSolicitacao DESC");
        $buscaSolicitacao->execute();

        $solicitacao = $buscaSolicitacao->fetchAll();
                       
        foreach($solicitacao as $solicitacao) {
            $idSolicitacao = $solicitacao['idSolicitacao'];
        }
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    for($i = 0;$i<count($data);$i++) {
        $codigo = $data[$i]['Codigo'];
        $quantidade = $data[$i]['Quantidade'];
        $aplicacao = $data[$i]['Aplicação'];
        try {
            $solicitar = $conn->prepare("INSERT INTO itens (codigoProduto,quantidade,aplicacao,codSolicitacao,dataHora) VALUES ('$codigo','$quantidade','$aplicacao','$idSolicitacao','$dataHora')");
            $solicitar->execute();
    
        } catch (PDOException $e){
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
    }
    echo json_encode(['status' => 'success', 'msg' => $data]);
?>