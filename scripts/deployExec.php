<?php
/*
 * deploy_exec.php
 * Backend chamado via fetch() pelo deploy.php.
 * Nunca deve ser acessado diretamente pelo browser.
 *
 * Ações:
 *   status → compara hash local vs remoto para main e dev
 *   deploy → faz git pull na branch escolhida com backup dos protegidos
 */

session_start();
header('Content-Type: application/json');

// Valida sessão
if(!isset($_SESSION['sLogin'])){
    http_response_code(403);
    echo json_encode(['erro' => 'Sessão inválida.']);
    exit;
}

$nivel     = $_SESSION['group'];
$logado    = $_SESSION['user'];
$nivelNorm = strtolower(str_replace('.', '', $nivel));

if(!in_array($nivelNorm, ['admin', 'administrator', 'sup tecnica'])){
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.']);
    exit;
}

/* ── CONFIGURAÇÕES ─────────────────────────────────────────── */

// Caminho absoluto do repositório no servidor
define('REPO_PATH', '/var/www/html');

// Branches permitidas
$branchesPermitidas = ['main', 'dev'];

// Arquivos que NUNCA serão sobrescritos pelo git pull
// Caminhos relativos à raiz do repositório
$arquivosProtegidos = [
    'conexao.php',
    'config.php',
];

/* ── LÊ O BODY JSON ── */
$input  = json_decode(file_get_contents('php://input'), true);
$acao   = $input['acao']   ?? '';
$branch = $input['branch'] ?? '';

/* ── FUNÇÃO: executa comando git no diretório do repo ── */
function gitCmd(string $cmd): array {
    $out  = [];
    $code = 0;
    exec('cd ' . escapeshellarg(REPO_PATH) . ' && ' . $cmd . ' 2>&1', $out, $code);
    return ['saida' => implode("\n", $out), 'codigo' => $code];
}

/* ══════════════════════════════════════════════════════
   AÇÃO: STATUS
   Compara local vs remoto com detecção de direção:
     - atualizado   : hashes iguais
     - atras        : remoto tem commits que o local não tem (precisa de pull)
     - adiantado    : local tem commits que o remoto não tem (precisa de push)
     - divergente   : ambos têm commits exclusivos (merge/rebase necessário)
     - sem_branch   : branch não existe localmente ainda
   ══════════════════════════════════════════════════════ */
if($acao === 'status'){

    // Busca atualizações remotas sem aplicar nada
    gitCmd('git fetch origin');

    $resultado = [];

    foreach($branchesPermitidas as $b){

        $bEsc = escapeshellarg($b);

        // Verifica se a branch existe localmente
        $existeLocal = gitCmd('git rev-parse --verify ' . $bEsc . ' 2>/dev/null');
        if($existeLocal['codigo'] !== 0){
            $resultado[$b] = [
                'local'    => 'N/A',
                'remoto'   => 'N/A',
                'situacao' => 'sem_branch',
                'msg'      => 'Branch não existe localmente. Rode: git checkout -b ' . $b . ' origin/' . $b,
            ];
            continue;
        }

        // Verifica se a branch remota existe
        $existeRemoto = gitCmd('git rev-parse --verify origin/' . $bEsc . ' 2>/dev/null');
        if($existeRemoto['codigo'] !== 0){
            $resultado[$b] = [
                'local'    => trim(gitCmd('git rev-parse --short ' . $bEsc)['saida']),
                'remoto'   => 'N/A',
                'situacao' => 'sem_remoto',
                'msg'      => 'Branch existe localmente mas não foi enviada ao GitHub ainda. Rode: git push -u origin ' . $b,
            ];
            continue;
        }

        // Hashes para exibição
        $hashLocal  = trim(gitCmd('git rev-parse --short ' . $bEsc)['saida']);
        $hashRemoto = trim(gitCmd('git rev-parse --short origin/' . $bEsc)['saida']);

        // Conta commits que o REMOTO tem e o LOCAL não tem (local está atrás)
        $atras = (int) trim(gitCmd(
            'git rev-list --count ' . $bEsc . '..origin/' . $bEsc
        )['saida']);

        // Conta commits que o LOCAL tem e o REMOTO não tem (local está à frente)
        $adiantado = (int) trim(gitCmd(
            'git rev-list --count origin/' . $bEsc . '..' . $bEsc
        )['saida']);

        if($atras === 0 && $adiantado === 0){
            $situacao = 'atualizado';
            $msg      = 'Servidor sincronizado com o GitHub.';
        } elseif($atras > 0 && $adiantado === 0){
            $situacao = 'atras';
            $msg      = $atras . ' commit(s) do GitHub ainda não aplicado(s) no servidor. Use o botão Deploy para atualizar.';
        } elseif($adiantado > 0 && $atras === 0){
            $situacao = 'adiantado';
            $msg      = $adiantado . ' commit(s) no servidor ainda não enviado(s) ao GitHub. Rode git push quando estiver pronto.';
        } else {
            $situacao = 'divergente';
            $msg      = $adiantado . ' commit(s) locais e ' . $atras . ' commit(s) remotos exclusivos. É necessário merge ou rebase antes do deploy.';
        }

        $resultado[$b] = [
            'local'    => $hashLocal,
            'remoto'   => $hashRemoto,
            'situacao' => $situacao,
            'msg'      => $msg,
        ];
    }

    echo json_encode($resultado);
    exit;
}

