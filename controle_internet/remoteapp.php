<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    //session_start();

    use PEAR2\Net\RouterOS;
    require_once '../PEAR2_Net_RouterOS-1.0.0b6/src/PEAR2/Autoload.php';
 
    $client = new RouterOS\Client('192.168.254.1', 'block', 'block_api@20');
    $printRequest = new RouterOS\Request('/ip/firewall/nat');
    $numbers = 0;
    $enableRequest = new RouterOS\Request('/ip/firewall/nat/enable');
    $enableRequest->setArgument('numbers',$numbers);
    $client->sendSync($enableRequest);

    echo("Acesso Liberado");
?>