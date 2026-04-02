<?php
/*
 * cadUser.php
 *
 * Cadastra o usuário internamente e tenta sincronizar com a catraca.
 * Se a catraca falhar, registra em catraca_pendentes.json para
 * reprocessamento posterior por script agendado (Python/cron).
 */

require_once __DIR__ . '/../conexao.php';
session_start();
 
if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}
 
// ARQUIVO DE FILA DE PENDENTES DA CATRACA 
define('FILA_CATRACA', __DIR__ . '/../data/catraca_pendentes.json');
 
// Garante que a pasta data existe
if(!is_dir(__DIR__ . '/../data')){
    mkdir(__DIR__ . '/../data', 0750, true);
}
 
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('location:cadastrarUsuario.php');
    exit;
}
 
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
 
/* ── VERIFICA DUPLICIDADE ── */
 
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ? OR email = ?");
$stmtCheck->execute([$usuario, $email]);
if($stmtCheck->fetchColumn() > 0){
    header('location:cadastrarUsuario.php?erro=2');
    exit;
}
 
/* ── INSERT INTERNO ── */
 
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);
 
try{
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (registro, nome, email, usuario, senha, perfil, status, primeiro_login)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ");
    $stmt->execute([$registro, $nome, $email, $usuario, $senhaHash, $perfil]);
 
} catch(PDOException $e){
    error_log("[cadUser] INSERT: ".$e->getMessage());
    header('location:cadastrarUsuario.php?erro=3');
    exit;
}
 
/* ── INTEGRAÇÃO COM A CATRACA ── */
 
$tipoCatraca = (strtolower($perfil) === 'professor') ? 4 : 2;
$validoAte   = (new DateTime())->modify('+5 years')->format('Y-m-d\TH:i:s');
 
$payload = json_encode([
    'tipo'      => $tipoCatraca,
    'nome'      => $nome,
    'documento' => $registro,
    'validoAte' => $validoAte,
    'ativo'     => true,
]);
 
$ch = curl_init('http://172.16.95.253:3002/backapi/Pessoas');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
 
$respostaCatraca = curl_exec($ch);
$httpCode        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erroCurl        = curl_error($ch);
curl_close($ch);
 
$catracaOk = ($httpCode >= 200 && $httpCode < 300 && empty($erroCurl));
 
if(!$catracaOk){
 
    // ── REGISTRA NA FILA DE PENDENTES ────────────────────────────
    // O script Python agendado (cron) lê este arquivo, tenta enviar
    // cada entrada para a catraca e remove as que tiverem sucesso.
    //
    // Estrutura de cada entrada:
    // {
    //   "registro":  "12345",
    //   "nome":      "Fulano",
    //   "tipo":      2,
    //   "validoAte": "2030-01-01T00:00:00",
    //   "ativo":     true,
    //   "tentativas": 0,
    //   "criadoEm":  "2025-04-01T14:00:00"
    // }
 
    $fila = [];
    if(file_exists(FILA_CATRACA)){
        $filaBruta = file_get_contents(FILA_CATRACA);
        $decoded   = json_decode($filaBruta, true);
        if(is_array($decoded)) $fila = $decoded;
    }
 
    $fila[] = [
        'registro'  => $registro,
        'nome'      => $nome,
        'tipo'      => $tipoCatraca,
        'validoAte' => $validoAte,
        'ativo'     => true,
        'tentativas'=> 0,
        'criadoEm'  => (new DateTime())->format('Y-m-d\TH:i:s'),
        'erroHttp'  => $httpCode,
        'erroCurl'  => $erroCurl,
    ];
 
    file_put_contents(FILA_CATRACA, json_encode($fila, JSON_PRETTY_PRINT));
 
    error_log("[cadUser] Catraca falhou (HTTP:{$httpCode}). Adicionado à fila: {$registro}");
 
    // ok=2 → interno ok, catraca na fila
    header('location:cadastrarUsuario.php?ok=2');
    exit;
}
 
// ok=1 → tudo certo
header('location:cadastrarUsuario.php?ok=1');
exit;
?>