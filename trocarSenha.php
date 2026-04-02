<!DOCTYPE html>
<?php
session_start();
require_once "conexao.php";

if(!isset($_SESSION['id'])){
    header("Location: index.php");
    exit;
}

$logado = $_SESSION['user'] ?? "";

// Mensagem de erro vinda do salvarNovaSenha.php
$erroMsg = '';
if(isset($_GET['erro'])){
    if($_GET['erro'] == 'divergentes') $erroMsg = 'As senhas não coincidem. Tente novamente.';
    if($_GET['erro'] == 'curta')       $erroMsg = 'A senha deve ter pelo menos 8 caracteres.';
    if($_GET['erro'] == 'db')          $erroMsg = 'Erro ao salvar. Tente novamente.';
}
?>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trocar Senha</title>

<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<style>
/* Centraliza o card na área de conteúdo */
.senha-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    /* Sem sidebar: ocupa toda a largura e centraliza verticalmente */
    min-height: calc(100vh - 70px);
    width: 100%;
    padding: 2rem 1rem;
    box-sizing: border-box;
}

.senha-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
}

/* Ícone decorativo no topo */
.senha-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #e8f0fe;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    color: #1a73e8;
    font-size: 20px;
}

.senha-card h2 {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 600;
    color: #202124;
    margin: 0 0 .4rem;
}

.senha-card .subtitulo {
    text-align: center;
    font-size: .875rem;
    color: #5f6368;
    margin: 0 0 1.75rem;
}

/* Campos */
.campo-grupo {
    margin-bottom: 1.1rem;
}

.campo-grupo label {
    display: block;
    font-size: .8125rem;
    font-weight: 600;
    color: #5f6368;
    margin-bottom: .35rem;
}

.campo-senha {
    position: relative;
}

.campo-senha input {
    width: 100%;
    padding: 10px 40px 10px 12px;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: .9375rem;
    box-sizing: border-box;
    transition: border-color .2s ease;
    outline: none;
}

.campo-senha input:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 3px rgba(26,115,232,.15);
}

