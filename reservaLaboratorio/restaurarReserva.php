<?php
/*
 * restaurarReserva.php
 * Chamado via fetch() pelo index.php. Retorna JSON.
 *
 * POST: idHistorico=N  [&forcar=1]
 *
 * Fluxo:
 *   1. Busca o registro no histórico
 *   2. Verifica conflito de horário na tabela reservas
 *   3a. Sem conflito        → INSERT reservas + DELETE histórico
 *   3b. Conflito sem forcar → retorna { conflito:true, info:"..." }
 *   3c. Conflito + forcar   → move conflitante p/ histórico, restaura original
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['sLogin'])){
    echo json_encode(['ok'=>false,'msg'=>'Sessão expirada.']);
    exit;
}

include("../conexao.php");

$nivel     = $_SESSION['group'];
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

if(!$supervisao){
    echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']);
    exit;
}

$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$uLogado  = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $uLogado['id'] ?? 0;

$idHistorico = intval($_POST['idHistorico'] ?? 0);
$forcar      = intval($_POST['forcar']      ?? 0);

if(!$idHistorico){
    echo json_encode(['ok'=>false,'msg'=>'Parâmetro inválido.']);
    exit;
}

/* ── BUSCA NO HISTÓRICO ── */
$stmtH = $pdo->prepare("SELECT * FROM reservas_historico WHERE idHistorico = ?");
$stmtH->execute([$idHistorico]);
$hist = $stmtH->fetch(PDO::FETCH_ASSOC);

if(!$hist){
    echo json_encode(['ok'=>false,'msg'=>'Registro não encontrado.']);
    exit;
}

/* ── VERIFICA CONFLITO ── */
$stmtC = $pdo->prepare("
    SELECT r.idReserva, u.nome AS solicitante
    FROM reservas r
    JOIN usuarios u ON u.id = r.solicitante
    WHERE r.laboratorio = ?
      AND r.data        = ?
      AND (? < r.horarioFim)
      AND (? > r.horarioInicio)
    LIMIT 1
");
$stmtC->execute([
    $hist['laboratorio'],
    $hist['data'],
    $hist['horarioInicio'],
    $hist['horarioFim'],
]);
$conflito = $stmtC->fetch(PDO::FETCH_ASSOC);

/* ── CONFLITO SEM FORÇA ── */
if($conflito && !$forcar){
    $dataFmt = date('d/m/Y', strtotime($hist['data']));
    echo json_encode([
        'ok'          => false,
        'conflito'    => true,
        'idConflito'  => $conflito['idReserva'],
        'idHistorico' => $idHistorico,
        'info'        => "O horário {$hist['horarioInicio']}–{$hist['horarioFim']} "
                       . "de {$dataFmt} está ocupado por uma reserva de "
                       . "<strong>{$conflito['solicitante']}</strong>. "
                       . "Deseja cancelar essa reserva e restaurar a original?",
    ]);
    exit;
}

/* ── RESTAURA ── */
try{
    $pdo->beginTransaction();

    // Se forçar: move conflitante para histórico
    if($forcar && $conflito){
        $stmtBusca = $pdo->prepare("SELECT * FROM reservas WHERE idReserva = ?");
        $stmtBusca->execute([$conflito['idReserva']]);
        $rc = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        if($rc){
            $pdo->prepare("
                INSERT INTO reservas_historico
                    (idReservaOrig,laboratorio,data,horarioInicio,horarioFim,
                     solicitante,turma,tipo,descricao,observacao,aprovado,
                     acao,executadoPor,motivo)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,'cancelado',?,?)
            ")->execute([
                $rc['idReserva'],$rc['laboratorio'],$rc['data'],
                $rc['horarioInicio'],$rc['horarioFim'],$rc['solicitante'],
                $rc['turma']??null,$rc['tipo']??null,
                $rc['descricao']??null,$rc['observacao']??null,$rc['aprovado'],
                $idLogado,
                'Cancelada para restaurar reserva (histórico #'.$idHistorico.')',
            ]);
            $pdo->prepare("DELETE FROM reservas WHERE idReserva = ?")
                ->execute([$conflito['idReserva']]);
        }
    }

    // Insere a reserva original de volta (status volta para "Aguardando")
    $pdo->prepare("
        INSERT INTO reservas
            (laboratorio,data,horarioInicio,horarioFim,
             solicitante,turma,tipo,descricao,observacao,aprovado)
        VALUES(?,?,?,?,?,?,?,?,?,0)
    ")->execute([
        $hist['laboratorio'],$hist['data'],
        $hist['horarioInicio'],$hist['horarioFim'],
        $hist['solicitante'],
        $hist['turma']??null,$hist['tipo']??null,
        $hist['descricao']??null,$hist['observacao']??null,
    ]);

    // Remove do histórico
    $pdo->prepare("DELETE FROM reservas_historico WHERE idHistorico = ?")
        ->execute([$idHistorico]);

    $pdo->commit();
    echo json_encode([
        'ok'  => true,
        'msg' => 'Reserva restaurada. Status redefinido para "Aguardando aprovação".',
    ]);

}catch(PDOException $e){
    $pdo->rollBack();
    error_log("[restaurarReserva] ".$e->getMessage());
    echo json_encode(['ok'=>false,'msg'=>'Erro ao restaurar. Tente novamente.']);
}
exit;
?>