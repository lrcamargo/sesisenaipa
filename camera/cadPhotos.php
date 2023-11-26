<?php
  
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  
  include("../conexaosec.php");

  $file = fopen("/home/suporte/fotos/AlunosPousoAlegre.txt", "r");
  $file2 = "/home/suporte/fotos/AlunosPousoAlegreControle.txt";
  
  if($file) {
    while (($line = fgets($file)) !== false) {
      $registro = substr($line, 0, 10);
      $nome = explode('  ', substr($line, 10, 99));
      $folder = explode(' ', substr($line, 124,25));
                
      if (file_exists(utf8_encode("snap/2021/".$folder[0]."/".$nome[0].".jpg"))) {
        try {
          $buscaAluno = $conn->prepare("SELECT id, n_identificador FROM pessoas WHERE n_identificador = ". $registro);
                                            
          $buscaAluno->execute();
                    
          $buscaAlunos = $buscaAluno->fetchAll();
          foreach ($buscaAlunos as $buscaAlunos) {
            $original = utf8_encode("snap/2021/".$folder[0]."/".$nome[0].".jpg");
            rename($original, "/home/suporte/fotos/".$buscaAlunos['id']."-1.jpg");
          }
                    
        } catch (PDOException $e) {
          die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
        }
      } else {
        $naoEncontrados .= $line;
      }
    } 
    file_put_contents($file2, $naoEncontrados . PHP_EOL, FILE_APPEND);
    fclose($file);
  } else {
      echo "Ooops... Erro ao abrir arquivo thi.";
  }

?>
