<?php
    class report {
        public function buscaLinks(docente) {
            include("../linksAulas/conexaoTeste.php");

            try {
                $busca = $conn->prepare("SELECT link FROM linksAulas WHERE docente = '$docente'");
        
                $busca->execute();

                $resultados = array();

                $links = $busca->fetchAll();
                foreach($links as $links) {
                    $resultados[] = $links;
                }

            } catch(PDOException $e) {
                throw new Exception($e->getMessage());
            }
            if(!$resultados) {
                throw new Exception("Nada encontrado");
            }

           return $resultados;
        }
    }
?>