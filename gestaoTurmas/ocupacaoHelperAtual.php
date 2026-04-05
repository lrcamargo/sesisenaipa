<?php
// Garante fuso horário correto em todas as operações de data
date_default_timezone_set('America/Sao_Paulo');

/*
 * ocupacaoHelper.php
 * Lógica de ocupação compartilhada entre painel.php e painel_logado.php.
 *
 * Dias da semana — bitmask (TINYINT):
 *   Seg=1  Ter=2  Qua=4  Qui=8  Sex=16  Sáb=32
 *   Exemplos: Seg-Sex=31  SóSáb=32  Ter/Qui=10  Todos=63
 *
 * Verificação: ($diasSemana & $valorDia) > 0
 */

/* ── Mapa dia da semana → bit ── */
const DIAS_BIT = [
    'seg' => 1,
    'ter' => 2,
    'qua' => 4,
    'qui' => 8,
    'sex' => 16,
    'sab' => 32,
];

/* Mapa date('w') → chave do bit (0=Dom, 1=Seg ... 6=Sáb) */
const DOW_MAP = [
    0 => null,   // Domingo — sem bit definido
    1 => 'seg',
    2 => 'ter',
    3 => 'qua',
    4 => 'qui',
    5 => 'sex',
    6 => 'sab',
];

/* ─────────────────────────────────────────────────────
   Retorna o bit do dia para uma data 'Y-m-d'.
   Retorna 0 se for domingo (sem aulas normalmente).
   ───────────────────────────────────────────────────── */
function bitDoDia(string $data): int {
    $dow = (int) date('w', strtotime($data));
    $chave = DOW_MAP[$dow] ?? null;
    if($chave === null) return 0;
    return DIAS_BIT[$chave];
}

/* ─────────────────────────────────────────────────────
   Verifica se a turma ocorre no dia (operação de bit).
   $diasSemana: valor TINYINT do banco
   $bit:        resultado de bitDoDia()
   ───────────────────────────────────────────────────── */
function turmaOcorreNoDia(int $diasSemana, int $bit): bool {
    if($bit === 0) return false;          // Domingo → nunca
    return ($diasSemana & $bit) > 0;     // Teste de bit
}

/* ─────────────────────────────────────────────────────
   Verifica sobreposição de dois intervalos de horário.
   ───────────────────────────────────────────────────── */
function sobreposicao(string $ini1, string $fim1, string $ini2, string $fim2): bool {
    return $ini1 < $fim2 && $fim1 > $ini2;
}

/* ─────────────────────────────────────────────────────
   Calcula a ocupação de todos os ambientes × turnos
   para uma data específica.

   Retorna: [idLaboratorio][turno] => [
       'status'      => 'ocupado'|'aguardando'|'na-sala'|'livre'|'vazio'
       'turma'       => string
       'sub'         => string
       'solicitante' => string
   ]
   ───────────────────────────────────────────────────── */
function calcularOcupacao(
    PDO    $pdo,
    string $data,
    array  $ambientes,
    array  $turnos
): array {

    $bit = bitDoDia($data);   // bit do dia da semana desta data

    /* ── Reservas aprovadas/aguardando do dia ── */
    $stmtR = $pdo->prepare("
        SELECT r.laboratorio, r.horarioInicio, r.horarioFim,
               r.turma, r.aprovado, u.nome AS solicitante
        FROM reservas r
        JOIN usuarios u ON u.id = r.solicitante
        WHERE r.data = ? AND r.aprovado IN (0,1)
    ");
    $stmtR->execute([$data]);
    $reservas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    /* ── Vínculos turma→sala ── */
    $stmtV = $pdo->prepare("SELECT * FROM turma_sala");
    $stmtV->execute();
    $vinculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    /*
     * vincIdxSala[idSala][turno] = [
     *   'codigoTurma' => string,
     *   'diasSemana'  => int (bitmask),
     * ]
     * Expande 'integral' para 'manha' e 'tarde'.
     */
    $vincIdxSala = [];
    foreach($vinculos as $v){
        $turnos_v = $v['turno'] === 'integral' ? ['manha','tarde'] : [$v['turno']];
        foreach($turnos_v as $tv){
            $vincIdxSala[$v['idSala']][$tv] = [
                'codigoTurma' => $v['codigoTurma'],
                'diasSemana'  => (int)($v['diasSemana'] ?? 31),
            ];
        }
    }

    /*
     * turmaReservas[codigoTurma] = [ reserva, ... ]
     * Para verificar se a turma está em laboratório.
     */
    $turmaReservas = [];
    foreach($reservas as $r){
        $t = $r['turma'] ?? '';
        if($t === '') continue;
        $turmaReservas[$t][] = $r;
    }

    /* ── Calcula status por ambiente × turno ── */
    $resultado = [];

    foreach($ambientes as $amb){
        $id = $amb['idLaboratorio'];
        $resultado[$id] = [];

        foreach($turnos as $turnoKey => $turno){
            $tIni = $turno['inicio'];
            $tFim = $turno['fim'];

            /* 1. Reserva direta neste ambiente/turno */
            $reservaDireta = null;
            foreach($reservas as $r){
                if($r['laboratorio'] == $id
                    && sobreposicao($r['horarioInicio'], $r['horarioFim'], $tIni, $tFim)){
                    $reservaDireta = $r;
                    break;
                }
            }

            if($reservaDireta){
                $resultado[$id][$turnoKey] = [
                    'status'      => $reservaDireta['aprovado']==1 ? 'ocupado' : 'aguardando',
                    'turma'       => $reservaDireta['turma'],
                    'sub'         => $reservaDireta['aprovado']==1 ? 'Reservado' : 'Aguardando',
                    'solicitante' => $reservaDireta['solicitante'] ?? '',
                ];
                continue;
            }

            /* 2. Sala com turma vinculada */
            if($amb['temSala'] && isset($vincIdxSala[$id][$turnoKey])){
                $vinc     = $vincIdxSala[$id][$turnoKey];
                $codTurma = $vinc['codigoTurma'];
                $diasBit  = $vinc['diasSemana'];

                /* Verifica se a turma ocorre neste dia (operação de bit) */
                if(!turmaOcorreNoDia($diasBit, $bit)){
                    $resultado[$id][$turnoKey] = [
                        'status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''
                    ];
                    continue;
                }

                /* Verifica se a turma está reservada em laboratório
                   durante qualquer parte deste turno */
                $turmaNoLab = false;
                if(isset($turmaReservas[$codTurma])){
                    foreach($turmaReservas[$codTurma] as $r){
                        if(sobreposicao($r['horarioInicio'], $r['horarioFim'], $tIni, $tFim)){
                            $turmaNoLab = true;
                            break;
                        }
                    }
                }

                if($turmaNoLab){
                    $resultado[$id][$turnoKey] = [
                        'status'      => 'livre',
                        'turma'       => 'Livre',
                        'sub'         => "{$codTurma} está no laboratório",
                        'solicitante' => '',
                    ];
                } else {
                    $resultado[$id][$turnoKey] = [
                        'status'      => 'na-sala',
                        'turma'       => $codTurma,
                        'sub'         => 'Na sala',
                        'solicitante' => '',
                    ];
                }
                continue;
            }

            /* 3. Vazio */
            $resultado[$id][$turnoKey] = [
                'status'=>'vazio','turma'=>'','sub'=>'','solicitante'=>''
            ];
        }
    }

    return $resultado;
}
?>