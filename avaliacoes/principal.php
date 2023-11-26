<?php
include("conexao.php");
require '../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["xls_file"]) && $_FILES["xls_file"]["error"] == UPLOAD_ERR_OK) {
    $xlsPath = $_FILES["xls_file"]["tmp_name"];

    $selectedDisciplina = $_POST["disciplina"];
    $selectedDocente = $_POST["docente"];
    $selectedAvaliacao = $_POST["avaliacao"];
    $selectedEtapa = $_POST["etapa"];
    $selectedTurma = $_POST["turma"];

    //registra informações da prova
    try {
        $addReg = $conn->prepare("INSERT INTO notas (idDisc, idDoc, avaliacao, etapa, turma) VALUES ($selectedDisciplina, $selectedDocente, $selectedAvaliacao, $selectedEtapa, $selectedTurma)");
        $addReg->execute();
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    //busca registro da prova
    try {
        $buscaNota = $conn->prepare("SELECT TOP 1 idNota FROM notas ORDER BY idNota DESC");
        $buscaNota->execute();

        $regNotas = $buscaNota->fetchAll();
                    
        foreach($regNotas as $regNotas) {
            $idNota = $regNotas['idNota'];
        }
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    //lê arquivo
    try {
        $spreadsheet = IOFactory::load($xlsPath);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $columnNames = [];
        $desiredColumns = [];

        // Ler os nomes das colunas da primeira linha
        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $columnName = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            $columnNames[$col] = $columnName;
        }

        // Escolher as colunas desejadas pelo nome
        foreach ($columnNames as $col => $columnName) {
            if ($columnName === "RA" || $columnName === "Grade" || $columnName === "Percent Score") {
                $desiredColumns[] = $col;
            }
        }
        /*Avaliação da rede - nomes das colunas
        foreach ($columnNames as $col => $columnName) {
            if ($columnName === "RA" || $columnName === "Resultados Grade" || $columnName === "Resultados Percent Score") {
                $desiredColumns[] = $col;
            }
        }*/

        echo "<h3>Dados do arquivo (colunas selecionadas):</h3>";
        for ($row = 2; $row <= $highestRow; ++$row) {
            foreach ($desiredColumns as $col) {
                $colValue = array();
                $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                echo "Célula ($col, $row): $cellValue<br>";
                $colValues[] = $cellValue;
            }
        }

        // Excluir o arquivo XLS temporário após a leitura
        unlink($xlsPath);
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        echo "Erro ao carregar o arquivo: " . $e->getMessage();
    }
    //registra notas
    try {
        $tamanho = count($colValues);
        for ($i = 0; $i < $tamanho; $i += 3) {
            $addResult = $conn->prepare("INSERT INTO resultados (idNota, ra, resultGrade, resultPercent) VALUES (?, ?, ?, ?)");
            $addResult->bindParam(1, $idNota);
            $addResult->bindParam(2, $colValues[$i]);
            $addResult->bindParam(3, $colValues[$i+1]);
            $addResult->bindParam(4, $colValues[$i+2]);
            $addResult->execute();

            /*$buscaResult = $conn->prepare("SELECT TOP 1 idReg FROM resultados ORDER BY idReg DESC");
            $buscaResult->execute();

            $result = $buscaResult->fetchAll();
                        
            foreach($result as $result) {
                $idResult = $result['idReg'];
            }

            $updateNota = $conn->prepare("UPDATE notas SET idResult = $idResult WHERE idNota = $idNota");
            $updateNota->execute();*/
        }
    } catch(PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload e Leitura de Arquivo XLS</title>
</head>
<body>

<h2>Upload e Leitura de Arquivo XLS</h2>

<form action="" method="post" enctype="multipart/form-data">
    Escolha uma Disciplina:
    <select name="disciplina">
        <option value='Select'>Selecione...</option>
        <?php
            
            try {
                $buscaDisc = $conn->prepare("SELECT * FROM disciplinas");
                $buscaDisc->execute();

                $disciplina = $buscaDisc->fetchAll();
                            
                foreach($disciplina as $disciplina) {
                    echo "<option value='".$disciplina['idDisc']."'>".$disciplina['discNome']."</option>";
                }
            } catch(PDOException $e) {
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
            }
        ?>
    </select>
    <br/>
    Escolha um Docente:
    <select name="docente">
        <option value='Select'>Selecione...</option>
        <?php
            
            try {
                $buscaDoc = $conn->prepare("SELECT * FROM docente");
                $buscaDoc->execute();

                $docente = $buscaDoc->fetchAll();
                            
                foreach($docente as $docente) {
                    echo "<option value='".$docente['idDocente']."'>".$docente['docenteNome']."</option>";
                }
            } catch(PDOException $e) {
                die("Erro ao conectar ao banco de dados :" . $e->getMessage());
            }
        ?>
    </select>
    <br/>
    Escolha uma Etapa Letiva:
    <select name="etapa">
        <option value='Select'>Selecione...</option>
        <option value='1'>1ª Etapa</option>
        <option value='2'>2ª Etapa</option>
        <option value='3'>3ª Etapa</option>
    </select>
    <br/>
    Escolha uma Avaliação:
    <select name="avaliacao">
        <option value='Select'>Selecione...</option>
        <option value='1'>Avaliação 1</option>
        <option value='2'>Avaliação 2</option>
        <option value='3'>Recuperação</option>
        <option value='4'>Avaliação da Rede - Caderno 01</option>
        <option value='5'>Avaliação da Rede - Caderno 02</option>
    </select>
    <br/>
    Escolha uma Turma:
    <select name="turma">
        <option value='Select'>Selecione...</option>
        <option value='1'>6º Ano</option>
        <option value='2'>7º Ano A</option>
        <option value='3'>7º Ano B</option>
        <option value='4'>8º Ano A</option>
        <option value='5'>8º Ano B</option>
        <option value='6'>9º Ano A</option>
        <option value='7'>9º Ano B</option>
        <option value='8'>1º Ano Alfa</option>
        <option value='9'>1º Ano Ômega</option>
        <option value='10'>2º Ano FGB</option>
        <option value='11'>2º Ano Alfa</option>
        <option value='12'>2º Ano Ômega</option>
        <option value='13'>3º Ano A</option>
        <option value='14'>3º Ano B</option>
    </select>
    <br/>
    Selecione um arquivo XLS: <input type="file" name="xls_file">
    <br>
    <input type="submit" value="Enviar e Ler">
</form>

</body>
</html>