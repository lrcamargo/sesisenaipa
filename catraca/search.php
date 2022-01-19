<?php
    include("conexao.php");

    $id = $_POST['id'];
    
    try {
        $query = $conn->prepare("SELECT CAST(validade_data_fim AS DATE) AS dataFim FROM pessoas WHERE nivel_id = $id");
                                
        $query->execute();

        $niveisData = $query->fetch();
        /*foreach ($niveisData as $query) {
            echo "<option value='".$query['id']."'>".$query['descricao']."</option>";
        }*/
        echo $niveisData['dataFim'];
        
    } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
    }
?>