<!DOCTYPE HTML>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="author" content="Letícia Rosa Camargo">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="../js/pieSenaiTotal.js"></script>
        <title>Alunos SENAI</title>
    </head>
    <body>
    <div style="width:500px; height: 400px;">
        <canvas id="pieChart" width="500" height="400" ></canvas>
    </div>
    <div>
    Turmas encerrando neste mês:
    <?php
    /* SELECT pessoas.id, n_identificador, nome, nivel_id, descricao FROM dbo.pessoas 
        INNER JOIN niveis ON nivel_id = niveis.id
        WHERE MONTH(validade_data_fim) = MONTH(GETDATE()) AND YEAR(validade_data_fim) = YEAR(getdate()) AND estado = 0
    */
    ?>
    </div>
    </body>
</html>


