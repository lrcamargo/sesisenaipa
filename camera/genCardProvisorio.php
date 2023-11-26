<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require("../fpdf/i25.php");

    $pdf=new PDF_i25();
    $pdf->AddPage("L", array(54,86));
    $pdf->AddFont('Univers','','univers.php');
    $pdf->SetAutoPageBreak(false);
    $pdf->Image("../img/ModeloCrachaProvisorio.png", 0, 0, 86, 54);
    $pdf->SetRightMargin(0.5);

    $pdf->SetFont('Univers', '', '7');
    $pdf->i25(23,39,str_pad('80100130',10,"0",STR_PAD_LEFT),1.3,14);
    $pdf->SetXY(3,12);
    $pdf->Write(10,str_pad('80100130',10,"0",STR_PAD_LEFT));

    $arquivo = "Provisorio.pdf";
    $filename = $arquivo;
    $pdf->Output($filename,'I');
?>
