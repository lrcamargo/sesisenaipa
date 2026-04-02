<?php
/*
 * dadosHistorico.php
 * Retorna JSON com as reservas do histórico para o toggle da index.php.
 * Só acessível pela supervisão.
 */

session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['sLogin'])){
    echo json_encode([]);
    exit;
}

include("../conexao.php");

$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

if(!$supervisao){
    echo json_encode([]);
    exit;
}

try{
    $stmt = $pdo->prepare("
        SELECT
            h.idHistorico,
            h.data,
            h.horarioInicio,
            h.horarioFim,
            h.acao,
            h.executadoEm,
            us.nome  AS solicitante,
            ue.nome  AS executadoPor,
            l.nome   AS laboratorio
        FROM reservas_historico h
        JOIN usuarios     us ON us.id              = h.solicitante
        JOIN usuarios     ue ON ue.id              = h.executadoPor
        JOIN laboratorios l  ON l.idLaboratorio    = h.laboratorio
        ORDER BY h.executadoEm DESC
        LIMIT 200
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formata campos de data para exibição
    foreach($rows as &$r){
        $r['dataFmt']     = date('d/m/Y', strtotime($r['data']));
        $r['executadoEm'] = date('d/m/Y H:i', strtotime($r['executadoEm']));
    }

    echo json_encode($rows);

}catch(PDOException $e){
    error_log("[dadosHistorico] ".$e->getMessage());
    echo json_encode([]);
}
?>