<?php
// pulso/gestao/devolutiva.php — Redação e publicação da devolutiva
include('../../conexao.php');
require_once '../_guard.php';
exige_gerente();

$msg_ok  = '';
$msg_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao     = $_POST['acao']     ?? '';
    $ciclo_id = filter_input(INPUT_POST, 'ciclo_id', FILTER_VALIDATE_INT);
    $conteudo = trim($_POST['conteudo'] ?? '');

    if ($acao === 'salvar' && $ciclo_id && $conteudo) {
        $pdo->prepare("
            INSERT INTO pulso_devolutivas (ciclo_id, conteudo, publicada)
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE conteudo = VALUES(conteudo)
        ")->execute([$ciclo_id, $conteudo]);
        $msg_ok = 'Rascunho salvo.';
    }

    if ($acao === 'publicar' && $ciclo_id && $conteudo) {
        $pdo->prepare("
            INSERT INTO pulso_devolutivas (ciclo_id, conteudo, publicada, publicada_em)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                conteudo=VALUES(conteudo), publicada=1, publicada_em=NOW()
        ")->execute([$ciclo_id, $conteudo]);
        $msg_ok = 'Devolutiva publicada! Já está visível para toda a equipe.';
    }

    if ($acao === 'despublicar' && $ciclo_id) {
        $pdo->prepare("UPDATE pulso_devolutivas SET publicada=0 WHERE ciclo_id=?")
            ->execute([$ciclo_id]);
        $msg_ok = 'Devolutiva despublicada.';
    }
}

// Ciclos
$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;
$ciclos_stmt = $pdo->prepare("SELECT id, titulo, status FROM pulso_ciclos WHERE unidade_id=? ORDER BY id DESC");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclo_sel && $ciclos_lista) $ciclo_sel = $ciclos_lista[0]['id'];

$ciclo_atual = null;
foreach ($ciclos_lista as $c) { if ($c['id'] == $ciclo_sel) { $ciclo_atual = $c; break; } }

