<?php
/*
 * syncTurmas.php
 *
 * Fluxo:
 *   1. GET {CATRACA_BASE_URL}/backapi/Turmas
 *      -> upsert em `turmas`
 *   2. Para cada turma retornada:
 *        GET {CATRACA_BASE_URL}/backapi/Turmas/{id}/alunos
 *        -> upsert em `turma_alunos`
 *        -> remove da tabela quem não veio mais na resposta (aluno saiu da turma)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ajuste este include para o caminho real do seu conexao.php
include(__DIR__ . '/conexao.php');

// TODO: ajuste para a mesma constante/config já usada em cadUser.php, se já existir uma
const CATRACA_BASE_URL = 'http://172.16.95.253:3002';

$logFile = __DIR__ . '/logs/sync_turmas_' . date('Y-m-d') . '.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0775, true);
}

function logMsg($msg) {
    global $logFile;
    $linha = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    echo $linha;
    file_put_contents($logFile, $linha, FILE_APPEND);
}

/**
 * Faz uma requisição GET simples via cURL e retorna o JSON decodificado.
 * Retorna null em caso de falha (timeout, erro de rede, HTTP != 200).
 */
function catracaGet($endpoint) {
    $url = CATRACA_BASE_URL . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro     = curl_error($ch);
    curl_close($ch);

    if ($resposta === false || $erro) {
        logMsg("ERRO cURL em $endpoint: $erro");
        return null;
    }

    if ($httpCode !== 200) {
        logMsg("ERRO HTTP $httpCode em $endpoint");
        return null;
    }

    $dados = json_decode($resposta, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMsg("ERRO ao decodificar JSON de $endpoint: " . json_last_error_msg());
        return null;
    }

    return $dados;
}

logMsg('===== Início da sincronização de turmas =====');

$turmas = catracaGet('/backapi/Turmas');

if ($turmas === null) {
    logMsg('Falha ao buscar lista de turmas na catraca. Abortando sincronização.');
    exit(1);
}

logMsg('Turmas recebidas da catraca: ' . count($turmas));

$agora = date('Y-m-d H:i:s');

$stmtUpsertTurma = $pdo->prepare("
    INSERT INTO turmas (id, nome, codigo, data_inicio, data_fim, ativo_catraca, sincronizado_em)
    VALUES (:id, :nome, :codigo, :data_inicio, :data_fim, :ativo, :sync)
    ON DUPLICATE KEY UPDATE
        nome = VALUES(nome),
        codigo = VALUES(codigo),
        data_inicio = VALUES(data_inicio),
        data_fim = VALUES(data_fim),
        ativo_catraca = VALUES(ativo_catraca),
        sincronizado_em = VALUES(sincronizado_em)
");

$stmtUpsertAluno = $pdo->prepare("
    INSERT INTO turma_alunos (turma_id, pessoa_id, nome, documento, foto_url, status_matricula, sincronizado_em)
    VALUES (:turma_id, :pessoa_id, :nome, :documento, :foto_url, :status, :sync)
    ON DUPLICATE KEY UPDATE
        nome = VALUES(nome),
        documento = VALUES(documento),
        foto_url = VALUES(foto_url),
        status_matricula = VALUES(status_matricula),
        sincronizado_em = VALUES(sincronizado_em)
");

// Remove da turma quem não veio mais na sincronização atual (saiu/foi remanejado)
$stmtLimpaAlunosAntigos = $pdo->prepare("
    DELETE FROM turma_alunos
    WHERE turma_id = :turma_id
      AND sincronizado_em < :sync
");

$totalTurmasOk = 0;
$totalAlunosOk = 0;

foreach ($turmas as $t) {
    if (empty($t['id'])) {
        continue;
    }

    try {
        $stmtUpsertTurma->execute([
            ':id'          => $t['id'],
            ':nome'        => $t['nome']    ?? '',
            ':codigo'      => $t['codigo']  ?? '',
            ':data_inicio' => $t['dataInicio'] ?? null,
            ':data_fim'    => $t['dataFim']    ?? null,
            ':ativo'       => !empty($t['ativo']) ? 1 : 0,
            ':sync'        => $agora,
        ]);
        $totalTurmasOk++;
    } catch (Exception $e) {
        logMsg("ERRO ao salvar turma {$t['id']}: " . $e->getMessage());
        continue;
    }

    $alunos = catracaGet('/backapi/Turmas/' . $t['id'] . '/alunos');

    if ($alunos === null) {
        logMsg("AVISO: não foi possível buscar alunos da turma {$t['codigo']} ({$t['id']}). Mantendo lista anterior.");
        continue;
    }

    foreach ($alunos as $a) {
        if (empty($a['pessoaId'])) {
            continue;
        }
        // Só carrega alunos com matrícula ativa (statusMatricula = 1) vindos da catraca.
        // Os demais simplesmente não são sincronizados (nem inseridos, nem atualizados).
        if (($a['statusMatricula'] ?? null) !== 1) {
            continue;
        }
        try {
            $stmtUpsertAluno->execute([
                ':turma_id' => $t['id'],
                ':pessoa_id' => $a['pessoaId'],
                ':nome'      => $a['nome']      ?? '',
                ':documento' => $a['documento'] ?? '',
                ':foto_url'  => $a['fotoUrl']   ?? null,
                ':status'    => $a['statusMatricula'] ?? 1,
                ':sync'      => $agora,
            ]);
            $totalAlunosOk++;
        } catch (Exception $e) {
            logMsg("ERRO ao salvar aluno {$a['pessoaId']} da turma {$t['id']}: " . $e->getMessage());
        }
    }

    // Só limpa alunos "sumidos" se a busca desta turma funcionou de verdade
    $stmtLimpaAlunosAntigos->execute([
        ':turma_id' => $t['id'],
        ':sync'     => $agora,
    ]);
}

logMsg("Turmas sincronizadas: $totalTurmasOk / " . count($turmas));
logMsg("Vínculos aluno-turma sincronizados: $totalAlunosOk");
logMsg('===== Fim da sincronização de turmas =====');