<?php
/*
 * atualizarCondicaoAluno.php
 * POST: turma_id, pessoa_id, condicao
 * Retorna JSON { ok: bool, msg?: string }
 */

header('Content-Type: application/json');

include('../conexao.php');
session_start();

if (!isset($_SESSION['sLogin'])) {
    echo json_encode(['ok' => false, 'msg' => 'Não autenticado.']);
    exit;
}

$turmaId  = $_POST['turma_id']  ?? '';
$pessoaId = $_POST['pessoa_id'] ?? '';
$condicao = $_POST['condicao']  ?? '';

$condicoesValidas = ['normal', 'atestado', 'evadido', 'cancelado'];

if (empty($turmaId) || empty($pessoaId) || !in_array($condicao, $condicoesValidas, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados inválidos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE turma_alunos
        SET condicao = :condicao
        WHERE turma_id = :turma_id AND pessoa_id = :pessoa_id
    ");
    $stmt->execute([
        ':condicao'  => $condicao,
        ':turma_id'  => $turmaId,
        ':pessoa_id' => $pessoaId,
    ]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
}