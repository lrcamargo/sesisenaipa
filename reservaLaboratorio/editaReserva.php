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

/* ── Permissão ── */
$stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE nome = ?");
$stmtU->execute([$logado]);
$uLogado  = $stmtU->fetch(PDO::FETCH_ASSOC);
$idLogado = $uLogado['id'] ?? 0;

if(!$supervisao && $idLogado != $reserva['idSolicitante']){
    header('location:index.php?msg=erro_permissao');
    exit;
}

/* ── Detecta turno e modo ── */
$hInicio = $reserva['horarioInicio'];
$hFim    = $reserva['horarioFim'];

if($hFim <= '13:00:00')                              $turnoAtual = 'manha';
elseif($hFim > '13:00:00' && $hFim <= '18:00:00')   $turnoAtual = 'tarde';
else                                                  $turnoAtual = 'noite';

$horariosTurno = [
    'manha' => ['07:00:00','12:20:00'],
    'tarde' => ['13:00:00','17:30:00'],
    'noite' => ['18:00:00','22:30:00'],
];
$todoTurno = (
    $hInicio === $horariosTurno[$turnoAtual][0] &&
    $hFim    === $horariosTurno[$turnoAtual][1]
);

/* ── Busca laboratórios ── */
$stmtLabs = $pdo->prepare("SELECT idLaboratorio, nome FROM laboratorios WHERE temReserva = 1 ORDER BY nome");
$stmtLabs->execute();
$laboratorios = $stmtLabs->fetchAll(PDO::FETCH_ASSOC);

/* ── Busca turmas via API da catraca para a data da reserva ──
 */
$turmasAPI     = [];
$catracaDisp   = false;
$dataReserva   = $reserva['data']; // yyyy-mm-dd

$urlBusca = 'http://172.16.95.253:3002/backapi/Turmas?dataInicio=' . urlencode($dataReserva);
$ch = curl_init($urlBusca);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if(!$curlErr && $httpCode >= 200 && $httpCode < 300 && $resp){
    $decoded = json_decode($resp, true);
    if(is_array($decoded) && count($decoded) > 0){
        // Filtra pelo intervalo de datas (igual ao buscarTurmasAPI.php)
        foreach($decoded as $t){
            if(!isset($t['dataInicio'],$t['dataFim'],$t['nome'])) continue;
            if($dataReserva >= $t['dataInicio'] && $dataReserva <= $t['dataFim']){
                $turmasAPI[] = $t['nome'];
            }
        }
        $catracaDisp = true;
    }
}

/* ── Mensagens ── */
$msgs = [
    'ok'        => ['tipo'=>'success','texto'=>'Reserva atualizada com sucesso.'],
    'erro_conf' => ['tipo'=>'danger', 'texto'=>'Conflito de horário com outra reserva.'],
    'erro_db'   => ['tipo'=>'danger', 'texto'=>'Erro ao salvar. Tente novamente.'],
    'erro_vazio'=> ['tipo'=>'danger', 'texto'=>'Preencha os campos obrigatórios.'],
];

// Turma atual da reserva (string simples — sem htmlspecialchars aqui,
// será escapada diretamente nos atributos HTML abaixo)
$turmaAtual = $reserva['turma'] ?? '';
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

