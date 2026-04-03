<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../conexao.php");
session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../index.php');
    exit;
}

$logado    = $_SESSION['user'];
$nivel     = $_SESSION['group'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

$supervisao = in_array($nivelNorm,[
    'sup tecnica','sup pedagogica','gerencia',
    'sup adm','admin','administrator'
]);

$idReserva = intval($_GET['id'] ?? 0);
if(!$idReserva){
    header('location:index.php?msg=erro_param');
    exit;
}

/* ── Busca a reserva ── */
$stmt = $pdo->prepare("
    SELECT r.*, u.nome AS nomesolicitante, u.id AS idSolicitante
    FROM reservas r
    JOIN usuarios u ON u.id = r.solicitante
    WHERE r.idReserva = ?
");
$stmt->execute([$idReserva]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$reserva){
    header('location:index.php?msg=erro_nao_encontrada');
    exit;
}

/* ── Permissão: só supervisão ou próprio solicitante ── */
$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$uLogado  = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $uLogado['id'] ?? 0;

if(!$supervisao && $idLogado != $reserva['idSolicitante']){
    header('location:index.php?msg=erro_permissao');
    exit;
}

/* ── Detecta turno a partir dos horários ── */
$hInicio = $reserva['horarioInicio'];
$hFim    = $reserva['horarioFim'];

if($hFim <= '13:00:00')                              $turnoAtual = 'manha';
elseif($hFim > '13:00:00' && $hFim <= '18:00:00')   $turnoAtual = 'tarde';
else                                                  $turnoAtual = 'noite';

/* ── Detecta se é todo o turno ── */
$horariosTurno = [
    'manha' => ['07:00:00','12:20:00'],
    'tarde' => ['13:00:00','17:30:00'],
    'noite' => ['18:00:00','22:30:00'],
];
$todoTurno = (
    $hInicio === $horariosTurno[$turnoAtual][0] &&
    $hFim    === $horariosTurno[$turnoAtual][1]
);

/* ── Busca laboratórios disponíveis para reserva ── */
$stmtLabs = $pdo->prepare("SELECT idLaboratorio, nome FROM laboratorios WHERE temReserva = 1 ORDER BY nome");
$stmtLabs->execute();
$laboratorios = $stmtLabs->fetchAll(PDO::FETCH_ASSOC);

/* ── Mensagens ── */
$msgs = [
    'ok'        => ['tipo'=>'success','texto'=>'Reserva atualizada com sucesso.'],
    'erro_conf' => ['tipo'=>'danger', 'texto'=>'Conflito de horário com outra reserva.'],
    'erro_db'   => ['tipo'=>'danger', 'texto'=>'Erro ao salvar. Tente novamente.'],
    'erro_vazio'=> ['tipo'=>'danger', 'texto'=>'Preencha os campos obrigatórios.'],
];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Reserva</title>

<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>

<style>
.form-container { max-width:600px; margin:0 auto; }
.form-header h2 { color:#333; font-size:1.5rem; margin-bottom:1.5rem; }
.submit-button {
    width:50%; padding:12px; background:#007bff; color:#fff;
    border:none; border-radius:5px; font-size:1rem;
    font-weight:700; cursor:pointer; transition:background .2s;
}
.submit-button:hover { background:#0056b3; }
</style>
</head>

<body>
<div class="wrapper">

<div class="header" style="z-index:99">
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
<div class="form-container">

    <?php
    if(isset($_GET['msg']) && isset($msgs[$_GET['msg']])){
        $m = $msgs[$_GET['msg']];
        echo "<div class='alert alert-{$m['tipo']} text-center mb-3'>{$m['texto']}</div>";
    }
    ?>

    <div class="form-header">
        <h2>Editar Reserva</h2>
        <small class="text-muted">Reserva #<?php echo $idReserva; ?></small>
    </div>

    <form method="POST" action="edit.php">

        <input type="hidden" name="id"          value="<?php echo $idReserva; ?>">
        <input type="hidden" name="solicitante"  value="<?php echo $reserva['idSolicitante']; ?>">

        <!-- Data -->
        <div class="form-group">
            <label><b>Data</b></label>
            <input type="date" name="dataInp" class="form-control"
                   value="<?php echo $reserva['data']; ?>" required>
        </div>

        <!-- Turno -->
        <div class="form-group">
            <label><b>Turno</b></label><br>
            <?php foreach(['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite'] as $val=>$label){ ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio"
                           name="turno" id="turno_<?php echo $val; ?>"
                           value="<?php echo $val; ?>"
                           <?php echo $turnoAtual === $val ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="turno_<?php echo $val; ?>">
                        <?php echo $label; ?>
                    </label>
                </div>
            <?php } ?>
        </div>

        <!-- Período -->
        <div class="form-group">
            <label><b>Período</b></label><br>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio"
                       name="periodo" value="parcial" id="per_parcial"
                       onchange="horarios()"
                       <?php echo !$todoTurno ? 'checked' : ''; ?>>
                <label class="form-check-label" for="per_parcial">Só um período</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio"
                       name="periodo" value="todo" id="per_todo"
                       onchange="horarios()"
                       <?php echo $todoTurno ? 'checked' : ''; ?>>
                <label class="form-check-label" for="per_todo">Todo o turno</label>
            </div>
        </div>

        <!-- Horários (visíveis só no período parcial) -->
        <div id="camposHorario" style="display:<?php echo !$todoTurno ? 'block' : 'none'; ?>">
            <div class="form-group">
                <label><b>Início</b></label>
                <input type="text" id="horainicio" name="horainicio"
                       class="form-control" placeholder="HH:MM" autocomplete="off"
                       value="<?php echo substr($hInicio,0,5); ?>">
            </div>
            <div class="form-group">
                <label><b>Fim</b></label>
                <input type="text" id="horafim" name="horafim"
                       class="form-control" placeholder="HH:MM" autocomplete="off"
                       value="<?php echo substr($hFim,0,5); ?>">
            </div>
        </div>

        <!-- Turma -->
        <div class="form-group">
            <label><b>Turma</b></label>
            <select name="turma" class="form-control" id="turmaSelect">
                <option value="<?php echo htmlspecialchars($reserva['turma']); ?>">
                    <?php echo htmlspecialchars($reserva['turma']); ?>
                </option>
            </select>
            <small class="text-muted">As turmas são carregadas automaticamente pela data selecionada.</small>
        </div>

        <!-- Laboratório -->
        <div class="form-group">
            <label><b>Laboratório</b></label>
            <select name="laboratorio" class="form-control">
                <?php foreach($laboratorios as $lab){ ?>
                    <option value="<?php echo $lab['idLaboratorio']; ?>"
                        <?php echo $lab['idLaboratorio'] == $reserva['laboratorio'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lab['nome']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- Solicitante (só supervisão vê) -->
        <?php if($supervisao){ ?>
        <div class="form-group">
            <label><b>Solicitante</b></label>
            <select name="solicitante" class="form-control">
                <?php
                $stmtUsers = $pdo->prepare("SELECT id, nome FROM usuarios ORDER BY nome");
                $stmtUsers->execute();
                foreach($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u){
                    $sel = $u['id'] == $reserva['idSolicitante'] ? 'selected' : '';
                    echo "<option value='{$u['id']}' {$sel}>".htmlspecialchars($u['nome'])."</option>";
                }
                ?>
            </select>
        </div>
        <?php } ?>

        <button type="submit" class="submit-button">
            <i class="fas fa-save mr-1"></i>Salvar alterações
        </button>

    </form>
</div>
</div>
</div>

<script src="../js/menu.js"></script>
<script>
function horarios(){
    var parcial = document.getElementById('per_parcial').checked;
    document.getElementById('camposHorario').style.display = parcial ? 'block' : 'none';
}

/* Recarrega turmas quando a data muda */
document.querySelector('input[name=dataInp]').addEventListener('change', function(){
    var data = this.value;
    if(!data) return;
    fetch('buscarTurmasAPI.php?data=' + data)
        .then(function(r){ return r.json(); })
        .then(function(turmas){
            var sel    = document.getElementById('turmaSelect');
            var atual  = sel.options[0] ? sel.options[0].value : '';
            sel.innerHTML = '';
            turmas.forEach(function(t){
                var opt = document.createElement('option');
                opt.value = t; opt.text = t;
                if(t === atual) opt.selected = true;
                sel.appendChild(opt);
            });
        });
});

/* Máscaras de hora — JS puro */
function aplicarMascaraHora(el){
    el.addEventListener('input', function(){
        var pos    = this.selectionStart;
        var digits = this.value.replace(/\D/g,'').substring(0,4);
        var result = '';
        if(digits.length >= 1){ var d0=parseInt(digits[0]); if(d0>2) digits='2'+digits.substring(1); result=digits[0]; }
        if(digits.length >= 2){ var h1=parseInt(digits[0]),h2=parseInt(digits[1]); if(h1===2&&h2>3) digits=digits[0]+'3'+digits.substring(2); result=digits.substring(0,2)+':'; }
        if(digits.length >= 3){ var m1=parseInt(digits[2]); if(m1>5) digits=digits.substring(0,2)+'5'+digits.substring(3); result=digits.substring(0,2)+':'+digits[2]; }
        if(digits.length >= 4){ result=digits.substring(0,2)+':'+digits.substring(2,4); }
        this.value=result;
        var novaPos=pos; if(pos===2&&digits.length>=2) novaPos=3;
        this.setSelectionRange(novaPos,novaPos);
    });
}
aplicarMascaraHora(document.getElementById('horainicio'));
aplicarMascaraHora(document.getElementById('horafim'));
</script>
</body>
</html>