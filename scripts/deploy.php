<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$nivel     = $_SESSION['group'];
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

// Apenas admin e sup tecnica
if(!in_array($nivelNorm, ['admin', 'administrator', 'sup tecnica'])){
    header('location:../index.php');
    exit;
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Deploy — Sincronização GitHub</title>

<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

<style>
.deploy-card {
    background:#fff;
    border:1px solid #e0e0e0;
    border-radius:10px;
    padding:24px;
    margin-bottom:20px;
    box-shadow:0 2px 6px rgba(0,0,0,.06);
}
.deploy-card h5 { font-weight:700; margin-bottom:16px; }

/* Terminal de log */
#logOutput {
    background:#1e1e1e;
    color:#d4d4d4;
    font-family:monospace;
    font-size:13px;
    padding:16px;
    border-radius:6px;
    min-height:160px;
    max-height:420px;
    overflow-y:auto;
    white-space:pre-wrap;
    word-break:break-all;
}

/* Badges de versão */
.ver-badge {
    display:inline-block;
    font-family:monospace;
    font-size:12px;
    padding:3px 10px;
    border-radius:4px;
    font-weight:600;
}
.ver-atualizado  { background:#d4edda; color:#155724; }
.ver-atras       { background:#fff3cd; color:#856404; }
.ver-adiantado   { background:#cce5ff; color:#004085; }
.ver-divergente  { background:#f8d7da; color:#721c24; }
.ver-sem_branch  { background:#e2e3e5; color:#383d41; }
.ver-sem_remoto  { background:#e2e3e5; color:#383d41; }

.branch-pill {
    font-size:11px;
    padding:2px 9px;
    border-radius:10px;
    font-weight:700;
}
.pill-main { background:#d4edda; color:#155724; }
.pill-dev  { background:#fff3cd; color:#856404; }

.btn-deploy { min-width:180px; }
</style>
</head>

<body>
<div class="wrapper">

<div class="header">
    <div class="header-menu">
        <div class="title"><img src="../img/logo_white.svg"></div>
        <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
        <ul>
            <li><a href="#" class="user"><?php echo htmlspecialchars($logado); ?></a></li>
            <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
        </ul>
    </div>
</div>

<div class="sidebar">
    <div class="sidebar-menu"><?php include_once('../menu.php'); ?></div>
</div>

<div class="main-container">

    <h3 class="mb-4">
        <i class="fab fa-github mr-2"></i>Sincronização com GitHub
    </h3>

    <!-- STATUS DAS VERSÕES -->
    <div class="deploy-card">
        <h5><i class="fas fa-code-branch mr-2 text-primary"></i>Status das versões</h5>

        <div id="statusArea" class="mb-3">
            <button class="btn btn-outline-primary btn-sm" onclick="verificarStatus(this)">
                <i class="fas fa-sync-alt mr-1"></i>Verificar agora
            </button>
        </div>

        <small class="text-muted">
            Última verificação automática:
            <span id="ultimaVerificacao">—</span>
        </small>
    </div>

    <!-- AÇÕES DE DEPLOY -->
    <div class="deploy-card">
        <h5><i class="fas fa-cloud-download-alt mr-2 text-success"></i>Atualizar servidor</h5>

        <div class="row">

            <!-- Produção -->
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex align-items-center mb-2">
                        <span class="branch-pill pill-main mr-2">main</span>
                        <strong>Produção</strong>
                    </div>
                    <p class="text-muted small mb-3">
                        Puxa a branch <code>main</code> do GitHub.<br>
                        Use após validar tudo em desenvolvimento.
                    </p>
                    <button class="btn btn-success btn-block btn-deploy"
                            onclick="executarDeploy('main')">
                        <i class="fas fa-rocket mr-1"></i>Deploy → Produção
                    </button>
                </div>
            </div>

            <!-- Desenvolvimento -->
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex align-items-center mb-2">
                        <span class="branch-pill pill-dev mr-2">dev</span>
                        <strong>Desenvolvimento</strong>
                    </div>
                    <p class="text-muted small mb-3">
                        Puxa a branch <code>dev</code> do GitHub.<br>
                        Use neste servidor para testar antes de subir para produção.
                    </p>
                    <button class="btn btn-warning btn-block btn-deploy"
                            onclick="executarDeploy('dev')">
                        <i class="fas fa-flask mr-1"></i>Atualizar → Dev
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- LOG DE SAÍDA -->
    <div class="deploy-card">
        <h5><i class="fas fa-terminal mr-2"></i>Saída do processo</h5>
        <div id="logOutput">Nenhuma operação executada ainda.</div>
    </div>

    <!-- INFO CRON -->
    <div class="deploy-card">
        <h5><i class="fas fa-clock mr-2 text-secondary"></i>Verificação automática</h5>
        <p class="text-muted small mb-1">
            Um script cron verifica a cada 30 minutos se há atualizações no GitHub
            e registra em <code>logs/deploy_cron.log</code>.
            O cron <strong>não aplica</strong> o update automaticamente —
            apenas notifica. O deploy precisa ser feito pelo botão acima.
        </p>
        <p class="text-muted small mb-0">
            <i class="fas fa-shield-alt mr-1 text-success"></i>
            Os arquivos <code>conexao.php</code> e <code>config.php</code>
            nunca são sobrescritos pelo deploy.
        </p>
    </div>

</div><!-- /main-container -->
</div><!-- /wrapper -->

<script src="../js/menu.js"></script>
<script>

/* ── Helpers ── */
function escHtml(str){
    if(!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function log(msg, append){
    var el = document.getElementById('logOutput');
    if(append) el.textContent += '\n' + msg;
    else        el.textContent = msg;
    el.scrollTop = el.scrollHeight;
}

function spinner(btn, ativo){
    if(ativo){
        btn.dataset.orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Aguarde...';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.orig;
    }
}

/* ── Verificar status (compara hash local vs remoto) ── */
// btn é opcional — passado quando chamado por clique, ausente quando chamado pelo load
function verificarStatus(btn){
    btn = btn || null;
    if(btn) spinner(btn, true);

    fetch('deployExec.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({acao:'status'})
    })
    .then(r => r.json())
    .then(data => {
        if(btn) spinner(btn, false);

        var html = '';

        // Mapa de situação → aparência
        var situacaoMap = {
            'atualizado' : { icone:'fas fa-check-circle text-success',  label:'Atualizado'           },
            'atras'      : { icone:'fas fa-cloud-download-alt text-warning', label:'GitHub tem updates'  },
            'adiantado'  : { icone:'fas fa-upload text-info',           label:'Commits não enviados' },
            'divergente' : { icone:'fas fa-exclamation-triangle text-danger', label:'Divergente'      },
            'sem_branch' : { icone:'fas fa-minus-circle text-secondary', label:'Branch inexistente'  },
            'sem_remoto' : { icone:'fas fa-minus-circle text-secondary', label:'Sem remoto'          },
        };

        ['main','dev'].forEach(function(branch){
            var b = data[branch];
            if(!b) return;

            var sit    = b.situacao || 'divergente';
            var mapa   = situacaoMap[sit] || situacaoMap['divergente'];
            var pillCls = branch == 'main' ? 'pill-main' : 'pill-dev';

            html += '<div class="mb-3 p-2 border rounded">'
                  + '<div class="d-flex align-items-center mb-1">'
                  + '<span class="branch-pill ' + pillCls + ' mr-2">' + branch + '</span>'
                  + '<i class="' + mapa.icone + ' mr-1"></i>'
                  + '<span class="ver-badge ver-' + sit + '">' + mapa.label + '</span>'
                  + '<small class="text-muted ml-3">'
                  + 'Local: <code>' + b.local + '</code>'
                  + ' &nbsp;|&nbsp; '
                  + 'Remoto: <code>' + b.remoto + '</code>'
                  + '</small>'
                  + '</div>'
                  + '<small class="text-muted d-block" style="padding-left:4px">'
                  + escHtml(b.msg)
                  + '</small>'
                  + '</div>';
        });

        html += '<div class="mt-2">'
              + '<button class="btn btn-outline-primary btn-sm" onclick="verificarStatus(this)">'
              + '<i class="fas fa-sync-alt mr-1"></i>Atualizar</button></div>';

        document.getElementById('statusArea').innerHTML = html;
        document.getElementById('ultimaVerificacao').textContent = new Date().toLocaleString('pt-BR');
    })
    .catch(function(e){
        if(btn) spinner(btn, false);
        document.getElementById('statusArea').innerHTML =
            '<div class="alert alert-danger py-2">Erro ao verificar: ' + e.message + '</div>'
          + '<button class="btn btn-outline-primary btn-sm" onclick="verificarStatus(this)">'
          + '<i class="fas fa-sync-alt mr-1"></i>Tentar novamente</button>';
    });
}

/* ── Executar deploy ── */
function executarDeploy(branch){
    var aviso = branch === 'main'
        ? 'Confirma deploy para PRODUÇÃO (branch main)?\n\nOs arquivos conexao.php e config.php não serão alterados.'
        : 'Confirma atualização do ambiente de desenvolvimento (branch dev)?';

    if(!confirm(aviso)) return;

    var btns = document.querySelectorAll('.btn-deploy');
    btns.forEach(b => b.disabled = true);

    log('⏳ Iniciando deploy → branch: ' + branch + ' ...');

    fetch('deployExec.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({acao:'deploy', branch: branch})
    })
    .then(r => r.json())
    .then(data => {
        btns.forEach(b => b.disabled = false);
        log(data.saida);
        log(data.sucesso ? '\n✅ Deploy concluído com sucesso.' : '\n❌ Erro no deploy. Veja a saída acima.', true);
        // Atualiza o status após o deploy
        verificarStatus();
    })
    .catch(function(e){
        btns.forEach(b => b.disabled = false);
        log('❌ Erro de comunicação: ' + e.message);
    });
}

// Verifica o status automaticamente ao carregar a página
window.addEventListener('load', function(){ verificarStatus(null); });

</script>
</body>
</html>