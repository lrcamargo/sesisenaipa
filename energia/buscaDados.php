<?php
    require 'PhpSerialModbus.php';
    include('conexao.php');

    $dataHora = date("Y-m-d H:i:s");
    $modbus = new PhpSerialModbus;
    $modbus->deviceinit('/dev/ttyUSB0',9600,'none',8,1,'none');
    $modbus->deviceOpen();
    
    function converte($recebido, $base, $referencia ) {
        $converte = ($recebido*$base)/$referencia;
        return number_format($converte, 3);
    }

    function calcula($dado1,$dado2,$dado3) {
        $total = ($dado1*1000)+$dado2+($dado3/1000);
        return number_format($total, 3);
    }

    $tensaol1neutro=$modbus->sendQuery(1,3,"00 64",01);
    var_dump($tensaol1neutro);
    $tensaol2neutro=$modbus->sendQuery(1,3,"00 65",01);
    $tensaol3neutro=$modbus->sendQuery(1,3,"00 66",01);
    $tensaol1l2=$modbus->sendQuery(1,3,"00 67",01);
    $tensaol2l3=$modbus->sendQuery(1,3,"00 68",01);
    $tensaol3l1=$modbus->sendQuery(1,3,"00 69",01);
    $correntei1=$modbus->sendQuery(1,3,"00 6a",01);
    $correntei2=$modbus->sendQuery(1,3,"00 6b",01);
    $correntei3=$modbus->sendQuery(1,3,"00 6c",01); 
    $somacorrentes=$modbus->sendQuery(1,3,"00 6d",01);
    $p1=$modbus->sendQuery(1,3,"00 6e",01);
    $p2=$modbus->sendQuery(1,3,"00 6f",01);
    $p3=$modbus->sendQuery(1,3,"00 70",01);
    $pt=$modbus->sendQuery(1,3,"00 71",01);
    $q1=$modbus->sendQuery(1,3,"00 72",01);
    $q2=$modbus->sendQuery(1,3,"00 73",01);
    $q3=$modbus->sendQuery(1,3,"00 74",01);
    $qt=$modbus->sendQuery(1,3,"00 75",01);
    $s1=$modbus->sendQuery(1,3,"00 76",01);
    $s2=$modbus->sendQuery(1,3,"00 77",01);
    $s3=$modbus->sendQuery(1,3,"00 78",01);
    $st=$modbus->sendQuery(1,3,"00 79",01);
    $cosphi1=$modbus->sendQuery(1,3,"00 7a",01);
    $cosphi2=$modbus->sendQuery(1,3,"00 7b",01);
    $cosphi3=$modbus->sendQuery(1,3,"00 7c",01);
    $cosphit=$modbus->sendQuery(1,3,"00 7d",01);
    $freq=$modbus->sendQuery(1,3,"00 7e",01);
    $consumidamwh=$modbus->sendQuery(1,3,"00 7f",01);
    $consumidakwh=$modbus->sendQuery(1,3,"00 80",01);
    $consumidawh=$modbus->sendQuery(1,3,"00 81",01);
    $consumidamvarh=$modbus->sendQuery(1,3,"00 82",01);
    $consumidakvarh=$modbus->sendQuery(1,3,"00 83",01);
    $consumidavarh=$modbus->sendQuery(1,3,"00 84",01);
    $fornecidamwh=$modbus->sendQuery(1,3,"00 85",01);
    $fornecidakwh=$modbus->sendQuery(1,3,"00 86",01);
    $fornecidawh=$modbus->sendQuery(1,3,"00 87",01);
    $fornecidamvarh=$modbus->sendQuery(1,3,"00 88",01);
    $fornecidakvarh=$modbus->sendQuery(1,3,"00 89",01);
    $fornecidavarh=$modbus->sendQuery(1,3,"00 8a",01);
    $angphi1=$modbus->sendQuery(1,3,"00 8b",01);
    $angphi2=$modbus->sendQuery(1,3,"00 8c",01);
    $angphi3=$modbus->sendQuery(1,3,"00 8d",01);
    $angphit=$modbus->sendQuery(1,3,"00 8e",01);
    
    $valtensaol1neutro=hexdec($tensaol1neutro[0].$tensaol1neutro[1]);
    $valtensaol2neutro=hexdec($tensaol2neutro[0].$tensaol2neutro[1]);
    $valtensaol3neutro=hexdec($tensaol3neutro[0].$tensaol3neutro[1]);
    $valtensaol1l2=hexdec($tensaol1l2[0].$tensaol1l2[1]);
    $valtensaol2l3=hexdec($tensaol2l3[0].$tensaol2l3[1]);
    $valtensaol3l1=hexdec($tensaol3l1[0].$tensaol3l1[1]);
    $valcorrentei1=hexdec($correntei1[0].$correntei1[1]);
    $valcorrentei2=hexdec($correntei2[0].$correntei2[1]);
    $valcorrentei3=hexdec($correntei3[0].$correntei3[1]);    
    $valsomacorrentes=hexdec($somacorrentes[0].$somacorrentes[1]);  
    $valp1=hexdec($p1[0].$p1[1]);   
    $valp2=hexdec($p2[0].$p2[1]);
    $valp3=hexdec($p3[0].$p3[1]);
    $valpt=hexdec($pt[0].$pt[1]);    
    $valq1=hexdec($q1[0].$q1[1]);
    $valq2=hexdec($q2[0].$q2[1]);
    $valq3=hexdec($q3[0].$q3[1]);
    $valqt=hexdec($qt[0].$qt[1]);
    $vals1=hexdec($s1[0].$s1[1]);
    $vals2=hexdec($s2[0].$s2[1]);
    $vals3=hexdec($s3[0].$s3[1]);
    $valst=hexdec($st[0].$st[1]);
    $valcosphi1=hexdec($cosphi1[0].$cosphi1[1]);
    $valcosphi2=hexdec($cosphi2[0].$cosphi2[1]);
    $valcosphi3=hexdec($cosphi3[0].$cosphi3[1]);
    $valcosphit=hexdec($cosphit[0].$cosphit[1]);
    $valfreq=hexdec($freq[0].$freq[1]);
    $valconsmwh=hexdec($consumidamwh[0].$consumidamwh[1]);
    $valconskwh=hexdec($consumidakwh[0].$consumidakwh[1]);
    $valconswh=hexdec($consumidawh[0].$consumidawh[1]);
    $valconsmvarh=hexdec($consumidamvarh[0].$consumidamvarh[1]);
    $valconskvarh=hexdec($consumidakvarh[0].$consumidakvarh[1]);
    $valconsvarh=hexdec($consumidavarh[0].$consumidavarh[1]);
    $valfornmwh=hexdec($fornecidamwh[0].$fornecidamwh[1]);
    $valfornkwh=hexdec($fornecidakwh[0].$fornecidakwh[1]);
    $valfornwh=hexdec($fornecidawh[0].$fornecidawh[1]);
    $valfornmvarh=hexdec($fornecidamvarh[0].$fornecidamvarh[1]);
    $valfornkvarh=hexdec($fornecidakvarh[0].$fornecidakvarh[1]);
    $valfornvarh=hexdec($fornecidavarh[0].$fornecidavarh[1]);
    $valangphi1=hexdec($angphi1[0].$angphi1[1]);
    $valangphi2=hexdec($angphi2[0].$angphi2[1]);
    $valangphi3=hexdec($angphi3[0].$angphi3[1]);
    $valangphit=hexdec($angphit[0].$angphit[1]);

    $val1 = converte($valtensaol1neutro,127,16384);
    $val2 = converte($valtensaol2neutro,127,16384);
    $val3 = converte($valtensaol3neutro,127,16384);
    $val4 = converte($valtensaol1l2,220,16384);
    $val5 = converte($valtensaol2l3,220,16384);
    $val6 = converte($valtensaol3l1,220,16384);
    $val7 = converte($valcorrentei1,600,16384);
    $val8 = converte($valcorrentei2,600,16384);
    $val9 = converte($valcorrentei3,600,16384);
    $val10 = converte($valsomacorrentes,1800,16384);
    $val11 = converte($valp1,76200,16384);
    $val12 = converte($valp2,76200,16384);
    $val13 = converte($valp3,76200,16384);
    $val14 = converte($valpt,228600,16384);
    $val15 = converte($valq1,76200,16384);
    $val16 = converte($valq2,76200,16384);
    $val17 = converte($valq3,76200,16384);
    $val18 = converte($valqt,228600,16384);
    $val19 = converte($vals1,76200,16384);
    $val20 = converte($vals2,76200,16384);
    $val21 = converte($vals3,76200,16384);
    $val22 = converte($valst,228600,16384);
    $val23 = converte($valcosphi1,1,16384);
    $val24 = converte($valcosphi2,1,16384);
    $val25 = converte($valcosphi3,1,16384);
    $val26 = converte($valcosphit,1,16384);
    $val27 = converte($valfreq,50,8192);
    $val28 = calcula($valconsmwh,$valconskwh,$valconswh);
    $val29 = calcula($valconsmvarh,$valconskvarh,$valconsvarh);
    $val30 = calcula($valfornmwh,$valfornkwh,$valfornvarh);
    $val31 = calcula($valfornmvarh,$valfornkvarh,$valfornvarh);
    $val32 = converte($valangphi1,360,16384);
    $val33 = converte($valangphi2,360,16384);
    $val34 = converte($valangphi3,360,16384);
    $val35 = converte($valangphit,360,16384);

    
    try {
        $insere = $conn->prepare("INSERT INTO consumoenergia (tensaol1neutro, tensaol2neutro, tensaol3neutro, tensaol1l2, tensaol2l3, tensaol3l1, correntei1,correntei2, correntei3, somacorrentes, potativap1, potativap2, potativap3, potativatotal, potreativaq1, potreativaq2, potreativaq3, potreativatotal, potaparentes1,potaparentes2, potaparentes3, potaparentetotalst,cosphi1,cosphi2,cosphi3,cosphit,frequencia,consumidawh,consumidavarh,fornecidawh,fornecidavarh, angulophi1, angulophi2, angulophi3, angulophit, dataHora) VALUES ('$val1', '$val2', '$val3', '$val4', '$val5', '$val6', '$val7', '$val8', '$val9', '$val10', '$val11', '$val12', '$val13', '$val14', '$val15', '$val16', '$val17', '$val18', '$val19', '$val20', '$val21', '$val22','$val23','$val24','$val25','$val26','$val27','$val28', '$val29','$val30','$val31','$val32','$val33','$val34','$val35','$dataHora')");
        
        $insere->execute();

    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados :" . $e->getMessage());
    }
    
    $modbus->deviceClose();
?>