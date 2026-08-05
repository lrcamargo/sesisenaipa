<?php
/*
 * instrutoresContainer.php
 * Dashboard do perfil Secretaria / Administrativo.
 * Exibe eventos dos próximos 30 dias:
 *   - reservas com tipo = 'evento'
 *   - reservas no ambiente com descricao = 'TEATRO'
 * (união dos dois critérios)
 */

// $pdo já está disponível — incluído pelo main.php
include('./conexao.php');
$stmt = $pdo->prepare("
    SELECT
        r.idReserva,
        r.data,
        r.horarioInicio,
        r.horarioFim,
        r.turma,
        r.descricao,
        r.aprovado,
        l.nome        AS ambiente,
        u.nome        AS solicitante
    FROM reservas r
    JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
    JOIN usuarios u     ON u.id = r.solicitante
    WHERE r.data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND r.aprovado IN (0, 1)
      AND (
            r.tipo = 'evento'
         OR UPPER(l.descricao) = 'TEATRO'
      )
    ORDER BY r.data ASC, r.horarioInicio ASC
");
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$diasSem = [
    'Sunday'    => 'Domingo',
    'Monday'    => 'Segunda-feira',
    'Tuesday'   => 'Terça-feira',
    'Wednesday' => 'Quarta-feira',
    'Thursday'  => 'Quinta-feira',
    'Friday'    => 'Sexta-feira',
    'Saturday'  => 'Sábado',
];
?>

<div class="mb-4">
    <br/>
    <center><a href='https://fiemg.sharepoint.com/:fl:/g/contentstorage/CSP_99e6ac63-e547-412f-abf5-4c35b607c621/IQDR0ol76DHvQIn21LmyU00tATBBPEv--s-Tnaiuvcgo6L4?e=yHfymN&nav=cz0lMkZjb250ZW50c3RvcmFnZSUyRkNTUF85OWU2YWM2My1lNTQ3LTQxMmYtYWJmNS00YzM1YjYwN2M2MjEmZD1iJTIxWTZ6bW1VZmxMMEdyOVV3MXRnZkdJWnZiN1NRSF8yUkZqNktuN2lSNVVjaFZrX2FxaktKU1Q3QlZQWTdSMUFMZCZmPTAxMzUzS0lMV1IyS0VYWDJCUjU1QUlUNVdVWEdaRkdUSk4mYz0lMkYmYT1Mb29wQXBwJnA9JTQwZmx1aWR4JTJGbG9vcC1wYWdlLWNvbnRhaW5lciZ4PSU3QiUyMnclMjIlM0ElMjJUMFJUVUh4bWFXVnRaeTV6YUdGeVpYQnZhVzUwTG1OdmJYeGlJVmsyZW0xdFZXWnNUREJIY2psVmR6RjBaMlpIU1ZwMllqZFRVVWhmTWxKR2FqWkxiamRwVWpWVlkyaFdhMTloY1dwTFNsTlVOMEpXVUZrM1VqRkJUR1I4TURFek5UTkxTVXhSTWxKTlJsWmFWa1ZZVEVaRk1rRlhNMG8wTnpSR1dEWlZVZyUzRCUzRCUyMiUyQyUyMmklMjIlM0ElMjI1YjgyMjFmZS1kODNmLTQyNDEtOWRlYS1hOTNjNWM5NmFkY2YlMjIlN0Q%3D'><h3>SISBIA<h3></a></center>
    <br/>
    <h5 class="mb-3">
        <center><i class="fa fa-calendar" style="color:#3C60A7"></i>
        Eventos — próximos 30 dias</center>
    </h5>

    <?php if(empty($eventos)){ ?>
        <div class="alert alert-info">
            Nenhum evento agendado para os próximos 30 dias.
        </div>

    <?php } else { ?>

        <div class="table-responsive">
        <table class="table table-striped table-hover mb-0"
               style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
            <thead style="background:#3C60A7;color:#fff">
                <tr>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Ambiente</th>
                    <th>Turma / Grupo</th>
                    <th>Solicitante</th>
                    <th>Descrição</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($eventos as $ev):
                $dataFmt  = date('d/m/Y', strtotime($ev['data']));
                $diaNome  = $diasSem[date('l', strtotime($ev['data']))] ?? '';
                $hIni     = substr($ev['horarioInicio'], 0, 5);
                $hFim     = substr($ev['horarioFim'],    0, 5);
                $ehHoje   = ($ev['data'] === date('Y-m-d'));

                if($ev['aprovado'] == 1){
                    $statusLabel = '<span class="badge badge-success">Aprovado</span>';
                } else {
                    $statusLabel = '<span class="badge badge-warning text-dark">Aguardando</span>';
                }
            ?>
                <tr <?php echo $ehHoje ? "style='background:#fff9e6'" : ''; ?>>
                    <td>
                        <strong><?php echo $dataFmt; ?></strong><br>
                        <small class="text-muted"><?php echo $diaNome; ?></small>
                        <?php if($ehHoje) echo '<br><span class="badge badge-primary">Hoje</span>'; ?>
                    </td>
                    <td><?php echo $hIni; ?> – <?php echo $hFim; ?></td>
                    <td><?php echo htmlspecialchars($ev['ambiente']); ?></td>
                    <td><?php echo htmlspecialchars($ev['turma'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($ev['solicitante']); ?></td>
                    <td><?php echo htmlspecialchars($ev['descricao'] ?: '—'); ?></td>
                    <td><?php echo $statusLabel; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <small class="text-muted mt-2 d-block">
            <?php echo count($eventos); ?> evento(s) encontrado(s).
            Exibindo apenas reservas aprovadas ou aguardando aprovação.
        </small>

    <?php } ?>
</div>