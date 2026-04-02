<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../../index.php');
    exit;
}

include("../conexao.php");

$logado = $_SESSION['user'];
$nivel  = $_SESSION['group'];

$data        = $_POST['dataInp']   ?? '';
$periodo     = $_POST['periodo']   ?? '';
$hInicio     = $_POST['horainicio']?? '';
$hFim        = $_POST['horafim']   ?? '';
$turma       = $_POST['turma']     ?? '';
$descricao   = $_POST['descricao'] ?? '';
$lab         = $_POST['lab']       ?? '';
$solicitante = $_POST['solicitante']?? '';
$tipo        = $_POST['tipo']      ?? 'aula';

// Valor do campo turno — pode ser simples ("manha") ou composto ("manha+tarde")
$turnoRaw = $_POST['turno'] ?? '';

/* ── HORÁRIOS FIXOS POR TURNO ── */
$horariosTurno = [
    'manha' => ['inicio' => '07:00', 'fim' => '12:20'],
    'tarde' => ['inicio' => '13:00', 'fim' => '17:30'],
    'noite' => ['inicio' => '18:00', 'fim' => '22:30'],
    'dia'   => ['inicio' => '06:00', 'fim' => '23:00'],
];

$ordemTurnos = ['manha', 'tarde', 'noite'];

/* ── PROCESSA O TURNO ── */

if($periodo == 'todo' || strpos($turnoRaw, '+') !== false || $turnoRaw === 'dia'){

    // Turno composto (ex: "manha+tarde") ou dia inteiro
    if(strpos($turnoRaw, '+') !== false){

        $partes   = explode('+', $turnoRaw);
        // Ordena pela sequência natural manhã→tarde→noite
        $ordenados = array_filter($ordemTurnos, function($t) use ($partes){
            return in_array($t, $partes);
        });
        $ordenados = array_values($ordenados);

        if(count($ordenados) >= 2){
            $primeiro = $ordenados[0];
            $ultimo   = $ordenados[count($ordenados)-1];
            $hInicio  = $horariosTurno[$primeiro]['inicio'];
            $hFim     = $horariosTurno[$ultimo]['fim'];
        }

    } elseif(isset($horariosTurno[$turnoRaw])){
        // Turno simples + "todo o turno"
        $hInicio = $horariosTurno[$turnoRaw]['inicio'];
        $hFim    = $horariosTurno[$turnoRaw]['fim'];
    }
}

/* ── VALIDAÇÕES ── */

if($periodo == 'parcial' && (empty($hInicio) || empty($hFim))){
    header("Location: laboratorios.php?lab={$lab}&erro=1");
    exit;
}

if($periodo == 'todo' && empty($turnoRaw)){
    header("Location: laboratorios.php?lab={$lab}&erro=2");
    exit;
}

if(empty($hInicio) || empty($hFim)){
    header("Location: laboratorios.php?lab={$lab}&erro=1");
    exit;
}

/* ── VERIFICA CONFLITO ── */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE laboratorio = ?
      AND data = ?
      AND (? < horarioFim)
      AND (? > horarioInicio)
");
$stmt->execute([$lab, $data, $hInicio, $hFim]);

if($stmt->fetchColumn() > 0){
    header("Location: laboratorios.php?lab={$lab}&erro=3");
    exit;
}

/* ── INSERE ── */

try{
    $stmt = $pdo->prepare("
        INSERT INTO reservas
            (data, horarioInicio, horarioFim, solicitante, laboratorio, turma, aprovado, descricao, tipo)
        VALUES (?,?,?,?,?,?,0,?,?)
    ");
    $stmt->execute([$data, $hInicio, $hFim, $solicitante, $lab, $turma, $descricao, $tipo]);
    header("Location: laboratorios.php?lab={$lab}&ok=1");

} catch(PDOException $e){
    header("Location: laboratorios.php?lab={$lab}&erro=4");
}
?>