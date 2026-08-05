<?php
/*
 * estoqueMovimentacoes.php
 * Controle de entrada/saída de estoque, saldo atual, alertas de compra
 * e gráfico de consumo médio.
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia'])){
    header('location:../index.php'); exit;
}

/* ══════════════════════════════
   POST AJAX
══════════════════════════════ */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    if($_POST['acao']==='movimentar'){
        $idItem  = intval($_POST['idItem']      ?? 0);
        $tipo    = $_POST['tipo']               ?? '';
        $qtd     = floatval($_POST['quantidade'] ?? 0);
        $custo   = $_POST['custo'] !== '' ? floatval($_POST['custo']) : null;
        $resp    = intval($_POST['responsavel'] ?? 0) ?: null;
        $obs     = trim($_POST['observacao']    ?? '') ?: null;

        if(!$idItem || !in_array($tipo,['entrada','saida','ajuste','inventario']) || $qtd <= 0){
            echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
        }

        try {
            // Calcula saldo atual
            $stSaldo = $pdo->prepare(
                "SELECT saldoApos FROM estoque_movimentacoes WHERE idItem=? ORDER BY criado_em DESC, id DESC LIMIT 1"
            );
            $stSaldo->execute([$idItem]);
            $saldoAtual = (float)($stSaldo->fetchColumn() ?: 0);

            // Calcula novo saldo
            if($tipo === 'entrada'){
                $novoSaldo = $saldoAtual + $qtd;
            } elseif($tipo === 'saida'){
                if($qtd > $saldoAtual){
                    echo json_encode(['ok'=>false,'msg'=>"Saldo insuficiente. Saldo atual: {$saldoAtual}."]); exit;
                }
                $novoSaldo = $saldoAtual - $qtd;
            } elseif($tipo === 'ajuste'){
                $novoSaldo = $saldoAtual + $qtd; // qtd pode ser negativa mas o form envia positivo
            } else { // inventario
                $novoSaldo = $qtd; // ajuste direto ao valor real
            }

            $pdo->prepare("
                INSERT INTO estoque_movimentacoes
                    (idItem,tipo,quantidade,saldoApos,custo,responsavel,observacao)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$idItem,$tipo,$qtd,$novoSaldo,$custo,$resp,$obs]);

            echo json_encode(['ok'=>true,'novoSaldo'=>$novoSaldo]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao registrar.']);
        }
        exit;
    }

    if($_POST['acao']==='vincular_item'){
        $idItemOrigem = intval($_POST['idItemOrigem'] ?? 0);
        $idLabDestino = intval($_POST['idLabDestino'] ?? 0);
        if(!$idItemOrigem || !$idLabDestino){
            echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
        }
        try {
            // Busca dados do item original
            $stOrig = $pdo->prepare("SELECT * FROM estoque_itens WHERE id=? AND ativo=1");
            $stOrig->execute([$idItemOrigem]);
            $orig = $stOrig->fetch(PDO::FETCH_ASSOC);
            if(!$orig){ echo json_encode(['ok'=>false,'msg'=>'Item original não encontrado.']); exit; }
            // Verifica se já existe neste lab
            $stExiste = $pdo->prepare("SELECT id FROM estoque_itens WHERE idLaboratorio=? AND descricao=? AND ativo=1");
            $stExiste->execute([$idLabDestino, $orig['descricao']]);
            $existe = $stExiste->fetchColumn();
            if($existe){
                echo json_encode(['ok'=>true,'idNovoItem'=>$existe,'msg'=>'Já existia neste setor.']);
                exit;
            }
            // Cria novo item no setor destino
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO estoque_itens (idLaboratorio,descricao,idUnidade,estoqueMinimo) VALUES (?,?,?,?)")
                ->execute([$idLabDestino, $orig['descricao'], $orig['idUnidade'], $orig['estoqueMinimo']]);
            $idNovo = $pdo->lastInsertId();
            // Cria movimentação inicial com saldo zero para que o item passe a
            // aparecer em v_estoque_saldo (a view depende de existir ao menos
            // 1 registro em estoque_movimentacoes para calcular o saldo).
            $pdo->prepare("
                INSERT INTO estoque_movimentacoes (idItem,tipo,quantidade,saldoApos,observacao)
                VALUES (?, 'inventario', 0, 0, 'Vínculo automático - item já cadastrado em outro setor')
            ")->execute([$idNovo]);
            $pdo->commit();
            echo json_encode(['ok'=>true,'idNovoItem'=>$idNovo]);
        } catch(PDOException $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['ok'=>false,'msg'=>'Erro ao vincular: '.$e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ══════════════════════════════
   Dados
══════════════════════════════ */
// Laboratório selecionado
$labs = $pdo->query(
    "SELECT idLaboratorio, nome FROM laboratorios WHERE temEstoque=1 ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$idLabSel = intval($_GET['lab'] ?? ($labs[0]['idLaboratorio'] ?? 0));
$idItemSel = intval($_GET['item'] ?? 0);

// Itens do lab com saldo atual (via view)
$itens = [];
if($idLabSel){
    $st = $pdo->prepare("SELECT * FROM v_estoque_saldo WHERE idLaboratorio=? ORDER BY descricao");
    $st->execute([$idLabSel]);
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);
     //if(isset($_GET['debug'])){ echo '<pre>IDs retornados: '; print_r(array_column($itens,'id')); exit; }
}

// Seleciona primeiro item se nenhum selecionado
if(!$idItemSel && !empty($itens)) $idItemSel = $itens[0]['id'];

// Histórico do item selecionado (últimos 60 dias)
$historico = [];
$itemSel   = null;
if($idItemSel){
    foreach($itens as $it) if($it['id']==$idItemSel){ $itemSel=$it; break; }
    $st = $pdo->prepare("
        SELECT m.*, u.nome AS nomeResp
        FROM estoque_movimentacoes m
        LEFT JOIN usuarios u ON u.id=m.responsavel
        WHERE m.idItem=?
        ORDER BY m.criado_em DESC, m.id DESC
        LIMIT 100
    ");
    $st->execute([$idItemSel]);
    $historico = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Alertas de compra (todos os labs)
$alertas = $pdo->query(
    "SELECT * FROM v_estoque_saldo WHERE alertaCompra=1 ORDER BY laboratorio, descricao"
)->fetchAll(PDO::FETCH_ASSOC);

// Dados para gráfico de consumo (últimos 6 meses, saídas por mês)
$consumoMensal = [];
if($idItemSel){
    $st = $pdo->prepare("
        SELECT DATE_FORMAT(criado_em,'%Y-%m') AS mes,
               SUM(quantidade) AS totalSaida
        FROM estoque_movimentacoes
        WHERE idItem=? AND tipo='saida'
          AND criado_em >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY mes ORDER BY mes
    ");
    $st->execute([$idItemSel]);
    $consumoMensal = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Todos como responsável
$instrutores = $pdo->query(
    "SELECT id,nome FROM usuarios ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$labSelNome = '';
foreach($labs as $l) if($l['idLaboratorio']==$idLabSel) $labSelNome=$l['nome'];

$tiposLabels = [
    'entrada'    => ['label'=>'Entrada',    'cor'=>'success', 'icone'=>'fa-arrow-down'],
    'saida'      => ['label'=>'Saída',      'cor'=>'danger',  'icone'=>'fa-arrow-up'],
    'ajuste'     => ['label'=>'Ajuste',     'cor'=>'warning', 'icone'=>'fa-balance-scale'],
    'inventario' => ['label'=>'Inventário', 'cor'=>'info',    'icone'=>'fa-clipboard-list'],
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Controle de Estoque</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- DataTables + Buttons (exportação Excel) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<style>
.lab-selector{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.lab-btn{padding:7px 16px;border-radius:6px;border:2px solid #dee2e6;background:#fff;
    font-size:.83rem;font-weight:600;cursor:pointer;color:#495057;text-decoration:none;transition:all .15s;}
.lab-btn:hover{border-color:#0d6efd;color:#0d6efd;text-decoration:none;}
.lab-btn.ativo{border-color:#0d6efd;background:#0d6efd;color:#fff;}
.item-card{display:block;background:#fff;border:2px solid #dee2e6;border-radius:8px;padding:10px 14px;
    margin-bottom:6px;cursor:pointer;transition:all .12s;font-size:.82rem;}
.item-card:hover{border-color:#0d6efd;}
.item-card.ativo{border-color:#0d6efd;background:#f0f7ff;}
.item-card.alerta{border-color:#dc3545;background:#fff5f5;}
.saldo-num{font-size:1.5rem;font-weight:800;}
.saldo-ok{color:#1b5e20;}
.saldo-alerta{color:#dc3545;}
.hist-table th{background:#343a40;color:#fff;font-size:.78rem;white-space:nowrap;}
.hist-table td{font-size:.8rem;vertical-align:middle;}
.badge-mov{display:inline-block;border-radius:4px;padding:2px 8px;font-size:.7rem;font-weight:700;}
.alerta-compra{background:#fff5f5;border:2px solid #dc3545;border-radius:8px;
    padding:12px 16px;margin-bottom:16px;}
.alerta-compra h6{color:#dc3545;font-weight:700;margin-bottom:8px;font-size:.9rem;}
.alerta-item{display:inline-block;background:#f8d7da;color:#721c24;border-radius:4px;
    padding:3px 10px;font-size:.75rem;font-weight:600;margin:2px;}
.chart-wrap{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:14px;
    margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
#toast{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toast.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toast.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.form-tipo-btn{padding:6px 14px;border-radius:6px;border:2px solid #dee2e6;background:#fff;
    font-size:.82rem;font-weight:600;cursor:pointer;color:#495057;transition:all .12s;}
.form-tipo-btn.sel-entrada{border-color:#198754;background:#d1e7dd;color:#0f5132;}
.form-tipo-btn.sel-saida  {border-color:#dc3545;background:#f8d7da;color:#721c24;}
.form-tipo-btn.sel-ajuste {border-color:#ffc107;background:#fff3cd;color:#664d03;}
.form-tipo-btn.sel-inventario{border-color:#0dcaf0;background:#cff4fc;color:#055160;}
.contexto-mov{background:#e7f1ff;border:1px solid #b6d4fe;border-radius:6px;padding:8px 10px;
    font-size:.78rem;margin-bottom:12px;display:none;color:#084298;}
</style>
</head>
<body>
<div class="wrapper">
<div class="header"><div class="header-menu">
    <div class="title"><img src="../img/logo_white.svg"></div>
    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
    <ul>
        <li><a href="#" class="user"><?php echo htmlspecialchars($_SESSION['user']); ?></a></li>
        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
    </ul>
</div></div>
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container" style="min-height:100vh">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-boxes mr-2"></i>Controle de Estoque</h4>
    <a href="cadastroEstoqueItens.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-cog mr-1"></i>Gerenciar Itens
    </a>
</div>

<!-- Alertas de compra -->
<?php if(!empty($alertas)):
    // Agrupa por setor (laboratório)
    $alertasPorSetor = [];
    foreach($alertas as $al) $alertasPorSetor[$al['laboratorio']][] = $al;
    $totalSetores = count($alertasPorSetor);
    $totalItens   = count($alertas);
?>
<div class="alerta-compra" id="blocoAlerta">
    <!-- Cabeçalho resumo -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <h6 style="margin:0">
            <i class="fas fa-exclamation-circle mr-1"></i>
            <strong><?php echo $totalItens; ?></strong> item(ns) de
            <strong><?php echo $totalSetores; ?></strong> setor(es) abaixo do mínimo — verificar reposição
        </h6>
        <div style="display:flex;gap:6px">
            <button class="btn btn-outline-secondary btn-sm" id="btnVerTodos" onclick="toggleVerTodos(this)">
                <i class="fas fa-list mr-1"></i>Ver todos
            </button>
        </div>
    </div>

    <!-- Setores (sempre visíveis) -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px" id="alertaSetores">
        <?php foreach($alertasPorSetor as $setor => $itensSetor): ?>
        <button class="btn btn-sm"
                style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;font-weight:600"
                onclick="mostrarSetor('<?php echo addslashes($setor); ?>', this)">
            <?php echo htmlspecialchars($setor); ?>
            <span style="background:#721c24;color:#fff;border-radius:10px;
                  padding:1px 7px;font-size:.7rem;margin-left:4px">
                <?php echo count($itensSetor); ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Itens do setor selecionado -->
    <div id="alertaItensSetor" style="display:none;margin-top:10px;
         background:#fff;border-radius:6px;padding:10px 12px">
        <div style="font-size:.72rem;font-weight:700;color:#721c24;margin-bottom:6px"
             id="alertaSetorTitulo"></div>
        <div id="alertaItensLista"></div>
    </div>

    <!-- Ver todos (colapsável) — tabela DataTables com exportação Excel -->
    <div id="alertaTodos" style="display:none;margin-top:12px">
        <table id="tabelaFaltantes" class="table table-sm table-bordered bg-white" style="font-size:.8rem;width:100%">
            <thead>
                <tr>
                    <th>Setor</th>
                    <th>Item</th>
                    <th>Saldo Atual</th>
                    <th>Estoque Mínimo</th>
                    <th>Unidade</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($alertas as $al): ?>
            <tr>
                <td><?php echo htmlspecialchars($al['laboratorio']); ?></td>
                <td style="font-weight:600"><?php echo htmlspecialchars($al['descricao']); ?></td>
                <td style="color:#dc3545;font-weight:700;text-align:right">
                    <?php echo number_format($al['saldoAtual'],2,',','.'); ?>
                </td>
                <td style="text-align:right">
                    <?php echo number_format($al['estoqueMinimo'],2,',','.'); ?>
                </td>
                <td><?php echo htmlspecialchars($al['unidadeAbv']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dados dos alertas para JS -->
<script>
var _alertasPorSetor = <?php
    $js = [];
    foreach($alertasPorSetor as $setor => $itensAlertaSetor){
        $js[$setor] = array_map(fn($a)=>[
            'descricao'    => $a['descricao'],
            'saldoAtual'   => $a['saldoAtual'],
            'estoqueMinimo'=> $a['estoqueMinimo'],
            'unidadeAbv'   => $a['unidadeAbv'],
        ], $itensAlertaSetor);
    }
    echo json_encode($js, JSON_UNESCAPED_UNICODE);
?>;
var _alertasTodos = <?php echo json_encode(array_map(fn($a)=>[
    'laboratorio'  => $a['laboratorio'],
    'descricao'    => $a['descricao'],
    'saldoAtual'   => $a['saldoAtual'],
    'estoqueMinimo'=> $a['estoqueMinimo'],
    'unidadeAbv'   => $a['unidadeAbv'],
], $alertas), JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php endif; ?>

<!-- Seletor de laboratório -->
<div class="lab-selector">
    <?php foreach($labs as $l): ?>
    <a href="?lab=<?php echo $l['idLaboratorio']; ?>"
       class="lab-btn <?php echo $l['idLaboratorio']==$idLabSel?'ativo':''; ?>">
        <i class="fas fa-flask mr-1"></i><?php echo htmlspecialchars($l['nome']); ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if($idLabSel): ?>
<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">

    <!-- Lista de itens (esquerda) -->
    <div style="width:240px;flex-shrink:0;position:sticky;top:0">
        <div style="font-size:.72rem;font-weight:700;color:#6c757d;text-transform:uppercase;
             letter-spacing:.05em;margin-bottom:6px">Itens — <?php echo htmlspecialchars($labSelNome); ?></div>
        <input type="text" id="buscaItem" placeholder="🔍 Buscar item..."
               oninput="filtrarItens(this.value)"
               style="width:100%;padding:5px 8px;border:1px solid #ced4da;border-radius:6px;
                      font-size:.8rem;margin-bottom:8px">
        <div id="crossSetorMsg" style="display:none;background:#fff3e0;border:1px solid #e65100;
             border-radius:6px;padding:8px 10px;margin-bottom:8px;font-size:.76rem">
        </div>
        <div id="listaItens" style="max-height:calc(100vh - 220px);overflow-y:auto">
        <?php foreach($itens as $it):
            $alerta = $it['alertaCompra'];
            $ativo  = $it['id']==$idItemSel;
        ?>
        <a href="?lab=<?php echo $idLabSel; ?>&item=<?php echo $it['id']; ?>"
           class="item-card text-decoration-none <?php echo $ativo?'ativo':''; ?> <?php echo $alerta?'alerta':''; ?>"
           data-nome="<?php echo strtolower(htmlspecialchars($it['descricao'])); ?>">
            <div style="font-weight:600;color:#212529"><?php echo htmlspecialchars($it['descricao']); ?></div>
            <div style="display:flex;justify-content:space-between;margin-top:4px">
                <span style="font-size:.72rem;color:#6c757d"><?php echo htmlspecialchars($it['unidade']); ?></span>
                <span style="font-weight:700;font-size:.85rem;color:<?php echo $alerta?'#dc3545':'#1b5e20'; ?>">
                    <?php echo $alerta?'⚠️ ':''; ?><?php echo number_format($it['saldoAtual'],2,',','.'); ?> <?php echo htmlspecialchars($it['unidadeAbv']); ?>
                </span>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
        <?php if(empty($itens)): ?>
        <div class="text-muted text-center py-3" style="font-size:.8rem">Nenhum item cadastrado.</div>
        <?php endif; ?>
    </div>

    <!-- Painel do item selecionado (direita) -->
    <?php if($itemSel): ?>
    <div style="flex:1;min-width:280px">

        <!-- Saldo atual -->
        <div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:16px;
             margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.06)">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <h6 style="font-weight:700;margin-bottom:2px"><?php echo htmlspecialchars($itemSel['descricao']); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($itemSel['laboratorio']); ?></small>
                </div>
                <button class="btn btn-primary btn-sm" onclick="abrirMovimentacao()">
                    <i class="fas fa-exchange-alt mr-1"></i>Movimentar
                </button>
            </div>
            <hr style="margin:10px 0">
            <div style="display:flex;gap:20px;flex-wrap:wrap">
                <div>
                    <div style="font-size:.7rem;color:#6c757d;text-transform:uppercase;font-weight:600">Saldo Atual</div>
                    <div class="saldo-num <?php echo $itemSel['alertaCompra']?'saldo-alerta':'saldo-ok'; ?>">
                        <?php echo number_format($itemSel['saldoAtual'],2,',','.'); ?>
                        <small style="font-size:.9rem"><?php echo htmlspecialchars($itemSel['unidadeAbv']); ?></small>
                    </div>
                </div>
                <?php if($itemSel['estoqueMinimo'] > 0): ?>
                <div>
                    <div style="font-size:.7rem;color:#6c757d;text-transform:uppercase;font-weight:600">Mínimo</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#e65100">
                        <?php echo number_format($itemSel['estoqueMinimo'],2,',','.'); ?>
                        <small style="font-size:.8rem"><?php echo htmlspecialchars($itemSel['unidadeAbv']); ?></small>
                    </div>
                </div>
                <?php if($itemSel['alertaCompra']): ?>
                <div class="align-self-center">
                    <span style="background:#f8d7da;color:#721c24;border-radius:6px;
                          padding:6px 12px;font-size:.8rem;font-weight:700">
                        ⚠️ Repor estoque
                    </span>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php
                // Consumo médio mensal
                if(!empty($consumoMensal)){
                    $mediaMes = round(array_sum(array_column($consumoMensal,'totalSaida')) / count($consumoMensal), 2);
                    $previsao = $mediaMes > 0 ? round($itemSel['saldoAtual'] / $mediaMes, 1) : null;
                }
                ?>
                <?php if(!empty($consumoMensal) && isset($mediaMes)): ?>
                <div>
                    <div style="font-size:.7rem;color:#6c757d;text-transform:uppercase;font-weight:600">Média Mensal</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#0d47a1">
                        <?php echo number_format($mediaMes,2,',','.'); ?>
                        <small style="font-size:.8rem"><?php echo htmlspecialchars($itemSel['unidadeAbv']); ?>/mês</small>
                    </div>
                </div>
                <?php if($previsao !== null): ?>
                <div>
                    <div style="font-size:.7rem;color:#6c757d;text-transform:uppercase;font-weight:600">Previsão de Duração</div>
                    <div style="font-size:1.2rem;font-weight:700;color:<?php echo $previsao<1?'#dc3545':($previsao<2?'#e65100':'#1b5e20'); ?>">
                        <?php echo $previsao; ?> mês(es)
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gráfico de consumo -->
        <?php if(!empty($consumoMensal)): ?>
        <div class="chart-wrap">
            <h6 style="font-weight:700;font-size:.88rem;margin-bottom:12px;color:#343a40">
                <i class="fas fa-chart-line mr-1 text-danger"></i>Consumo Mensal (saídas — últimos 6 meses)
            </h6>
            <canvas id="chartConsumo" height="80"></canvas>
        </div>
        <?php endif; ?>

        <!-- Histórico -->
        <div style="margin-top:14px">
            <h6 style="font-weight:700;font-size:.88rem;margin-bottom:10px;color:#343a40">
                <i class="fas fa-history mr-1"></i>Histórico de Movimentações
            </h6>
            <?php if(empty($historico)): ?>
            <div class="text-muted text-center py-3" style="font-size:.8rem">Nenhuma movimentação registrada.</div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm table-bordered hist-table bg-white">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Qtd</th>
                        <th>Saldo</th>
                        <th>Responsável</th>
                        <th>Obs</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($historico as $h):
                    $tConf = $tiposLabels[$h['tipo']] ?? ['label'=>$h['tipo'],'cor'=>'secondary','icone'=>'fa-circle'];
                ?>
                <tr>
                    <td style="white-space:nowrap">
                        <?php echo date('d/m/Y H:i',strtotime($h['criado_em'])); ?>
                    </td>
                    <td>
                        <span class="badge-mov badge-<?php echo $tConf['cor']; ?>">
                            <i class="fas <?php echo $tConf['icone']; ?> mr-1"></i><?php echo $tConf['label']; ?>
                        </span>
                    </td>
                    <td style="font-weight:700;color:<?php echo $h['tipo']==='entrada'?'#1b5e20':'#c62828'; ?>">
                        <?php echo $h['tipo']==='entrada'?'+':($h['tipo']==='saida'?'-':'±'); ?>
                        <?php echo number_format($h['quantidade'],2,',','.'); ?>
                    </td>
                    <td style="font-weight:600">
                        <?php echo number_format($h['saldoApos'],2,',','.'); ?>
                    </td>
                    <td><?php echo htmlspecialchars($h['nomeResp'] ?? $h['turma'] ?? '—'); ?></td>
                    <td style="color:#6c757d;font-size:.76rem"><?php echo htmlspecialchars($h['observacao'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /painel item -->
    <?php endif; ?>

</div><!-- /flex container -->
<?php endif; ?>

</div></div>

<!-- Modal Movimentação -->
<div class="modal fade" id="modalMov" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">
            <i class="fas fa-exchange-alt mr-2"></i>Registrar Movimentação
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <div class="contexto-mov" id="mContexto"></div>
        <!-- Seletor de tipo -->
        <div class="mb-3">
            <label class="font-weight-bold d-block mb-2" style="font-size:.82rem">Tipo <span class="text-danger">*</span></label>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button type="button" class="form-tipo-btn" data-tipo="entrada" onclick="selTipo('entrada',this)">
                    <i class="fas fa-arrow-down mr-1"></i>Entrada
                </button>
                <button type="button" class="form-tipo-btn" data-tipo="saida" onclick="selTipo('saida',this)">
                    <i class="fas fa-arrow-up mr-1"></i>Saída
                </button>
                <button type="button" class="form-tipo-btn" data-tipo="ajuste" onclick="selTipo('ajuste',this)">
                    <i class="fas fa-balance-scale mr-1"></i>Ajuste
                </button>
                <button type="button" class="form-tipo-btn" data-tipo="inventario" onclick="selTipo('inventario',this)">
                    <i class="fas fa-clipboard-list mr-1"></i>Inventário
                </button>
            </div>
            <input type="hidden" id="mTipo" value="">
            <small id="mTipoInfo" class="text-muted d-block mt-1"></small>
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">
                    Quantidade <span class="text-danger">*</span>
                    <span id="mQtdLabel" style="color:#6c757d;font-weight:400"></span>
                </label>
                <input type="number" id="mQtd" class="form-control form-control-sm"
                       min="0.001" step="0.001" placeholder="0">
            </div>
            <div class="col" id="mCustoWrap">
                <label class="font-weight-bold" style="font-size:.82rem">Custo Unitário (R$)</label>
                <input type="number" id="mCusto" class="form-control form-control-sm"
                       min="0" step="0.01" placeholder="Opcional">
            </div>
        </div>
        <div class="form-row mb-3" id="mRespTurmaWrap">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Responsável</label>
                <select id="mResp" class="form-control form-control-sm">
                    <option value="">— Selecione —</option>
                    <?php foreach($instrutores as $i): ?>
                    <option value="<?php echo $i['id']; ?>">
                        <?php echo htmlspecialchars(mb_strtoupper($i['nome'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>
        <div class="form-group mb-0">
            <label class="font-weight-bold" style="font-size:.82rem">Observação</label>
            <textarea id="mObs" class="form-control form-control-sm" rows="2"
                      placeholder="Motivo, nota fiscal, lote..."></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarMov()">
            <i class="fas fa-save mr-1"></i>Registrar
        </button>
    </div>
</div></div>
</div>

<div id="toast"></div>
<script src="../js/menu.js"></script>
<script>
var _idItemSel = <?php echo $idItemSel ?: 0; ?>;
var _idLabSel  = <?php echo $idLabSel  ?: 0; ?>;
var _labSelNome = <?php echo json_encode($labSelNome); ?>;

/* Todos os itens de todos os setores para busca cross-setor */
var _todosItens = <?php
    $todosItens = $pdo->query("
        SELECT i.id, i.idLaboratorio, i.descricao, l.nome AS laboratorio
        FROM estoque_itens i
        JOIN laboratorios l ON l.idLaboratorio=i.idLaboratorio
        WHERE i.ativo=1
        ORDER BY i.descricao
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($todosItens, JSON_UNESCAPED_UNICODE);
?>;

/* ── Gráfico de consumo ── */
<?php if(!empty($consumoMensal)): ?>
(function(){
    var meses   = <?php echo json_encode(array_column($consumoMensal,'mes')); ?>;
    var valores = <?php echo json_encode(array_map(fn($r)=>floatval($r['totalSaida']),$consumoMensal)); ?>;
    // Formata meses para exibição
    var labels = meses.map(function(m){
        var p=m.split('-'); var ns=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return ns[parseInt(p[1])-1]+'/'+p[0].substring(2);
    });
    var ctx = document.getElementById('chartConsumo').getContext('2d');
    new Chart(ctx,{
        type:'bar',
        data:{
            labels:labels,
            datasets:[{
                label:'Consumo (saídas)',
                data:valores,
                backgroundColor:'rgba(220,53,69,.7)',
                borderColor:'#dc3545',
                borderWidth:1, borderRadius:4
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{display:false},
                tooltip:{callbacks:{label:function(c){return c.parsed.y+' <?php echo addslashes($itemSel['unidadeAbv'] ?? ''); ?>';}}
            }},
            scales:{y:{beginAtZero:true,title:{display:true,text:'Quantidade'}}}
        }
    });
})();
<?php endif; ?>

/* ── Movimentação ── */
var _tipoSel = '';
var _tipoDescs = {
    entrada:    'Adiciona quantidade ao saldo atual.',
    saida:      'Remove quantidade do saldo. Informe a turma/responsável.',
    ajuste:     'Adiciona ou remove quantidade por diferença (ex: quebra, perda).',
    inventario: 'Define o saldo real atual — substitui o valor calculado.'
};

function aplicarTipo(tipo){
    _tipoSel = tipo;
    document.getElementById('mTipo').value = tipo;
    document.getElementById('mTipoInfo').textContent = _tipoDescs[tipo] || '';
    document.querySelectorAll('.form-tipo-btn').forEach(function(b){
        b.className = 'form-tipo-btn' + (b.dataset.tipo === tipo ? ' sel-' + tipo : '');
    });
    // Custo só faz sentido na entrada
    document.getElementById('mCustoWrap').style.display = tipo === 'entrada' ? '' : 'none';
    // Label da quantidade
    document.getElementById('mQtdLabel').textContent = tipo === 'inventario' ? '(novo saldo total)' : '';
}

function selTipo(tipo, btn){
    aplicarTipo(tipo);
}

function abrirMovimentacao(idItemForcado, contextoTexto){
    _idItemSel = idItemForcado || <?php echo $idItemSel ?: 0; ?>;
    _tipoSel='';
    document.getElementById('mTipo').value='';
    document.getElementById('mTipoInfo').textContent='';
    document.getElementById('mQtd').value='';
    document.getElementById('mCusto').value='';
    document.getElementById('mResp').value='';
    document.getElementById('mObs').value='';
    document.querySelectorAll('.form-tipo-btn').forEach(b=>{
        b.className='form-tipo-btn';
    });
    var ctx = document.getElementById('mContexto');
    if(contextoTexto){
        ctx.innerHTML = '<i class="fas fa-info-circle mr-1"></i>' + contextoTexto;
        ctx.style.display = '';
    } else {
        ctx.style.display = 'none';
    }
    $('#modalMov').modal('show');
}
// Esconde custo inicialmente
document.getElementById('mCustoWrap').style.display='none';

function salvarMov(){
    if(!_tipoSel){ alert('Selecione o tipo de movimentação.'); return; }
    var qtd = parseFloat(document.getElementById('mQtd').value)||0;
    if(qtd<=0){ alert('Informe a quantidade.'); return; }
    var fd=new FormData();
    fd.append('acao','movimentar');
    fd.append('idItem',_idItemSel);
    fd.append('tipo',_tipoSel);
    fd.append('quantidade',qtd);
    fd.append('custo',document.getElementById('mCusto').value);
    fd.append('responsavel',document.getElementById('mResp').value);
    fd.append('observacao',document.getElementById('mObs').value);
    fetch('estoqueMovimentacoes.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Registrado! Novo saldo: '+res.novoSaldo,'ok');
            $('#modalMov').modal('hide');
            setTimeout(()=>location.reload(), 800);
        }).catch(()=>toast('Erro.','err'));
}

/* ── Busca de itens ── */
function filtrarItens(q){
    q = q.toLowerCase().trim();
    var encontrouNoSetor = false;
    var crossMsg = document.getElementById('crossSetorMsg');

    document.querySelectorAll('#listaItens a').forEach(function(card){
        var nome = (card.dataset.nome || '').toLowerCase();
        var match = (!q || nome.includes(q));
        card.style.display = match ? 'block' : 'none';
        if(match) encontrouNoSetor = true;
    });

    if(q && !encontrouNoSetor){
        var outros = _todosItens.filter(function(it){
            return it.descricao.toLowerCase().includes(q)
                && String(it.idLaboratorio) !== String(_idLabSel);
        });
        if(outros.length > 0){
            var html = '';
            outros.slice(0,5).forEach(function(it){
                html += '<div style="margin-top:6px;padding-top:6px;border-top:1px solid #ffe0b2">'
                    + '<strong>"' + esc(it.descricao) + '"</strong> não está cadastrado no setor selecionado '
                    + '(<em>' + esc(_labSelNome) + '</em>). Ele já existe em <em>' + esc(it.laboratorio) + '</em>.'
                    + '<div style="margin-top:6px">'
                    + '<button class="btn btn-outline-warning btn-sm mr-1" style="font-size:.7rem" '
                    + 'onclick="vincularItem(' + it.id + ',' + it.idLaboratorio + ')">'
                    + '<i class="fas fa-link mr-1"></i>Adicionar ao setor novo (' + esc(_labSelNome) + ')'
                    + '</button>'
                    + '<button class="btn btn-outline-primary btn-sm" style="font-size:.7rem" '
                    + 'onclick="movimentarOutroSetor(' + it.id + ',\'' + escJS(it.descricao) + '\',\'' + escJS(it.laboratorio) + '\')">'
                    + '<i class="fas fa-exchange-alt mr-1"></i>Dar entrada no setor onde já existe (' + esc(it.laboratorio) + ')'
                    + '</button>'
                    + '</div></div>';
            });
            crossMsg.innerHTML = html;
            crossMsg.style.display = '';
        } else {
            crossMsg.innerHTML = '<span style="color:#721c24">Item não encontrado em nenhum setor.</span>';
            crossMsg.style.display = '';
        }
    } else {
        if(crossMsg) crossMsg.style.display = 'none';
    }
}

function vincularItem(idItem, idLabOrigem){
    if(!confirm('Adicionar este item também ao setor "'+_labSelNome+'"? Ele será criado com saldo inicial zero, mantendo o cadastro original intacto.')) return;
    var fd = new FormData();
    fd.append('acao','vincular_item');
    fd.append('idItemOrigem', idItem);
    fd.append('idLabDestino', _idLabSel);
    fetch('estoqueMovimentacoes.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Item vinculado! Redirecionando...','ok');
            setTimeout(()=>location.href='?lab='+_idLabSel+'&item='+res.idNovoItem, 800);
        }).catch(()=>toast('Erro.','err'));
}

function movimentarOutroSetor(idItem, descricao, labOrigem){
    var contexto = 'Esta movimentação será registrada para <strong>"'+esc(descricao)+'"</strong> no setor original: <strong>'+esc(labOrigem)+'</strong>.';
    abrirMovimentacao(idItem, contexto);
    document.getElementById('buscaItem').value = '';
    document.getElementById('crossSetorMsg').style.display = 'none';
    document.querySelectorAll('#listaItens a').forEach(function(card){ card.style.display='block'; });
    // Pré-seleciona "entrada", já que normalmente é o caso de uso (reposição vinda de outro setor)
    var btnEntrada = document.querySelector('.form-tipo-btn[data-tipo="entrada"]');
    if(btnEntrada) selTipo('entrada', btnEntrada);
}

function escJS(s){ return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

/* ── Alertas por setor ── */
var _setorAtivo = null;
var _dtFaltantes = null;

function mostrarSetor(setor, btn){
    if(_setorAtivo === setor){
        _setorAtivo = null;
        document.getElementById('alertaItensSetor').style.display='none';
        document.querySelectorAll('#alertaSetores button').forEach(b=>b.style.outline='none');
        return;
    }
    _setorAtivo = setor;
    document.querySelectorAll('#alertaSetores button').forEach(b=>b.style.outline='none');
    btn.style.outline='2px solid #721c24';
    var itens = _alertasPorSetor[setor] || [];
    var titulo = document.getElementById('alertaSetorTitulo');
    var lista  = document.getElementById('alertaItensLista');
    titulo.textContent = '⚠️ ' + setor + ' — ' + itens.length + ' item(ns) abaixo do mínimo';
    lista.innerHTML = '';
    itens.forEach(function(it){
        lista.innerHTML += '<div style="display:flex;justify-content:space-between;'
            +'padding:5px 0;border-bottom:1px solid #f5c6cb;font-size:.8rem">'
            +'<span style="font-weight:600">'+esc(it.descricao)+'</span>'
            +'<span style="color:#dc3545;font-weight:700">'
            +it.saldoAtual+' / '+it.estoqueMinimo+' '+esc(it.unidadeAbv)+'</span>'
            +'</div>';
    });
    document.getElementById('alertaItensSetor').style.display='';
    document.getElementById('alertaTodos').style.display='none';
    var btnTodos = document.getElementById('btnVerTodos');
    if(btnTodos) btnTodos.innerHTML='<i class="fas fa-list mr-1"></i>Ver todos';
}

function toggleVerTodos(btn){
    var el = document.getElementById('alertaTodos');
    var visivel = el.style.display !== 'none';
    if(visivel){
        el.style.display='none';
        btn.innerHTML='<i class="fas fa-list mr-1"></i>Ver todos';
        return;
    }
    document.getElementById('alertaItensSetor').style.display='none';
    _setorAtivo=null;
    document.querySelectorAll('#alertaSetores button').forEach(b=>b.style.outline='none');
    el.style.display='';
    btn.innerHTML='<i class="fas fa-times mr-1"></i>Fechar';
    if(!_dtFaltantes){
        _dtFaltantes = $('#tabelaFaltantes').DataTable({
            dom: '<"d-flex justify-content-between align-items-center mb-2"Bf>t<"d-flex justify-content-between"ip>',
            buttons:[{
                extend:'excelHtml5',
                text:'<i class="fas fa-file-excel mr-1"></i>Exportar Excel',
                className:'btn btn-success btn-sm',
                title:'Itens Abaixo do Estoque Mínimo',
                filename:'itens_faltantes_'+new Date().toISOString().slice(0,10),
            }],
            language:{ url:'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            order:[[0,'asc'],[1,'asc']],
            pageLength:25,
        });
    }
}

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toast');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(()=>el.style.display='none',4000);
}
</script>
</body>
</html>