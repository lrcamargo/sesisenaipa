<!DOCTYPE html>
<?php
$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];
$codnivel = $_SESSION['codNivel'];
$ra = $_SESSION['ra'];
?>
<html>

<!--Inicio grid-->
<div class="info-container">
    <form method="POST" action="resetPassword.php">
        Senha atual: <input type="password" name="atual"></input><br/>
        Nova senha: <input type="password" name="nova"></input><br/>
        Confirme a senha: <input type="password" name="confirma"></input><br/>
        <input type="hidden" name="usuario" value=<?php echo $ra; ?>></input>
        <input type="submit" value="Alterar Senha"></input>
    </form> 
</div>
<!--Fim grid-->

</html>