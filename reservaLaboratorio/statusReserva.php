<?php
/*
 * statusReserva.php
 *
 * ?id=ID_RESERVA&status=ACAO
 *
 * status=1 → Aprovar      (supervisão) — UPDATE aprovado=1
 * status=2 → Reprovar     (supervisão sobre reserva de outro)
 *                          → histórico + DELETE + chama email.php
 * status=3 → Cancelar     (própria reserva, qualquer usuário)
 *                          → histórico + DELETE
 *
 * Supervisão cancelando própria reserva usa status=3 (mesmo fluxo).
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

include("../conexao.php");

$logado    = $_SESSION['user'];
$nivel     = $_SESSION['group'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

/* ── PARÂMETROS ── */

$idReserva = intval($_GET['id']     ?? 0);
$status    = intval($_GET['status'] ?? 0);

if(!$idReserva || !in_array($status,[1,2,3])){
    header('location:principal.php?msg=erro_param');
    exit;
}

/* ── VALIDAÇÃO DE PERMISSÃO RÁPIDA ── */

if(in_array($status,[1,2]) && !$supervisao){
    header('location:principal.php?msg=erro_permissao');
    exit;
}

/* ── BUSCA A RESERVA ── */

$stmt = $pdo->prepare("
    SELECT r.*, u.nome AS nomesolicitante, u.id AS idSolicitante
    FROM reservas r
    JOIN usuarios u ON u.id = r.solicitante
    WHERE r.idReserva = ?
");
$stmt->execute([$idReserva]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$reserva){
    header('location:principal.php?msg=erro_nao_encontrada');
    exit;
}

/* ── BUSCA ID DO USUÁRIO LOGADO ── */

$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$uLogado  = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $uLogado['id'] ?? 0;

$ehPropria = ($idLogado == $reserva['idSolicitante']);

/* ── CANCELAR: só pode agir sobre a própria (a menos que seja supervisão) ── */

if($status === 3 && !$ehPropria && !$supervisao){
    header('location:principal.php?msg=erro_permissao');
    exit;
}

/* ══════════════════════════════════════════════
   AÇÃO 1 — APROVAR
   ══════════════════════════════════════════════ */

if($status === 1){
    $pdo->prepare("UPDATE reservas SET aprovado = 1 WHERE idReserva = ?")
        ->execute([$idReserva]);
    header('location:principal.php?msg=aprovada');
    exit;
}

/* ══════════════════════════════════════════════
   FUNÇÃO AUXILIAR — HISTÓRICO + DELETE
   Usada tanto ao reprovar quanto ao cancelar.
   ══════════════════════════════════════════════ */

function moverParaHistorico(PDO $pdo, array $reserva, int $idLogado, string $acao, string $motivo): bool {
    try{
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO reservas_historico
                (idReservaOrig, laboratorio, data, horarioInicio, horarioFim,
                 solicitante, turma, tipo, descricao, observacao, aprovado,
                 acao, executadoPor, motivo)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $reserva['idReserva'],
            $reserva['laboratorio'],
            $reserva['data'],
            $reserva['horarioInicio'],
            $reserva['horarioFim'],
            $reserva['solicitante'],
            $reserva['turma']      ?? null,
            $reserva['tipo']       ?? null,
            $reserva['descricao']  ?? null,
            $reserva['observacao'] ?? null,
            $reserva['aprovado'],
            $acao,
            $idLogado,
            $motivo,
        ]);

        // DELETE libera o UNIQUE (laboratorio, data, horarioInicio, horarioFim)
        $pdo->prepare("DELETE FROM reservas WHERE idReserva = ?")
            ->execute([$reserva['idReserva']]);

        $pdo->commit();
        return true;

    } catch(PDOException $e){
        $pdo->rollBack();
        error_log("[statusReserva] ".$e->getMessage());
        return false;
    }
}

/* ══════════════════════════════════════════════
   AÇÃO 2 — REPROVAR
   ══════════════════════════════════════════════ */

if($status === 2){

    // Supervisão reprovando a própria reserva → trata como cancelamento
    $acao   = $ehPropria ? 'cancelado' : 'reprovado';
    $motivo = $ehPropria
        ? 'Cancelado pelo próprio solicitante (supervisão)'
        : 'Reprovado pela supervisão';

    $ok = moverParaHistorico($pdo, $reserva, $idLogado, $acao, $motivo);

    if(!$ok){
        header('location:principal.php?msg=erro_db');
        exit;
    }

    // Envia e-mail de notificação só ao reprovar reserva de outro
    if(!$ehPropria){
        //header("Location: email.php?cod=2&id={$idReserva}");
        header('location:principal.php?msg=reprovada');
        exit;
    }

    header('location:principal.php?msg=' . ($ehPropria ? 'cancelada' : 'reprovada'));
    exit;
}

/* ══════════════════════════════════════════════
   AÇÃO 3 — CANCELAR
   ══════════════════════════════════════════════ */

if($status === 3){
    $ok = moverParaHistorico($pdo, $reserva, $idLogado, 'cancelado', 'Cancelado pelo solicitante');
    header('location:principal.php?msg=' . ($ok ? 'cancelada' : 'erro_db'));
    exit;
}

header('location:principal.php?msg=erro_param');
exit;
?>