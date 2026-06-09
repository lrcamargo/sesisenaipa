<?php
// pulso/gestao/api_teste.php
// Página de diagnóstico — acesse pelo browser para verificar se tudo está ok.
// REMOVA este arquivo após os testes.

if (session_status() === PHP_SESSION_NONE) session_start();

ob_start();
include('../conexao.php');
$output_lixo = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico — PulsoSENAI IA</title>
    <style>
        body { font-family: monospace; padding: 24px; background: #f4f6fa; }
        .ok  { color: #1a9e4a; font-weight: bold; }
        .err { color: #c0392b; font-weight: bold; }
        .box { background: #fff; border-radius: 8px; padding: 16px;
               margin-bottom: 16px; border: 1px solid #e0e6f0; }
        h2   { font-size: 14px; color: #164194; margin: 0 0 10px; }
        pre  { margin: 0; font-size: 13px; white-space: pre-wrap; }
    </style>
</head>
<body>
<h1 style="color:#164194;">Diagnóstico do Proxy de IA — PulsoSENAI</h1>

<?php
// 1. Sessão
echo '<div class="box"><h2>1. Sessão</h2>';
echo isset($_SESSION['sLogin'])
    ? '<span class="ok">✔ Autenticado</span> — usuário: ' . htmlspecialchars($_SESSION['sLogin'])
    : '<span class="err">✗ Não autenticado — faça login na intranet primeiro</span>';
echo '</div>';

// 2. Output do conexao.php
echo '<div class="box"><h2>2. Output do conexao.php</h2>';
echo empty($output_lixo)
    ? '<span class="ok">✔ Nenhum output indesejado</span>'
    : '<span class="err">✗ conexao.php está emitindo output:</span><pre>' . htmlspecialchars($output_lixo) . '</pre>';
echo '</div>';

// 3. cURL disponível
echo '<div class="box"><h2>3. cURL</h2>';
echo function_exists('curl_init')
    ? '<span class="ok">✔ cURL disponível</span>'
    : '<span class="err">✗ cURL não disponível — habilite a extensão no PHP</span>';
echo '</div>';

// 4. Tabela pulso_config
echo '<div class="box"><h2>4. Configuração de IA no banco</h2>';
try {
    $provedor = '';
    $chave    = '';
    $q = $pdo->prepare("SELECT chave, valor FROM pulso_config WHERE unidade_id = 1 AND chave IN ('ia_provedor','ia_key_groq','ia_key_gemini','ia_key_openai','ia_key_anthropic')");
    $q->execute();
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo '<span class="err">✗ Nenhuma configuração de IA encontrada — acesse Configurações para cadastrar</span>';
    } else {
        foreach ($rows as $r) {
            $val = $r['chave'] === 'ia_provedor'
                ? htmlspecialchars($r['valor'])
                : (empty($r['valor']) ? '<span class="err">vazia</span>' : '<span class="ok">configurada (' . substr($r['valor'],0,8) . '...)</span>');
            echo '<div>' . htmlspecialchars($r['chave']) . ': ' . $val . '</div>';
            if ($r['chave'] === 'ia_provedor') $provedor = $r['valor'];
        }
    }
} catch (Exception $e) {
    echo '<span class="err">✗ Erro ao consultar banco: ' . htmlspecialchars($e->getMessage()) . '</span>';
    echo '<br><small>Provavelmente a tabela pulso_config ainda não existe — acesse Configurações para criá-la</small>';
}
echo '</div>';

// 5. Teste de conectividade com a API
echo '<div class="box"><h2>5. Teste de conectividade com a API</h2>';
if (!empty($provedor) && function_exists('curl_init')) {
    $urls = [
        'groq'      => 'https://api.groq.com',
        'gemini'    => 'https://generativelanguage.googleapis.com',
        'openai'    => 'https://api.openai.com',
        'anthropic' => 'https://api.anthropic.com',
    ];
    $url_teste = $urls[$provedor] ?? '';
    if ($url_teste) {
        $ch = curl_init($url_teste);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        echo empty($err)
            ? '<span class="ok">✔ Servidor ' . htmlspecialchars($url_teste) . ' alcançável</span>'
            : '<span class="err">✗ Não consegue alcançar ' . htmlspecialchars($url_teste) . ': ' . htmlspecialchars($err) . '</span>';
    }
} else {
    echo '<span style="color:#888;">— configure o provedor primeiro</span>';
}
echo '</div>';

// 6. Teste do próprio proxy via POST interno
echo '<div class="box"><h2>6. Teste do proxy (POST para api_proxy.php)</h2>';
echo '<button onclick="testarProxy()" style="padding:8px 16px;background:#164194;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">Executar teste</button>';
echo '<div id="resultado_proxy" style="margin-top:12px;"></div>';
echo '</div>';
?>

<script>
async function testarProxy() {
    const div = document.getElementById('resultado_proxy');
    div.innerHTML = 'Testando...';
    try {
        const r = await fetch('api_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                messages: [{ role: 'user', content: 'Responda apenas: OK' }]
            })
        });
        const text = await r.text();
        let parsed;
        try { parsed = JSON.parse(text); } catch(e) { parsed = null; }

        if (parsed?.content?.[0]?.text) {
            div.innerHTML = '<span style="color:#1a9e4a;font-weight:bold;">✔ Proxy funcionando!</span> Resposta: ' +
                            parsed.content[0].text;
        } else if (parsed?.error) {
            div.innerHTML = '<span style="color:#c0392b;font-weight:bold;">✗ Erro:</span> ' + parsed.error +
                            (parsed.raw ? '<pre>' + JSON.stringify(parsed.raw, null, 2) + '</pre>' : '');
        } else {
            div.innerHTML = '<span style="color:#c0392b;font-weight:bold;">✗ Resposta inesperada:</span><pre>' +
                            text.substring(0, 500) + '</pre>';
        }
    } catch(e) {
        div.innerHTML = '<span style="color:#c0392b;font-weight:bold;">✗ Failed to fetch:</span> ' + e.message +
                        '<br><small>Verifique se está logado na intranet e se o arquivo api_proxy.php está na pasta correta.</small>';
    }
}
</script>
</body>
</html>