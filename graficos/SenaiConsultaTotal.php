<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    require("../conexaosec.php");    

    $countSenai = 0;
    $dataTotal = [];
    $aprendizagem = 0;
    $qualificacao = 0;
    $tecnico = 0;
    $aperfeicoamento = 0;

    try {
        $buscaSenai = $conn->prepare("SELECT * FROM pessoas INNER JOIN niveis ON pessoas.nivel_id = niveis.id
        WHERE SUBSTRING(niveis.descricao, 1, 1) != 'E' AND estado = 0 AND nivel_id != 1 AND nivel_id != 22 
        AND nivel_id != 18 AND nivel_id != 44 AND nivel_id != 129 AND validade_data_fim > GETDATE()");
                                
        $buscaSenai->execute();

        $buscaAlunosSenai = $buscaSenai->fetchAll();
        foreach($buscaAlunosSenai as $buscaAlunosSenai) {
            if(substr($buscaAlunosSenai["descricao"],0,2) == "AI") {
                $aprendizagem = $aprendizagem +1;
            } else if(substr($buscaAlunosSenai["descricao"],0,2) == "AP") {
                $aperfeicoamento = $aperfeicoamento +1;
            } else if(substr($buscaAlunosSenai["descricao"],0,1) == "T") {
                $tecnico = $tecnico +1;
            } else if(substr($buscaAlunosSenai["descricao"],0,1) == "Q") {
                $aperfeicoamento = $aperfeicoamento+1;
            }
        }

        $doisAi = $conn->prepare("SELECT * FROM dbo.pessoas AS pessoas INNER JOIN pessoas_niveis AS pniveis ON pessoas.id = pniveis.pessoa_id
        INNER JOIN niveis AS niveis on pniveis.nivel_id = niveis.id WHERE estado = 0 AND pessoas.nivel_id != 1 AND pessoas.nivel_id != 22 
        AND pessoas.nivel_id != 18 AND pessoas.nivel_id != 44 AND pessoas.nivel_id != 129 AND pniveis.nivel_id != 1 AND pniveis.nivel_id != 22
        AND pniveis.nivel_id != 18 AND pniveis.nivel_id != 44 AND pniveis.nivel_id != 129 AND validade_data_fim >= GETDATE() AND SUBSTRING(niveis.descricao, 1, 2) = 'AI'");

        $doisAi -> execute();
        $doisAiTotal = $doisAi->fetchAll();
        foreach($doisAiTotal as $doisAiTotal) {
            $aprendizagem = $aprendizagem +1;
        }

        $doisAp = $conn->prepare("SELECT * FROM dbo.pessoas AS pessoas INNER JOIN pessoas_niveis AS pniveis ON pessoas.id = pniveis.pessoa_id
        INNER JOIN niveis AS niveis on pniveis.nivel_id = niveis.id WHERE estado = 0 AND pessoas.nivel_id != 1 AND pessoas.nivel_id != 22 
        AND pessoas.nivel_id != 18 AND pessoas.nivel_id != 44 AND pessoas.nivel_id != 129 AND pniveis.nivel_id != 1 AND pniveis.nivel_id != 22
        AND pniveis.nivel_id != 18 AND pniveis.nivel_id != 44 AND pniveis.nivel_id != 129 AND validade_data_fim >= GETDATE() AND SUBSTRING(niveis.descricao, 1, 2) = 'AP'");

        $doisAp -> execute();
        $doisApTotal = $doisAp->fetchAll();
        foreach($doisApTotal as $doisApTotal) {
            $aperfeicoamento = $aperfeicoamento +1;
        }

        $doisT = $conn->prepare("SELECT * FROM dbo.pessoas AS pessoas INNER JOIN pessoas_niveis AS pniveis ON pessoas.id = pniveis.pessoa_id
        INNER JOIN niveis AS niveis on pniveis.nivel_id = niveis.id WHERE estado = 0 AND pessoas.nivel_id != 1 AND pessoas.nivel_id != 22 
        AND pessoas.nivel_id != 18 AND pessoas.nivel_id != 44 AND pessoas.nivel_id != 129 AND pniveis.nivel_id != 1 AND pniveis.nivel_id != 22
        AND pniveis.nivel_id != 18 AND pniveis.nivel_id != 44 AND pniveis.nivel_id != 129 AND validade_data_fim >= GETDATE() AND SUBSTRING(niveis.descricao, 1, 1) = 'T'");

        $doisT -> execute();
        $doisTTotal = $doisT->fetchAll();
        foreach($doisTTotal as $doisTTotal) {
            $tecnico = $tecnico +1;
        }

        $doisQ = $conn->prepare("SELECT * FROM dbo.pessoas AS pessoas INNER JOIN pessoas_niveis AS pniveis ON pessoas.id = pniveis.pessoa_id
        INNER JOIN niveis AS niveis on pniveis.nivel_id = niveis.id WHERE estado = 0 AND pessoas.nivel_id != 1 AND pessoas.nivel_id != 22 
        AND pessoas.nivel_id != 18 AND pessoas.nivel_id != 44 AND pessoas.nivel_id != 129 AND pniveis.nivel_id != 1 AND pniveis.nivel_id != 22
        AND pniveis.nivel_id != 18 AND pniveis.nivel_id != 44 AND pniveis.nivel_id != 129 AND validade_data_fim >= GETDATE() AND SUBSTRING(niveis.descricao, 1, 1) = 'Q'");

        $doisQ -> execute();
        $doisQTotal = $doisQ->fetchAll();
        foreach($doisQTotal as $doisQTotal) {
            $qualificacao = $qualificacao +1;
        }
        
        array_push($dataTotal, $aprendizagem, $aperfeicoamento, $qualificacao, $tecnico);

        echo json_encode($dataTotal);

    } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }


?>