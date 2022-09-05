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

    $codigos = array('EF-6ºA-M-11775-21','EF-7ºA-M-11775-21','EF-8ºA-M-11775-21','EM-1ªA-M-11775-21','EM-1ªB-M-11775-21','EM-1ªC-M-11775-21',
    'EM-2ªA-M-11775-21','EM-3ªA-M-11775-21','QCEPP01I','QEI17N','AICQ01T-1','AICQI33T-2','AICQI34N-2','AIEM26M-2','AIEX03T-1','AIIRRC12T-2',
    'AIME01M-01','AIME02T-01','AIPA49T-2','AIPA50N-2','AIPA52T-1','AIPA53N-1','AIPPI04T-1','HT-MEC-001-N-01','HT-MET-001-T-01','HT-MET-002-N-01',
    'TAI22N-2','TAIEAD03I-3','TAIEAD04I-1','TAIEAD05I-1','TE01I-1','TM46N-2','TM46N-3','TMMECA24N-3','TMMECA24N-4','TMMM40N-4','AIAL01T-1',
    'AICQ01T-2','AICQ02T-2','AIEX03T-2','AIFEDCRP01M-1','AIMAELE01T-1','AIME01M-02','AIME02T-02','AIPA52T-2','AIPA53N-2','AIPA56T-2','AIPA57N-1',
    'AIRRC01M-1','AITA05M-1','AITA06T-1','HT-ELM-001-N-01','HT-ELT-001-M-01','HT-ELT-002-N-01','HT-ETT-001-N-01','HT-ETT-002-T-01','HT-ETT-003-N-01',
    'HT-MEC-001-N-02','HT-MEC-002-N-01','HT-MEC-004-M-01','HT-MET-003-N-01','HT-MMI-001-T-01','HT-MMI-002-N-01','HT-QUA-001-T-01',
    'HT-QUI-001-T-01','HT-QUI-002-N-01','HTAUTD-001-T-01','HTAUTD-002-T-01','TAI22N-3','TAIEAD03I-4','TAIEAD04I-2','TAIEAD05I-2','TMMECA28N-4','HT-MET-002-N-02','Funcionarios','AIAL02T-1', 'AICQ04T-1','AIAL01T-2',
    'EF-6ºA-M-11775-22','EF-7ºA-M-11775-22','EF-8ºA-M-11775-22','EF-8ºB-M-11775-22','EF-9ºA-M-11775-22','AIMAELE02T-1','AICQ05N-1','EF-9ºA-M-11775-22','EF-9ºB-M-11775-22','HT-QUA-001-T-01','EM-1ªA-O-M-11775-22','EM-1ªA-A-M-11775-22','EM-2ªA-M-11775-22','EM-2ªB-M-11775-22','EM-3ªA-M-11775-22','HT-FME-01-T-22-13310','AI-PPI-01-T-22-13310','AI-MEL-01-T-22-13310','AI-LOG-02-T-22-13310','AI-QUA-01-T-22-13310','AI-LOG-01-T-22-13310','HT-MET-01-N-22-13310','HT-ELT-01-N-22-13310','HT-MEC-01-N-22-13310','HT-FME-02-N-22-13310','HT-AUT-01-N-22-13310','HT-AUT-02-N-22-13310','AI-RCO-01-M-22-13310', 'AI-QUA-02-N-22-13310', 'AIADMEAD01T-1','NE-EF-22-11775-0001',
    'QPPPRIND01I');

    $turmas = array('6º ANO A','7º ANO A', '8º ANO A', '1º ANO A', '1º ANO B', '1º ANO C', '2º ANO A','3º ANO A','QUAL. CONT. PROG. PRODUÇÃO',
    'QUAL. ELETRICISTA INDUSTRIAL','AP. CONTROLE QUALIDADE','APR. CONTROLE QUALIDADE','APR. CONTROLE QUALIDADE','APR. ELETROMECÂNICA',
    'APR. ELETROMECÂNICA (ROBÓTICA)','APR. REDES COMPUTADORES','APR. MANUT. ELETROMECÂNICA','APR. MANUT. ELETROMECÂNICA','APR. PROCESSOS ADMINISTRATIVOS',
    'APR. PROCESSOS ADMINISTRATIVOS','APR. PROCESSOS ADMINISTRATIVOS','APR. PROCESSOS ADMINISTRATIVOS','APR. PROC. PRODUÇÃO INDUSTRIAL',
    'TÉCNICO EM MECÂNICA', 'TÉCNICO EM MECATRÔNICA', 'TÉCNICO EM MECATRÔNICA','TÉCNICO EM AUTOMAÇÃO', 'TÉCNICO AUTOMAÇÃO INDUSTRIAL', 
    'TÉCNICO AUTOMAÇÃO INDUSTRIAL','TÉCNICO AUTOMAÇÃO INDUSTRIAL','TÉCNICO EM ELETROTÉCNICA','TÉCNICO EM MECÂNICA','TÉCNICO EM MECÂNICA',
    'TÉCNICO EM MECATRÔNICA','TÉCNICO EM MECATRÔNICA','TÉCNICO EM MECÂNICA','APR. AUX. DE LOGÍSTICA','AP. CONTROLE QUALIDADE', 'AP. CONTROLE QUALIDADE',
    'APR. ELETROMECÂNICA (ROBÓTICA)','APR. EM FERRAMENTARIA','APR. MANUT. ELETROMECÂNICA','APR. MANUT. ELETROMECÂNICA','APR. MANUT. ELETROMECÂNICA',
    'APR. PROCESSOS ADMINISTRATIVOS','APR. PROCESSOS ADMINISTRATIVOS','APR. PROCESSOS ADMINISTRATIVOS','APR. PROCESSOS ADMINISTRATIVOS','APR. REDES DE COMPUTADORES',
    'APR. TAPECEIRO DE AUTOS','APR. TAPECEIRO DE AUTOS','TÉCNICO EM ELETROMECÂNICA','TÉCNICO EM ELETRÔNICA','TÉCNICO EM ELETRÔNICA',
    'TÉCNICO EM ELETROTÉCNICA','TÉCNICO EM ELETROTÉCNICA','TÉCNICO EM ELETROTÉCNICA','TÉCNICO EM MECÂNICA','TÉCNICO EM MECÂNICA','TÉCNICO EM MECÂNICA','TÉCNICO EM MECÂNICA',
    'TÉCNICO EM MECATRÔNICA','TÉCNICO EM MANUTENÇÃO DE MÁQUINAS','TÉCNICO EM MANUTENÇÃO DE MÁQUINAS','TÉCNICO EM QUALIDADE','TÉCNICO EM QUÍMICA',
    'TÉCNICO EM QUÍMICA','TÉCNICO EM AUTOMAÇÃO','TÉCNICO EM AUTOMAÇÃO','TÉCNICO EM AUTOMAÇÃO','TÉCNICO EM AUTOMAÇÃO',
    'TÉCNICO EM AUTOMAÇÃO','TÉCNICO EM MECATRÔNICA','TÉCNICO EM MECATRÔNICA','Funcionário','APR. AUX. LOGÍSTICA', 'APR. CONTROLE QUALIDADE', 'APR. AUX. LOGÍSTICA',
    '6º ANO A','7º ANO A','8º ANO A','8º ANO B','9º ANO A','APR. MANUT. ELETROMECÂNICA','APR. CONTROLE QUALIDADE','9º ANO A','9º ANO B','TÉCNICO EM QUALIDADE','1º ANO','1º ANO','2º ANO', '2º ANO', '3º ANO','TEC. FABRICAÇÃO MECÂNICA','APR. PROC. DE PROD. INDUSTRIAL','APR. MANUTENÇÃO ELETROMECÂNICA','APR. AUXILIAR LOGÍSTICA','APR. CONTROLE DE QUALIDADE','APR. AUXILIAR LOGÍSTICA','TÉCNICO MECATRÔNICA','TÉCNICO EM ELETRÔNICA','TÉCNICO EM MECÂNICA','TÉC. FABRICAÇÃO MECÂNICA','TÉC. AUTOMAÇÃO INDUSTRIAL','TÉC. AUTOMAÇÃO INDUSTRIAL','APR. REDES DE COMPUTADORES','APR. CONTROLE QUALIDADE','APR. ASSIST. ADMINISTRATIVO','ENS. JOVENS ADULTOS',
    'QUAL. EM PROC. DE PROD. INDUSTRIAL');
    

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
