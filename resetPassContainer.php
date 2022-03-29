<!DOCTYPE html>
<?php
$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];
$codnivel = $_SESSION['codNivel'];
$ra = $_SESSION['ra'];
?>
<html>
    <head>
        <link rel="stylesheet" href="../css/telefone.css">
    </head>
    <!--Inicio grid-->
    <h3>Alteração de senha</h3> </br>
    <span style="color: red">*A senha precisa ter ao menos 8 dígitos. Ao final você será redirecionado para realizar um novo login com a nova senha.</span>
    <div class="info-container">
        <form method="POST" action="resetPassword.php">
            Senha atual: <input type="password" name="atual" autocomplete="on"></input><br/>
            Nova senha: <input type="password" name="nova" autocomplete="off"></input><br/>
            Confirme a senha: <input type="password" name="confirma" onChange="onChange()" autocomplete="off"></input><br/>
            <input type="hidden" name="usuario" value=<?php echo $ra; ?>></input>
            <br/>
            <input type="submit" class="btn btn-green" value="Alterar Senha"></input>
        </form> 
    </div>
<!--Fim grid-->
    <script>
        function onChange() {
            const password = document.querySelector('input[name=nova]');
            const confirm = document.querySelector('input[name=confirma]');
            console.log(confirm.value);
            console.log(password.value);
            if (confirm.value === password.value) {
                console.log(confirm.value.length);
                if(confirm.value.length >= 8) {
                    confirm.setCustomValidity('');
                } else {
                    confirm.setCustomValidity('Sua senha precisa ter pelo menos 8 caracteres.');
                }
            } else {
                confirm.setCustomValidity('Senhas não combinam.');
            }
        }
    </script>
</html>