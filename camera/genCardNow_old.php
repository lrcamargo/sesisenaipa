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
   
     $codigos = array('EF-6A-M-11775-25', 'EF-7A-M-11775-25', 'EF-7B-M-11775-25', 'EF-8A-M-11775-25',
     'EF-8B-M-11775-25', 'EF-9A-M-11775-25', 'EF-9B-M-11775-25', 'EM-1A-A-M-11775-25', 
     'EM-1B-O-M-11775-25','EM-2A-A-M-11775-25', 'EM-2B-O-M-11775-25', 'EM-3A-A-M-11775-25', 'EM-3B-O-M-11775-25',
     'Funcionários', 'HT-AUT-01-N-25-13310','HT-AUT-02-N-25-13310', 'HT-ELM-01-N-25-13310', 
     'HT-ELM-02-N-25-13310', 'HT-ETT-01-N-25-13310', 'HT-ETT-02-N-25-13310', 'HT-LOG-01-N-25-13310', 
     'HT-MEC-01-N-25-13310', 'HT-MEC-02-N-25-13310', 'HT-MMI-01-N-25-13310', 'HT-MMI-02-N-25-13310',
     'AI-GEI-05-M-25-13310', 'AI-MEL-06-T-24-13310','AI-GEI-04-T-13310','AI-GEI-05-T-24-13310',
     'AI-GEI-06-N-24-13310','AI-GEI-07-T-24-13310','AI-MEL-04-T-24-13310','AI-MEL-06-T-24-13310',
     'AI-MEL-07-T-24-13310','HTAUTM-01-I-24-13310','HT-ELE-01-N-24-13310','HTELET-01-I-24-13310',
     'HTELET-02-I-24-13310','HT-ELM-01-N-24-13310','HT-ELM-02-N-24-13310','HT-ETT-01-N-24-13310',
     'HT-MEC-01-N-24-13310','HT-MEC-02-N-24-13310','HT-MET-01-N-24-13310','HT-MET-02-M-24-13310',
     'AIASLO-01-T-24-13310','AI-MEL-01-M-24-13310');
    
    $turmas = array('6 ANO A', '7 ANO A', '7 ANO B', '8 ANO A', '8 ANO B', '9 ANO A', '9 ANO B', 
    '1 ANO ALFA', '1 ANO ÔMEGA', '2 ANO ALFA', '2 ANO ÔMEGA', '3 ANO ALFA', '3 ANO ÔMEGA', 
    'Funcionários', 'TÉC. AUTOMAÇÃO INDUSTRIAL', 'TÉC. AUTOMAÇÃO INDUSTRIAL', 'TÉC. ELETROMECÂNICA', 
    'TÉC. ELETROMECÂNICA', 'TÉC. ELETROTÉCNICA', 'TÉC. ELETROTÉCNICA', 'TÉC. LOGÍSTICA', 'TÉC. MECÂNICA INDUSTRIAL', 
    'TÉC. MECÂNICA INDUSTRIAL', 'TÉC. MANUT. DE MÁQUINAS', 'TÉC. MANUT DE MÁQUINAS','APREND. GESTÃO INDUSTRIAL', 
    'APREND. ELETROMECÂNICA','APREND. GESTÃO INDUSTRIAL','APREND. GESTÃO INDUSTRIAL','APREND. GESTÃO INDUSTRIAL',
    'APREND. GESTÃO INDUSTRIAL','APREND. MANUTENÇÃO ELETROMECÂNICA','APREND. MANUTENÇÃO ELETROMECÂNICA',
    'APREND. MANUT. ELETROMECÂNICA','TEC. AUTOMAÇÃO INDUSTRIAL','TEC. ELETROELETRÔNICA',
    'TEC. ELETROTÉCNICA','TEC. ELETROTÉCNICA','TEC. ELETROMECÂNICA','TEC. ELETROMECÂNICA',
    'TEC. ELETROTÉCNICA','TEC. MECÂNICA','TEC. MECÂNICA','TEC. MECATRÔNICA','TEC. MECATRÔNICA',
    'APREND. ASSISTENTE LOGÍSTICA','APREND. ELETROMECÂNICA');

    require("../fpdf/i25.php");

    try {
        $buscaAluno = $conn->prepare("SELECT pessoas.id, n_identificador,nome,nivel_id, validade_data_fim, niveis.descricao FROM pessoas 
        INNER JOIN niveis ON pessoas.nivel_id = niveis.id
        WHERE nome = '". $nome."'");
        $buscaAluno->execute();
        
        $buscaAlunos = $buscaAluno->fetchAll();
        foreach ($buscaAlunos as $buscaAlunos) {
            $nome=$buscaAlunos['nome'];
            $validade=$buscaAlunos['validade_data_fim'];
            $registro=$buscaAlunos['n_identificador'];
            $id=$buscaAlunos['id'];
            $nivel = $buscaAlunos['descricao'];
        }
        
    } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
    }

    $pdf=new PDF_i25();
    $pdf->AddPage("L", array(54,86));
    $pdf->AddFont('Univers','','univers.php');
    $pdf->SetAutoPageBreak(false);
    $pdf->Image("../img/ModeloCrachaNovo.png", 0, 0, 86, 54);
    $pdf->SetRightMargin(0.5);

    $f = 8;
    $pdf->SetFont('Univers', '', $f);
    $pdf->SetXY(24.8,19.5);
    while ($pdf->GetStringWidth($nome) > 50) {
        $f-=0.2;
        $pdf->SetFontSize($f);
    }
    $pdf->Write(6, utf8_decode($nome));

    $escola='Escola SESI/SENAI Orlando Chiarini';
    $pdf->SetFont('Univers', '', '8');
    $pdf->SetXY(24.8,27.5);
    $pdf->Write(6, $escola);

    $font = 8;
    $busca = array_search($nivel,$codigos);
    $pdf->SetFont('Univers', '', $font);
    $pdf->SetXY(24.8,34.5);
    while ($pdf->GetStringWidth($turmas[$busca]) > 35) {
        $font-=0.5;
        $pdf->SetFontSize($font);
    }
    $pdf->Write(6, utf8_decode($turmas[$busca]));
    
    if($turno == 'manha') {
        $turno = 'Manhã';
    }
    if($turno == 'tarde') {
        $turno = 'Tarde';
    }
    if($turno == 'noite') {
        $turno = 'Noite';
    }
    if($turno == 'integral') {
        $turno = 'Integral';
    }
    $pdf->SetFont('Univers', '', '8');
    $pdf->SetXY(60,34.5);
    $pdf->Write(6, utf8_decode($turno));

    $val = substr($validade,0,-9);
    $data=explode("-",$val);
    $pdf->SetFont('Univers', '', '7');
    $pdf->SetXY(16,46.7);
    $pdf->Write(6, $data[2]."/".$data[1]."/".$data[0]);

    $pdf->SetFont('Univers', '', '7');
    $pdf->i25(38,39,str_pad($registro,10,"0",STR_PAD_LEFT),1.3,14);
    $pdf->SetXY(15.7,39);
    $pdf->Write(14,str_pad($registro,10,"0",STR_PAD_LEFT));

    $imagem = "/home/suporte/fotos/".$id."-1.jpg";
    $pdf->Image($imagem,1.6,11.8,23.8);
    
    $arquivo = $nome . "_" . $nivel . ".pdf";
    $filename = $arquivo;
    $pdf->Output($filename,'I');
?>
