<?php
error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

$draw = $_POST['draw'] ?? 0;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';

$where = "";
$params = [];

if ($search != "") {

    $where = " WHERE registro LIKE ? OR nome LIKE ? OR usuario LIKE ? OR perfil LIKE ?";

    $like = "%$search%";
    $params = [$like,$like,$like,$like];
}

$sql = "SELECT COUNT(*) as total FROM usuarios $where";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$totalFiltered = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$sql = "SELECT id, registro, nome, usuario, status, perfil
        FROM usuarios
        $where
        ORDER BY status DESC, nome ASC
        LIMIT ?, ?";

$stmt = $pdo->prepare($sql);

$i = 1;

foreach ($params as $p) {
    $stmt->bindValue($i++, $p, PDO::PARAM_STR);
}

$stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
$stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);

$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);