// Devolutiva atual
$dev = null;
if ($ciclo_sel) {
    $stmt = $pdo->prepare("SELECT * FROM pulso_devolutivas WHERE ciclo_id=?");
    $stmt->execute([$ciclo_sel]);
    $dev = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Participação
$participacao = 0;
if ($ciclo_sel) {
    $q = $pdo->prepare("SELECT total FROM pulso_participacao WHERE ciclo_id=?");
    $q->execute([$ciclo_sel]);
    $participacao = (int)($q->fetchColumn() ?: 0);
}

// Itens de SLA por status
$itens_sla = ['resolvido'=>[],'em_andamento'=>[],'aberto'=>[],'escalado'=>[]];
$total_sla  = 0;
if ($ciclo_sel) {
    $q2 = $pdo->prepare("
        SELECT id, titulo, categoria, status, prazo, conteudo,
               responsavel_publico
        FROM pulso_itens_sla
        WHERE ciclo_id=?
        ORDER BY FIELD(status,'escalado','aberto','em_andamento','resolvido'), categoria
    ");
    $q2->execute([$ciclo_sel]);
    foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $itens_sla[$it['status']][] = $it;
        $total_sla++;
    }
}

// Planos de ação ativos
$planos = [];
if ($ciclo_sel) {
    $q3 = $pdo->prepare("
        SELECT id, titulo, status, tipo_responsavel, acoes
        FROM pulso_planos_acao
        WHERE ciclo_id=? AND status IN ('ativo','concluido')
        ORDER BY status DESC, criado_em DESC
    ");
    $q3->execute([$ciclo_sel]);
    $planos = $q3->fetchAll(PDO::FETCH_ASSOC);
}

// Dados para IA (SLAs + planos como contexto)
$contexto_ia = [];
foreach (['escalado','aberto','em_andamento','resolvido'] as $st) {
    foreach ($itens_sla[$st] as $it) {
        $contexto_ia[] = ['status' => $st, 'titulo' => $it['titulo'],
                          'categoria' => $it['categoria'], 'responsavel' => $it['responsavel_publico']];
    }
}

$status_label = ['aberto'=>'Em aberto','em_andamento'=>'Em andamento',
                 'resolvido'=>'Resolvido','escalado'=>'Escalado'];
$status_cor   = ['aberto'=>'#E84910','em_andamento'=>'#f59e0b',
                 'resolvido'=>'#1a9e4a','escalado'=>'#7c3aed'];

$titulo_pagina = 'Devolutiva';
$pagina_ativa  = 'devolutiva';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-bullhorn" style="color:var(--laranja);margin-right:8px;"></i>Devolutiva</h1>
            <div class="page-sub">Redija e publique a devolutiva do ciclo para a equipe</div>
        </div>
        <?php if ($dev && $dev['publicada']): ?>
        <a href="../status.php" target="_blank" class="btn-sec">
            <i class="fa fa-eye"></i> Ver no painel público
        </a>
        <?php endif; ?>
    </div>

    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>

    <!-- SELETOR -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <select name="ciclo" class="form-input-g" style="max-width:300px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['titulo']) ?> — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($participacao): ?>
                <span style="font-size:13px;color:#888;">
                    <i class="fa fa-users"></i> <?= $participacao ?> participantes
                </span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

        <!-- ── EDITOR DA DEVOLUTIVA ── -->
        <div>
            <?php if ($dev && $dev['publicada']): ?>
            <div style="background:#eafaf1;border:1px solid #a9dfbf;border-radius:10px;
                        padding:12px 18px;margin-bottom:16px;font-size:13px;color:#1a9e4a;
                        display:flex;align-items:center;gap:10px;">
                <i class="fa fa-check-circle"></i>
                <strong>Publicada em <?= date('d/m/Y \à\s H:i', strtotime($dev['publicada_em'])) ?></strong>
                — visível para toda a equipe.
            </div>
            <?php elseif ($dev): ?>
            <div style="background:#fef9e7;border:1px solid #f9e79f;border-radius:10px;
                        padding:12px 18px;margin-bottom:16px;font-size:13px;color:#b7770d;
                        display:flex;align-items:center;gap:10px;">
                <i class="fa fa-pencil"></i> Rascunho salvo — ainda não publicado.
            </div>
            <?php endif; ?>

            <div class="g-card">
                <div class="g-card-header">
                    <h2><i class="fa fa-file-text-o"></i> Texto da devolutiva</h2>
                    <button class="btn-sec btn-sm" onclick="gerarRascunhoIA()"
                            id="btnRascunhoIA">
                        <i class="fa fa-magic"></i> Gerar rascunho com IA
                    </button>
                </div>
                <div class="g-card-body">

                    <!-- Loading IA -->
                    <div id="loadingRascunho" style="display:none;text-align:center;padding:24px;">
                        <i class="fa fa-spinner fa-spin" style="font-size:28px;color:var(--azul);"></i>
                        <div style="margin-top:10px;font-size:13px;color:#888;">
                            Analisando itens de SLA e planos para gerar o rascunho...
                        </div>
                    </div>

                    <form method="POST" id="formDevolutiva">
                        <input type="hidden" name="ciclo_id" value="<?= $ciclo_sel ?>">
                        <textarea name="conteudo" id="editorDevolutiva"
                                  class="form-input-g"
                                  rows="18"
                                  placeholder="Escreva aqui a devolutiva ou use o botão 'Gerar rascunho com IA' acima.

A devolutiva deve responder:
— O que a pesquisa mostrou (principais pontos)
— O que foi resolvido neste ciclo
— O que está em andamento
— O que foi escalado e por quê
— O que vem no próximo ciclo"
                                  style="resize:vertical;line-height:1.8;font-size:14px;"><?= htmlspecialchars($dev['conteudo'] ?? '') ?></textarea>

                        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
                            <button type="submit" name="acao" value="salvar" class="btn-sec">
                                <i class="fa fa-floppy-o"></i> Salvar rascunho
                            </button>
                            <button type="submit" name="acao" value="publicar" class="btn-pri"
                                    onclick="return confirm('Publicar? Ficará visível para toda a equipe.')">
                                <i class="fa fa-send"></i> Publicar para a equipe
                            </button>
                            <?php if ($dev && $dev['publicada']): ?>
                            <button type="submit" name="acao" value="despublicar" class="btn-danger"
                                    onclick="return confirm('Despublicar?')">
                                <i class="fa fa-eye-slash"></i> Despublicar
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── PAINEL LATERAL: SLAs + PLANOS ── -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Contadores de SLA -->
            <div class="g-card">
                <div class="g-card-header">
                    <h2><i class="fa fa-tasks"></i> Itens de SLA do ciclo</h2>
                    <a href="itens_sla.php?ciclo=<?= $ciclo_sel ?>" class="btn-sec btn-sm">
                        Gerenciar
                    </a>
                </div>
                <div class="g-card-body" style="padding:0;">
                    <?php if ($total_sla === 0): ?>
                    <div style="padding:20px;text-align:center;color:#aaa;font-size:13px;">
                        Nenhum item de SLA neste ciclo.
                    </div>
                    <?php else: ?>
                    <?php foreach (['escalado','aberto','em_andamento','resolvido'] as $st):
                        if (empty($itens_sla[$st])) continue;
                        $cor = $status_cor[$st];
                        $lbl = $status_label[$st];
                    ?>
                    <div style="border-bottom:1px solid #f0f3f8;">
                        <div style="padding:10px 16px;background:#f8faff;
                                    font-size:11px;font-weight:700;color:<?= $cor ?>;
                                    text-transform:uppercase;letter-spacing:.5px;
                                    display:flex;justify-content:space-between;">
                            <span><?= $lbl ?></span>
                            <span><?= count($itens_sla[$st]) ?></span>
                        </div>
                        <?php foreach ($itens_sla[$st] as $it): ?>
                        <div style="padding:8px 16px;border-bottom:1px solid #f8faff;
                                    font-size:12px;line-height:1.4;">
                            <div style="font-weight:600;color:var(--texto);margin-bottom:2px;">
                                <?= htmlspecialchars($it['titulo'] ?: $it['categoria']) ?>
                            </div>
                            <div style="color:#aaa;font-size:11px;">
                                <span class="badge-cat" style="font-size:10px;padding:2px 7px;">
                                    <?= htmlspecialchars($it['categoria']) ?>
                                </span>
                                <?php if ($it['responsavel_publico']): ?>
                                · <?= htmlspecialchars($it['responsavel_publico']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Planos de ação -->
            <?php if (!empty($planos)): ?>
            <div class="g-card">
                <div class="g-card-header">
                    <h2><i class="fa fa-list-alt"></i> Planos de ação</h2>
                </div>
                <div class="g-card-body" style="padding:0;">
                    <?php foreach ($planos as $pl):
                        $acoes_arr  = json_decode($pl['acoes'], true) ?: [];
                        $concluidas = count(array_filter($acoes_arr, fn($a) => ($a['status']??'') === 'concluida'));
                        $total_ac   = count($acoes_arr);
                        $pct        = $total_ac > 0 ? round(($concluidas/$total_ac)*100) : 0;
                    ?>
                    <div style="padding:12px 16px;border-bottom:1px solid #f0f3f8;">
                        <div style="font-size:13px;font-weight:600;margin-bottom:6px;">
                            <?= htmlspecialchars($pl['titulo']) ?>
                        </div>
                        <?php if ($total_ac > 0): ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:#eee;border-radius:3px;height:5px;overflow:hidden;">
                                <div style="height:5px;border-radius:3px;width:<?= $pct ?>%;
                                            background:<?= $pct>=100?'#1a9e4a':'var(--azul)' ?>;"></div>
                            </div>
                            <span style="font-size:11px;color:#888;"><?= $concluidas ?>/<?= $total_ac ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dica -->
            <div style="background:#f8faff;border:1px solid #d8e4f5;border-radius:10px;
                        padding:16px;font-size:12px;color:#555;line-height:1.7;">
                <strong style="color:var(--azul);display:block;margin-bottom:6px;">
                    <i class="fa fa-lightbulb-o"></i> Dica de redação
                </strong>
                Use os itens de SLA do painel ao lado como estrutura da devolutiva.
                Ou clique em <strong>"Gerar rascunho com IA"</strong> — a IA lê os SLAs
                e os planos e propõe um texto completo para você editar.
            </div>

        </div>
    </div>

</main>

<script>
// Dados para o rascunho com IA
const CONTEXTO_IA = <?= json_encode($contexto_ia, JSON_UNESCAPED_UNICODE) ?>;
const PLANOS_IA   = <?= json_encode(array_map(fn($p) => [
    'titulo'  => $p['titulo'],
    'status'  => $p['status'],
    'acoes'   => count(json_decode($p['acoes'],true) ?: []),
    'concluidas' => count(array_filter(json_decode($p['acoes'],true) ?: [], fn($a) => ($a['status']??'') === 'concluida')),
], $planos), JSON_UNESCAPED_UNICODE) ?>;
const CICLO_TITULO = <?= json_encode($ciclo_atual['titulo'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
const PARTICIPACAO = <?= (int)$participacao ?>;

async function gerarRascunhoIA() {
    if (!CONTEXTO_IA.length && !PLANOS_IA.length) {
        alert('Nenhum item de SLA ou plano de ação encontrado neste ciclo. Crie pelo menos um item antes de gerar o rascunho.');
        return;
    }

    document.getElementById('loadingRascunho').style.display = 'block';
    document.getElementById('btnRascunhoIA').disabled        = true;
    document.getElementById('editorDevolutiva').style.display = 'none';

    // Monta contexto
    const sla_resolvidos   = CONTEXTO_IA.filter(i => i.status === 'resolvido');
    const sla_andamento    = CONTEXTO_IA.filter(i => i.status === 'em_andamento');
    const sla_abertos      = CONTEXTO_IA.filter(i => i.status === 'aberto');
    const sla_escalados    = CONTEXTO_IA.filter(i => i.status === 'escalado');

    const fmt = itens => itens.map(i => `- ${i.titulo} (${i.categoria}, ${i.responsavel})`).join('\n');

    const contexto = `CICLO: ${CICLO_TITULO}
PARTICIPANTES: ${PARTICIPACAO}

ITENS DE SLA RESOLVIDOS (${sla_resolvidos.length}):
${fmt(sla_resolvidos) || '(nenhum)'}

ITENS EM ANDAMENTO (${sla_andamento.length}):
${fmt(sla_andamento) || '(nenhum)'}

ITENS EM ABERTO (${sla_abertos.length}):
${fmt(sla_abertos) || '(nenhum)'}

ITENS ESCALADOS (${sla_escalados.length}):
${fmt(sla_escalados) || '(nenhum)'}

PLANOS DE AÇÃO:
${PLANOS_IA.map(p => `- ${p.titulo} (${p.status}: ${p.concluidas}/${p.acoes} ações concluídas)`).join('\n') || '(nenhum)'}`;

    const prompt = `Você é especialista em gestão escolar. Escreva uma devolutiva de pesquisa de clima para a equipe de uma escola SENAI.

DADOS DO CICLO:
${contexto}

A devolutiva deve:
— Ser direta, honesta e humana (não corporativa)
— Reconhecer o que a equipe levantou
— Informar o que foi resolvido, o que está em andamento e o que não foi possível resolver ainda
— Indicar os próximos passos
— Ter no máximo 400 palavras
— Ser em português, tom respeitoso e acessível

Escreva apenas o texto da devolutiva, sem título, sem formatação markdown, sem asteriscos.`;

    try {
        const resp = await fetch('api_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                messages: [{ role: 'user', content: prompt }],
                max_tokens: 800
            })
        });
        const raw  = await resp.text();
        const data = JSON.parse(raw);
        if (data.error) throw new Error(data.error);
        const texto = data.content?.[0]?.text || '';
        if (!texto) throw new Error('Resposta vazia da IA');

        document.getElementById('editorDevolutiva').value = texto.trim();
    } catch(err) {
        alert('Erro ao gerar rascunho: ' + err.message);
    } finally {
        document.getElementById('loadingRascunho').style.display  = 'none';
        document.getElementById('btnRascunhoIA').disabled         = false;
        document.getElementById('editorDevolutiva').style.display = 'block';
    }
}
</script>
</body>
</html>