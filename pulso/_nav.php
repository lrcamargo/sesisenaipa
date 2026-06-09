<?php
// pulso/gestao/_nav.php
// Topbar e sidebar do painel de gestão.
// Usa o layout e cores da intranet existente.
// Requer: $pagina_ativa, $g_nome, $g_perfil, $g_unidade_nome, $is_gerente
?>

<!-- TOPBAR DO PULSO SENAI -->
<div class="g-topbar">
    <div style="display:flex;align-items:center;gap:14px;">
        <img src="../../img/pulso.png" alt="PulsoSENAI" style="height:34px;">
        <span style="font-size:12px;color:rgba(255,255,255,.55);">
            <i class="fa fa-map-marker" style="margin-right:4px;"></i>
            <?= htmlspecialchars($g_unidade_nome) ?>
        </span>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
        <span style="font-size:12px;color:rgba(255,255,255,.7);">
            <?= htmlspecialchars($g_nome) ?>
        </span>
        <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;
                     background:rgba(255,255,255,.12);color:rgba(255,255,255,.85);text-transform:capitalize;">
            <?= htmlspecialchars($g_perfil) ?>
        </span>
        <a href="../../main.php"
           style="font-size:12px;color:rgba(255,255,255,.5);text-decoration:none;
                  display:flex;align-items:center;gap:4px;"
           title="Voltar à intranet">
            <i class="fa fa-arrow-left"></i> Intranet
        </a>
    </div>
</div>

<!-- SIDEBAR DO PULSO SENAI -->
<nav class="g-sidebar">

    <div class="nav-section">Ciclo atual</div>
    <a href="gestao.php"        class="<?= $pagina_ativa === 'dashboard'  ? 'ativo' : '' ?>">
        <i class="fa fa-tachometer"></i> Dashboard
    </a>
    <a href="itens_sla.php"    class="<?= $pagina_ativa === 'sla'        ? 'ativo' : '' ?>">
        <i class="fa fa-tasks"></i> Itens de SLA
    </a>
    <a href="devolutiva.php"   class="<?= $pagina_ativa === 'devolutiva' ? 'ativo' : '' ?>">
        <i class="fa fa-bullhorn"></i> Devolutiva
    </a>
    <a href="respostas.php"    class="<?= $pagina_ativa === 'respostas'   ? 'ativo' : '' ?>">
        <i class="fa fa-comments"></i> Respostas
    </a>
    <a href="analise_ia.php"   class="<?= $pagina_ativa === 'analise'      ? 'ativo' : '' ?>">
        <i class="fa fa-magic"></i> Análise com IA
    </a>
    <a href="plano_acao.php"   class="<?= $pagina_ativa === 'planos'      ? 'ativo' : '' ?>">
        <i class="fa fa-list-alt"></i> Planos de ação
    </a>

    <?php if ($is_gerente): ?>
    <div class="nav-section">Configuração</div>
    <a href="ciclos.php"       class="<?= $pagina_ativa === 'ciclos'        ? 'ativo' : '' ?>">
        <i class="fa fa-calendar"></i> Ciclos
    </a>
    <a href="perguntas.php"    class="<?= $pagina_ativa === 'perguntas'     ? 'ativo' : '' ?>">
        <i class="fa fa-question-circle"></i> Perguntas
    </a>
    <a href="configuracoes.php" class="<?= $pagina_ativa === 'configuracoes' ? 'ativo' : '' ?>">
        <i class="fa fa-cog"></i> Configurações
    </a>

    <div class="nav-section">Histórico</div>
    <a href="historico.php"    class="<?= $pagina_ativa === 'historico'  ? 'ativo' : '' ?>">
        <i class="fa fa-history"></i> Ciclos anteriores
    </a>
    <a href="relatorios.php"   class="<?= $pagina_ativa === 'relatorios' ? 'ativo' : '' ?>">
        <i class="fa fa-bar-chart"></i> Relatórios
    </a>
    <?php endif; ?>

    <div class="nav-section">Público</div>
    <a href="../index.php" target="_blank">
        <i class="fa fa-external-link"></i> Ver pesquisa
    </a>
    <a href="../status.php" target="_blank">
        <i class="fa fa-eye"></i> Painel de status
    </a>

</nav>