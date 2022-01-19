<html>
    <head>
        <link href="css/main.css" rel="stylesheet">
    </head>
<?php
require('conexao.php');

    $tabela1 = "registros";
    $data_Hora = "dataHora";
    $tipo = "tipo"; 

    $dataHora = date("Y-m-d h:i:sa");
    $type = "Entrada";

    //Inserir
    $instrucaoSQL = "INSERT INTO $tabela1 ($data_Hora, $tipo) VALUES ('$dataHora','$type')";
    $params = array();
    $inserir = sqlsrv_query($conn, $instrucaoSQL, $params);

    //echo $instrucaoSQL;
    $rows = sqlsrv_rows_affected($inserir);

    if($rows === false) {
        echo '<h3 style="color:red"> Erro. <p>';
    } else {
        echo '<h3 style="color:blue"> Entrada cadastrada. <p>';
    }

    //Encerrar
    sqlsrv_free_stmt($consulta);
    sqlsrv_close($conn);

?>
    
    <a class="btnreg" href="index.php">Voltar</a>
</html>