<?php

include("conexaoteste.php");
$idAtividade = $_POST['idAtividade'];

if(isset($_POST['submit'])){

    if(!empty($_POST['ra'])) {

        foreach($_POST['ra'] as $value){
            try {
                $cadStatus = $conn->prepare("INSERT INTO status (idAtividade, idAlunos) VALUES ('$idAtividade','$value')");
                $cadStatus->execute();
                
            } catch (PDOException $e){
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
            }
        }

    }

}

?>