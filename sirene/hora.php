<?php

$data = date('Y/m/d h:i:s');

if (!extension_loaded('sockets')) {
    die('The sockets extension is not loaded.');
}
if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
    echo "Erro: " . socket_strerror(socket_last_error());
} else {
    if(socket_connect($sock,"192.168.254.28",80)) {
    
    $ano = substr(date('Y'), -2);
    socket_write($sock, "44");
    socket_write($sock,date('H'));
    socket_write($sock,date('i'));
    socket_write($sock,date('s'));
    socket_write($sock,date('d'));
    socket_write($sock,date('m'));
    socket_write($sock,$ano);
    socket_write($sock,(date('w')+1));
    socket_write($sock, "\r");
    
    socket_close($sock);
    header("location:index.php?acao=4");
} else {
    header("location:index.php?acao=5");
}
    
} 

?>