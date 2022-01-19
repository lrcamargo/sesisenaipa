<?php
$ipAtual = $_POST['ipAtual'];
$portaAtual = $_POST['portaAtual'];

$ip1 = str_pad($_POST['novo1'], 3, '0', STR_PAD_LEFT);
$ip2 = str_pad($_POST['novo2'], 3, '0', STR_PAD_LEFT);
$ip3 = str_pad($_POST['novo3'], 3, '0', STR_PAD_LEFT);
$ip4 = str_pad($_POST['novo4'], 3, '0', STR_PAD_LEFT);

$mascara1 = str_pad($_POST['mascara1'], 3, '0', STR_PAD_LEFT);
$mascara2 = str_pad($_POST['mascara2'], 3, '0', STR_PAD_LEFT);
$mascara3 = str_pad($_POST['mascara3'], 3, '0', STR_PAD_LEFT);
$mascara4 = str_pad($_POST['mascara4'], 3, '0', STR_PAD_LEFT);

$gtw1 = str_pad($_POST['gtw1'], 3, '0', STR_PAD_LEFT);
$gtw2 = str_pad($_POST['gtw2'], 3, '0', STR_PAD_LEFT);
$gtw3 = str_pad($_POST['gtw3'], 3, '0', STR_PAD_LEFT);
$gtw4 = str_pad($_POST['gtw4'], 3, '0', STR_PAD_LEFT);
    
$dns1 = str_pad($_POST['dns1'], 3, '0', STR_PAD_LEFT);
$dns2 = str_pad($_POST['dns2'], 3, '0', STR_PAD_LEFT);
$dns3 = str_pad($_POST['dns3'], 3, '0', STR_PAD_LEFT);
$dns4 = str_pad($_POST['dns4'], 3, '0', STR_PAD_LEFT);

$porta = str_pad($_POST['porta'], 4, '0', STR_PAD_LEFT);

if (!extension_loaded('sockets')) {
    die('The sockets extension is not loaded.');
}
if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
    echo "Erro: " . socket_strerror(socket_last_error());
} else {
    if(socket_connect($sock,$ipAtual,$portaAtual)){
    
        socket_write($sock, "55");
        socket_write($sock, $ip1);
        socket_write($sock, $ip2);
        socket_write($sock, $ip3);
        socket_write($sock, $ip4);
        socket_write($sock, $mascara1);
        socket_write($sock, $mascara2);
        socket_write($sock, $mascara3);
        socket_write($sock, $mascara4);
        socket_write($sock, $gtw1);
        socket_write($sock, $gtw2);
        socket_write($sock, $gtw3);
        socket_write($sock, $gtw4);
        socket_write($sock, $dns1);
        socket_write($sock, $dns2);
        socket_write($sock, $dns3);
        socket_write($sock, $dns4);
        socket_write($sock, $porta);
        socket_write($sock, "\r"); 
        
        socket_close($sock);  
    
        header("location:configuracoes.php?acao=1");
    } else {
    header("location:configuracoes.php?acao=2");
    }
}
?>