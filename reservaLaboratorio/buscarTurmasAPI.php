<?php
header('Content-Type: application/json');

// Valida entrada
$data = $_GET['data'] ?? '';
if(!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)){
    echo json_encode([]);
    exit;
}

// ── CONFIGURAÇÕES ──────────────────────────────────────────────

$urlCatraca   = 'http://172.16.95.253:3002/backapi/Turmas';
$timeoutSegundos = 5;        // desiste da catraca após 5s
$cacheMinutos    = 10;       // reutiliza lista de turmas por 10 min
$cacheArquivo    = sys_get_temp_dir() . '/turmas_cache.json';

// ── TENTA USAR O CACHE ─────────────────────────────────────────

$turmas = null;

if(file_exists($cacheArquivo)){
    $idadeCache = time() - filemtime($cacheArquivo);
    if($idadeCache < ($cacheMinutos * 60)){
        $conteudo = file_get_contents($cacheArquivo);
        $turmas   = json_decode($conteudo, true);
        // Se o cache estiver corrompido, ignora e busca de novo
        if(!is_array($turmas)) $turmas = null;
    }
}

// ── BUSCA NA CATRACA SE NÃO HÁ CACHE VÁLIDO ───────────────────

if($turmas === null){

    $ch = curl_init($urlCatraca);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutSegundos,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $resposta  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errocurl  = curl_error($ch);
    curl_close($ch);

    // Qualquer falha → retorna [] e não grava cache
    if($errocurl || $httpCode < 200 || $httpCode >= 300 || !$resposta){
        error_log("[buscarTurmasAPI] Falha na catraca. HTTP: {$httpCode} | cURL: {$errocurl}");
        echo json_encode([]);
        exit;
    }

    $turmas = json_decode($resposta, true);

    if(!is_array($turmas)){
        error_log("[buscarTurmasAPI] JSON inválido recebido da catraca.");
        echo json_encode([]);
        exit;
    }

    // Grava cache para as próximas requisições
    file_put_contents($cacheArquivo, json_encode($turmas));
}

// ── FILTRA AS TURMAS PELA DATA ─────────────────────────────────

$resultado = [];

foreach($turmas as $t){
    // Proteção: ignora entradas sem os campos esperados
    if(!isset($t['dataInicio'], $t['dataFim'], $t['nome'])) continue;

    if($data >= $t['dataInicio'] && $data <= $t['dataFim']){
        $resultado[] = $t['nome'];
    }
}

echo json_encode($resultado);
?>