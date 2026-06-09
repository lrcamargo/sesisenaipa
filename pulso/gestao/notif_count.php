<?php
// pulso/notif_count.php
// Endpoint leve consultado pelo main.php via AJAX.
// Retorna JSON com o número de atualizações de SLA não vistas pelo usuário.
// SEM autenticação própria — usa a sessão da intranet.

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['sLogin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['count' => 0]);
    exit;
}

// Marca como lidas se action=marcar
if (($_GET['action'] ?? '') === 'marcar') {
    ob_end_clean();
    include('../conexao.php');
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if (!$uid) {
        // Tenta buscar o id pelo login
        $q = $pdo->prepare("SELECT id FROM usuarios WHERE login=? LIMIT 1");
        $q->execute([$_SESSION['sLogin']]);
        $uid = (int)($q->fetchColumn() ?: 0);
    }
    if ($uid) {
        $pdo->prepare("
            INSERT INTO pulso_notif_lidas (usuario_id, tipo, lida_em)
            VALUES (?, 'sla', NOW())
            ON DUPLICATE KEY UPDATE lida_em = NOW()
        ")->execute([$uid]);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

include('../conexao.php');
ob_end_clean();
header('Content-Type: application/json');

// Busca id do usuário
$uid = (int)($_SESSION['usuario_id'] ?? 0);
if (!$uid) {
    $q = $pdo->prepare("SELECT id FROM usuarios WHERE login=? LIMIT 1");
    $q->execute([$_SESSION['sLogin']]);
    $uid = (int)($q->fetchColumn() ?: 0);
}

if (!$uid) {
    echo json_encode(['count' => 0]);
    exit;
}

// Busca a última vez que este usuário marcou como lido
$q2 = $pdo->prepare("
    SELECT lida_em FROM pulso_notif_lidas
    WHERE usuario_id=? AND tipo='sla'
");
$q2->execute([$uid]);
$lida_em = $q2->fetchColumn() ?: '1970-01-01 00:00:00';

// Conta itens de SLA atualizados após a última leitura
// Inclui: novos itens criados E itens com status atualizado
$q3 = $pdo->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM pulso_itens_sla s
    INNER JOIN pulso_ciclos c ON c.id = s.ciclo_id
    INNER JOIN pulso_unidades u ON u.id = c.unidade_id
    WHERE u.ativa = 1
      AND s.atualizado_em > ?
");
$q3->execute([$lida_em]);
$count = (int)$q3->fetchColumn();

echo json_encode(['count' => $count]);