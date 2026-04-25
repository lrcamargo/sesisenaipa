<?php
/*
 * logIoT.php — Log de acessos e comandos IoT
 * Acesso restrito: admin, sup tecnica, gerencia
 */

require_once('../conexao.php');
session_start();

if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));

if(!in_array($nivelNorm,['admin','administrator','sup tecnica','gerencia'])){
    header('location:../index.php'); exit;
}

try {
    $pdoIot = new PDO(
        "mysql:host=localhost;dbname=cadastroiot;charset=utf8mb4",
        "root", "BdP@25!",
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch(PDOException $e){ $pdoIot = null; }

// Filtros GET
$filtroPorta  = trim($_GET['porta']  ?? '');
$filtroNome   = trim($_GET['nome']   ?? '');
$filtroDe     = trim($_GET['de']     ?? '');
$filtroAte    = trim($_GET['ate']    ?? '');
$pagAcessos   = max(1, intval($_GET['pag_a'] ?? 1));
$pagComandos  = max(1, intval($_GET['pag_c'] ?? 1));
$porPagina    = 50;

/* ── Acessos ── */
$acessos = []; $totalAcessos = 0;
if($pdoIot){
    try {
        $where = []; $params = [];
        if($filtroPorta){ $where[] = 'h.porta LIKE ?';  $params[] = '%'.$filtroPorta.'%'; }
        if($filtroNome) { $where[] = 'h.nome  LIKE ?';  $params[] = '%'.$filtroNome.'%'; }
        if($filtroDe)   { $where[] = 'h.data  >= ?';    $params[] = $filtroDe.' 00:00:00'; }
        if($filtroAte)  { $where[] = 'h.data  <= ?';    $params[] = $filtroAte.' 23:59:59'; }
        $sql = "FROM historico h".($where ? ' WHERE '.implode(' AND ',$where) : '');
        $totalAcessos = $pdoIot->prepare("SELECT COUNT(*) ".$sql);
        $totalAcessos->execute($params);
        $totalAcessos = (int)$totalAcessos->fetchColumn();
        $offset = ($pagAcessos-1)*$porPagina;
        $stmt = $pdoIot->prepare("SELECT h.* ".$sql." ORDER BY h.data DESC LIMIT {$porPagina} OFFSET {$offset}");
        $stmt->execute($params);
        $acessos = $stmt->fetchAll();
    } catch(PDOException $e){}
}

/* ── Comandos ── */
$comandos = []; $totalComandos = 0;
try {
    $totalComandos = (int)$pdo->query("SELECT COUNT(*) FROM iot_comandos")->fetchColumn();
    $offset2 = ($pagComandos-1)*$porPagina;
    $comandos = $pdo->query("SELECT * FROM iot_comandos ORDER BY enviado_em DESC LIMIT {$porPagina} OFFSET {$offset2}")->fetchAll();
} catch(PDOException $e){}

function paginacao(int $total, int $pagAtual, int $porPag, string $paramName): string {
    $totalPags = (int)ceil($total/$porPag);
    if($totalPags <= 1) return '';
    $qs = $_GET;
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    for($i=1; $i<=$totalPags; $i++){
        $qs[$paramName] = $i;
        $ativo = $i===$pagAtual ? 'active' : '';
        $html .= "<li class='page-item {$ativo}'><a class='page-link' href='?".http_build_query($qs)."'>{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log IoT</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.log-tabs{display:flex;gap:4px;border-bottom:2px solid #dee2e6;margin-bottom:0;}
.log-tab{padding:7px 18px;border-radius:6px 6px 0 0;border:1px solid #dee2e6;border-bottom:none;
    background:#f8f9fa;cursor:pointer;font-size:.85rem;font-weight:600;color:#495057;margin-bottom:-2px;}
.log-tab.ativo{background:#fff;border-bottom:2px solid #fff;color:#0d6efd;}
.log-panel{display:none;padding:16px 0;}
.log-panel.ativo{display:block;}
.log-table{font-size:.82rem;background:#fff;}
.log-table th{background:#343a40;color:#fff;white-space:nowrap;}
.log-table td,.log-table th{padding:6px 10px;vertical-align:middle;}
.badge-cracha{display:inline-block;background:#e3f2fd;color:#0d47a1;border-radius:4px;
    padding:2px 7px;font-size:.72rem;font-weight:700;font-family:monospace;}
.badge-desc{background:#fff3e0!important;color:#e65100!important;}
.filtro-bar{background:#fff;border:1px solid #dee2e6;border-radius:8px;
    padding:12px 14px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.filtro-bar .fg{display:flex;flex-direction:column;gap:3px;}
.filtro-bar label{font-size:.75rem;font-weight:600;color:#495057;margin:0;}
.filtro-bar input,.filtro-bar select{padding:4px 8px;border:1px solid #ced4da;border-radius:4px;font-size:.83rem;}
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
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-history mr-2"></i>Log IoT</h4>
        <a href="monitorIoT.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i>Voltar ao Monitor
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filtro-bar">
        <div class="fg">
            <label>Porta/ID</label>
            <input type="text" name="porta" value="<?php echo htmlspecialchars($filtroPorta); ?>" placeholder="Ex: 103">
        </div>
        <div class="fg">
            <label>Nome</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($filtroNome); ?>" placeholder="Nome do usuário">
        </div>
        <div class="fg">
            <label>De</label>
            <input type="date" name="de" value="<?php echo htmlspecialchars($filtroDe); ?>">
        </div>
        <div class="fg">
            <label>Até</label>
            <input type="date" name="ate" value="<?php echo htmlspecialchars($filtroAte); ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
            <i class="fas fa-search mr-1"></i>Filtrar
        </button>
        <a href="logIoT.php" class="btn btn-outline-secondary btn-sm" style="align-self:flex-end">Limpar</a>
    </form>

    <!-- Abas -->
    <div class="log-tabs">
        <div class="log-tab ativo" onclick="trocarAba('acessos')">
            <i class="fas fa-id-card mr-1"></i>Acessos
            <span class="badge badge-secondary ml-1"><?php echo number_format($totalAcessos); ?></span>
        </div>
        <div class="log-tab" onclick="trocarAba('comandos')">
            <i class="fas fa-terminal mr-1"></i>Comandos
            <span class="badge badge-secondary ml-1"><?php echo number_format($totalComandos); ?></span>
        </div>
    </div>

    <!-- Acessos -->
    <div class="log-panel ativo" id="panel-acessos">
    <?php if(!$pdoIot): ?>
        <div class="alert alert-warning mt-2">Não foi possível conectar ao banco <code>cadastroiot</code>.</div>
    <?php elseif(empty($acessos)): ?>
        <div class="text-muted text-center py-4"><i class="fas fa-inbox mr-2"></i>Nenhum acesso encontrado.</div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center py-2">
            <small class="text-muted"><?php echo $totalAcessos; ?> registros — página <?php echo $pagAcessos; ?></small>
            <?php echo paginacao($totalAcessos, $pagAcessos, $porPagina, 'pag_a'); ?>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-bordered log-table">
            <thead><tr><th>Data/Hora</th><th>Porta</th><th>Crachá</th><th>Nome</th><th>Registro</th></tr></thead>
            <tbody>
            <?php foreach($acessos as $a):
                $nomeExib = $a['nome'] ?: 'Desconhecido';
                $ehDesc   = $nomeExib === 'Desconhecido';
            ?>
            <tr>
                <td style="white-space:nowrap"><?php echo htmlspecialchars($a['data']); ?></td>
                <td><strong><?php echo htmlspecialchars($a['porta']); ?></strong></td>
                <td><span class="badge-cracha <?php echo $ehDesc?'badge-desc':''; ?>"><?php echo htmlspecialchars($a['cracha']); ?></span></td>
                <td><?php echo htmlspecialchars($nomeExib); ?></td>
                <td><?php echo htmlspecialchars($a['registro']??''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php echo paginacao($totalAcessos, $pagAcessos, $porPagina, 'pag_a'); ?>
    <?php endif; ?>
    </div>

    <!-- Comandos -->
    <div class="log-panel" id="panel-comandos">
    <?php if(empty($comandos)): ?>
        <div class="text-muted text-center py-4"><i class="fas fa-inbox mr-2"></i>Nenhum comando registrado.</div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center py-2">
            <small class="text-muted"><?php echo $totalComandos; ?> registros — página <?php echo $pagComandos; ?></small>
            <?php echo paginacao($totalComandos, $pagComandos, $porPagina, 'pag_c'); ?>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-bordered log-table">
            <thead><tr><th>Data/Hora</th><th>Dispositivo</th><th>Payload</th><th>Enviado por</th></tr></thead>
            <tbody>
            <?php foreach($comandos as $c): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo htmlspecialchars($c['enviado_em']); ?></td>
                <td><strong><?php echo htmlspecialchars($c['topico_id']); ?></strong></td>
                <td><code style="font-size:.75rem"><?php echo htmlspecialchars($c['payload']??''); ?></code></td>
                <td><?php echo htmlspecialchars($c['enviado_por']??''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php echo paginacao($totalComandos, $pagComandos, $porPagina, 'pag_c'); ?>
    <?php endif; ?>
    </div>

</div>
</div>
<script src="../js/menu.js"></script>
<script>
function trocarAba(aba){
    document.querySelectorAll('.log-tab').forEach(function(t){ t.classList.remove('ativo'); });
    document.querySelectorAll('.log-panel').forEach(function(p){ p.classList.remove('ativo'); });
    document.querySelector('.log-tab[onclick*="'+aba+'"]').classList.add('ativo');
    document.getElementById('panel-'+aba).classList.add('ativo');
}
</script>
</body>
</html>