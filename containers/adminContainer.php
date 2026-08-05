<!DOCTYPE html>

<html>
<?php 
    include('conexao.php');
    include('gestaoAmbientes/widgetOcupacao.php');
?>
<div class="mb-4">
    <h5 class="mb-3">
        <center>Atualizações de versão:
       </center>
    </h5>
</div>
<div class="table-responsive">
        <table class="table table-striped table-hover mb-0"
               style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
            <thead style="background:#3C60A7;color:#fff">
                <tr>
                   <th>Versão intranet: 3.4</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>Mídias para painel:</b> Adicionadas mídias para o painel de ocupação.</td>
                </tr>
                <tr>
                    <td><b>Consulta Horários:</b> adicionada turmas de aperfeiçoamento no registro, adicionadas férias da turma e férias do instrutor.</td>
                </tr>
                <tr>
                    <td><b>Meu Horário:</b> Instrutor vê suas férias agendadas agora.</td>
                </tr>
                <tr>
                    <td><b>Painel Docentes:</b> lista turmas de aperfeiçoamento, turmas que estao de férias, se o instrutor de férias não aparece como disponível e adicionado botão de listar todas as inconsistências.</td>
                </tr>
                <tr>
                    <td><b>Inconsistências:</b> Lista e filtra as inconsistências no calendário para facilitar ajuste. Podem ser impressas.</td>
                </tr>
                <tr>
                    <td><b>Ocupação de espaços:</b> Gráfico de ocupação de espaços - salas e laboratórios.</td>
                </tr>
            </tbody>
</table>

</html>