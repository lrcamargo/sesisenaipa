<?php
$mes = $_POST['mes'];
$ano = $_POST['ano'];

?>

<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link href="css/main.css" rel="stylesheet">
       
        <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
  <!-- Font Awesome -->
  <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
  
<!--Datatable-->
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.dataTables.min.css">
     
    
    </head>
    <body style="background-color: rgb(220, 220, 220);">
    <div >
        <h1 style="text-align: center; color: #4E9CAF">Resultado <?php echo $mes; echo "/"; echo $ano?></h1>
        <br/>
        <table id="registros" class="table table-responsive table-striped table-hover ">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Descrição</th>
                </tr>
            </thead>
              <tbody>
                <?php
                  try {
                      
                      $servername = "bart";
                        $username = "sa";
                        $password = "2016@Tcwl";

                        try {
                            $conn = new PDO("sqlsrv:Server=$servername;Database=Registro", $username, $password);
                            // set the PDO error mode to exception
                            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            }
                        catch(PDOException $e)
                            {
                            echo "Conexão falhou: " . $e->getMessage();
                            }
                      
                      $tabela = "registros";
                  $data_Hora = "dataHora";
                  $tipo = "tipo"; 
                  $id = "id";
                   
                        $query = $conn->prepare("SELECT $id, $data_Hora, $tipo FROM $tabela where DATEPART(mm, $data_Hora) = $mes AND DATEPART(yyyy, $data_Hora) = $ano order by $id");
                        $query->execute();

                        $livrosData = $query->fetchAll();
                        foreach ($livrosData as $query) {
                            echo '<tr>';
                                echo '<td>'.$query['id'].'</td>';
                                echo '<td>'. date('d/m/Y', strtotime($query[$data_Hora])) . '</td>';
                                echo '<td>'. date('H:i:s', strtotime($query[$data_Hora])) . '</td>';
                                echo '<td>'.$query['tipo'].'</td>';
                            echo '</tr>';
                        }



                    } catch (PDOException $e) {
                            die("Erro ao conectar ao banco de dados $dbname :" . $e->getMessage());
                    }
                  
          ?>                   
                            
              </tbody>
              
            
        </table>
    </div>
<!-- jQuery 3 -->
<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.3.1.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
<link href="https://nightly.datatables.net/css/jquery.dataTables.css" rel="stylesheet" type="text/css" />
<script src="https://nightly.datatables.net/js/jquery.dataTables.js"></script>
<script src="https://cdn.datatables.net/1.10.15/js/dataTables.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.print.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.15/css/dataTables.bootstrap.min.css" />  
<!-- FastClick -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fastclick/1.0.6/fastclick.js"></script>
<!-- Sparkline -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>
<!-- SlimScroll -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>

    <script>  
 $(document).ready(function(){  
      $('#registros').DataTable( {
        dom: 'Bfrtip',
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Portuguese-Brasil.json",
            buttons: {
                pageLength: {
                    _: "Mostrar %d registros",
                    '-1': 'Mostrar todos'
                }
            }
        },
        lengthMenu: [
            [ 10, 25, 50, -1 ],
            [ '10 registros', '25 registros', '50 registros', 'Mostrar todos' ]
        ],
        buttons: [
            {extend: 'copy', text: 'Copiar'},
            {extend: 'csv', text: 'CSV'},
            {extend: 'excel', text: 'Excel'},
            {extend: 'pdf', text: 'PDF'},
            {extend: 'print', text: 'Imprimir'},
            'pageLength'
            
        ],
        
    } );
} );
 </script>  
    </body>

</html>



