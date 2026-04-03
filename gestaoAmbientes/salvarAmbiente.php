<?php
require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:index.php?msg=erro_permissao'); exit;
}

$nome              = trim($_POST['nome']               ?? '');
$localizacao       = trim($_POST['localizacao']        ?? '');
$capacidade        = intval($_POST['capacidade']       ?? 0) ?: null;
$descricao         = trim($_POST['descricao']          ?? '');
$temPorta          = intval($_POST['temPorta']         ?? 0);
$temArCondicionado = intval($_POST['temArCondicionado'] ?? 0);

// Flags de função — checkbox desmarcado não envia valor, então default 0
$temReserva = isset($_POST['temReserva']) ? 1 : 0;
$temEstoque = isset($_POST['temEstoque']) ? 1 : 0;
$temSala    = isset($_POST['temSala'])    ? 1 : 0;

if(!$nome){
    header('location:cadastrarAmbiente.php?erro=1'); exit;
}

try{
    $pdo->prepare("
        INSERT INTO laboratorios
            (nome, localizacao, capacidade, descricao,
             temPorta, temArCondicionado, temReserva, temEstoque, temSala)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $nome, $localizacao, $capacidade, $descricao,
        $temPorta, $temArCondicionado, $temReserva, $temEstoque, $temSala
    ]);
    header('location:index.php?msg=criado');
} catch(PDOException $e){
    error_log("[salvarAmbiente] ".$e->getMessage());
    header('location:cadastrarAmbiente.php?erro=2');
}
exit;
?>