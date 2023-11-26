<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    header('Content-Type: text/html; charset=utf-8');
    
    include("../conexaosec.php");
    $pasta = $_GET['codT'];
    $nome = $_GET['nome'];
    $turno = $_GET['turno'];
    $data = $_GET['data'];

    $folder = utf8_decode($pasta);
    
        if (file_exists(utf8_encode("snap/2021/".$folder."/".utf8_decode($nome).".jpg"))) {
            echo "Nao";
            try {
                $buscaAluno = $conn->prepare("SELECT id, n_identificador,nome FROM pessoas WHERE nome = '". $nome."' AND n_identificador IS NOT NULL");
                $buscaAluno->execute();
                    
                $buscaAlunos = $buscaAluno->fetchAll();
                foreach ($buscaAlunos as $buscaAlunos) {
                    $original = utf8_encode("snap/2021/".$folder."/".utf8_decode($nome).".jpg");
                    rename($original, "/home/suporte/fotos/".$buscaAlunos['id']."-1.jpg");               
                }
            } catch (PDOException $e) {
                die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
            } 
        } 
        
  header('Location: genCardNow.php?codT='.$pasta.'&nome='.$nome.'&turno='.$turno.'&data='.$data);
?>
