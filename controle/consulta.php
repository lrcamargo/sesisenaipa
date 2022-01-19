<!DOCTYPE html>
<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
?>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
        <link href="css/main.css" rel="stylesheet">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
        
    </head>
    <body>
        <br>
        <div style="margin-left: auto; margin-right: auto; text-align:center; width:300px">
           <form action="resultado.php" name="mes" method="POST">  
                    <table class="table table-fit" style="width:100%; display: block;">
                        <thead>
                            <tr>
                                <th scope="col">Escolha o mês: </th>
                                <th scope="col">Escolha o ano: </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td scope="col">
                                    <select name="mes" class="form-control" id="cmbmes" style = "margin-left: auto; margin-right: auto;">
                                          <option hidden >Selecione...</option>
                                          <option value="01">Janeiro</option>
                                          <option value="02">Fevereiro</option>
                                          <option value="03">Março</option>
                                          <option value="04">Abril</option>
                                          <option value="05">Maio</option>
                                          <option value="06">Junho</option>
                                          <option value="07">Julho</option>
                                          <option value="08">Agosto</option>
                                          <option value="09">Setembro</option>
                                          <option value="10">Outubro</option>
                                          <option value="11">Novembro</option>
                                          <option value="12">Dezembro</option>
                                    </select>
                                </td>
                                <td scope="col">
                                    <select name="ano" class="form-control" id="cmbmes" style = "margin-left: auto; margin-right: auto;">
                                          <option hidden >Selecione...</option>
                                          <?php
                                            ini_set('display_errors', 1);
                                            ini_set('display_startup_errors', 1);
                                            error_reporting(E_ALL);
                                            
                                            require('conexao.php');

                                            $tabela = "registros";
                                            $data_Hora = "dataHora";
                                            $ano = date('Y');

                                            $instrucaoSQL = "SELECT DISTINCT DATEPART(yyyy,$data_Hora) AS ano FROM $tabela";
                                            $params = array();
                                            $options =array("Scrollable" => SQLSRV_CURSOR_KEYSET);
                                            $consulta = sqlsrv_query($conn, $instrucaoSQL, $params, $options);

                                            while( $row = sqlsrv_fetch_array( $consulta, SQLSRV_FETCH_ASSOC) ) {
                                            echo '<option>'. $row[ano] . '</option>';

                                            } while ( sqlsrv_next_result($consulta) );

                                            sqlsrv_free_stmt($consulta);
                                            sqlsrv_close($conn);  
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
               <input type="submit" name="Buscar" class="btn" value="Buscar" style="width: 115px !important; height: 30px !important;">
            </form>
        </div>
        <br>
    </body>
</html>