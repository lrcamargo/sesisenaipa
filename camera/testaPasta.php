<?php

$dir = 'snap/2021/fotos/';
$files1 = scandir($dir);

$files2 = scandir($dir, SCANDIR_SORT_DESCENDING);

print_r($files1);
print_r($files2);

?>
