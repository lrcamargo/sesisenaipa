<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION['sLogin'])){
    header('location:../../index.php');
    exit;
}

include("../conexao.php");

/* ── RECEBE OS CAMPOS DO FORMULÁRIO ── */

$dataInicio  = $_POST['dataInicio']  ?? '';   // yyyy-mm-dd (hidden ISO)
$dataFim     = $_POST['dataFim']     ?? '';   // yyyy-mm-dd (hidden ISO)
$horaInicio  = $_POST['horaInicio']  ?? '';   // HH:MM
$horaFim     = $_POST['horaFim']     ?? '';   // HH:MM
$diasSemana  = $_POST['diasSemana']  ?? [];   // array int (1=seg … 5=sex, padrão JS getDay())
$turma       = $_POST['turma']       ?? '';
$lab         = $_POST['lab']         ?? '';
$solicitante = $_POST['solicitante'] ?? '';
$descricao   = $_POST['descricao']   ?? '';   // texto livre do usuário
$tipo        = $_POST['tipo']        ?? 'aula';

/* ── VALIDAÇÕES BÁSICAS ── */

// Campos obrigatórios ausentes → erro=1
if(empty($dataInicio) || empty($dataFim) || empty($horaInicio) || empty($horaFim)){
    header("Location: laboratorios.php?lab=".$lab."&erro=1");
    exit;
}

// Nenhum dia da semana marcado → erro=2
if(empty($diasSemana)){
    header("Location: laboratorios.php?lab=".$lab."&erro=2");
    exit;
}

/* ── GERA ID DO GRUPO RECORRENTE ── */
// Formato: REC-xxxxxxxx (8 hex aleatórios → 16^8 = ~4 bilhões de combinações)
// Salvo no início do campo observacao: "[REC-abc12345] texto do usuário"
// Para excluir todas as reservas do grupo basta:
//   DELETE FROM reservas WHERE observacao LIKE '[REC-abc12345]%' AND laboratorio = ?

$recId      = 'REC-' . bin2hex(random_bytes(4));  // ex: REC-a1b2c3d4
$observacao = '[' . $recId . ']' . (trim($descricao) !== '' ? ' ' . trim($descricao) : '');

/* ── GERA A LISTA DE DATAS DO INTERVALO ── */
// getDay() PHP via format('w'): 0=dom, 1=seg, 2=ter, 3=qua, 4=qui, 5=sex, 6=sab
// Igual ao JS — os valores dos checkboxes são 1=seg … 5=sex

$datas = [];

$dtAtual   = new DateTime($dataInicio);
$dtFim     = new DateTime($dataFim);
$dtFim->modify('+1 day'); // DatePeriod é exclusivo no limite final

$intervalo = new DateInterval('P1D');
$periodo   = new DatePeriod($dtAtual, $intervalo, $dtFim);

foreach($periodo as $dia){
    $diaSemana = (int) $dia->format('w');
    if(in_array($diaSemana, array_map('intval', $diasSemana))){
        $datas[] = $dia->format('Y-m-d');
    }
}

// Intervalo válido mas nenhuma data bate com os dias marcados
if(empty($datas)){
    header("Location: laboratorios.php?lab=".$lab."&erro=2");
    exit;
}

/* ── PROCESSA CADA DATA INDIVIDUALMENTE ── */
// Tenta inserir cada data:
//   - sem conflito → INSERT dentro de transaction
//   - com conflito → registra na lista de falhas, segue para a próxima
// Ao final redireciona com resumo: inseridas + lista de conflitos

$stmtConflito = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservas
    WHERE laboratorio = ?
      AND data = ?
      AND (? < horarioFim)
      AND (? > horarioInicio)
");

$stmtInsert = $pdo->prepare("
    INSERT INTO reservas
        (data, horarioInicio, horarioFim, solicitante, laboratorio, turma, aprovado, observacao, tipo)
    VALUES
        (?, ?, ?, ?, ?, ?, 0, ?, ?)
");

$inseridas = 0;
$conflitos = []; // datas no formato dd/mm/yyyy para exibir ao usuário

foreach($datas as $data){

    // Verifica conflito para esta data
    $stmtConflito->execute([$lab, $data, $horaInicio, $horaFim]);
    $conflito = $stmtConflito->fetchColumn();

    if($conflito > 0){
        // Converte yyyy-mm-dd → dd/mm/yyyy para a mensagem
        $partes     = explode('-', $data);
        $conflitos[] = $partes[2] . '/' . $partes[1] . '/' . $partes[0];
        continue; // pula para a próxima data
    }

    // Sem conflito: insere
    try{
        $pdo->beginTransaction();

        $stmtInsert->execute([
            $data,
            $horaInicio,
            $horaFim,
            $solicitante,
            $lab,
            $turma,
            $observacao,   // "[REC-abc12345] descrição do usuário"
            $tipo
        ]);

        $pdo->commit();
        $inseridas++;

    } catch(PDOException $e){
        $pdo->rollBack();
        // Falha de BD: trata como conflito para não silenciar o erro
        $partes      = explode('-', $data);
        $conflitos[] = $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
}

/* ── REDIRECIONA COM RESUMO ── */
// ok=2 + &inseridas=X + &rec_id=REC-xxxx + &conflitos=dd/mm,dd/mm,...
// O index.php monta a mensagem com esses parâmetros.

$params  = "lab=" . $lab;
$params .= "&ok=2";
$params .= "&inseridas=" . $inseridas;
$params .= "&rec_id=" . urlencode($recId);

if(!empty($conflitos)){
    $params .= "&conflitos=" . urlencode(implode(',', $conflitos));
}

header("Location: laboratorios.php?" . $params);
exit;
?>