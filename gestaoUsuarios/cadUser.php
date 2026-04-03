<?php
/*
 * cadUser.php
 *
 * Fluxo:
 *   1. INSERT interno no banco
 *   2. GET na catraca para verificar se o registro já existe
 *        a) Catraca inacessível  → grava em catraca_pendentes.json
 *        b) Pessoa já existe     → nada a fazer na catraca
 *        c) Pessoa não existe    → POST para criar na catraca
 *                                   → falha no POST → grava pendente
 */

require_once __DIR__ . '/../conexao.php';
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

/* ── CONFIGURAÇÕES ── */

define('API_BASE',     'http://172.16.95.253:3002/backapi');
define('TIMEOUT_CONN', 5);   // segundos para estabelecer conexão
define('TIMEOUT_REQ',  10);  // segundos para a resposta completa

// Arquivo de fila para cadastros pendentes na catraca
define('FILA_CATRACA', __DIR__ . '/../data/catraca_pendentes.json');

if(!is_dir(__DIR__ . '/../data')){
    mkdir(__DIR__ . '/../data', 0750, true);
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('location:cadastrarUsuario.php');
    exit;
}

/* ── RECEBE OS CAMPOS ── */

$registro = trim($_POST['registro'] ?? '');
$nome     = trim($_POST['nome']     ?? '');
$email    = trim($_POST['email']    ?? '');
$usuario  = trim($_POST['user']     ?? '');
$senha    = $_POST['senha']          ?? '';
$perfil   = trim($_POST['perfil']   ?? '');

/* ── VALIDAÇÃO ── */

if(!$registro || !$nome || !$email || !$usuario || !$senha || !$perfil){
    header('location:cadastrarUsuario.php?erro=1');
    exit;
}

/* ── VERIFICA DUPLICIDADE INTERNA ── */

$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ? OR email = ?");
$stmtCheck->execute([$usuario, $email]);
if($stmtCheck->fetchColumn() > 0){
    header('location:cadastrarUsuario.php?erro=2');
    exit;
}

/* ── INSERT INTERNO ── */

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try{
    $pdo->prepare("
        INSERT INTO usuarios (registro, nome, email, usuario, senha, perfil, status, primeiro_login)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ")->execute([$registro, $nome, $email, $usuario, $senhaHash, $perfil]);

} catch(PDOException $e){
    error_log("[cadUser] INSERT: " . $e->getMessage());
    header('location:cadastrarUsuario.php?erro=3');
    exit;
}

/* ── PREPARA DADOS DA CATRACA ── */

$tipoCatraca = (strtolower($perfil) === 'professor') ? 4 : 2;
$validoAte   = (new DateTime())->modify('+5 years')->format('Y-m-d\TH:i:s');

/* ════════════════════════════════════════════════════════════
   PASSO 1 — Verifica se o registro já existe na catraca
   GET /backapi/Pessoas?documento={registro}
   ════════════════════════════════════════════════════════════ */

$urlBusca = API_BASE . '/Pessoas?documento=' . urlencode($registro);

$chBusca = curl_init($urlBusca);
curl_setopt_array($chBusca, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => TIMEOUT_REQ,
    CURLOPT_CONNECTTIMEOUT => TIMEOUT_CONN,
]);

$respostaBusca = curl_exec($chBusca);
$httpBusca     = curl_getinfo($chBusca, CURLINFO_HTTP_CODE);
$erroBusca     = curl_error($chBusca);
curl_close($chBusca);

/* ── Catraca inacessível na busca ── */
if($erroBusca || $httpBusca === 0){
    error_log("[cadUser] Catraca inacessível na busca. cURL: {$erroBusca}");
    gravarPendente($registro, $nome, $tipoCatraca, $validoAte, 0, $erroBusca);
    header('location:cadastrarUsuario.php?ok=2');
    exit;
}

/* ── Interpreta a resposta da busca ──
 *
 * Trata os cenários mais comuns:
 *   - HTTP 404              → pessoa não existe
 *   - HTTP 200 + array []   → pessoa não existe
 *   - HTTP 200 + dados      → pessoa já existe
 *   - Outro código          → tratado como "não existe" para tentar o POST
 */

$pessoaExiste = false;

if($httpBusca === 200 && !empty($respostaBusca)){
    $dados = json_decode($respostaBusca, true);

    if(is_array($dados) && count($dados) > 0){
        // Retornou array com dados → pessoa existe
        $pessoaExiste = true;
    } elseif(is_array($dados) && count($dados) === 0){
        // Array vazio → pessoa não existe
        $pessoaExiste = false;
    } elseif(is_object($dados) || (is_array($dados) && isset($dados['id']))){
        // Retornou objeto único → pessoa existe
        $pessoaExiste = true;
    }
    // JSON inválido → trata como não existe e tenta POST
}

/* ── Pessoa já existe na catraca: nada a fazer ── */
if($pessoaExiste){
    error_log("[cadUser] Registro {$registro} já existe na catraca. Cadastrado apenas internamente.");
    header('location:cadastrarUsuario.php?ok=3'); // ok=3 = interno ok, catraca já tinha
    exit;
}

/* ════════════════════════════════════════════════════════════
   PASSO 2 — Pessoa não existe: cria na catraca
   POST /backapi/Pessoas
   ════════════════════════════════════════════════════════════ */

$payload = json_encode([
    'tipo'      => $tipoCatraca,
    'nome'      => $nome,
    'documento' => $registro,
    'validoAte' => $validoAte,
    'ativo'     => true,
]);

$chPost = curl_init(API_BASE . '/Pessoas');
curl_setopt_array($chPost, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => TIMEOUT_REQ,
    CURLOPT_CONNECTTIMEOUT => TIMEOUT_CONN,
]);

$respostaPost = curl_exec($chPost);
$httpPost     = curl_getinfo($chPost, CURLINFO_HTTP_CODE);
$erroPost     = curl_error($chPost);
curl_close($chPost);

$catracaOk = ($httpPost >= 200 && $httpPost < 300 && empty($erroPost));

if($catracaOk){
    header('location:cadastrarUsuario.php?ok=1');
    exit;
}

/* ── POST falhou: grava na fila de pendentes ── */
error_log("[cadUser] Falha no POST da catraca. HTTP: {$httpPost} | cURL: {$erroPost}");
gravarPendente($registro, $nome, $tipoCatraca, $validoAte, $httpPost, $erroPost);
header('location:cadastrarUsuario.php?ok=2');
exit;

function gravarPendente(
    string $registro,
    string $nome,
    int    $tipo,
    string $validoAte,
    int    $httpCode,
    string $erroMsg
): void {

    $fila = [];

    if(file_exists(FILA_CATRACA)){
        $decoded = json_decode(file_get_contents(FILA_CATRACA), true);
        if(is_array($decoded)) $fila = $decoded;
    }

    // Evita duplicatas: se o registro já está na fila, não adiciona de novo
    foreach($fila as $entrada){
        if(($entrada['registro'] ?? '') === $registro) return;
    }

    $fila[] = [
        'registro'  => $registro,
        'nome'      => $nome,
        'tipo'      => $tipo,
        'validoAte' => $validoAte,
        'ativo'     => true,
        'tentativas'=> 0,
        'criadoEm'  => (new DateTime())->format('Y-m-d\TH:i:s'),
        'erroHttp'  => $httpCode,
        'erroCurl'  => $erroMsg,
    ];

    file_put_contents(FILA_CATRACA, json_encode($fila, JSON_PRETTY_PRINT));
}
?>