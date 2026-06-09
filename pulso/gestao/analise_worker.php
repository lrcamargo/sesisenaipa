<?php
// pulso/gestao/analise_worker.php
// Processo de análise em background.
// Chamado via exec() pelo analise_ia.php — NÃO deve ser acessado pelo browser diretamente.
// Roda análise completa sem limitação de timeout do browser.
//
// Chamada: php analise_worker.php <job_id>

// Sem limite de tempo — processo longo
set_time_limit(0);
ini_set('memory_limit', '256M');

// Só aceita execução via CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$job_id = (int)($argv[1] ?? 0);
if (!$job_id) {
    echo "Uso: php analise_worker.php <job_id>\n";
    exit(1);
}

// Carrega conexão com o banco
// Ajuste o caminho conforme a estrutura do seu servidor
$base = dirname(__DIR__, 2); // sobe 2 níveis: gestao → pulso → raiz
require_once $base . '/conexao.php';

// Helpers
function atualizar(PDO $pdo, int $job_id, string $status, int $pct, string $msg): void {
    $pdo->prepare("
        UPDATE pulso_analise_jobs
        SET status=?, progresso=?, progresso_msg=?,
            iniciado_em = CASE WHEN iniciado_em IS NULL THEN NOW() ELSE iniciado_em END
        WHERE id=?
    ")->execute([$status, $pct, $msg, $job_id]);
}

function concluir(PDO $pdo, int $job_id, string $resultado): void {
    $pdo->prepare("
        UPDATE pulso_analise_jobs
        SET status='concluido', progresso=100, progresso_msg='Análise concluída!',
            resultado=?, concluido_em=NOW()
        WHERE id=?
    ")->execute([$resultado, $job_id]);
}

function falhar(PDO $pdo, int $job_id, string $erro): void {
    $pdo->prepare("
        UPDATE pulso_analise_jobs
        SET status='erro', progresso_msg=?, erro_msg=?, concluido_em=NOW()
        WHERE id=?
    ")->execute([$erro, $erro, $job_id]);
}

function chamar_api(string $api_key, string $provedor, string $prompt, int $max_tokens = 600): string {
    $cfg = [
        'groq'      => ['url' => 'https://api.groq.com/openai/v1/chat/completions',      'fmt' => 'openai'],
        'gemini'    => ['url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent', 'fmt' => 'gemini'],
        'openai'    => ['url' => 'https://api.openai.com/v1/chat/completions',            'fmt' => 'openai'],
        'anthropic' => ['url' => 'https://api.anthropic.com/v1/messages',                'fmt' => 'anthropic'],
    ];
    $modelos = [
        'groq' => 'llama-3.1-8b-instant',
        'gemini' => 'gemini-1.5-flash',
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-haiku-4-5-20251001',
    ];

    $c   = $cfg[$provedor];
    $mod = $modelos[$provedor];

    if ($c['fmt'] === 'openai') {
        $body    = json_encode(['model' => $mod, 'max_tokens' => $max_tokens, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key];
        $url     = $c['url'];
    } elseif ($c['fmt'] === 'gemini') {
        $body    = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['maxOutputTokens' => $max_tokens, 'temperature' => 0.4]]);
        $headers = ['Content-Type: application/json'];
        $url     = $c['url'] . '?key=' . urlencode($api_key);
    } elseif ($c['fmt'] === 'anthropic') {
        $body    = json_encode(['model' => $mod, 'max_tokens' => $max_tokens, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $headers = ['Content-Type: application/json', 'x-api-key: ' . $api_key, 'anthropic-version: 2023-06-01'];
        $url     = $c['url'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("cURL: $err");

    $data = json_decode($resp, true);
    if (!$data) throw new Exception("Resposta inválida da API: " . substr($resp, 0, 200));

    if ($c['fmt'] === 'openai') {
        $texto = $data['choices'][0]['message']['content'] ?? '';
        if (!$texto) throw new Exception($data['error']['message'] ?? 'Resposta vazia');
    } elseif ($c['fmt'] === 'gemini') {
        $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!$texto) throw new Exception($data['error']['message'] ?? 'Resposta vazia');
    } elseif ($c['fmt'] === 'anthropic') {
        $texto = $data['content'][0]['text'] ?? '';
        if (!$texto) throw new Exception($data['error']['message'] ?? 'Resposta vazia');
    }

    return $texto;
}

// ── INÍCIO DO PROCESSAMENTO ───────────────────────────────────

// Busca o job
$stmt = $pdo->prepare("SELECT * FROM pulso_analise_jobs WHERE id=? AND status='pendente'");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo "Job $job_id não encontrado ou já processado.\n";
    exit(1);
}

// Marca como processando
atualizar($pdo, $job_id, 'processando', 2, 'Iniciando análise...');

try {
    // Configurações de IA
    $provedor = '';
    $api_key  = '';
    $stmt2 = $pdo->prepare("SELECT chave, valor FROM pulso_config WHERE unidade_id=?");
    $stmt2->execute([$job['unidade_id']]);
    $configs = [];
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $configs[$r['chave']] = $r['valor'];
    }
    $provedor = $configs['ia_provedor'] ?? 'groq';
    $api_key  = $configs["ia_key_{$provedor}"] ?? '';

    if (!$api_key) throw new Exception("Chave de API não configurada para o provedor '$provedor'.");

    // Busca respostas abertas do ciclo
    $stmt3 = $pdo->prepare("
        SELECT p.categoria, p.texto AS pergunta,
               ra.texto AS resposta
        FROM pulso_perguntas p
        INNER JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
        WHERE p.ciclo_id = ? AND p.tipo = 'aberta'
          AND ra.texto IS NOT NULL AND TRIM(ra.texto) != ''
        ORDER BY p.categoria, p.ordem, p.id
    ");
    $stmt3->execute([$job['ciclo_id']]);

    $por_categoria = [];
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $key = $r['categoria'];
        if (!isset($por_categoria[$key])) {
            $por_categoria[$key] = ['categoria' => $key, 'blocos' => []];
        }
        $pk = $r['pergunta'];
        if (!isset($por_categoria[$key]['blocos'][$pk])) {
            $por_categoria[$key]['blocos'][$pk] = [];
        }
        $por_categoria[$key]['blocos'][$pk][] = $r['resposta'];
    }

    if (empty($por_categoria)) {
        throw new Exception("Nenhuma resposta aberta encontrada neste ciclo.");
    }

    $categorias  = array_values($por_categoria);
    $total_cats  = count($categorias);
    $resumos     = [];

    // ── FASE 1: análise por categoria ──────────────────────────
    // No worker não há rate limit de browser — mas ainda respeitamos o Groq
    $pausa_segundos = ($provedor === 'groq') ? 30 : 3;

    foreach ($categorias as $i => $cat_data) {
        $cat = $cat_data['categoria'];
        $pct = (int)(($i / $total_cats) * 75); // fase 1 vai até 75%

        atualizar($pdo, $job_id, 'processando', $pct,
            "Analisando: $cat (" . ($i + 1) . "/$total_cats)");

        // Monta texto do bloco
        $texto_bloco = "CATEGORIA: $cat\n";
        $chars = 0;
        foreach ($cat_data['blocos'] as $pergunta => $respostas) {
            $texto_bloco .= "PERGUNTA: \"$pergunta\"\nRESPOSTAS:\n";
            foreach ($respostas as $j => $resp) {
                $linha = "  " . ($j + 1) . ". \"$resp\"\n";
                if ($chars + strlen($linha) > 4000) break; // ~1000 tokens max por bloco no worker
                $texto_bloco .= $linha;
                $chars += strlen($linha);
            }
        }

        $prompt = "Analise as respostas de clima organizacional abaixo. Responda SOMENTE com JSON válido, sem texto adicional.\n\n$texto_bloco\n\nJSON:\n{\"categoria\":\"$cat\",\"temas\":[\"tema1\"],\"positivos\":\"frase\",\"criticos\":\"frase\",\"acoes\":[\"acao1\",\"acao2\"]}";

        $texto = chamar_api($api_key, $provedor, $prompt, 800);
        $clean = trim(preg_replace('/```json|```/', '', $texto));

        $parcial = json_decode($clean, true);
        if ($parcial) {
            $resumos[] = $parcial;
        } else {
            $resumos[] = ['categoria' => $cat, 'texto_bruto' => $texto];
        }

        echo "[$cat] OK\n";

        // Pausa entre categorias (respeita rate limit do Groq free)
        if ($i < $total_cats - 1) {
            atualizar($pdo, $job_id, 'processando', $pct,
                "\"$cat\" concluída. Aguardando {$pausa_segundos}s para continuar...");
            sleep($pausa_segundos);
        }
    }

    // ── FASE 2: consolidação ──────────────────────────────────
    atualizar($pdo, $job_id, 'processando', 80, 'Consolidando análise completa...');

    if ($provedor === 'groq') sleep(30); // pausa antes da consolidação

    $resumo_texto = implode("\n\n---\n\n", array_map(function($r) {
        if (isset($r['texto_bruto'])) return "CATEGORIA: {$r['categoria']}\n{$r['texto_bruto']}";
        $pos  = $r['positivos']  ?? $r['pontos_positivos']  ?? '';
        $crit = $r['criticos']   ?? $r['pontos_criticos']   ?? '';
        $acs  = $r['acoes']      ?? $r['acoes_sugeridas']   ?? [];
        return "CATEGORIA: {$r['categoria']}\nTemas: " . implode(', ', $r['temas'] ?? []) .
               "\nPositivos: $pos\nCríticos: $crit\nAções: " . implode('; ', $acs);
    }, $resumos));

    $prompt_final = "Você é especialista em clima organizacional em escolas técnicas SENAI.
Com base nos resumos por categoria abaixo, produza uma análise qualitativa completa e profunda.

$resumo_texto

Responda APENAS com JSON válido, sem texto antes ou depois, sem markdown:
{
  \"temas_recorrentes\": [{\"tema\": \"nome\", \"frequencia\": \"alta|media|baixa\", \"descricao\": \"frase\"}],
  \"o_que_valoriza\": \"parágrafo completo sobre o que a equipe mais valoriza\",
  \"o_que_preocupa\": \"parágrafo completo sobre os pontos críticos e preocupantes\",
  \"planos_acao\": [
    {
      \"titulo\": \"título conciso\",
      \"urgencia\": \"imediata|medio_prazo|longo_prazo\",
      \"objetivo\": \"o que se quer alcançar\",
      \"responsavel_tipo\": \"gestao|docente|compartilhado\",
      \"responsavel_sugerido\": \"papel específico\",
      \"prazo_sugerido\": \"prazo realista\",
      \"acoes\": [
        {\"descricao\": \"ação\", \"como\": \"como executar\", \"responsavel_tipo\": \"gestao\", \"responsavel_sugerido\": \"papel\", \"prazo_sugerido\": \"prazo\"}
      ]
    }
  ],
  \"observacoes\": \"observações importantes sobre padrões, contradições ou nuances\"
}";

    atualizar($pdo, $job_id, 'processando', 88, 'Gerando análise integrada...');
    $texto_final = chamar_api($api_key, $provedor, $prompt_final, 1500);

    $clean_final = trim(preg_replace('/```json|```/', '', $texto_final));
    $analise = json_decode($clean_final, true);

    if (!$analise) {
        throw new Exception("JSON inválido na consolidação: " . substr($clean_final, 0, 300));
    }

    // Salva resultado
    atualizar($pdo, $job_id, 'processando', 95, 'Salvando resultado...');
    concluir($pdo, $job_id, json_encode($analise, JSON_UNESCAPED_UNICODE));
    echo "Job $job_id concluído com sucesso.\n";

    // ── NOTIFICAÇÃO POR E-MAIL ────────────────────────────────
    $email_destino = $job['email_destino'];
    if ($email_destino) {
        // Busca nome do ciclo
        $stmt4 = $pdo->prepare("SELECT titulo FROM pulso_ciclos WHERE id=?");
        $stmt4->execute([$job['ciclo_id']]);
        $ciclo_titulo = $stmt4->fetchColumn() ?: "Ciclo #{$job['ciclo_id']}";

        $url_resultado = INTRANET_URL . "/pulso/gestao/analise_ia.php?ciclo={$job['ciclo_id']}&job={$job_id}";

        $n_planos = count($analise['planos_acao'] ?? []);
        $n_temas  = count($analise['temas_recorrentes'] ?? []);

        $html_email = "
<p>Olá,</p>
<p>A análise qualitativa com IA do ciclo <strong>$ciclo_titulo</strong> foi concluída.</p>
<hr>
<p>
    <strong>Resumo:</strong><br>
    • $n_temas tema(s) recorrente(s) identificado(s)<br>
    • $n_planos plano(s) de ação sugerido(s)<br>
</p>
<p><a href='$url_resultado' class='btn'>Ver análise completa →</a></p>
<hr>
<p style='font-size:12px;color:#888;'>
    Esta análise foi gerada automaticamente pela IA.
    Revise os planos de ação antes de publicar para a equipe.
</p>";

        $mailer_path = dirname(__DIR__, 2) . '/includes/email/mailer.php';
        if (file_exists($mailer_path)) {
            require_once $mailer_path;
            Mailer::enviar(
                $email_destino,
                "✅ Análise de Clima Concluída — $ciclo_titulo",
                $html_email
            );
            echo "E-mail enviado para $email_destino.\n";
        } else {
            echo "Mailer não encontrado — e-mail não enviado.\n";
        }
    }

} catch (Exception $e) {
    falhar($pdo, $job_id, $e->getMessage());
    echo "ERRO no job $job_id: " . $e->getMessage() . "\n";
    exit(1);
}