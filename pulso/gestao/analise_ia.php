<?php
// pulso/gestao/analise_ia.php — Análise qualitativa com IA (background worker)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('../../conexao.php');
require_once '../_guard.php';
exige_gerente();

$ciclo_sel = filter_input(INPUT_GET, 'ciclo', FILTER_VALIDATE_INT) ?: 0;
$job_retomar = filter_input(INPUT_GET, 'job',   FILTER_VALIDATE_INT) ?: 0;

// Lista de ciclos
$ciclos_stmt = $pdo->prepare("
    SELECT id, titulo, status FROM pulso_ciclos
    WHERE unidade_id = ? ORDER BY id DESC
");
$ciclos_stmt->execute([$g_unidade_id]);
$ciclos_lista = $ciclos_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ciclo_sel && $ciclos_lista) {
    $ciclo_sel = $ciclos_lista[0]['id'];
}

$ciclo_atual = null;
foreach ($ciclos_lista as $c) {
    if ($c['id'] == $ciclo_sel) { $ciclo_atual = $c; break; }
}

// Participação e total de respostas abertas
$participacao    = 0;
$total_respostas = 0;
if ($ciclo_sel) {
    $q = $pdo->prepare("SELECT total FROM pulso_participacao WHERE ciclo_id=?");
    $q->execute([$ciclo_sel]);
    $participacao = (int)($q->fetchColumn() ?: 0);

    $q2 = $pdo->prepare("
        SELECT COUNT(*) FROM pulso_perguntas p
        INNER JOIN pulso_respostas_agregadas ra ON ra.pergunta_id = p.id
        WHERE p.ciclo_id=? AND p.tipo='aberta'
          AND ra.texto IS NOT NULL AND TRIM(ra.texto) != ''
    ");
    $q2->execute([$ciclo_sel]);
    $total_respostas = (int)$q2->fetchColumn();
}

// Verifica se exec() está disponível
$exec_disponivel = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

// Verifica se há job ativo ou recente para este ciclo
$job_ativo = null;
if ($ciclo_sel) {
    $stmt_job = $pdo->prepare("
        SELECT * FROM pulso_analise_jobs
        WHERE ciclo_id = ? AND unidade_id = ?
        ORDER BY criado_em DESC LIMIT 1
    ");
    $stmt_job->execute([$ciclo_sel, $g_unidade_id]);
    $job_ativo = $stmt_job->fetch(PDO::FETCH_ASSOC);
}

// ── AÇÃO: DISPARAR NOVO JOB ───────────────────────────────────
$msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'iniciar') {
    $email = trim($_POST['email_notificacao'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_err = 'Informe um e-mail válido para receber a notificação.';
    } elseif (!$exec_disponivel) {
        $msg_err = 'A função exec() não está disponível no servidor. Habilite-a no php.ini.';
    } else {
        // Cria o job
        $pdo->prepare("
            INSERT INTO pulso_analise_jobs
                (unidade_id, ciclo_id, solicitado_por, email_destino, status)
            VALUES (?, ?, ?, ?, 'pendente')
        ")->execute([$g_unidade_id, $ciclo_sel, $g_user_id ?? null, $email]);
        $novo_job_id = (int)$pdo->lastInsertId();

        // Caminho do worker
        $worker = realpath(__DIR__ . '/analise_worker.php');
        $php    = PHP_BINARY ?: 'php';
        $log    = sys_get_temp_dir() . "/pulso_job_{$novo_job_id}.log";

        // Dispara em background (Linux)
        $cmd = escapeshellcmd($php) . ' ' .
               escapeshellarg($worker) . ' ' .
               (int)$novo_job_id .
               ' > ' . escapeshellarg($log) . ' 2>&1 &';
        exec($cmd);

        // Redireciona para a própria página com o job_id
        header("Location: analise_ia.php?ciclo={$ciclo_sel}&job={$novo_job_id}");
        exit;
    }
}

// Email do usuário logado como sugestão
$email_sugerido = '';
if (isset($g_user_id)) {
    $q = $pdo->prepare("SELECT email FROM usuarios WHERE id=? LIMIT 1");
    $q->execute([$g_user_id]);
    $email_sugerido = $q->fetchColumn() ?: '';
}

$titulo_pagina = 'Análise com IA';
$pagina_ativa  = 'analise';
?>
<?php include '../_layout_head.php'; ?>
<style>
.progress-wrap {
    background: #eee; border-radius: 8px; height: 12px;
    overflow: hidden; margin: 12px 0;
}
.progress-fill {
    height: 12px; border-radius: 8px;
    background: linear-gradient(90deg, var(--azul), var(--azul-c));
    transition: width .5s ease;
}
.status-card {
    border-radius: 12px; padding: 24px;
    display: flex; align-items: flex-start; gap: 16px;
}
.status-pendente   { background: #fef9e7; border: 1px solid #f9e79f; }
.status-processando{ background: #eef3fd; border: 1px solid #c8d9f5; }
.status-concluido  { background: #eafaf1; border: 1px solid #a9dfbf; }
.status-erro       { background: #fff0eb; border: 1px solid #f5c6bb; }
.status-icon { font-size: 32px; flex-shrink: 0; }

/* Resultado */
.analise-bloco {
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 12px rgba(22,65,148,.07);
    margin-bottom: 20px; overflow: hidden;
}
.analise-header {
    background: linear-gradient(90deg, var(--azul), #1e54c5);
    padding: 14px 20px; display: flex; align-items: center; gap: 10px;
}
.analise-header h3 { font-size: 14px; font-weight: 700; color: #fff; margin: 0; }
.analise-body { padding: 20px; font-size: 14px; color: #444; line-height: 1.8; }

.tema-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: #eef3fd; color: var(--azul);
    font-size: 12px; font-weight: 600;
    padding: 4px 12px; border-radius: 12px; margin: 3px;
}
.acao-card {
    background: #f8faff; border: 1px solid #dde9f8;
    border-left: 4px solid var(--azul);
    border-radius: 0 8px 8px 0; padding: 14px 16px; margin-bottom: 10px;
}
.acao-card.urgente { border-left-color: #c0392b; background: #fff8f7; }
.acao-card.medio   { border-left-color: #f59e0b; background: #fefdf5; }
.acao-card.longo   { border-left-color: #1a9e4a; background: #f5fdf8; }
.btn-converter {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;
    border: 1.5px solid var(--azul); color: var(--azul);
    background: transparent; cursor: pointer; transition: all .15s;
    text-decoration: none;
}
.btn-converter:hover { background: var(--azul); color: #fff; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; display: inline-block; }
</style>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1><i class="fa fa-magic" style="color:var(--laranja);margin-right:8px;"></i>Análise com IA</h1>
            <div class="page-sub">Análise qualitativa completa das respostas abertas do ciclo</div>
        </div>
    </div>

    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- SELETOR DE CICLO -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <select name="ciclo" class="form-input-g" style="max-width:300px;margin:0;"
                        onchange="this.form.submit()">
                    <?php foreach ($ciclos_lista as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_sel == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['titulo']) ?> — <?= ucfirst($c['status']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($participacao): ?>
                <span style="font-size:13px;color:#888;">
                    <i class="fa fa-users"></i> <?= $participacao ?> participantes
                    &bull; <i class="fa fa-comment-o"></i> <?= $total_respostas ?> respostas abertas
                </span>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($total_respostas === 0): ?>
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:48px;color:#aaa;">
            <i class="fa fa-comment-o" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
            Nenhuma resposta aberta encontrada neste ciclo.
        </div>
    </div>

    <?php elseif ($job_retomar && $job_ativo && $job_ativo['id'] == $job_retomar): ?>
    <!-- ── ACOMPANHAMENTO DO JOB ── -->
    <div id="painelJob">
        <div class="status-card status-<?= $job_ativo['status'] ?>" id="statusCard">
            <div class="status-icon" id="statusIcon">
                <?php if ($job_ativo['status'] === 'pendente'):     ?>⏳
                <?php elseif ($job_ativo['status'] === 'processando'): ?><i class="fa fa-spinner spin"></i>
                <?php elseif ($job_ativo['status'] === 'concluido'): ?>✅
                <?php else: ?>❌<?php endif; ?>
            </div>
            <div style="flex:1;">
                <div style="font-size:15px;font-weight:700;margin-bottom:6px;" id="statusTitulo">
                    <?php if ($job_ativo['status'] === 'pendente'):     echo 'Aguardando início...';
                    elseif ($job_ativo['status'] === 'processando'):    echo 'Análise em andamento...';
                    elseif ($job_ativo['status'] === 'concluido'):      echo 'Análise concluída!';
                    else:                                               echo 'Erro na análise'; endif; ?>
                </div>
                <div style="font-size:13px;color:#666;" id="statusMsg">
                    <?= htmlspecialchars($job_ativo['progresso_msg'] ?? '') ?>
                </div>
                <div class="progress-wrap" style="margin-top:12px;">
                    <div class="progress-fill" id="progressFill"
                         style="width:<?= $job_ativo['progresso'] ?>%;"></div>
                </div>
                <div style="font-size:12px;color:#aaa;display:flex;justify-content:space-between;">
                    <span id="progressPct"><?= $job_ativo['progresso'] ?>%</span>
                    <span>Notificação por e-mail quando concluir</span>
                </div>
            </div>
        </div>
    </div>

    <div id="painelResultado" style="display:none;margin-top:24px;"></div>

    <?php elseif ($job_ativo && in_array($job_ativo['status'], ['pendente','processando'])): ?>
    <!-- JOB EM ANDAMENTO (acesso sem job_id na URL) -->
    <div class="g-card">
        <div class="g-card-body" style="text-align:center;padding:32px;">
            <i class="fa fa-spinner spin" style="font-size:32px;color:var(--azul);"></i>
            <div style="margin-top:12px;font-size:15px;font-weight:600;">Análise em andamento</div>
            <div style="font-size:13px;color:#888;margin-top:6px;">
                <?= htmlspecialchars($job_ativo['progresso_msg'] ?? '') ?>
            </div>
            <a href="?ciclo=<?= $ciclo_sel ?>&job=<?= $job_ativo['id'] ?>"
               class="btn-pri" style="display:inline-flex;margin-top:16px;">
                Acompanhar progresso →
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ── FORMULÁRIO PARA INICIAR ── -->
    <div class="g-card" style="margin-bottom:20px;">
        <div class="g-card-header">
            <h2><i class="fa fa-magic"></i> Iniciar análise qualitativa</h2>
        </div>
        <div class="g-card-body">
            <div style="background:#f8faff;border:1px solid #d8e4f5;border-radius:10px;
                        padding:16px 18px;margin-bottom:20px;font-size:13px;color:#555;line-height:1.8;">
                <strong style="color:var(--azul);display:block;margin-bottom:8px;">
                    <i class="fa fa-info-circle"></i> Como funciona
                </strong>
                A análise roda em <strong>segundo plano no servidor</strong> — você pode fechar o navegador.
                Quando concluir, você receberá um e-mail com o link para o resultado.<br><br>
                A IA vai analisar as <strong><?= $total_respostas ?> respostas abertas</strong>
                de <?= $participacao ?> participantes, identificar padrões e sugerir planos de ação.
                <?php if ($job_ativo && $job_ativo['status'] === 'concluido'): ?>
                <br><br>
                <strong>Última análise:</strong>
                <?= date('d/m/Y \à\s H:i', strtotime($job_ativo['concluido_em'])) ?>
                — <a href="?ciclo=<?= $ciclo_sel ?>&job=<?= $job_ativo['id'] ?>">ver resultado anterior</a>
                <?php endif; ?>
            </div>

            <?php if (!$exec_disponivel): ?>
            <div class="alerta-erro" style="margin-bottom:20px;">
                <i class="fa fa-exclamation-circle"></i>
                A função <code>exec()</code> está desabilitada no PHP.
                Habilite-a no <code>php.ini</code> removendo <code>exec</code> da diretiva
                <code>disable_functions</code>.
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="acao"     value="iniciar">
                <input type="hidden" name="ciclo_id" value="<?= $ciclo_sel ?>">
                <div class="form-group-g" style="max-width:400px;">
                    <label class="form-label-g">
                        E-mail para notificação quando concluir *
                    </label>
                    <input type="email" name="email_notificacao" class="form-input-g"
                           placeholder="seu@email.com"
                           value="<?= htmlspecialchars($email_sugerido) ?>" required>
                    <div style="font-size:11px;color:#aaa;margin-top:4px;">
                        Você receberá um e-mail com o link para a análise completa.
                    </div>
                </div>
                <button type="submit" class="btn-pri"
                        <?= !$exec_disponivel ? 'disabled' : '' ?>>
                    <i class="fa fa-magic"></i> Iniciar análise em background
                </button>
            </form>
        </div>
    </div>

    <?php if ($job_ativo && $job_ativo['status'] === 'concluido'): ?>
    <!-- Mostra última análise salva -->
    <div id="painelResultado"></div>
    <script>
    window._analiseAtual = <?= $job_ativo['resultado'] ?>;
    document.addEventListener('DOMContentLoaded', () => renderAnalise(window._analiseAtual));
    </script>
    <?php endif; ?>

    <?php endif; ?>

    <!-- FORM OCULTO PARA SALVAR PLANO -->
    <form id="formSalvarIA" method="POST" action="plano_acao.php" style="display:none;">
        <input type="hidden" name="acao"             value="salvar_novo">
        <input type="hidden" name="ciclo_id"         value="<?= $ciclo_sel ?>">
        <input type="hidden" name="titulo"           id="ia_titulo">
        <input type="hidden" name="problema"         id="ia_problema">
        <input type="hidden" name="objetivo"         id="ia_objetivo">
        <input type="hidden" name="acoes"            id="ia_acoes">
        <input type="hidden" name="tipo_responsavel" id="ia_tipo">
        <input type="hidden" name="visivel_equipe"   value="0">
        <input type="hidden" name="gerado_por_ia"    value="1">
    </form>

</main>

<script>
const JOB_ID    = <?= $job_retomar ?: 'null' ?>;
const CICLO_ID  = <?= (int)$ciclo_sel ?>;

// ── POLLING ──────────────────────────────────────────────────
let pollingTimer = null;

function iniciarPolling() {
    if (!JOB_ID) return;
    pollingTimer = setInterval(consultarStatus, 5000);
    consultarStatus(); // imediato
}

async function consultarStatus() {
    try {
        const r    = await fetch(`analise_status.php?job_id=${JOB_ID}`);
        const data = await r.json();

        if (data.error) { clearInterval(pollingTimer); return; }

        // Atualiza UI
        const pct = data.progresso || 0;
        const el  = {
            fill:   document.getElementById('progressFill'),
            pct:    document.getElementById('progressPct'),
            msg:    document.getElementById('statusMsg'),
            titulo: document.getElementById('statusTitulo'),
            icon:   document.getElementById('statusIcon'),
            card:   document.getElementById('statusCard'),
        };

        if (el.fill)   el.fill.style.width = pct + '%';
        if (el.pct)    el.pct.textContent  = pct + '%';
        if (el.msg)    el.msg.textContent  = data.mensagem || '';

        if (data.status === 'concluido') {
            clearInterval(pollingTimer);
            if (el.titulo) el.titulo.textContent = '✅ Análise concluída!';
            if (el.icon)   el.icon.innerHTML     = '✅';
            if (el.card) {
                el.card.className = 'status-card status-concluido';
            }
            // Renderiza o resultado
            if (data.resultado) {
                window._analiseAtual = data.resultado;
                document.getElementById('painelResultado').style.display = 'block';
                renderAnalise(data.resultado);

                // Scroll suave para o resultado
                setTimeout(() => {
                    document.getElementById('painelResultado')
                            .scrollIntoView({ behavior: 'smooth' });
                }, 500);
            }
        } else if (data.status === 'erro') {
            clearInterval(pollingTimer);
            if (el.titulo) el.titulo.textContent = '❌ Erro na análise';
            if (el.card)   el.card.className     = 'status-card status-erro';
            if (el.msg)    el.msg.textContent     = data.erro || 'Erro desconhecido';
        }
    } catch(e) {
        console.warn('Polling error:', e);
    }
}

// ── RENDERIZAR RESULTADO ─────────────────────────────────────
function renderAnalise(a) {
    const container = document.getElementById('painelResultado');
    if (!container) return;

    const freqCor   = { alta: '#c0392b', media: '#f59e0b', baixa: '#1a9e4a' };
    const freqLabel = { alta: 'Alta frequência', media: 'Média', baixa: 'Baixa' };
    const urgCls    = { imediata: 'urgente', medio_prazo: 'medio', longo_prazo: 'longo' };
    const urgLabel  = { imediata: '🔴 Ação imediata', medio_prazo: '🟡 Médio prazo', longo_prazo: '🟢 Longo prazo' };
    const respLabel = { gestao: 'Gestão', docente: 'Docentes', compartilhado: 'Compartilhado' };

    let html = '';

    // Temas
    html += `<div class="analise-bloco">
        <div class="analise-header"><h3><i class="fa fa-tags"></i> Temas mais recorrentes</h3></div>
        <div class="analise-body">
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
                ${(a.temas_recorrentes||[]).map(t =>
                    `<span class="tema-chip" style="border-left:3px solid ${freqCor[t.frequencia]||'#888'}">
                        ${t.tema}
                        <span style="font-size:10px;color:#888;">${freqLabel[t.frequencia]||''}</span>
                    </span>`
                ).join('')}
            </div>
            ${(a.temas_recorrentes||[]).map(t =>
                `<div style="font-size:13px;padding:8px 12px;margin-bottom:6px;background:#f8faff;
                             border-radius:6px;border-left:3px solid ${freqCor[t.frequencia]||'#888'}">
                    <strong>${t.tema}:</strong> ${t.descricao}
                </div>`
            ).join('')}
        </div>
    </div>`;

    // Valoriza
    html += `<div class="analise-bloco">
        <div class="analise-header" style="background:linear-gradient(90deg,#1a9e4a,#22c55e)">
            <h3><i class="fa fa-heart"></i> O que a equipe mais valoriza</h3>
        </div>
        <div class="analise-body">${(a.o_que_valoriza||'').replace(/\n/g,'<br>')}</div>
    </div>`;

    // Preocupa
    html += `<div class="analise-bloco">
        <div class="analise-header" style="background:linear-gradient(90deg,#c0392b,#e74c3c)">
            <h3><i class="fa fa-exclamation-triangle"></i> O que mais preocupa</h3>
        </div>
        <div class="analise-body">${(a.o_que_preocupa||'').replace(/\n/g,'<br>')}</div>
    </div>`;

    // Planos
    html += `<div class="analise-bloco">
        <div class="analise-header">
            <h3><i class="fa fa-list-alt"></i> Planos de ação sugeridos</h3>
            <span style="margin-left:auto;font-size:12px;color:rgba(255,255,255,.7)">
                Clique em "Criar" para salvar no sistema
            </span>
        </div>
        <div class="analise-body">
            ${(a.planos_acao||[]).map((p,i) => `
            <div class="acao-card ${urgCls[p.urgencia]||''}">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;">
                        <div style="font-size:11px;font-weight:700;color:#888;margin-bottom:4px;">
                            ${urgLabel[p.urgencia]||p.urgencia}
                        </div>
                        <div style="font-size:14px;font-weight:700;margin-bottom:6px;">${p.titulo}</div>
                        <div style="font-size:13px;color:#555;margin-bottom:10px;">${p.objetivo}</div>
                        ${(p.acoes||[]).map((ac,j) => `
                        <div style="margin:6px 0;padding:8px 12px;background:rgba(255,255,255,.6);
                                    border-radius:6px;font-size:13px;">
                            <strong>${j+1}. ${ac.descricao}</strong>
                            ${ac.como ? `<div style="color:#666;margin-top:3px;font-size:12px;">${ac.como}</div>` : ''}
                            <div style="font-size:11px;color:#aaa;margin-top:4px;">
                                <i class="fa fa-user"></i> ${ac.responsavel_sugerido||''}
                                &nbsp;·&nbsp;
                                <i class="fa fa-clock-o"></i> ${ac.prazo_sugerido||''}
                            </div>
                        </div>`).join('')}
                        <div style="display:flex;gap:12px;flex-wrap:wrap;
                                    margin-top:10px;font-size:12px;color:#888;">
                            <span><i class="fa fa-users"></i> ${respLabel[p.responsavel_tipo]||p.responsavel_tipo}</span>
                            <span><i class="fa fa-user"></i> ${p.responsavel_sugerido||''}</span>
                            <span><i class="fa fa-clock-o"></i> ${p.prazo_sugerido||''}</span>
                        </div>
                    </div>
                    <button class="btn-converter" onclick="criarPlano(${i})">
                        <i class="fa fa-save"></i> Criar plano
                    </button>
                </div>
            </div>`).join('')}
        </div>
    </div>`;

    // Observações
    html += `<div class="analise-bloco">
        <div class="analise-header" style="background:linear-gradient(90deg,#7c3aed,#8b5cf6)">
            <h3><i class="fa fa-lightbulb-o"></i> Observações da IA</h3>
        </div>
        <div class="analise-body">${(a.observacoes||'').replace(/\n/g,'<br>')}</div>
    </div>`;

    container.innerHTML = html;
}

function criarPlano(idx) {
    const p = window._analiseAtual?.planos_acao?.[idx];
    if (!p) return;
    document.getElementById('ia_titulo').value   = p.titulo;
    document.getElementById('ia_problema').value = p.objetivo;
    document.getElementById('ia_objetivo').value = p.objetivo;
    document.getElementById('ia_acoes').value    = JSON.stringify(
        (p.acoes||[]).map(a => ({
            descricao: a.descricao, como: a.como||'',
            responsavel_tipo: a.responsavel_tipo,
            responsavel_sugerido: a.responsavel_sugerido,
            prazo_sugerido: a.prazo_sugerido, status: 'pendente',
        }))
    );
    document.getElementById('ia_tipo').value = p.responsavel_tipo;
    document.getElementById('formSalvarIA').submit();
}

// Inicia polling se houver job ativo
document.addEventListener('DOMContentLoaded', iniciarPolling);
</script>
</body>
</html>