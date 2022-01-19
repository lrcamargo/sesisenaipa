<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    require("../conexaosec.php");    

    $countSesi = 0;
    $dataTotal = [];
    $sexto = 0;
    $setimo = 0;
    $oitavo = 0;
    $primeiro = 0;
    $segundo = 0;
    $terceiro = 0;

    try {
        $buscaSexto = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EF-6%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaSexto->execute();

        $buscaS = $buscaSexto -> fetchAll();
        foreach($buscaS as $buscaS) {
            $sexto = $sexto+1;
        }

        $buscaSetimo = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EF-7%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaSetimo->execute();

        $buscaSt = $buscaSetimo -> fetchAll();
        foreach($buscaSt as $buscaSt) {
            $setimo = $setimo+1;
        }
        
        $buscaOitavo = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EF-8%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaOitavo->execute();

        $buscaO = $buscaOitavo -> fetchAll();
        foreach($buscaO as $buscaO) {
            $oitavo = $oitavo+1;
        }
        
        $buscaPrimeiro = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EM-1%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaPrimeiro->execute();

        $buscaP = $buscaPrimeiro -> fetchAll();
        foreach($buscaP as $buscaP) {
            $primeiro = $primeiro+1;
        }
        
        $buscaSegundo = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EM-2%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaSegundo->execute();

        $buscaSeg = $buscaSegundo -> fetchAll();
        foreach($buscaSeg as $buscaSeg) {
            $segundo = $segundo+1;
        }
        
        $buscaTerceiro = $conn->prepare("SELECT * from dbo.pessoas AS p INNER JOIN dbo.niveis AS n ON p.nivel_id = n.id
        WHERE n.descricao LIKE 'EM-3%' AND validade_data_fim > GETDATE() AND estado = 0");
        
        $buscaTerceiro->execute();

        $buscaT = $buscaTerceiro -> fetchAll();
        foreach($buscaT as $buscaT) {
            $terceiro = $terceiro+1;
        }

        array_push($dataTotal, $sexto, $setimo, $oitavo, $primeiro, $segundo, $terceiro);

        echo json_encode($dataTotal);

        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados :" . $e->getMessage());
        }
        
?>