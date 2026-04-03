<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once '../conexao.php';
session_start();

ob_clean();
header('Content-Type: application/json');

if(!isset($_SESSION['sLogin'])){ echo json_encode(['data' => []]); exit; }

$draw   = intval($_POST['draw']           ?? 0);
$start  = intval($_POST['start']          ?? 0);
$length = intval($_POST['length']         ?? 10);
$search = $_POST['search']['value']       ?? '';

$where  = '';
$params = [];
if($search !== ''){
    $where  = "WHERE nome LIKE ? OR localizacao LIKE ? OR descricao LIKE ?";
    $like   = "%{$search}%";
    $params = [$like, $like, $like];
}

$total         = $pdo->query("SELECT COUNT(*) FROM laboratorios")->fetchColumn();
$stmtCount     = $pdo->prepare("SELECT COUNT(*) FROM laboratorios {$where}");
$stmtCount->execute($params);
$totalFiltered = $stmtCount->fetchColumn();

$sql  = "SELECT idLaboratorio, nome, localizacao, capacidade, descricao,
                temPorta, temArCondicionado, temReserva, temEstoque, temSala
         FROM laboratorios
         {$where}
         ORDER BY nome ASC
         LIMIT ?, ?";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach($params as $p){ $stmt->bindValue($i++, $p, PDO::PARAM_STR); }
$stmt->bindValue($i++, $start,  PDO::PARAM_INT);
$stmt->bindValue($i,   $length, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => intval($total),
    'recordsFiltered' => intval($totalFiltered),
    'data'            => $data,
]);
?>