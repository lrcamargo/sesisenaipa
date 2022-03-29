<?php

include("conexaoatv.php");
$idAtividade = $_POST['idAtividade'];

if(isset($_POST['submit'])){

    if(!empty($_POST['ra'])) {

        foreach($_POST['ra'] as $value){
            try {
                $cadStatus = $conn->prepare("INSERT INTO status (idAtividade, idAlunos) VALUES ('$idAtividade','$value')");
                $cadStatus->execute();
                
                header("Location:professor.php");
            } catch (PDOException $e){
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
            }
        }

    }

}

?>