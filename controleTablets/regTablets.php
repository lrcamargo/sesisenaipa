<?php
    include("conexaoteste.php");

    $dados = $_POST['data'];
    $salva = explode(",",$dados);

    foreach($salva as $i =>$key) {
        $editado = str_replace( array( '[', ']', '"','\''), '', $salva[$i]);
        try {
            if($i % 2 == 0) {
                $cadAtv = $conn->prepare("INSERT INTO tablets (aluno) VALUES ('$editado')");
                $cadAtv->execute();
            } else {
                $buscaId = $conn->prepare("SELECT TOP 1 * FROM tablets ORDER BY id DESC");
                $buscaId->execute();
                $buscaReg = $buscaId->fetchAll();
                foreach ($buscaReg as $buscaReg) {
                    $lastId=$buscaReg['id'];
                    $cadAtv = $conn->prepare("UPDATE tablets SET tablet = '$editado' WHERE id=$lastId");
                    $cadAtv->execute();
                }
                
            }
        } catch (PDOException $e){
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
    }
    echo json_encode(array('success' => "Ok"));

    
?>
