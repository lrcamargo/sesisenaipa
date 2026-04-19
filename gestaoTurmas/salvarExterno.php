<?php
/*
 * salvarExterno.php
 * Backend JSON para gerenciar prédios externos, salas externas
 * e vínculos turma → sala externa.
 *
 * Ações:
 *   predio_salvar   — INSERT ou UPDATE em predios
 *   predio_excluir  — DELETE em predios (cascade para salas e vínculos)
 *   sala_salvar     — INSERT ou UPDATE em salas_externas
 *   sala_excluir    — DELETE em salas_externas
 *   vinculo_salvar  — INSERT em turma_sala_externa (com validação bitmask)
 *   vinculo_atualizar — UPDATE turno + diasSemana
 *   vinculo_remover — DELETE em turma_sala_externa
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'msg'=>'Sessão inválida.']); exit;
}

// Captura output indesejado antes de enviar JSON
ob_start();
header('Content-Type: application/json');
ob_clean();

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit;
}

$acao = trim($_POST['acao'] ?? '');
// Aliases: gestaoTurmas envia 'salvar'/'atualizar'/'remover' para vínculos
if($acao === 'salvar')    $acao = 'vinculo_salvar';
if($acao === 'atualizar') $acao = 'vinculo_atualizar';
if($acao === 'remover')   $acao = 'vinculo_remover';

/* ── helpers ── */
function ok(array $extra=[]): void {
    echo json_encode(array_merge(['ok'=>true], $extra)); exit;
}
function erro(string $msg): void {
    echo json_encode(['ok'=>false,'msg'=>$msg]); exit;
}
function validarMask(int $m): int {
    return ($m >= 1 && $m <= 63) ? $m : 31;
}
function validarTurno(string $t): bool {
    return in_array($t, ['manha','tarde','noite']);
}

/* ════════════ PRÉDIOS ════════════ */

