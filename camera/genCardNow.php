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

    $codigos = array('EF-6°A-M-11775-23','EF-7°A-M-11775-23','EF-7°B-M-11775-23','EF-8°A-M-11775-23','EF-8°B-M-11775-23','EF-9°A-M-11775-23','EF-9°B-M-11775-23','EM-3ªA-M-11775-23','EM-3ªB-M-11775-23','EM-1ªA-A-M-11775-23','EM-2ªA-A-M-11775-23','EM-1ªB-O-M-11775-23','EM-2ªA-O-M-11775-23', 'AI-AST-01-T-22-13310', 'HT-ELT-001-M-03', 'HT-ELT-002-N-03', 'HT-MEC-004-M-03','HT-ETT-002-T-03', 'HT-ETT-003-N-03', 'HT-MMI-001-T-03', 'HT-MMI-002-N-03', 'HT-QUI-001-T-03', 'HT-QUI-002-N-03', 'HTAUTD-001-T-03', 'HTAUTD-002-T-03', 'HTAUTD-005-N-01', 'HT-ELT-004-T-01', 'HT-ETT-005-M-01', 'AI-RCO-01-M-22-13310', 'AI-QUA-02-N-22-13310', 'AI-LOG-01-T-22-13310', 'AI-LOG-02-T-22-13310', 'AI-MEL-01-T-22-13310', 'AI-QUA-01-T-22-13310', 'AI-PPI-01-T-22-13310', 'AI-TAU-01-M-22-13310', 'AI-TAU-02-T-22-13310', 'AI-MEL-02-T-22-13310', 'HTAUTM-01-I-22-13310', 'HTAUTD-003-I-01', 'HT-AUT-01-N-22-13310', 'HT-AUT-02-N-22-13310', 'HT-ELT-01-N-22-13310', 'HT-FME-01-T-22-13310', 'HT-FME-02-N-22-13310', 'HT-MEC-01-N-22-13310', 'HT-MET-01-N-22-13310', 'HTAUTM-02-I-22-13310', 'HTELET-01-I-22-13310', 'HTELET-02-I-22-13310', 'HT-MET-02-M-22-13310', 'HT-ELT-001-M-04', 'HT-ELT-002-N-04', 'HT-MEC-004-M-04', 'HT-ETT-002-T-04', 'HT-ETT-003-N-04', 'HT-MMI-001-T-04', 'HT-MMI-002-N-04', 'HT-QUI-001-T-04', 'HT-QUI-002-N-04', 'AI-LOG-01-N-23-13310', 'AI-MEL-01-M-23-13310', 'AI-ALM-01-T-23-13310', 'AI-PRD-01-T-23-13310', 'AI-MEL-02-T-23-13310', 'AI-LOG-02-M-23-13310', 'EM-1ªA-A-M-11775-23', 'EM-1ªB-O-M-11775-23', 'EF-7ºB-M-11775-23','EF-7ºA-M-11775-23', 'EF-9ºA-M-11775-23', 'EF-9ºB-M-11775-23', 'EF-8ºA-M-11775-23','EF-8ºB-M-11775-23', 'QEI19N', 'QFEDCR10N', 'HT-ELT-02-T-23-13310', 'AI-QUA-02-N-23-13310', 'AI-PRD-02-N-23-13310', 'AI-MEL-03-T-23-13310', 'HT-MEC-01-N-23-13310', 'QEI20N', 'QARH02T', 'QPMECMAI01T', 'QPOPMQUC01T');


    $turmas = array('6º ANO A','7º ANO A','7° ANO B', '8º ANO A','8° ANO B','9° ANO A', '9° ANO B', '3º ANO A', '3º ANO B', '1º ANO A', '2º ANO A','1º ANO O','2° ANO O', 'APR. IND.ASSIST ADM', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM MECÂNICA', 'TÉCNICO EM ELETROTÉCNICA', 'TÉCNICO EM ELETROTÉCNICA', 'TÉCNICO EM MANUTENÇÃO DE MÁQUINAS', 'TÉCNICO EM MANUTENÇÃO DE MÁQUINAS', 'TÉCNICO EM QUÍMICA', 'TÉCNICO EM QUÍMICA', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM ELETRÔTECNICA', 'APRENDIZAGEM IND EM REDES COMP', 'APRENDIZAGEM IND EM CONTROLE QUA', 'APRENDIZAGEM IND EM LOGISICA', 'APRENDIZAGEM IND EM LOGISTICA', 'APRENDIZAGEM IND EM ELETROMEC', 'APRENDIZAGEM IND EM CONTROLE QUA', 'APRENDIZAGEM IND EM PROCESSO PROD IND', 'APRENDIZAGEM IND EM TAPECEIROS AUTO', 'APRENDIZAGEM IND EM TAPECEIROS AUTO', 'APRENDIZAGEM IND EM ELETROMEC', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM AUTOMAÇÃO IND', 'TÉCNICO EM AUTOMAÇÃO IND', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM FABRICAÇÃO MECÂNICA', 'TÉCNICO EM FABRICAÇÃO MECÂNICA', 'TÉCNCIO EM MECÂNICA', 'TÉCNICO EM MECATRÔNICA', 'TÉCNICO EM AUTOMAÇÃO IND EAD', 'TÉCNICO EM ELETROTÉCNICA EAD', 'TÉCNICO EM ELETROTÉCNICA EAD', 'TÉCNICO EM MECATRÔNICA SESI', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM ELETRÔNICA', 'TÉCNICO EM MECÂNICA', 'TÉCNICO EM ELETROTÉCNICA', 'TÉCNICO EM ELETROTÉCNICA', 'TÉCNICO EM MANUTENÇÃO MÁQUINAS IND', 'TÉCNICO EM MANUTENÇÃO MÁQUINAS IND', 'TÉCNICO EM QUÍMICA',  'TÉCNICO EM QUÍMICA', 'APRENDIZAGEM IND EM LOGISTICA', 'APRENDIZAGEM IND EM ELETROMEC', 'APRENDIZAGEM IND EM PROCESSO PROD ALIMENTOS', 'APRENDIZAGEM IND EM PROCESSO PROD IND', 'APRENDIZAGEM IND EM ELETROMECÂNICA', 'APREDIZAGEM IND EM AUXILIAR DE LOGISTICA', '1º ANO A', '1° ANO O', '7º ANO B', '7º ANO A', '9º ANO A', '9º ANO B', '8º ANO A', '8º ANO B', 'ELETRICISTA IND', 'FERRAMENTARIA', 'TÉCNICO EM ELETRÔNICA', 'CONTROLE DE QUALIDADE', 'APRENDIZAGEM PROCESSO DE PRODUÇÃO', 'MANUTENÇÃO ELETROMECÂNICA', 'TÉCNICO EM MECÂNICA', 'Eletricista Industrial', 'QUAL. AUX. RECURSOS HUMANOS', 'QUAL. EM MECÂNICO DE MANUTENÇÃO IND', 'Op. de Máquinas de Usinagem' );
   

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
