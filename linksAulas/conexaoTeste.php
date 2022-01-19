<?php
$servername = "192.168.254.13";
$username = "sa";
$password = "2019!BdPa";

try {
    $conn = new PDO("sqlsrv:Server=$servername;Database=teste", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
catch(PDOException $e)
    {
    echo "Conexão falhou: " . $e->getMessage();
    }
?>