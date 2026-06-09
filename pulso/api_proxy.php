<?php
// pulso/gestao/api_proxy.php
// Proxy multi-provedor para análise com IA.
// Suporta: Groq, Gemini, OpenAI, Anthropic.

// ob_start captura qualquer output indesejado (warnings, BOM do conexao.php, etc.)
// que quebraria a resposta JSON
ob_start();
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['sLogin'])) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

include('../conexao.php');

// Descarta qualquer output que o conexao.php possa ter emitido
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$unidade_id = $_SESSION['pulso_unidade_id'] ?? 1;

// Lê configurações do banco
function cfg_read(PDO $pdo, int $uid, string $chave): string {
    $q = $pdo->prepare("SELECT valor FROM pulso_config WHERE unidade_id=? AND chave=? LIMIT 1");
    $q->execute([$uid, $chave]);
    return $q->fetchColumn() ?: '';
}

$provedor = cfg_read($pdo, $unidade_id, 'ia_provedor') ?: 'groq';
$api_key  = cfg_read($pdo, $unidade_id, "ia_key_{$provedor}");
if (!$api_key) $api_key = getenv('IA_API_KEY') ?: '';

if (!$api_key) {
    http_response_code(503);
    echo json_encode(['error' => "Chave da API não configurada para o provedor '{$provedor}'. Acesse Configurações → Análise com IA."]);
    exit;
}

// Lê payload do JS
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload || empty($payload['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido', 'recebido' => substr($raw, 0, 200)]);
    exit;
}

// Extrai prompt
$prompt = '';
foreach ($payload['messages'] as $msg) {
    if ($msg['role'] === 'user') {
        $prompt = is_array($msg['content'])
            ? implode(' ', array_column($msg['content'], 'text'))
            : $msg['content'];
        break;
    }
}

// Configurações por provedor
$cfg = [
    'groq' => [
        'url'     => 'https://api.groq.com/openai/v1/chat/completions',
        'modelo'  => 'llama-3.1-8b-instant',
        'formato' => 'openai',
    ],
    'gemini' => [
        'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
        'modelo'  => 'gemini-1.5-flash',
        'formato' => 'gemini',
    ],
    'openai' => [
        'url'     => 'https://api.openai.com/v1/chat/completions',
        'modelo'  => 'gpt-4o-mini',
        'formato' => 'openai',
    ],
    'anthropic' => [
        'url'     => 'https://api.anthropic.com/v1/messages',
        'modelo'  => 'claude-haiku-4-5-20251001',
        'formato' => 'anthropic',
    ],
];

if (!isset($cfg[$provedor])) {
    http_response_code(400);
    echo json_encode(['error' => "Provedor desconhecido: {$provedor}"]);
    exit;
}

$c = $cfg[$provedor];

// Monta payload e headers por formato
if ($c['formato'] === 'openai') {
    // Respeita max_tokens enviado pelo cliente (para controle de rate limit)
    // padrão 600 — suficiente para JSON compacto de análise parcial
    $max_tok = isset($payload['max_tokens']) ? (int)$payload['max_tokens'] : 600;
    $max_tok = min($max_tok, 1500); // nunca passa de 1500 no groq free

    $body_enviar = json_encode([
        'model'      => $c['modelo'],
        'max_tokens' => $max_tok,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ];
    $url = $c['url'];

} elseif ($c['formato'] === 'gemini') {
    $body_enviar = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 2000, 'temperature' => 0.4],
    ]);
    $headers = ['Content-Type: application/json'];
    $url     = $c['url'] . '?key=' . urlencode($api_key);

} elseif ($c['formato'] === 'anthropic') {
    $body_enviar = json_encode([
        'model'      => $c['modelo'],
        'max_tokens' => 2000,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ];
    $url = $c['url'];
}

// Chamada cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body_enviar,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    http_response_code(502);
    echo json_encode(['error' => 'Erro de conexão cURL: ' . $curl_err]);
    exit;
}

// Se não for JSON válido, devolve o raw para diagnóstico
$data = json_decode($response, true);
if ($data === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Resposta inválida da API', 'raw' => substr($response, 0, 500)]);
    exit;
}

// Normaliza resposta para o formato que o JS espera
$texto = '';

if ($c['formato'] === 'openai') {
    $texto = $data['choices'][0]['message']['content'] ?? '';
} elseif ($c['formato'] === 'gemini') {
    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
} elseif ($c['formato'] === 'anthropic') {
    $texto = $data['content'][0]['text'] ?? '';
}

if (!$texto) {
    http_response_code($http_code ?: 500);
    echo json_encode([
        'error' => $data['error']['message'] ?? 'Resposta vazia da API',
        'raw'   => $data,
    ]);
    exit;
}

echo json_encode([
    'content' => [['type' => 'text', 'text' => $texto]]
]);