/* Fail-safe turma — igual ao laboratorios.php */
.turma-manual-wrapper { display:none; }
.turma-manual-wrapper .turma-aviso {
    font-size:.82em; color:#856404;
    background:#fff3cd; border:1px solid #ffc107;
    border-radius:4px; padding:5px 10px; margin-bottom:6px;
}
.turma-manual-wrapper input.is-valid   { border-color:#28a745; }
.turma-manual-wrapper input.is-invalid { border-color:#dc3545; }
.turma-codigo-feedback { font-size:.8em; margin-top:3px; }
.turma-codigo-feedback.valido   { color:#28a745; }
.turma-codigo-feedback.invalido { color:#dc3545; }
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

        <input type="hidden" name="id" value="<?php echo $idReserva; ?>">

        <!-- Data -->
        <div class="form-group">
            <label><b>Data</b></label>
            <input type="date" name="dataInp" id="dataInp" class="form-control"
                   value="<?php echo htmlspecialchars($reserva['data']); ?>" required>
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

        <!-- Horários -->
        <div id="camposHorario" style="display:<?php echo !$todoTurno ? 'block' : 'none'; ?>">
            <div class="form-group">
                <label><b>Início</b></label>
                <input type="text" id="horainicio" name="horainicio"
                       class="form-control" placeholder="HH:MM" autocomplete="off"
                       value="<?php echo htmlspecialchars(substr($hInicio,0,5)); ?>">
            </div>
            <div class="form-group">
                <label><b>Fim</b></label>
                <input type="text" id="horafim" name="horafim"
                       class="form-control" placeholder="HH:MM" autocomplete="off"
                       value="<?php echo htmlspecialchars(substr($hFim,0,5)); ?>">
            </div>
        </div>

        <!-- Turma com fail-safe -->
        <div class="form-group">
            <label><b>Turma</b></label>

            <?php if($catracaDisp && count($turmasAPI) > 0){ ?>
            <!--
                Catraca disponível: select com as turmas da data.
                A turma atual da reserva é pré-selecionada se estiver na lista;
                caso contrário aparece como primeira opção.
            -->
            <select id="turmaSelect" name="turma" class="form-control">
                <?php
                $turmaEncontrada = false;
                foreach($turmasAPI as $t){
                    $sel = ($t === $turmaAtual) ? 'selected' : '';
                    if($sel) $turmaEncontrada = true;
                    echo "<option value='".htmlspecialchars($t)."' {$sel}>".htmlspecialchars($t)."</option>";
                }
                // Se a turma atual não está na lista da API, adiciona como opção no topo
                if(!$turmaEncontrada && $turmaAtual !== ''){
                    echo "<option value='".htmlspecialchars($turmaAtual)."' selected>".htmlspecialchars($turmaAtual)." (turma original)</option>";
                }
                ?>
            </select>
            <small class="text-muted">
                Turmas carregadas da API. Ao mudar a data, a lista será recarregada.
            </small>

            <?php } else { ?>
            <!--
                Catraca indisponível ou sem turmas para a data:
                campo de texto com validação de código, igual ao laboratorios.php.
            -->
            <select id="turmaSelect" name="turma" class="form-control"
                    style="display:none"></select>

            <div id="turmaManualWrapper" class="turma-manual-wrapper"
                 style="display:block">
                <div class="turma-aviso">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?php echo $catracaDisp ? 'Nenhuma turma encontrada para esta data.' : 'API de turmas indisponível.'; ?>
                    Digite o código da turma manualmente.
                </div>
                <input type="text"
                       id="turmaManualInput"
                       class="form-control"
                       placeholder="Ex: HT-MET-01-M-25-13310"
                       autocomplete="off"
                       maxlength="50"
                       value="<?php echo htmlspecialchars($turmaAtual); ?>">
                <div id="turmaFeedback" class="turma-codigo-feedback"></div>
            </div>
            <!-- Hidden que o PHP recebe quando no modo manual -->
            <input type="hidden" id="turmaHidden" name="turma"
                   value="<?php echo htmlspecialchars($turmaAtual); ?>">
            <?php } ?>
        </div>

        <!-- Laboratório -->
        <div class="form-group">
            <label><b>Laboratório</b></label>
            <select name="laboratorio" class="form-control">
                <?php foreach($laboratorios as $lab){ ?>
                <option value="<?php echo (int)$lab['idLaboratorio']; ?>"
                    <?php echo $lab['idLaboratorio'] == $reserva['laboratorio'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lab['nome']); ?>
                </option>
                <?php } ?>
            </select>
        </div>

        <!-- Solicitante (só supervisão) -->
        <?php if($supervisao){ ?>
        <div class="form-group">
            <label><b>Solicitante</b></label>
            <select name="solicitante" class="form-control">
                <?php
                $stmtUsers = $pdo->prepare("SELECT id, nome FROM usuarios ORDER BY nome");
                $stmtUsers->execute();
                foreach($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u){
                    $sel = ($u['id'] == $reserva['idSolicitante']) ? 'selected' : '';
                    echo "<option value='{$u['id']}' {$sel}>".htmlspecialchars($u['nome'])."</option>";
                }
                ?>
            </select>
        </div>
        <?php } else { ?>
        <input type="hidden" name="solicitante" value="<?php echo (int)$reserva['idSolicitante']; ?>">
        <?php } ?>

        <button type="submit" class="submit-button"
                onclick="return prepararSubmit()">
            <i class="fas fa-save mr-1"></i>Salvar alterações
        </button>

    </form>
</div>
</div>
</div>

<script src="../js/menu.js"></script>
<script>

/* ── Mostrar/ocultar campos de horário ── */
function horarios(){
    var parcial = document.getElementById('per_parcial').checked;
    document.getElementById('camposHorario').style.display = parcial ? 'block' : 'none';
}

/* ── Recarrega turmas ao mudar a data (só quando catraca estava disponível) ── */
<?php if($catracaDisp){ ?>
document.getElementById('dataInp').addEventListener('change', function(){
    var data = this.value;
    if(!data) return;

    var select = document.getElementById('turmaSelect');
    select.innerHTML = '<option value="">Carregando...</option>';
    select.style.display = 'block';

    fetch('buscarTurmasAPI.php?data=' + data)
        .then(function(r){ return r.text(); })
        .then(function(txt){
            var turmas;
            try { turmas = JSON.parse(txt); } catch(e){ turmas = []; }

            if(!turmas || !Array.isArray(turmas) || turmas.length === 0){
                ativarModoManual(select, document.getElementById('turmaManualWrapper'),
                    'Nenhuma turma encontrada para esta data.');
                return;
            }

            var turmaAtual = '<?php echo addslashes($turmaAtual); ?>';
            select.innerHTML = '';
            turmas.forEach(function(t){
                var opt = document.createElement('option');
                opt.value = t; opt.text = t;
                if(t === turmaAtual) opt.selected = true;
                select.appendChild(opt);
            });
        })
        .catch(function(){
            ativarModoManual(select, document.getElementById('turmaManualWrapper'),
                'API de turmas indisponível.');
        });
});

function ativarModoManual(selectEl, wrapperEl, motivo){
    selectEl.innerHTML = '';
    selectEl.style.display = 'none';
    if(wrapperEl){
        var aviso = wrapperEl.querySelector('.turma-aviso');
        if(aviso) aviso.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>' + motivo + ' Digite o código manualmente.';
        wrapperEl.style.display = 'block';
        var inp = wrapperEl.querySelector('input[type=text]');
        if(inp){ inp.value = ''; inp.classList.remove('is-valid','is-invalid'); }
    }
}
<?php } ?>

/* ── Validação e submit ── */
var TURMA_VALORES_FIXOS = ['Funcionários','Funcionarios'];
var TURMA_REGRAS = [
    { prefixo:/^(?!EM|EF|APP)[A-Z]{2}-/, regex:/^[A-Z]{2}-[A-Z]{2,4}-\d{2}-[A-Z]-\d{2}-\d+$/ },
    { prefixo:/^EM-/, regex:/^EM-\d[A-Z]-[A-Z]-[A-Z]-\d{3,6}-\d{2}$/ },
    { prefixo:/^EF-/, regex:/^EF-\d[A-Z]-[A-Z]-[A-Z]-\d{3,6}-\d{2}$/ },
    { prefixo:/^APP-/, regex:/^APP(-[A-Z0-9]+){1,}$/ },
];

function validarCodigoTurma(codigo){
    var c = codigo.trim().toUpperCase();
    if(!c) return { valido:false, mensagem:'' };
    for(var f=0;f<TURMA_VALORES_FIXOS.length;f++){
        if(c.toLowerCase()===TURMA_VALORES_FIXOS[f].toLowerCase())
            return { valido:true, mensagem:'✔ Valor aceito.' };
    }
    for(var i=0;i<TURMA_REGRAS.length;i++){
        var r=TURMA_REGRAS[i];
        if(r.prefixo.test(c))
            return r.regex.test(c) ? {valido:true,mensagem:'✔ Formato válido.'} : {valido:false,mensagem:'✘ Formato incorreto.'};
    }
    return /^[A-Z0-9]+(-[A-Z0-9]+){1,}$/.test(c)
        ? {valido:true,mensagem:'✔ Código aceito.'}
        : {valido:false,mensagem:'✘ Use letras maiúsculas e números separados por hífen.'};
}

/* Feedback em tempo real no campo manual */
var manualInput = document.getElementById('turmaManualInput');
if(manualInput){
    manualInput.addEventListener('input', function(){
        this.value = this.value.toUpperCase();
        var r   = validarCodigoTurma(this.value);
        var fb  = document.getElementById('turmaFeedback');
        this.classList.remove('is-valid','is-invalid');
        if(fb){ fb.className='turma-codigo-feedback'; fb.textContent=r.mensagem; }
        if(this.value.trim() === '') return;
        this.classList.add(r.valido ? 'is-valid' : 'is-invalid');
        if(fb) fb.classList.add(r.valido ? 'valido' : 'invalido');

        // Atualiza o hidden
        var hidden = document.getElementById('turmaHidden');
        if(hidden) hidden.value = this.value.trim().toUpperCase();
    });
}

function prepararSubmit(){
    var wrapper = document.getElementById('turmaManualWrapper');
    if(wrapper && wrapper.style.display !== 'none'){
        var inp = document.getElementById('turmaManualInput');
        if(!inp || !inp.value.trim()){ alert('Informe o código da turma.'); return false; }
        var r = validarCodigoTurma(inp.value);
        if(!r.valido){ alert('Código de turma inválido.\n' + r.mensagem); return false; }
        var hidden = document.getElementById('turmaHidden');
        if(hidden) hidden.value = inp.value.trim().toUpperCase();
    }
    return true;
}

/* ── Máscaras de hora ── */
function aplicarMascaraHora(el){
    el.addEventListener('input', function(){
        var pos=this.selectionStart;
        var digits=this.value.replace(/\D/g,'').substring(0,4);
        var result='';
        if(digits.length>=1){var d0=parseInt(digits[0]);if(d0>2)digits='2'+digits.substring(1);result=digits[0];}
        if(digits.length>=2){var h1=parseInt(digits[0]),h2=parseInt(digits[1]);if(h1===2&&h2>3)digits=digits[0]+'3'+digits.substring(2);result=digits.substring(0,2)+':';}
        if(digits.length>=3){var m1=parseInt(digits[2]);if(m1>5)digits=digits.substring(0,2)+'5'+digits.substring(3);result=digits.substring(0,2)+':'+digits[2];}
        if(digits.length>=4){result=digits.substring(0,2)+':'+digits.substring(2,4);}
        this.value=result;
        var novaPos=pos;if(pos===2&&digits.length>=2)novaPos=3;
        this.setSelectionRange(novaPos,novaPos);
    });
}
aplicarMascaraHora(document.getElementById('horainicio'));
aplicarMascaraHora(document.getElementById('horafim'));
</script>
</body>
</html>