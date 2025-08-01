<?php
include('conect.php');

// 2. Receber os parâmetros do DataTables
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search_value = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// 3. Mapear os índices das colunas do frontend para o banco de dados
$colunas_db = ['id', 'registro', 'nome', 'usuario', 'status', 'perfil', 'id', 'id'];

// 4. Lógica de Ordenação Personalizada
$order_by_column = 'nome'; 
$order_by_dir = 'asc';

if (isset($_POST['order'][0]['column'])) {
    $order_by_column_index = $_POST['order'][0]['column'];
    
    if ($order_by_column_index > 0 && $order_by_column_index < 6) {
        $order_by_column = $colunas_db[$order_by_column_index];
        $order_by_dir = $_POST['order'][0]['dir'];
    }
}

$order_by_clause = " ORDER BY status DESC, " . $order_by_column . " " . $order_by_dir;

// 5. Construir a cláusula WHERE de forma segura com Prepared Statements
$where_clause = "";
$where_params = [];
if (!empty($search_value)) {
    $where_clause = " WHERE ";
    $condicoes = [];
    $search_param = '%' . $search_value . '%';

    foreach ($colunas_db as $coluna) {
        if ($coluna != 'id') {
            $condicoes[] = "$coluna LIKE ?";
            $where_params[] = $search_param;
        }
    }
    $where_clause .= implode(" OR ", $condicoes);
}

// 6. Contar o total de registros filtrados (de forma segura)
$total_filtered_records_query = "SELECT COUNT(*) AS total FROM funcionarios" . $where_clause;
$total_filtered_records_stmt = sqlsrv_query($conn, $total_filtered_records_query, $where_params);
$total_filtered_records = sqlsrv_fetch_array($total_filtered_records_stmt)['total'];

// 7. Contar o total de registros (sem filtro)
$total_records_query = "SELECT COUNT(*) AS total FROM funcionarios";
$total_records_stmt = sqlsrv_query($conn, $total_records_query);
$total_records = sqlsrv_fetch_array($total_records_stmt)['total'];


// 8. Consulta final para buscar os dados (de forma segura)
$query = "SELECT id, registro, nome, usuario, status, perfil FROM funcionarios" . $where_clause;
$query .= $order_by_clause . " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

// Combinar os parâmetros da busca e da paginação em um único array
$query_params = array_merge($where_params, [$start, $length]);

$stmt = sqlsrv_query($conn, $query, $query_params);

if ($stmt === false) {
    header('Content-Type: application/json');
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $total_records,
        "recordsFiltered" => $total_filtered_records,
        "data" => [],
        "error" => print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $row['registro'] = $row['registro'] ?? '';
    $row['tag'] = $row['tag'] ?? '';
    $data[] = $row;
}

// 9. Formatar a resposta no padrão JSON do DataTables
$response = [
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($total_filtered_records),
    "data" => $data
];

header('Content-Type: application/json');
echo json_encode($response);

// 10. Fechar a conexão
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>