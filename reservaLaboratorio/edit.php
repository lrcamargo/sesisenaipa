<?php
/*
 * edit.php
 * Processa o formulário de edição de reserva.
 * Substitui a versão antiga que usava $conn e interpolação direta de variáveis no SQL.
 */

include("../conexao.php");
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$logado    = $_SESSION['user'];
$nivel     = $_SESSION['group'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

/* ── Recebe os campos ── */
$id          = intval($_POST['id']          ?? 0);
$data        = trim($_POST['dataInp']       ?? '');
$turno       = trim($_POST['turno']         ?? '');
$periodo     = trim($_POST['periodo']       ?? '');
$hInicio     = trim($_POST['horainicio']    ?? '');
$hFim        = trim($_POST['horafim']       ?? '');
$turma       = trim($_POST['turma']         ?? '');
$laboratorio = intval($_POST['laboratorio'] ?? 0);
$solicitante = intval($_POST['solicitante'] ?? 0);

if(!$id || !$data || !$laboratorio){
    header("location:editaReserva.php?id={$id}&msg=erro_vazio");
    exit;
}

/* ── Horários fixos por turno ── */
$horariosTurno = [
    'manha' => ['inicio'=>'07:00','fim'=>'12:20'],
    'tarde' => ['inicio'=>'13:00','fim'=>'17:30'],
    'noite' => ['inicio'=>'18:00','fim'=>'22:30'],
];

if($periodo === 'todo' && isset($horariosTurno[$turno])){
    $hInicio = $horariosTurno[$turno]['inicio'];
    $hFim    = $horariosTurno[$turno]['fim'];
}

if(empty($hInicio) || empty($hFim)){
    header("location:editaReserva.php?id={$id}&msg=erro_vazio");
    exit;
}

/* ── Permissão: só supervisão ou próprio solicitante ── */
$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$uLogado  = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $uLogado['id'] ?? 0;

$stmtR = $pdo->prepare("SELECT solicitante FROM reservas WHERE idReserva = ?");
$stmtR->execute([$id]);
$reservaAtual = $stmtR->fetch(PDO::FETCH_ASSOC);

if(!$supervisao && $idLogado != ($reservaAtual['solicitante'] ?? -1)){
    header("location:index.php?msg=erro_permissao");
    exit;
}

/* ── Verifica conflito (exclui a própria reserva da verificação) ── */
$stmtConf = $pdo->prepare("
    SELECT COUNT(*) FROM reservas
    WHERE laboratorio = ?
      AND data = ?
      AND idReserva != ?
      AND (? < horarioFim)
      AND (? > horarioInicio)
");
$stmtConf->execute([$laboratorio, $data, $id, $hInicio, $hFim]);
if($stmtConf->fetchColumn() > 0){
    header("location:editaReserva.php?id={$id}&msg=erro_conf");
    exit;
}

/* ── UPDATE ── */
try{
    $pdo->prepare("
        UPDATE reservas
        SET data = ?, horarioInicio = ?, horarioFim = ?,
            laboratorio = ?, turma = ?, solicitante = ?
        WHERE idReserva = ?
    ")->execute([$data, $hInicio, $hFim, $laboratorio, $turma, $solicitante, $id]);

    header("location:editaReserva.php?id={$id}&msg=ok");

} catch(PDOException $e){
    error_log("[edit] ".$e->getMessage());
    header("location:editaReserva.php?id={$id}&msg=erro_db");
}
exit;
?>