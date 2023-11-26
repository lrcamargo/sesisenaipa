<?php

include("conexao.php");

try { 
    // Consulta SQL para obter os dados do banco de dados
    $stmt = $conn->prepare("SELECT * FROM notas WHERE idDisc = $selectedDisciplina AND idDoc = $selectedDocente AND avaliacao = $selectedAvaliacao AND etapa = $selectedEtapa AND turma = $selectedTurma");
    $stmt->execute();

    $data = array(
        'labels' => array(),
        'values' => array()
    );

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data['labels'][] = $row['label'];
        $data['values'][] = $row['value'];
    }

    header('Content-Type: application/json');
    echo json_encode($data);
} catch(PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}

?>