<?php
    file_get_contents("http://192.168.254.40/admin/reboot");
    file_get_contents("http://192.168.254.41/admin/reboot");
    file_get_contents("http://192.168.254.43/admin/reboot");
    file_get_contents("http://192.168.254.44/admin/reboot");

    header("location:telefone.php?acao=1");
?>