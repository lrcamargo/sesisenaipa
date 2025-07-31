<?php
$serverName = "192.168.254.13";
$connectionOptions = array(
    "Database" => "trabalhos",
    "Uid" => "sa",
    "PWD" => "2019!BdPa",
    "CharacterSet" => "UTF-8"
);
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>