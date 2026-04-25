<?php
/**
 * aprovarReservas.php
 * ===================
 * Aprova automaticamente reservas que estão "Aguardando" (aprovado = 0)
 * cujo horário de início seja no próximo turno calendário.
 *
 * Regra: aprova reservas do dia atual cujo horário de início está
 * entre AGORA e o final do próximo turno, ou seja:
 *   - Se são 12:00 → aprova reservas de tarde (início >= 13:00)
 *   - Se são 07:00 → aprova reservas de manhã (início >= 07:30)
 *   - Se são 17:30 → aprova reservas de noite (início >= 19:00)
 *
 * Na prática: aprova todas as reservas do dia cujo turno começa
 * nas próximas 2 horas a partir de agora.
 *
 * Uso via cron (a cada 30 minutos):
 *   30 * * * * /usr/bin/php /var/www/html/scripts/aprovarReservas.php >> /var/www/html/logs/aprovacao_reservas.log 2>&1
 *
 * Ou manualmente:
 *   php /var/www/html/scripts/aprovarReservas.php
 *   php /var/www/html/scripts/aprovarReservas.php --simular   (não salva, só exibe)
 */

// Garante execução apenas via CLI ou cron (não via browser)
if(php_sapi_name() !== 'cli'){
    http_response_code(403);
    echo "Acesso negado. Este script é executado via CLI/cron.\n";
    exit(1);
}

require_once __DIR__ . '/../conexao.php';

date_default_timezone_set('America/Sao_Paulo');

$simular = in_array('--simular', $argv ?? []);
$agora   = new DateTime();
$hoje    = $agora->format('Y-m-d');
$horaAtual = $agora->format('H:i:s');

/* ── Turnos da instituição ── */
$turnos = [
    'manha' => ['inicio' => '07:30:00', 'fim' => '12:30:00', 'label' => 'Manhã'],
    'tarde' => ['inicio' => '13:00:00', 'fim' => '18:00:00', 'label' => 'Tarde'],
    'noite' => ['inicio' => '19:00:00', 'fim' => '23:00:00', 'label' => 'Noite'],
];

/*
 * Determina qual turno aprovar:
 * Aprova reservas cujo horário de início está entre AGORA e AGORA+2h.
 * Isso cobre o cenário de cron rodando a cada 30min:
 *   12:00 → pega turno tarde (13:00-18:00) ✓
 *   12:30 → pega turno tarde (13:00-18:00) ✓
 *   07:00 → pega turno manhã (07:30-12:30) ✓
 */
$limite  = (clone $agora)->modify('+2 hours')->format('H:i:s');

log_("=== Aprovação automática de reservas ===");
log_("Hora atual: {$horaAtual} | Janela: até {$limite}");
if($simular) log_("*** MODO SIMULAÇÃO — nenhuma alteração será salva ***");

/* ── Busca reservas aguardando dentro da janela de tempo ── */
$stmt = $pdo->prepare("
    SELECT r.idReserva, r.laboratorio, r.data, r.horarioInicio, r.horarioFim,
           r.turma, u.nome AS solicitante, l.nome AS laboratorioNome
    FROM reservas r
    JOIN usuarios u ON u.id = r.solicitante
    JOIN laboratorios l ON l.idLaboratorio = r.laboratorio
    WHERE r.data       = :hoje
      AND r.aprovado   = 0
      AND r.horarioInicio >= :horaAtual
      AND r.horarioInicio <= :limite
    ORDER BY r.horarioInicio ASC
");
$stmt->execute([
    ':hoje'      => $hoje,
    ':horaAtual' => $horaAtual,
    ':limite'    => $limite,
]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(empty($reservas)){
    log_("Nenhuma reserva aguardando na janela [{$horaAtual} - {$limite}].");
    exit(0);
}

log_(count($reservas) . " reserva(s) encontrada(s) para aprovar:");

$aprovadas = 0;
$erros     = 0;

foreach($reservas as $r){
    $info = sprintf(
        "  [ID %d] %s | %s→%s | %s | Solicitante: %s",
        $r['idReserva'], $r['laboratorioNome'],
        $r['horarioInicio'], $r['horarioFim'],
        $r['turma'] ?: '—',
        $r['solicitante']
    );
    log_($info);

    if($simular){
        log_("    → SIMULAÇÃO: seria aprovada.");
        $aprovadas++;
        continue;
    }

    try {
        $upd = $pdo->prepare("UPDATE reservas SET aprovado = 1 WHERE idReserva = ? AND aprovado = 0");
        $upd->execute([$r['idReserva']]);
        if($upd->rowCount() > 0){
            log_("    → Aprovada com sucesso.");
            $aprovadas++;
        } else {
            log_("    → Já aprovada por outro processo (sem alteração).");
        }
    } catch(PDOException $e){
        log_("    → ERRO: " . $e->getMessage());
        $erros++;
    }
}

log_("─────────────────────────────────────────");
log_("Resultado: {$aprovadas} aprovada(s) | {$erros} erro(s).");
log_("=========================================");
exit($erros > 0 ? 1 : 0);


function log_(string $msg): void {
    $ts = date('[d/m/Y H:i:s]');
    echo "{$ts} {$msg}\n";
}