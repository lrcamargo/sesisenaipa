<!DOCTYPE html>
<?php
$logado = $_SESSION['user'];
$nivel = $_SESSION['group'];
$codnivel = $_SESSION['codNivel'];
$ra = $_SESSION['ra'];
?>
<html>
<!--Inicio grid-->
    <h3>Próximas atividades a entregar:</h3>
    <br/>
    <ul>
    <?php
        include("./atividades/conexaoatv.php");

        try{
            $buscaAtvRecente = $conn->prepare("SELECT TOP 6 * FROM dbo.atividades WHERE turma = $codnivel and dataEntrega >= CAST(GETDATE() as DATE) order by dataEntrega ");
            $buscaAtvRecente->execute();

            $result=$buscaAtvRecente->fetchAll();
            foreach($result as $result) {
                echo "<li><i class='fas fa-angle-right'></i>" . $result['nome'] . " - " . date("d-m-Y",strtotime($result['dataEntrega'])) . " - ". $result['disciplina'] . "</li>";
            }
        } catch(PDOException $e) {
            die("Erro ao conectar no banco de dados.". $e->getMessage());
        }
    ?>
    </ul>

<!--Fim grid-->

</html>