/* ══════════════════════════════════════════════════════
   AÇÃO: DEPLOY
   1. Valida branch
   2. Backup dos arquivos protegidos
   3. git fetch + checkout + pull
   4. Restaura protegidos
   5. Retorna log completo
   ══════════════════════════════════════════════════════ */
if($acao === 'deploy'){

    if(!in_array($branch, $branchesPermitidas)){
        echo json_encode(['sucesso' => false, 'saida' => 'Branch inválida.']);
        exit;
    }

    $log = [];
    $log[] = '=== Deploy iniciado ===';
    $log[] = 'Branch   : ' . $branch;
    $log[] = 'Usuário  : ' . $logado;
    $log[] = 'Data/hora: ' . date('d/m/Y H:i:s');
    $log[] = str_repeat('-', 48);

    /* 1. Backup dos arquivos protegidos */
    $backups = [];
    foreach($arquivosProtegidos as $arquivo){
        $caminho = REPO_PATH . '/' . $arquivo;
        if(file_exists($caminho)){
            $backups[$arquivo] = file_get_contents($caminho);
            $log[] = '🔒 Backup: ' . $arquivo;
        }
    }

    /* 2. git fetch */
    $r = gitCmd('git fetch origin');
    $log[] = '';
    $log[] = '$ git fetch origin';
    $log[] = $r['saida'];

    /* 3. git checkout */
    $r = gitCmd('git checkout ' . escapeshellarg($branch));
    $log[] = '$ git checkout ' . $branch;
    $log[] = $r['saida'];

    if($r['codigo'] !== 0){
        // Restaura mesmo em caso de falha no checkout
        foreach($backups as $arquivo => $conteudo){
            file_put_contents(REPO_PATH . '/' . $arquivo, $conteudo);
        }
        echo json_encode(['sucesso' => false, 'saida' => implode("\n", $log)]);
        exit;
    }

    /* 4. git pull */
    $r = gitCmd('git pull origin ' . escapeshellarg($branch));
    $log[] = '$ git pull origin ' . $branch;
    $log[] = $r['saida'];
    $pullOk = ($r['codigo'] === 0);

    /* 5. Restaura arquivos protegidos (sempre, independente do resultado) */
    foreach($backups as $arquivo => $conteudo){
        file_put_contents(REPO_PATH . '/' . $arquivo, $conteudo);
        $log[] = '🔓 Restaurado: ' . $arquivo;
    }

    /* 6. Hash final após o pull */
    $hashFinal = gitCmd('git rev-parse --short HEAD');
    $log[] = '';
    $log[] = '📝 Versão atual: ' . trim($hashFinal['saida']);
    $log[] = str_repeat('-', 48);

    // Grava no log do servidor
    $logDir = REPO_PATH . '/logs';
    if(!is_dir($logDir)) mkdir($logDir, 0750, true);
    file_put_contents(
        $logDir . '/deploy.log',
        implode("\n", $log) . "\n\n",
        FILE_APPEND
    );

    echo json_encode([
        'sucesso' => $pullOk,
        'saida'   => implode("\n", $log),
    ]);
    exit;
}

/* Ação inválida */
echo json_encode(['sucesso' => false, 'saida' => 'Ação inválida.']);
exit;
?>