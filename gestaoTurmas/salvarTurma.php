<?php
/*
 * salvarTurma.php
 * Backend JSON para salvar, atualizar e remover vínculos turma→sala.
 *
 * Regras de conflito:
 *  - Mesma sala + mesmo turno + dias que se sobrepõem → BLOQUEADO
 *  - Sobreposição de dias: (maskA & maskB) > 0
 *  - Mesma turma + mesmo turno + dias que se sobrepõem → BLOQUEADO
 *    (uma turma não pode estar em dois lugares ao mesmo tempo)
 *
 * diasSemana bitmask: Seg=1 Ter=2 Qua=4 Qui=8 Sex=16 Sáb=32
 */

require_once('../conexao.php');
session_start();

// Captura qualquer output indesejado (warnings, notices do conexao.php)
// que quebraria o JSON retornado ao fetch()
ob_start();

header('Content-Type: application/json');
ob_clean(); // descarta output acumulado antes de qualquer echo

if(!isset($_SESSION['sLogin'])){
    echo json_encode(['ok'=>false,'msg'=>'Sessão inválida.']); exit;
}

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit;
}

$acao = trim($_POST['acao'] ?? '');

/* ════════════════════════════════════════════════════
   SALVAR — novo vínculo
   ════════════════════════════════════════════════════ */
if($acao === 'salvar'){

    $codigoTurma = strtoupper(trim($_POST['codigoTurma'] ?? ''));
    $idSala      = intval($_POST['idSala']      ?? 0);
    $turno       = trim($_POST['turno']         ?? '');
    $diasSemana  = intval($_POST['diasSemana']  ?? 31);
    $observacao  = trim($_POST['observacao']    ?? '');

    if(!$codigoTurma || !$idSala || !$turno){
        echo json_encode(['ok'=>false,'msg'=>'Campos obrigatórios ausentes.']); exit;
    }
    if(!in_array($turno,['manha','tarde','noite'])){
        echo json_encode(['ok'=>false,'msg'=>'Turno inválido.']); exit;
    }
    if($diasSemana < 1 || $diasSemana > 63) $diasSemana = 31;

    $turnosVerif = [$turno];

    /* ── Conflito 1: mesma sala, mesmo turno (ou integral), dias sobrepostos ── */
    $stmtSala = $pdo->prepare("
        SELECT id, codigoTurma, diasSemana FROM turma_sala
        WHERE idSala = ? AND turno IN (" . implode(',', array_fill(0, count($turnosVerif), '?')) . ")
    ");
    $stmtSala->execute(array_merge([$idSala], $turnosVerif));
    foreach($stmtSala->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0){
            echo json_encode(['ok'=>false,'msg'=>
                "Conflito: sala já ocupada por {$row['codigoTurma']} em dias sobrepostos."
            ]); exit;
        }
    }

    /* ── Conflito 2: mesma turma, mesmo turno, dias sobrepostos ── */
    $stmtTurma = $pdo->prepare("
        SELECT id, idSala, diasSemana FROM turma_sala
        WHERE codigoTurma = ? AND turno IN (" . implode(',', array_fill(0, count($turnosVerif), '?')) . ")
    ");
    $stmtTurma->execute(array_merge([$codigoTurma], $turnosVerif));
    foreach($stmtTurma->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0){
            echo json_encode(['ok'=>false,'msg'=>
                "Conflito: esta turma já está vinculada a outra sala nestes dias/turno."
            ]); exit;
        }
    }

    try{
        $pdo->prepare("
            INSERT INTO turma_sala (codigoTurma, idSala, turno, diasSemana, observacao)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$codigoTurma, $idSala, $turno, $diasSemana, $observacao]);

        echo json_encode(['ok'=>true, 'id'=>intval($pdo->lastInsertId())]);
    } catch(PDOException $e){
        error_log("[salvarTurma] ".$e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar.']);
    }
    exit;
}

/* ════════════════════════════════════════════════════
   ATUALIZAR — turno e/ou dias de um vínculo existente
   ════════════════════════════════════════════════════ */
if($acao === 'atualizar'){

    $id         = intval($_POST['id']         ?? 0);
    $turno      = trim($_POST['turno']        ?? '');
    $diasSemana = intval($_POST['diasSemana'] ?? 31);

    if(!$id || !$turno){
        echo json_encode(['ok'=>false,'msg'=>'Campos obrigatórios ausentes.']); exit;
    }
    if(!in_array($turno,['manha','tarde','noite'])){
        echo json_encode(['ok'=>false,'msg'=>'Turno inválido.']); exit;
    }
    if($diasSemana < 1 || $diasSemana > 63) $diasSemana = 31;

    /* Busca o vínculo atual para saber turma e sala */
    $stmtAtual = $pdo->prepare("SELECT codigoTurma, idSala FROM turma_sala WHERE id = ?");
    $stmtAtual->execute([$id]);
    $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
    if(!$atual){ echo json_encode(['ok'=>false,'msg'=>'Vínculo não encontrado.']); exit; }

    $codigoTurma = $atual['codigoTurma'];
    $idSala      = $atual['idSala'];
    $turnosVerif = [$turno];

    /* Conflito 1: sala ocupada por outra turma nos mesmos dias */
    $stmtSala = $pdo->prepare("
        SELECT id, codigoTurma, diasSemana FROM turma_sala
        WHERE idSala = ?
          AND turno IN (" . implode(',', array_fill(0, count($turnosVerif), '?')) . ")
          AND id != ?
    ");
    $stmtSala->execute(array_merge([$idSala], $turnosVerif, [$id]));
    foreach($stmtSala->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0){
            echo json_encode(['ok'=>false,'msg'=>
                "Conflito: sala já ocupada por {$row['codigoTurma']} em dias sobrepostos."
            ]); exit;
        }
    }

    /* Conflito 2: mesma turma em outra sala nos mesmos dias/turno */
    $stmtTurma = $pdo->prepare("
        SELECT id, idSala, diasSemana FROM turma_sala
        WHERE codigoTurma = ?
          AND turno IN (" . implode(',', array_fill(0, count($turnosVerif), '?')) . ")
          AND id != ?
    ");
    $stmtTurma->execute(array_merge([$codigoTurma], $turnosVerif, [$id]));
    foreach($stmtTurma->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0){
            echo json_encode(['ok'=>false,'msg'=>
                "Conflito: esta turma já está vinculada a outra sala nestes dias/turno."
            ]); exit;
        }
    }

    try{
        $pdo->prepare("
            UPDATE turma_sala SET turno=?, diasSemana=? WHERE id=?
        ")->execute([$turno, $diasSemana, $id]);
        echo json_encode(['ok'=>true]);
    } catch(PDOException $e){
        error_log("[salvarTurma] atualizar: ".$e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Erro ao atualizar.']);
    }
    exit;
}

/* ════════════════════════════════════════════════════
   REMOVER
   ════════════════════════════════════════════════════ */
if($acao === 'remover'){
    $id = intval($_POST['id'] ?? 0);
    if(!$id){ echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
    try{
        $pdo->prepare("DELETE FROM turma_sala WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch(PDOException $e){
        error_log("[salvarTurma] remover: ".$e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Erro ao remover.']);
    }
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']);
?>