<?php
/*
 * horarios_helper.php
 * Funções para consultar o cache de horários gerado pelo script Python.
 *
 * Uso:
 *   require_once 'horarios_helper.php';
 *   $info = horario_get('HT-MEP-01-M-26-13310', '2026-04-07');
 *   // Retorna: ['instrutor'=>'Patrick', 'uc'=>'Metrologia', 'nome_turma'=>'...']
 *   // ou null se não encontrado
 */

define('HORARIOS_CACHE', __DIR__ . '/../data/horariosCache.json');

/* Cache em memória — carregado uma vez por request */
$_horarios_cache_dados  = null;
$_horarios_cache_gerado = null;

/**
 * Carrega o cache JSON em memória (lazy, uma vez por request).
 */
function horarios_carregar(): void {
    global $_horarios_cache_dados, $_horarios_cache_gerado;
    if($_horarios_cache_dados !== null) return;

    if(!file_exists(HORARIOS_CACHE)){
        $_horarios_cache_dados  = [];
        $_horarios_cache_gerado = null;
        return;
    }

    $json = file_get_contents(HORARIOS_CACHE);
    $parsed = json_decode($json, true);

    if(!$parsed || !isset($parsed['dados'])){
        $_horarios_cache_dados  = [];
        $_horarios_cache_gerado = null;
        return;
    }

    $_horarios_cache_dados  = $parsed['dados'];
    $_horarios_cache_gerado = $parsed['gerado_em'] ?? null;
}

/**
 * Retorna informações do instrutor para uma turma em uma data.
 *
 * @param string $codigoTurma  Ex: 'HT-MEP-01-M-26-13310'
 * @param string $data         Formato 'Y-m-d'
 * @return array|null  ['instrutor', 'uc', 'nome_turma'] ou null
 */
function horario_get(string $codigoTurma, string $data): ?array {
    global $_horarios_cache_dados;
    horarios_carregar();
    return $_horarios_cache_dados[$data][$codigoTurma] ?? null;
}

/**
 * Retorna o instrutor de uma turma em uma data (atalho).
 *
 * @return string|null
 */
function horario_instrutor(string $codigoTurma, string $data): ?string {
    $info = horario_get($codigoTurma, $data);
    return $info['instrutor'] ?? null;
}

/**
 * Retorna quando o cache foi gerado.
 *
 * @return string|null  Ex: '2026-04-07T10:30:00'
 */
function horarios_gerado_em(): ?string {
    global $_horarios_cache_gerado;
    horarios_carregar();
    return $_horarios_cache_gerado;
}

/**
 * Verifica se o cache está desatualizado (mais de X minutos).
 *
 * @param int $maxMinutos  Padrão: 10
 */
function horarios_desatualizado(int $maxMinutos = 10): bool {
    $gerado = horarios_gerado_em();
    if(!$gerado) return true;
    $ts = strtotime($gerado);
    if(!$ts) return true;
    return (time() - $ts) > ($maxMinutos * 60);
}
?>