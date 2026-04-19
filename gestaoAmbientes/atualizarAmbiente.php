<?php
require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:index.php?msg=erro_permissao'); exit;
}

$id                = intval($_POST['id']               ?? 0);
$nome              = trim($_POST['nome']               ?? '');
$localizacao       = trim($_POST['localizacao']        ?? '');
$capacidade        = intval($_POST['capacidade']       ?? 0) ?: null;
$descricao         = trim($_POST['descricao']          ?? '');
$temPorta          = intval($_POST['temPorta']         ?? 0);
$temArCondicionado = intval($_POST['temArCondicionado'] ?? 0);
$temReserva        = isset($_POST['temReserva']) ? 1 : 0;
$temEstoque        = isset($_POST['temEstoque']) ? 1 : 0;
$temSala           = isset($_POST['temSala'])    ? 1 : 0;

// MAC só persiste quando IoT correspondente está ativo; caso contrário grava NULL
$macPorta          = $temPorta          === 1 ? trim($_POST['macPorta']          ?? '') : null;
$macArCondicionado = $temArCondicionado === 1 ? trim($_POST['macArCondicionado'] ?? '') : null;
if($macPorta === '')          $macPorta = null;
if($macArCondicionado === '') $macArCondicionado = null;

if(!$id || !$nome){
    header('location:index.php?msg=erro_vazio'); exit;
}

try{
    $pdo->prepare("
        UPDATE laboratorios SET
            nome              = ?,
            localizacao       = ?,
            capacidade        = ?,
            descricao         = ?,
            temPorta          = ?,
            macPorta          = ?,
            temArCondicionado = ?,
            macArCondicionado = ?,
            temReserva        = ?,
            temEstoque        = ?,
            temSala           = ?
        WHERE idLaboratorio = ?
    ")->execute([
        $nome, $localizacao, $capacidade, $descricao,
        $temPorta, $macPorta,
        $temArCondicionado, $macArCondicionado,
        $temReserva, $temEstoque, $temSala,
        $id
    ]);
    header("location:editarAmbiente.php?id={$id}&msg=ok");
} catch(PDOException $e){
    error_log("[atualizarAmbiente] ".$e->getMessage());
    header("location:editarAmbiente.php?id={$id}&msg=erro_db");
}
exit;
?>