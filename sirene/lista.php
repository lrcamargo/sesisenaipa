<?php

$data = date('Y/m/d h:i:s');

if (!extension_loaded('sockets')) {
    die('The sockets extension is not loaded.');
}
if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
    echo "Erro: " . socket_strerror(socket_last_error());
} else {
    if(socket_connect($sock,"192.168.254.28",80)) {
    
    socket_write($sock, "33");
    socket_write($sock, "\r");
    
    socket_close($sock);
    header("location:index.php?acao=4");
} else {
    header("location:index.php?acao=5");
}
    
} 

?>