if($acao === 'predio_salvar'){
    $id       = intval($_POST['id'] ?? 0);
    $nome     = trim($_POST['nome']     ?? '');
    $cidade   = trim($_POST['cidade']   ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    if(!$nome) erro('Nome do prédio obrigatório.');
    try{
        if($id){
            $pdo->prepare("UPDATE predios SET nome=?,cidade=?,endereco=? WHERE id=?")
                ->execute([$nome,$cidade,$endereco,$id]);
            ok(['id'=>$id]);
        } else {
            $pdo->prepare("INSERT INTO predios (nome,cidade,endereco) VALUES (?,?,?)")
                ->execute([$nome,$cidade,$endereco]);
            ok(['id'=>intval($pdo->lastInsertId())]);
        }
    } catch(PDOException $e){ erro('Erro ao salvar prédio.'); }
}

if($acao === 'predio_excluir'){
    $id = intval($_POST['id'] ?? 0);
    if(!$id) erro('ID inválido.');
    try{
        $pdo->prepare("DELETE FROM predios WHERE id=?")->execute([$id]);
        ok();
    } catch(PDOException $e){ erro('Erro ao excluir prédio.'); }
}

/* ════════════ SALAS ════════════ */

if($acao === 'sala_salvar'){
    $id        = intval($_POST['id']        ?? 0);
    $idPredio  = intval($_POST['idPredio']  ?? 0);
    $nome      = trim($_POST['nome']        ?? '');
    $descricao = trim($_POST['descricao']   ?? '');
    if(!$idPredio || !$nome) erro('Prédio e nome da sala são obrigatórios.');
    try{
        if($id){
            $pdo->prepare("UPDATE salas_externas SET nome=?,descricao=? WHERE id=?")
                ->execute([$nome,$descricao,$id]);
            ok(['id'=>$id]);
        } else {
            $pdo->prepare("INSERT INTO salas_externas (idPredio,nome,descricao) VALUES (?,?,?)")
                ->execute([$idPredio,$nome,$descricao]);
            ok(['id'=>intval($pdo->lastInsertId())]);
        }
    } catch(PDOException $e){ erro('Erro ao salvar sala.'); }
}

if($acao === 'sala_excluir'){
    $id = intval($_POST['id'] ?? 0);
    if(!$id) erro('ID inválido.');
    try{
        $pdo->prepare("DELETE FROM salas_externas WHERE id=?")->execute([$id]);
        ok();
    } catch(PDOException $e){ erro('Erro ao excluir sala.'); }
}

/* ════════════ VÍNCULOS ════════════ */

if($acao === 'vinculo_salvar'){
    $codigoTurma = strtoupper(trim($_POST['codigoTurma'] ?? ''));
    $idSala      = intval($_POST['idSala']      ?? 0);
    $turno       = trim($_POST['turno']         ?? '');
    $diasSemana  = validarMask(intval($_POST['diasSemana'] ?? 31));
    $observacao  = trim($_POST['observacao']    ?? '');

    if(!$codigoTurma || !$idSala) erro('Turma e sala são obrigatórios.');
    if(!validarTurno($turno))     erro('Turno inválido.');

    /* Conflito: mesma sala + mesmo turno + dias sobrepostos */
    $stmtC = $pdo->prepare("
        SELECT codigoTurma, diasSemana FROM turma_sala_externa
        WHERE idSala=? AND turno=?
    ");
    $stmtC->execute([$idSala, $turno]);
    foreach($stmtC->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0)
            erro("Conflito com turma {$row['codigoTurma']} nesta sala/turno.");
    }

    /* Conflito: mesma turma + mesmo turno + dias sobrepostos (outra sala) */
    $stmtT = $pdo->prepare("
        SELECT idSala, diasSemana FROM turma_sala_externa
        WHERE codigoTurma=? AND turno=?
    ");
    $stmtT->execute([$codigoTurma, $turno]);
    foreach($stmtT->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(($row['diasSemana'] & $diasSemana) > 0)
            erro("Esta turma já está alocada em outra sala neste turno/dias.");
    }

    try{
        $pdo->prepare("
            INSERT INTO turma_sala_externa (codigoTurma,idSala,turno,diasSemana,observacao)
            VALUES (?,?,?,?,?)
        ")->execute([$codigoTurma,$idSala,$turno,$diasSemana,$observacao]);
        ok(['id'=>intval($pdo->lastInsertId())]);
    } catch(PDOException $e){ erro('Erro ao salvar vínculo.'); }
}

if($acao === 'vinculo_atualizar'){
    $id         = intval($_POST['id']          ?? 0);
    $turno      = trim($_POST['turno']         ?? '');
    $diasSemana = validarMask(intval($_POST['diasSemana'] ?? 31));
    if(!$id || !validarTurno($turno)) erro('Dados inválidos.');

    /* Busca dados atuais para validação */
    $atual = $pdo->prepare("SELECT codigoTurma,idSala FROM turma_sala_externa WHERE id=?");
    $atual->execute([$id]);
    $row = $atual->fetch(PDO::FETCH_ASSOC);
    if(!$row) erro('Vínculo não encontrado.');

    /* Conflito sala */
    $stmtC = $pdo->prepare("
        SELECT codigoTurma,diasSemana FROM turma_sala_externa
        WHERE idSala=? AND turno=? AND id!=?
    ");
    $stmtC->execute([$row['idSala'],$turno,$id]);
    foreach($stmtC->fetchAll(PDO::FETCH_ASSOC) as $r){
        if(($r['diasSemana'] & $diasSemana) > 0)
            erro("Conflito com turma {$r['codigoTurma']} nesta sala/turno.");
    }

    try{
        $pdo->prepare("UPDATE turma_sala_externa SET turno=?,diasSemana=? WHERE id=?")
            ->execute([$turno,$diasSemana,$id]);
        ok();
    } catch(PDOException $e){ erro('Erro ao atualizar.'); }
}

if($acao === 'vinculo_remover'){
    $id = intval($_POST['id'] ?? 0);
    if(!$id) erro('ID inválido.');
    try{
        $pdo->prepare("DELETE FROM turma_sala_externa WHERE id=?")->execute([$id]);
        ok();
    } catch(PDOException $e){ erro('Erro ao remover.'); }
}

erro('Ação inválida.');
?>