.btn-olho {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #5f6368;
    padding: 0;
    font-size: 15px;
    line-height: 1;
}
.btn-olho:hover { color: #202124; }

/* Barra de força */
.forca-wrapper {
    margin-top: .4rem;
    display: none;
}
.forca-barras {
    display: flex;
    gap: 4px;
    margin-bottom: 3px;
}
.forca-barra {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: #e0e0e0;
    transition: background .25s ease;
}
.forca-label {
    font-size: .75rem;
    color: #5f6368;
}

/* Erro */
.erro-inline {
    font-size: .8125rem;
    color: #d93025;
    margin: .3rem 0 0;
    display: none;
}

/* Botão */
.btn-salvar {
    width: 100%;
    padding: 11px;
    background: #1a73e8;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: .5rem;
    transition: background .2s ease;
}
.btn-salvar:hover  { background: #1558b0; }
.btn-salvar:active { background: #0f429b; }

/* Alerta de erro vindo do PHP */
.alerta-erro {
    background: #fce8e6;
    border: 1px solid #f5c6c3;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: .875rem;
    color: #c5221f;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
</head>

<body>
<div class="wrapper">

    <!-- Cabeçalho — sem sidebar: usuário ainda não está logado normalmente -->
    <div class="header">
        <div class="header-menu">
            <div class="title"><img src="img/logo_white.svg"></div>
            <ul>
                <li><a href="#" class="user"><?php echo htmlspecialchars($logado); ?></a></li>
                <li><a href="sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
            </ul>
        </div>
    </div>

    <!-- Conteúdo ocupa largura total pois não há sidebar -->
    <div class="main-container" style="margin-left:0;width:100%">
    <div class="senha-wrapper">
    <div class="senha-card">

        <!-- Ícone + título -->
        <div class="senha-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h2>Crie sua nova senha</h2>
        <p class="subtitulo">Escolha uma senha segura para continuar.</p>

        <!-- Erro vindo do servidor -->
        <?php if($erroMsg){ ?>
        <div class="alerta-erro">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $erroMsg; ?>
        </div>
        <?php } ?>

        <form action="salvarNovaSenha.php" method="POST" id="formSenha" novalidate>

            <!-- Nova senha -->
            <div class="campo-grupo">
                <label for="senha1">Nova senha</label>
                <div class="campo-senha">
                    <input type="password" id="senha1" name="senha1"
                           placeholder="Digite sua nova senha"
                           autocomplete="new-password">
                    <button type="button" class="btn-olho" onclick="toggleVis('senha1', this)"
                            title="Mostrar/ocultar senha">
                        <i class="fas fa-eye" id="icone-senha1"></i>
                    </button>
                </div>
                <!-- Barra de força da senha -->
                <div class="forca-wrapper" id="forcaWrapper">
                    <div class="forca-barras">
                        <div class="forca-barra" id="fb1"></div>
                        <div class="forca-barra" id="fb2"></div>
                        <div class="forca-barra" id="fb3"></div>
                        <div class="forca-barra" id="fb4"></div>
                    </div>
                    <span class="forca-label" id="forcaLabel"></span>
                </div>
                <div class="erro-inline" id="erroComprimento">
                    A senha deve ter pelo menos 8 caracteres.
                </div>
            </div>

            <!-- Confirmar senha -->
            <div class="campo-grupo">
                <label for="senha2">Confirme a senha</label>
                <div class="campo-senha">
                    <input type="password" id="senha2" name="senha2"
                           placeholder="Repita a nova senha"
                           autocomplete="new-password">
                    <button type="button" class="btn-olho" onclick="toggleVis('senha2', this)"
                            title="Mostrar/ocultar senha">
                        <i class="fas fa-eye" id="icone-senha2"></i>
                    </button>
                </div>
                <div class="erro-inline" id="erroDivergente">
                    As senhas não coincidem.
                </div>
            </div>

            <button type="submit" class="btn-salvar">
                <i class="fas fa-check mr-2"></i>Salvar senha
            </button>

        </form>

    </div>
    </div>
    </div>

</div>

<script>
/* Mostrar / ocultar senha */
function toggleVis(inputId, btn){
    var inp   = document.getElementById(inputId);
    var icone = btn.querySelector('i');
    if(inp.type === 'password'){
        inp.type = 'text';
        icone.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icone.className = 'fas fa-eye';
    }
}

/* Indicador de força */
var cores  = ['#d93025', '#f29900', '#1e8e3e', '#137333'];
var labels = ['Muito fraca', 'Fraca', 'Boa', 'Forte'];

document.getElementById('senha1').addEventListener('input', function(){
    var v      = this.value;
    var wrap   = document.getElementById('forcaWrapper');
    wrap.style.display = v.length ? 'block' : 'none';

    var score = 0;
    if(v.length >= 8)           score++;
    if(/[A-Z]/.test(v))         score++;
    if(/[0-9]/.test(v))         score++;
    if(/[^A-Za-z0-9]/.test(v)) score++;

    for(var i = 1; i <= 4; i++){
        var b = document.getElementById('fb' + i);
        b.style.background = (i <= score && score > 0) ? cores[score - 1] : '#e0e0e0';
    }
    document.getElementById('forcaLabel').textContent = score > 0 ? labels[score - 1] : '';

    // Valida comprimento mínimo em tempo real
    var erroComp = document.getElementById('erroComprimento');
    erroComp.style.display = (v.length > 0 && v.length < 8) ? 'block' : 'none';
});

/* Validação antes do submit */
document.getElementById('formSenha').addEventListener('submit', function(e){
    var s1 = document.getElementById('senha1').value;
    var s2 = document.getElementById('senha2').value;
    var ok = true;

    var erroComp = document.getElementById('erroComprimento');
    var erroDiverg = document.getElementById('erroDivergente');

    erroComp.style.display   = 'none';
    erroDiverg.style.display = 'none';

    if(s1.length < 8){
        erroComp.style.display = 'block';
        ok = false;
    }
    if(s1 !== s2){
        erroDiverg.style.display = 'block';
        ok = false;
    }
    if(!ok) e.preventDefault();
});
</script>

</body>
</html>