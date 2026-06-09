<?php
// pulso/gestao/_guard.php
// Inclua no topo de TODAS as páginas da área de gestão:
//   require_once '_guard.php';
//
// Usa a sessão da intranet — sem login próprio.
// Redireciona para o login da intranet se não estiver autenticado.
// Bloqueia acesso se o grupo não tiver permissão de gestão.

if (session_status() === PHP_SESSION_NONE) session_start();

// Não autenticado → volta para o login da intranet
if (!isset($_SESSION['sLogin'])) {
    header('Location: ../../index.php'); exit;
}

// Normaliza grupo (mesmo padrão do resto da intranet)
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group'] ?? ''));

// Grupos com acesso ao painel de gestão do PulsoSENAI
$grupos_gestao = ['gerencia', 'sup tecnica', 'sup pedagogica', 'administrator'];

if (!in_array($nivelNorm, $grupos_gestao)) {
    http_response_code(403);
    echo '<p style="font-family:\'Segoe UI\',sans-serif;padding:48px;color:#c0392b;font-size:15px;">
            <strong>Acesso restrito.</strong><br>
            Seu perfil não tem permissão para acessar o painel de gestão do PulsoSENAI.
          </p>';
    exit;
}

// Atalhos disponíveis em todas as páginas de gestão
$g_nome        = $_SESSION['user']  ?? 'Gestor';
$g_perfil      = $nivelNorm;
$is_gerente    = in_array($nivelNorm, ['gerencia', 'sup tecnica', 'sup pedagogica', 'administrator']);

// Unidade: fixada por enquanto na unidade piloto.
// Quando houver multitenancy, buscar aqui a unidade do usuário.
// Por ora lê da sessão ou usa o default.
if (!isset($_SESSION['pulso_unidade_id'])) {
    // Carrega a unidade ativa padrão (única por enquanto)
    // conexao.php já foi incluído pela página que chamou este guard
    $stmt = $pdo->query("SELECT id, nome, slug FROM pulso_unidades WHERE ativa = 1 LIMIT 1");
    $uni  = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['pulso_unidade_id']   = $uni['id']   ?? 1;
    $_SESSION['pulso_unidade_nome'] = $uni['nome']  ?? 'Unidade';
    $_SESSION['pulso_unidade_slug'] = $uni['slug']  ?? '';
}
$g_unidade_id   = $_SESSION['pulso_unidade_id'];
$g_unidade_nome = $_SESSION['pulso_unidade_nome'];
$g_unidade_slug = $_SESSION['pulso_unidade_slug'];

// Helper: bloqueia página se não for gerente/admin
function exige_gerente() {
    global $is_gerente;
    if (!$is_gerente) {
        http_response_code(403);
        echo '<p style="font-family:\'Segoe UI\',sans-serif;padding:48px;color:#c0392b;font-size:15px;">
                <strong>Acesso restrito ao gerente.</strong>
              </p>';
        exit;
    }
}