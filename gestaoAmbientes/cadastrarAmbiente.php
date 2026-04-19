<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm, ['admin','administrator','sup tecnica','gerencia'])){
    header('location:index.php?msg=erro_permissao'); exit;
}
$erros = [
    1 => "Preencha todos os campos obrigatórios.",
    2 => "Erro ao salvar. Tente novamente.",
];
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastrar Ambiente</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>
.form-container { max-width:600px; margin:0 auto; }
.form-header h2 { color:#333; font-size:1.5rem; margin-bottom:1.5rem; }
.toggle-tags { display:flex; gap:10px; }
.toggle-tags input[type="radio"] { display:none; }
.toggle-tag {
    cursor:pointer; padding:8px 18px; border-radius:20px;
    font-size:14px; font-weight:600; transition:all .25s ease;
    border:2px solid transparent;
}
.tag-sim { background:#cce5ff; color:#004085; }
.tag-nao { background:#e2e3e5; color:#383d41; }
.toggle-tags input[type="radio"]:checked + .tag-sim { border-color:#004085; transform:scale(1.05); box-shadow:0 0 5px rgba(0,0,0,.15); }
.toggle-tags input[type="radio"]:checked + .tag-nao { border-color:#383d41; transform:scale(1.05); box-shadow:0 0 5px rgba(0,0,0,.15); }
.submit-button {
    width:50%; padding:12px; background:#007bff; color:#fff;
    border:none; border-radius:5px; font-size:1rem;
    font-weight:700; cursor:pointer; transition:background .2s;
}
.submit-button:hover { background:#0056b3; }
.funcoes-hint { font-size:.82rem; color:#666; margin-top:6px; }
/* Campo MAC condicional */
.mac-field {
    margin-top:10px; padding:10px 14px;
    background:#e8f4fd; border:1px solid #90caf9; border-radius:6px;
    display:none;
}
.mac-field label { font-size:.83rem; font-weight:600; color:#0d47a1; margin-bottom:4px; }
.mac-field input { font-family:monospace; text-transform:uppercase; letter-spacing:.05em; }
.mac-field small { color:#555; font-size:.75rem; }
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
<div class="form-container">

    <?php
    if(isset($_GET['erro'])){
        $cod = intval($_GET['erro']);
        if(isset($erros[$cod])) echo "<div class='alert alert-danger text-center mb-3'>{$erros[$cod]}</div>";
    }
    ?>

    <div class="form-header"><h2>Cadastrar Ambiente</h2></div>

    <form action="salvarAmbiente.php" method="POST">

        <div class="form-group">
            <label><b>Nome do Ambiente</b></label>
            <input type="text" name="nome" class="form-control" placeholder="Ex: 201A" required>
        </div>
        <div class="form-group">
            <label><b>Localização</b></label>
            <input type="text" name="localizacao" class="form-control" placeholder="Ex: Bloco A, 2º andar">
        </div>
        <div class="form-group">
            <label><b>Capacidade (pessoas)</b></label>
            <input type="number" name="capacidade" class="form-control" placeholder="Ex: 30" min="1" max="999">
        </div>
        <div class="form-group">
            <label><b>Descrição</b></label>
            <textarea name="descricao" class="form-control" rows="3"
                      placeholder="Identificação da sala. Ex.: Eletrohidropneumática"></textarea>
        </div>

        <!-- Funções do ambiente -->
        <div class="form-group">
            <label><b>Funções do Ambiente</b></label>
            <p class="funcoes-hint">Um ambiente pode ter mais de uma função.</p>
            <div class="d-flex flex-column" style="gap:10px">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="temReserva" name="temReserva" value="1">
                    <label class="custom-control-label" for="temReserva">
                        <i class="fas fa-calendar-check mr-1 text-success"></i>
                        <strong>Disponível para reserva</strong>
                        <small class="text-muted d-block">Aparece como opção no sistema de reservas</small>
                    </label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="temEstoque" name="temEstoque" value="1">
                    <label class="custom-control-label" for="temEstoque">
                        <i class="fas fa-boxes mr-1 text-warning"></i>
                        <strong>Tem estoque</strong>
                        <small class="text-muted d-block">Aparece no sistema de controle de estoque</small>
                    </label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="temSala" name="temSala" value="1">
                    <label class="custom-control-label" for="temSala">
                        <i class="fas fa-chalkboard mr-1" style="color:#673ab7"></i>
                        <strong>Sala de aula</strong>
                        <small class="text-muted d-block">Identificado como sala de aula no sistema</small>
                    </label>
                </div>
            </div>
        </div>

        <!-- Porta IoT -->
        <div class="form-group">
            <label><b>Tem Porta IoT?</b></label><br>
            <div class="toggle-tags">
                <input type="radio" id="porta_sim" name="temPorta" value="1"
                       onchange="toggleMac('macPortaField', true)" checked required>
                <label for="porta_sim" class="toggle-tag tag-sim">
                    <i class="fas fa-door-open mr-1"></i>Sim
                </label>
                <input type="radio" id="porta_nao" name="temPorta" value="0"
                       onchange="toggleMac('macPortaField', false)" required>
                <label for="porta_nao" class="toggle-tag tag-nao">
                    <i class="fas fa-times mr-1"></i>Não
                </label>
            </div>
            <div class="mac-field" id="macPortaField">
                <label><i class="fas fa-wifi mr-1"></i>MAC Address da porta</label>
                <input type="text" name="macPorta" class="form-control form-control-sm"
                       placeholder="AA:BB:CC:DD:EE:FF" maxlength="17"
                       oninput="formatarMac(this)" autocomplete="off">
                <small>Formato: XX:XX:XX:XX:XX:XX</small>
            </div>
        </div>

        <!-- Ar Condicionado IoT -->
        <div class="form-group">
            <label><b>Tem Ar Condicionado IoT?</b></label><br>
            <div class="toggle-tags">
                <input type="radio" id="ar_sim" name="temArCondicionado" value="1"
                       onchange="toggleMac('macArField', true)" required>
                <label for="ar_sim" class="toggle-tag tag-sim">
                    <i class="fas fa-snowflake mr-1"></i>Sim
                </label>
                <input type="radio" id="ar_nao" name="temArCondicionado" value="0"
                       onchange="toggleMac('macArField', false)" checked required>
                <label for="ar_nao" class="toggle-tag tag-nao">
                    <i class="fas fa-times mr-1"></i>Não
                </label>
            </div>
            <div class="mac-field" id="macArField">
                <label><i class="fas fa-wifi mr-1"></i>MAC Address do ar condicionado</label>
                <input type="text" name="macArCondicionado" class="form-control form-control-sm"
                       placeholder="AA:BB:CC:DD:EE:FF" maxlength="17"
                       oninput="formatarMac(this)" autocomplete="off">
                <small>Formato: XX:XX:XX:XX:XX:XX</small>
            </div>
        </div>

        <button type="submit" class="submit-button">
            <i class="fas fa-save mr-1"></i>Cadastrar
        </button>

    </form>
</div>
</div>
</div>
<script src="../js/menu.js"></script>
<script>
function toggleMac(fieldId, mostrar) {
    var el = document.getElementById(fieldId);
    if (!el) return;
    el.style.display = mostrar ? 'block' : 'none';
    if (!mostrar) {
        var inp = el.querySelector('input');
        if (inp) inp.value = '';
    }
}

function formatarMac(el) {
    var v = el.value.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
    var fmt = '';
    for (var i = 0; i < v.length && i < 12; i++) {
        if (i > 0 && i % 2 === 0) fmt += ':';
        fmt += v[i];
    }
    el.value = fmt;
}

document.addEventListener('DOMContentLoaded', function() {
    // Estado inicial: porta começa Sim → mostra MAC da porta
    toggleMac('macPortaField', document.getElementById('porta_sim').checked);
    toggleMac('macArField',    document.getElementById('ar_sim').checked);
});
</script>
</body>
</html>