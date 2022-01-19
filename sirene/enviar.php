<?php
include("conexao.php");
if (!extension_loaded('sockets')) {
    die('The sockets extension is not loaded.');
}
if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
    echo "Erro: " . socket_strerror(socket_last_error());
} else {
    if(socket_connect($sock,"192.168.254.28",80)){

                    
try{
    $sql = $conn->prepare("SELECT * from dbo.horarioSirene ORDER BY hora, minuto");
    $sql->execute();
    
    socket_write($sock, "2233");
    socket_write($sock, "\r");                                
    
    $horarios = $sql->fetchAll();
    foreach($horarios as $sql) {
        $dias = 0;
        if($sql['domingo'] == 1) {
            $dias = $dias + 1;
        } else {
            $dias = $dias;
        }
        if($sql['segunda'] == 1) {
            $dias = $dias + 2;
        } else {
            $dias = $dias;
        }
        if($sql['terca'] == 1) {
            $dias = $dias + 4;
        } else {
            $dias = $dias;
        }
        if($sql['quarta'] == 1) {
            $dias = $dias + 8;
        } else {
            $dias = $dias;
        }
        if($sql['quinta'] == 1) {
            $dias = $dias + 16;
        } else {
            $dias = $dias;
        }
        if($sql['sexta'] == 1) {
            $dias = $dias + 32;
        } else {
            $dias = $dias;
        }
        if($sql['sabado'] == 1) {
            $dias = $dias + 64;
        } else {
            $dias = $dias;
        }
        
        $hora = str_pad($sql['hora'], 2, '0', STR_PAD_LEFT);
        $minuto = str_pad($sql['minuto'], 2, '0', STR_PAD_LEFT);
        $duracao = str_pad($sql['duracao'], 2, '0', STR_PAD_LEFT);
        $totalDias = str_pad($dias, 3, '0', STR_PAD_LEFT);
        $sirene = $sql['sirene'];
        
        socket_write($sock,'11');
        socket_write($sock,$hora);
        socket_write($sock,$minuto);
        socket_write($sock,$duracao);
        socket_write($sock,$totalDias);
        socket_write($sock,$sirene);
        socket_write($sock,"\r");
    }
 } catch(PDOException $e) {
    die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
}

    socket_close($sock);                                                                                                                                                               
    header("location:index.php?acao=1");
} else {
    header("location:index.php?acao=5");
    } 
}
?>