<!DOCTYPE HTML>
<?php
session_start();

if((!isset ($_SESSION['sLogin']) == true))
{
    unset($_SESSION['sLogin']);
    unset($_SESSION['user']);
    header('location:../index.php');
    }
 
$logado = $_SESSION['user'];

include('conexao.php');
$id = $_GET['id'];

try{        
    $sql = "DELETE FROM horarioSirene WHERE idHorario = '$id'";
                                
    $conn->exec($sql);
} catch(PDOException $e) {
    echo $sql . "<br>" . $e->getMessage();
}
$conn = null;

header("location:index.php?acao=3");                       
?>
                       