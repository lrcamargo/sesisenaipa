<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);

    //require("../fpdf/code128.php");
    require("../fpdf/i25.php");

    //$pdf=new PDF_Code128();
    $pdf=new PDF_i25();
    $pdf->AddPage("L", array(54,86));
    $pdf->AddFont('Univers','','univers.php');
    $pdf->SetAutoPageBreak(false);
    $pdf->Image("../img/modeloCracha.png", 0, 0, 86, 54);

    $nome='Leticia Rosa Camargo';
    $pdf->SetFont('Univers', '', '8');
    $pdf->SetXY(28.8,17.5);
    $pdf->Write(6, $nome);

    $escola='Escola SESI/SENAI Orlando Chiarini';
    $pdf->SetFont('Univers', '', '7');
    $pdf->SetXY(28.8,26);
    $pdf->Write(6, $escola);

    $curso='Teste';
    $pdf->SetFont('Univers', '', '8');
    $pdf->SetXY(28.8,34.5);
    $pdf->Write(6, $curso);

    $turno='Integral';
    $pdf->SetFont('Univers', '', '8');
    $pdf->SetXY(51.8,34.5);
    $pdf->Write(6, $turno);

    $validade='12/2021';
    $pdf->SetFont('Univers', '', '6');
    $pdf->SetXY(14,48);
    $pdf->Write(6, $validade);

    $registro='0009108377';
    //$pdf->Code128(32.8,40,$registro,35,12);
    $pdf->SetFont('Univers', '', '6');
    $pdf->i25(38,39,$registro,1.3,14);
    $pdf->SetXY(14,39);
    $pdf->Write(14,$registro);
    
    //$filename = $nome . "_" . $curso . ".pdf";
    $filename = "Teste.pdf";
    $pdf->Output($filename,'I');
?>
