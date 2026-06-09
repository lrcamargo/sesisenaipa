<?php
// pulso/gestao/configuracoes.php — Configurações do sistema
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('../../conexao.php');
require_once '../_guard.php';
exige_gerente();

$msg_ok  = '';
$msg_err = '';

// ── Tabela de configurações ───────────────────────────────────
// Cria se não existir (migration inline — sem precisar de SQL separado)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pulso_config (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        unidade_id  INT         NOT NULL,
        chave       VARCHAR(60) NOT NULL,
        valor       TEXT        NULL,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_unidade_chave (unidade_id, chave),
        FOREIGN KEY (unidade_id) REFERENCES pulso_unidades(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Helper: lê config
function cfg_get(PDO $pdo, int $unidade_id, string $chave): string {
    $q = $pdo->prepare("SELECT valor FROM pulso_config WHERE unidade_id=? AND chave=?");
    $q->execute([$unidade_id, $chave]);
    return $q->fetchColumn() ?: '';
}

// Helper: salva config
function cfg_set(PDO $pdo, int $unidade_id, string $chave, string $valor): void {
    $pdo->prepare("
        INSERT INTO pulso_config (unidade_id, chave, valor)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ")->execute([$unidade_id, $chave, $valor]);
}

// ── AÇÕES POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secao = $_POST['secao'] ?? '';
    // SALVAR CONFIGURAÇÃO DE IA
    if ($secao === 'api') {
        $provedor  = $_POST['ia_provedor'] ?? 'groq';
        $chave_api = trim($_POST['ia_chave'] ?? '');
        $provedores_validos = ['groq','gemini','openai','anthropic'];
        if (!in_array($provedor, $provedores_validos)) {
            $msg_err = 'Provedor inválido.';
        } else {
            cfg_set($pdo, $g_unidade_id, 'ia_provedor', $provedor);
            if ($chave_api) cfg_set($pdo, $g_unidade_id, "ia_key_{$provedor}", $chave_api);
            $msg_ok = $chave_api
                ? "Provedor '{$provedor}' configurado com sucesso. A análise com IA está disponível."
                : "Provedor alterado para '{$provedor}'.";
        }
    }

    // SALVAR CONFIG DE SLA
    if ($secao === 'sla') {
        $categorias_sla = $_POST['sla'] ?? [];
        foreach ($categorias_sla as $cat => $prazos) {
            $resp = max(1, (int)($prazos['resposta']  ?? 5));
            $res  = max(1, (int)($prazos['resolucao'] ?? 30));
            $pdo->prepare("
                INSERT INTO pulso_sla_config (unidade_id, categoria, prazo_resposta, prazo_resolucao)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE prazo_resposta=VALUES(prazo_resposta),
                                        prazo_resolucao=VALUES(prazo_resolucao)
            ")->execute([$g_unidade_id, $cat, $resp, $res]);
        }
        $msg_ok = 'Prazos de SLA atualizados.';
    }

    // SALVAR INFO DA UNIDADE
    if ($secao === 'unidade') {
        $nome = trim($_POST['unidade_nome'] ?? '');
        if ($nome) {
            $pdo->prepare("UPDATE pulso_unidades SET nome=? WHERE id=?")
                ->execute([$nome, $g_unidade_id]);
            $_SESSION['pulso_unidade_nome'] = $nome;
            $msg_ok = 'Informações da unidade atualizadas.';
        }
    }
}

// ── LEITURA DAS CONFIGS ATUAIS ────────────────────────────────
$ia_provedor_atual = cfg_get($pdo, $g_unidade_id, 'ia_provedor') ?: 'groq';
$provedores_info = [
    'groq'      => [
        'nome'   => 'Groq',
        'modelo' => 'Llama 3.1 (8B)',
        'url'    => 'https://console.groq.com/keys',
        'prefixo'=> 'gsk_',
        'placeholder' => 'gsk_...',
        'dica'   => 'Cadastro gratuito sem cartão em console.groq.com',
    ],
    'gemini'    => [
        'nome'   => 'Google Gemini',
        'modelo' => 'Gemini 1.5 Flash',
        'url'    => 'https://aistudio.google.com/app/apikey',
        'prefixo'=> 'AIzaSy',
        'placeholder' => 'AIzaSy...',
        'dica'   => 'Login com conta Google em aistudio.google.com → Get API Key → Create API Key',
    ],
    'openai'    => [
        'nome'   => 'OpenAI',
        'modelo' => 'GPT-4o Mini',
        'url'    => 'https://platform.openai.com/api-keys',
        'prefixo'=> 'sk-',
        'placeholder' => 'sk-...',
        'dica'   => 'Requer conta com crédito (não é totalmente gratuito)',
    ],
    'anthropic' => [
        'nome'   => 'Anthropic (Claude)',
        'modelo' => 'Claude Haiku',
        'url'    => 'https://console.anthropic.com',
        'prefixo'=> 'sk-ant-',
        'placeholder' => 'sk-ant-...',
        'dica'   => 'Créditos iniciais gratuitos em console.anthropic.com',
    ],
];

// Chaves salvas por provedor
$chaves_salvas = [];
foreach (array_keys($provedores_info) as $prov) {
    $k = cfg_get($pdo, $g_unidade_id, "ia_key_{$prov}");
    $chaves_salvas[$prov] = $k
        ? substr($k, 0, 8) . str_repeat('•', 12) . substr($k, -4)
        : '';
}
$api_ativa = !empty(cfg_get($pdo, $g_unidade_id, "ia_key_{$ia_provedor_atual}"));

// SLA configs
$sla_stmt = $pdo->prepare("
    SELECT categoria, prazo_resposta, prazo_resolucao
    FROM pulso_sla_config WHERE unidade_id = ?
    ORDER BY categoria
");
$sla_stmt->execute([$g_unidade_id]);
$sla_configs = $sla_stmt->fetchAll(PDO::FETCH_ASSOC);

// Padrões se vazio
$sla_default = [
    'Infraestrutura crítica'        => ['resposta' => 1,  'resolucao' => 7],
    'Condições Físicas'             => ['resposta' => 2,  'resolucao' => 30],
    'Reconhecimento e Carreira'     => ['resposta' => 5,  'resolucao' => 10],
    'Treinamento e Desenvolvimento' => ['resposta' => 5,  'resolucao' => 60],
    'Relacionamento'                => ['resposta' => 3,  'resolucao' => 21],
    'Qualidade de Vida'             => ['resposta' => 5,  'resolucao' => 30],
    'Liderança'                     => ['resposta' => 5,  'resolucao' => 21],
    'Comunicação'                   => ['resposta' => 3,  'resolucao' => 15],
    'Geral'                         => ['resposta' => 7,  'resolucao' => 45],
];

// Mescla banco com defaults
$sla_mapa = $sla_default;
foreach ($sla_configs as $s) {
    $sla_mapa[$s['categoria']] = [
        'resposta'  => $s['prazo_resposta'],
        'resolucao' => $s['prazo_resolucao'],
    ];
}

// Info da unidade
$uni = $pdo->prepare("SELECT nome, slug FROM pulso_unidades WHERE id=?");
$uni->execute([$g_unidade_id]);
$unidade_info = $uni->fetch(PDO::FETCH_ASSOC);

$titulo_pagina = 'Configurações';
$pagina_ativa  = 'configuracoes';
?>
<?php include '../_layout_head.php'; ?>
<body>
<?php include '../_nav.php'; ?>
<main class="g-main">

    <div class="page-header">
        <div>
            <h1>
                <i class="fa fa-cog" style="color:var(--laranja);margin-right:8px;"></i>
                Configurações
            </h1>
            <div class="page-sub">Gerencie as configurações do PulsoSENAI para esta unidade</div>
        </div>
    </div>

    <?php if ($msg_ok): ?>
    <div class="alerta-ok"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="alerta-erro"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
    <?php endif; ?>

    <!-- ── SEÇÃO 1: INFORMAÇÕES DA UNIDADE ──────────────────── -->
    <div class="g-card" style="margin-bottom:24px;">
        <div class="g-card-header">
            <h2><i class="fa fa-building-o"></i> Informações da Unidade</h2>
        </div>
        <div class="g-card-body">
            <form method="POST">
                <input type="hidden" name="secao" value="unidade">
                <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:flex-end;">
                    <div>
                        <label class="form-label-g">Nome da unidade</label>
                        <input type="text" name="unidade_nome" class="form-input-g"
                               value="<?= htmlspecialchars($unidade_info['nome'] ?? '') ?>"
                               required>
                    </div>
                    <button type="submit" class="btn-pri">
                        <i class="fa fa-save"></i> Salvar
                    </button>
                </div>
                <div style="margin-top:8px;font-size:12px;color:#aaa;">
                    Slug (URL): <code><?= htmlspecialchars($unidade_info['slug'] ?? '') ?></code>
                    — imutável após criação.
                </div>
            </form>
        </div>
    </div>

    <!-- ── SEÇÃO 2: ANÁLISE COM IA ──────────────────────────── -->
    <div class="g-card" style="margin-bottom:24px;">
        <div class="g-card-header">
            <h2><i class="fa fa-magic"></i> Análise com IA</h2>
            <?php if ($api_ativa): ?>
            <span style="font-size:12px;font-weight:600;color:#1a9e4a;display:flex;align-items:center;gap:6px;">
                <i class="fa fa-check-circle"></i>
                IA ativa — <?= $provedores_info[$ia_provedor_atual]['nome'] ?>
                (<?= $provedores_info[$ia_provedor_atual]['modelo'] ?>)
            </span>
            <?php else: ?>
            <span style="font-size:12px;font-weight:600;color:#c0392b;display:flex;align-items:center;gap:6px;">
                <i class="fa fa-times-circle"></i> IA inativa — configure uma chave abaixo
            </span>
            <?php endif; ?>
        </div>
        <div class="g-card-body">

            <form method="POST">
                <input type="hidden" name="secao" value="api">

                <!-- SELEÇÃO DO PROVEDOR -->
                <div class="form-group-g">
                    <label class="form-label-g">Escolha o provedor de IA</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:4px;">
                    <?php foreach ($provedores_info as $prov_id => $prov): ?>
                    <?php $tem_chave = !empty(cfg_get($pdo, $g_unidade_id, "ia_key_{$prov_id}")); ?>
                    <label id="prov_card_<?= $prov_id ?>"
                           style="display:flex;flex-direction:column;gap:6px;cursor:pointer;
                                  padding:14px 16px;border:2px solid #dde3ef;border-radius:10px;
                                  transition:all .15s;position:relative;">
                        <input type="radio" name="ia_provedor" value="<?= $prov_id ?>"
                               style="position:absolute;top:12px;right:12px;accent-color:var(--azul);"
                               <?= $ia_provedor_atual === $prov_id ? 'checked' : '' ?>
                               onchange="selecionarProvedor('<?= $prov_id ?>')">
                        <div style="font-size:14px;font-weight:700;color:var(--texto);">
                            <?= $prov['nome'] ?>
                        </div>
                        <div style="font-size:12px;color:#888;"><?= $prov['modelo'] ?></div>
                        <?php if ($tem_chave): ?>
                        <div style="font-size:11px;color:#1a9e4a;display:flex;align-items:center;gap:4px;">
                            <i class="fa fa-key"></i>
                            Chave: <code><?= $chaves_salvas[$prov_id] ?></code>
                        </div>
                        <?php else: ?>
                        <div style="font-size:11px;color:#aaa;">Sem chave cadastrada</div>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>

                <!-- CAMPO DE CHAVE (muda conforme provedor selecionado) -->
                <div class="form-group-g" id="campoChave">
                    <?php foreach ($provedores_info as $prov_id => $prov): ?>
                    <div id="chave_info_<?= $prov_id ?>"
                         style="display:<?= $ia_provedor_atual === $prov_id ? 'block' : 'none' ?>">
                        <label class="form-label-g">
                            Chave da API — <?= $prov['nome'] ?>
                        </label>
                        <div style="background:#f8faff;border:1px solid #d8e4f5;border-radius:8px;
                                    padding:12px 16px;margin-bottom:12px;font-size:12px;color:#555;line-height:1.7;">
                            <i class="fa fa-info-circle" style="color:var(--azul);margin-right:4px;"></i>
                            <?= $prov['dica'] ?><br>
                            <a href="<?= $prov['url'] ?>" target="_blank"
                               style="color:var(--azul-c);">
                                Obter chave gratuita →
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="password" name="ia_chave" id="inputApiKey"
                               class="form-input-g"
                               placeholder="<?= $provedores_info[$ia_provedor_atual]['placeholder'] ?>"
                               id="inputChave"
                               autocomplete="off"
                               style="font-family:monospace;letter-spacing:1px;">
                        <button type="button" onclick="toggleVerChave()"
                                class="btn-sec" style="flex-shrink:0;white-space:nowrap;">
                            <i class="fa fa-eye" id="iconVerChave"></i> Ver
                        </button>
                    </div>
                    <div style="font-size:11px;color:#aaa;margin-top:6px;">
                        Deixe em branco para apenas trocar o provedor sem alterar a chave.
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button type="submit" class="btn-pri">
                        <i class="fa fa-save"></i> Salvar configuração
                    </button>
                    <a href="analise_ia.php" class="btn-sec">
                        <i class="fa fa-magic"></i> Ir para análise com IA
                    </a>
                    <span style="font-size:11px;color:#aaa;">
                        <i class="fa fa-lock"></i>
                        As chaves ficam apenas no banco desta instalação.
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- ── SEÇÃO 3: PRAZOS DE SLA ────────────────────────────── -->
    <div class="g-card" style="margin-bottom:24px;">
        <div class="g-card-header">
            <h2><i class="fa fa-clock-o"></i> Prazos de SLA por Categoria</h2>
            <span style="font-size:12px;color:#888;">
                Dias corridos a partir da data de abertura do item
            </span>
        </div>
        <div class="g-card-body">
            <form method="POST">
                <input type="hidden" name="secao" value="sla">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th style="width:180px;text-align:center;">
                                Prazo de resposta
                                <div style="font-size:10px;font-weight:400;color:#aaa;margin-top:2px;">
                                    (posição inicial obrigatória)
                                </div>
                            </th>
                            <th style="width:180px;text-align:center;">
                                Prazo de resolução
                                <div style="font-size:10px;font-weight:400;color:#aaa;margin-top:2px;">
                                    (problema resolvido)
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sla_mapa as $cat => $prazos): ?>
                    <tr>
                        <td>
                            <span class="badge-cat"><?= htmlspecialchars($cat) ?></span>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                <input type="number"
                                       name="sla[<?= htmlspecialchars($cat) ?>][resposta]"
                                       value="<?= $prazos['resposta'] ?>"
                                       min="1" max="365"
                                       class="form-input-g"
                                       style="width:70px;text-align:center;padding:8px;">
                                <span style="font-size:12px;color:#888;">dias</span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                <input type="number"
                                       name="sla[<?= htmlspecialchars($cat) ?>][resolucao]"
                                       value="<?= $prazos['resolucao'] ?>"
                                       min="1" max="365"
                                       class="form-input-g"
                                       style="width:70px;text-align:center;padding:8px;">
                                <span style="font-size:12px;color:#888;">dias</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:16px;display:flex;gap:10px;align-items:center;">
                    <button type="submit" class="btn-pri">
                        <i class="fa fa-save"></i> Salvar prazos
                    </button>
                    <span style="font-size:12px;color:#aaa;">
                        Os prazos são pré-preenchidos ao criar um item de SLA e sempre editáveis.
                    </span>
                </div>
            </form>
        </div>
    </div>

</main>

<script>
const placeholders = {
    groq:      'gsk_...',
    gemini:    'AIzaSy...',
    openai:    'sk-...',
    anthropic: 'sk-ant-...',
};

function selecionarProvedor(id) {
    // Destaca o card selecionado
    document.querySelectorAll('[id^="prov_card_"]').forEach(el => {
        const cb = el.querySelector('input[type="radio"]');
        el.style.borderColor = cb.checked ? 'var(--azul)' : '#dde3ef';
        el.style.background  = cb.checked ? '#eef3fd'     : '#fff';
    });
    // Mostra a dica do provedor selecionado
    document.querySelectorAll('[id^="chave_info_"]').forEach(el => {
        el.style.display = el.id === 'chave_info_' + id ? 'block' : 'none';
    });
    // Atualiza placeholder
    const input = document.getElementById('inputApiKey');
    if (input) input.placeholder = placeholders[id] || '';
}

function toggleVerChave() {
    const input = document.getElementById('inputApiKey');
    const icon  = document.getElementById('iconVerChave');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

// Destaca o provedor atual ao carregar
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="ia_provedor"]:checked');
    if (checked) selecionarProvedor(checked.value);
});
</script>
</body>
</html>