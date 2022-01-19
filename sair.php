<?php
    session_destroy();
    unset($_SESSION['sLogin']);
    header('location:index.php');    
?>