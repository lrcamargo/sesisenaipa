<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=utf-8');
    include('conexaosec.php');
    /*
    EM-1ªA-M-11775-21
EM-1ªB-M-11775-21
EM-1ªC-M-11775-21
EM-2ªA-M-11775-21
EM-3ªA-M-11775-21

    */
    try {
        $busca = $conn->prepare("SELECT TOP 1 id FROM dbo.horarios WHERE nome = ?");
        $busca->bindValue(1,"EM-3ªA-M-11775-21");
        $busca->execute();
        
        $buscaN= $busca->fetchAll();
        foreach ($buscaN as $buscaN) {
            $nivel=$buscaN['id'];
            echo $nivel;
            $inclui = $conn->prepare("INSERT INTO dbo.horarios_itens(horario_id, inicio, fim, seg, ter, qua, qui, sex, sab, dom, fer, t_minimo_sentido, t_minimo_tempo, t_minimo_fora_faixa, tipo_limite) 
            VALUES ($nivel,'06:00', '07:05', 1, 1, 1, 1, 1, 1, 0, 0, 0, 5, 0, 0)");
            $inclui->execute();
            $inclui2 = $conn->prepare("INSERT INTO dbo.horarios_itens(horario_id, inicio, fim, seg, ter, qua, qui, sex, sab, dom, fer, t_minimo_sentido, t_minimo_tempo, t_minimo_fora_faixa, tipo_limite) 
            VALUES ($nivel,'12:20', '13:30', 1, 1, 1, 1, 1, 1, 0, 0, 0, 5, 0, 0)");
            $inclui2->execute();
        }
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
    }

    
    
    
    
?>