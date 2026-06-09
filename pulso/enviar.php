<?php
// pulso/enviar.php — Processa o envio anônimo
// REGRA CRÍTICA: nenhum identificador do respondente é armazenado.
// Apenas totalizadores agregados por pergunta.

include('../conexao.php');

// Valida POST básico
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$ciclo_id = filter_input(INPUT_POST, 'ciclo_id', FILTER_VALIDATE_INT);
$slug     = filter_input(INPUT_POST, 'unidade_slug', FILTER_SANITIZE_STRING);

if (!$ciclo_id || !$slug) {
    header('Location: index.php'); exit;
}

// Confirma que o ciclo ainda está aberto
$stmt = $pdo->prepare("
    SELECT c.id FROM pulso_ciclos c
    INNER JOIN pulso_unidades u ON u.id = c.unidade_id
    WHERE c.id = ? AND c.status = 'aberto' AND u.slug = ?
");
$stmt->execute([$ciclo_id, $slug]);
if (!$stmt->fetch()) {
    header('Location: index.php?erro=ciclo_encerrado'); exit;
}

// Busca perguntas do ciclo
$stmt2 = $pdo->prepare("SELECT id, tipo FROM pulso_perguntas WHERE ciclo_id = ?");
$stmt2->execute([$ciclo_id]);
$perguntas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    foreach ($perguntas as $p) {
        $campo = 'p_' . $p['id'];
        $val   = $_POST[$campo] ?? null;

        // Campo vazio e não obrigatório (aberta): ignora
        if ($val === null || $val === '') {
            if ($p['tipo'] === 'aberta') continue;
            // Obrigatória sem resposta: aborta transação
            $pdo->rollBack();
            header('Location: responder.php?unidade=' . urlencode($slug) . '&erro=incompleto');
            exit;
        }

        if ($p['tipo'] === 'aberta') {
            // Texto aberto: armazena a resposta individualmente
            // (sem qualquer vínculo com o respondente)
            $ins = $pdo->prepare("
                INSERT INTO pulso_respostas_agregadas
                    (ciclo_id, pergunta_id, texto)
                VALUES (?, ?, ?)
            ");
            $ins->execute([$ciclo_id, $p['id'], mb_substr(trim($val), 0, 2000)]);

        } else {
            // Escala ou escolha: incrementa o contador do valor
            // Usa INSERT ... ON DUPLICATE KEY para agregar eficientemente
            // Como não há UNIQUE composta (ciclo+pergunta+valor), fazemos SELECT + UPDATE
            $sel = $pdo->prepare("
                SELECT id, quantidade FROM pulso_respostas_agregadas
                WHERE ciclo_id = ? AND pergunta_id = ? AND valor = ?
                LIMIT 1
            ");
            $sel->execute([$ciclo_id, $p['id'], (int)$val]);
            $existe = $sel->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                $upd = $pdo->prepare("
                    UPDATE pulso_respostas_agregadas
                    SET quantidade = quantidade + 1
                    WHERE id = ?
                ");
                $upd->execute([$existe['id']]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO pulso_respostas_agregadas
                        (ciclo_id, pergunta_id, valor, quantidade)
                    VALUES (?, ?, ?, 1)
                ");
                $ins->execute([$ciclo_id, $p['id'], (int)$val]);
            }
        }
    }

    // Incrementa contador de participação (totalizador anônimo)
    $part = $pdo->prepare("
        INSERT INTO pulso_participacao (ciclo_id, total)
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE total = total + 1
    ");
    $part->execute([$ciclo_id]);

    $pdo->commit();
    header('Location: obrigado.php?unidade=' . urlencode($slug));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // Em produção: logar o erro sem expor detalhes
    error_log('PulsoSENAI enviar.php: ' . $e->getMessage());
    header('Location: responder.php?unidade=' . urlencode($slug) . '&erro=sistema');
    exit;
}