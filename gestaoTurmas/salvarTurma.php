<?php
/*
 * salvarTurma.php
 * Backend JSON para salvar e remover vínculos turma→sala.
 *
 * diasSemana: TINYINT bitmask
 *   Seg=1  Ter=2  Qua=4  Qui=8  Sex=16  Sáb=32
 *   Exemplos: Seg-Sex=31  SóSáb=32  Ter/Qui=10  Todos=63
 */

require_once('../conexao.php');
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['sLogin'])){
    echo json_encode(['ok'=>false,'msg'=>'Sessão inválida.']); exit;
}

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit;
}

$acao = trim($_POST['acao'] ?? '');

/* ── SALVAR ── */
if($acao === 'salvar'){

    $codigoTurma = strtoupper(trim($_POST['codigoTurma'] ?? ''));
    $idSala      = intval($_POST['idSala']  ?? 0);
    $turno       = trim($_POST['turno']     ?? '');
    $observacao  = trim($_POST['observacao'] ?? '');

    /*
     * diasSemana: recebe o valor inteiro já calculado pelo JS
     * (soma dos bits dos dias marcados).
     * Valida que está entre 1 e 63 (pelo menos 1 dia, máximo todos).
     */
    $diasSemana = intval($_POST['diasSemana'] ?? 31);
    if($diasSemana < 1 || $diasSemana > 63) $diasSemana = 31; // fallback Seg-Sex

    if(!$codigoTurma || !$idSala || !$turno){
        echo json_encode(['ok'=>false,'msg'=>'Campos obrigatórios ausentes.']); exit;
    }
    if(!in_array($turno,['manha','tarde','noite','integral'])){
        echo json_encode(['ok'=>false,'msg'=>'Turno inválido.']); exit;
    }

    /* Verifica duplicidade de turma */
    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM turma_sala WHERE codigoTurma = ?");
    $stmtT->execute([$codigoTurma]);
    if($stmtT->fetchColumn() > 0){
        echo json_encode(['ok'=>false,'msg'=>'Esta turma já está vinculada a outra sala.']); exit;
    }

    /* Verifica se o slot está ocupado */
    if($turno === 'integral'){
        $stmtS = $pdo->prepare("
            SELECT COUNT(*) FROM turma_sala
            WHERE idSala = ? AND turno IN ('manha','tarde','integral')
        ");
        $params = [$idSala];
    } else {
        $stmtS = $pdo->prepare("
            SELECT COUNT(*) FROM turma_sala
            WHERE idSala = ? AND (turno = ? OR turno = 'integral')
        ");
        $params = [$idSala, $turno];
    }
    $stmtS->execute($params);
    if($stmtS->fetchColumn() > 0){
        echo json_encode(['ok'=>false,'msg'=>'Esta sala/turno já está ocupada.']); exit;
    }

    try{
        $pdo->prepare("
            INSERT INTO turma_sala (codigoTurma, idSala, turno, diasSemana, observacao)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$codigoTurma, $idSala, $turno, $diasSemana, $observacao]);

        echo json_encode([
            'ok'         => true,
            'id'         => intval($pdo->lastInsertId()),
            'diasSemana' => $diasSemana,
        ]);
    } catch(PDOException $e){
        error_log("[salvarTurma] ".$e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar no banco.']);
    }
    exit;
}

/* ── REMOVER ── */
if($acao === 'remover'){
    $id = intval($_POST['id'] ?? 0);
    if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
    try{
        $pdo->prepare("DELETE FROM turma_sala WHERE id = ?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch(PDOException $e){
        error_log("[salvarTurma] remover: ".$e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Erro ao remover.']);
    }
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']);
?>