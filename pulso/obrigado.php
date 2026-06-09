<?php
// pulso/obrigado.php — Confirmação de envio
include('../conexao.php');

$slug = $_GET['unidade'] ?? '';

// Busca total de participação para exibir contador motivacional
$total = 0;
if ($slug) {
    $stmt = $pdo->prepare("
        SELECT pp.total
        FROM pulso_participacao pp
        INNER JOIN pulso_ciclos c ON c.id = pp.ciclo_id
        INNER JOIN pulso_unidades u ON u.id = c.unidade_id
        WHERE u.slug = ? AND c.status = 'aberto'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resposta enviada — PulsoSENAI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <style>
        :root {
            --azul:    #164194;
            --laranja: #E84910;
            --verde:   #1a9e4a;
            --cinza-f: #F4F6FA;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cinza-f);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .pulso-header {
            background: var(--azul);
            padding: 14px 24px;
            display: flex; align-items: center; gap: 10px;
        }
                        
        .hero {
            background: linear-gradient(135deg, #0d8c3c 0%, #16a34a 100%);
            padding: 64px 24px;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 30%, rgba(255,255,255,.08) 0%, transparent 50%);
        }
        .check-circle {
            width: 80px; height: 80px;
            background: rgba(255,255,255,.15);
            border: 3px solid rgba(255,255,255,.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            position: relative; z-index: 1;
        }
        .check-circle .fa { font-size: 36px; color: #fff; }
        .hero h1 {
            font-size: clamp(24px, 4vw, 34px);
            font-weight: 700;
            margin-bottom: 12px;
            position: relative; z-index: 1;
        }
        .hero p {
            font-size: 15px;
            color: rgba(255,255,255,.85);
            max-width: 420px;
            margin: 0 auto;
            line-height: 1.6;
            position: relative; z-index: 1;
        }

        .content {
            max-width: 540px;
            margin: -32px auto 48px;
            position: relative; z-index: 2;
            padding: 0 20px;
        }

        .card-branco {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(22,65,148,.1);
            overflow: hidden;
        }
        .card-topo {
            height: 4px;
            background: linear-gradient(90deg, var(--verde), #22c55e, var(--laranja));
        }
        .card-inner { padding: 32px 32px 36px; }

        /* CONTADOR */
        .contador-bloco {
            text-align: center;
            padding: 24px;
            background: #f0faf4;
            border-radius: 10px;
            margin-bottom: 24px;
        }
        .contador-num {
            font-size: 52px;
            font-weight: 800;
            color: var(--verde);
            line-height: 1;
            display: block;
        }
        .contador-label {
            font-size: 14px;
            color: #555;
            margin-top: 6px;
        }

        /* COMPROMISSO */
        .compromisso {
            background: #eef3fd;
            border: 1px solid #c8d9f5;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        .compromisso h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--azul);
            margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .compromisso ul {
            margin: 0; padding: 0;
            list-style: none;
        }
        .compromisso ul li {
            font-size: 13px;
            color: #444;
            padding: 5px 0;
            display: flex; align-items: center; gap: 8px;
            border-bottom: 1px solid #d8e4f5;
        }
        .compromisso ul li:last-child { border-bottom: none; }
        .compromisso ul li .fa { color: var(--azul); width: 16px; }

        /* AÇÕES */
        .acoes { display: flex; flex-direction: column; gap: 12px; }
        .btn-status {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 13px;
            background: var(--azul);
            color: #fff;
            font-size: 14px; font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-status:hover { background: #1a52c4; color: #fff; text-decoration: none; }
        .btn-voltar {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 13px;
            background: transparent;
            color: #888;
            font-size: 14px;
            border: 1.5px solid #dde3ef;
            border-radius: 10px;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-voltar:hover {
            border-color: var(--azul);
            color: var(--azul);
            text-decoration: none;
        }

        footer {
            margin-top: auto;
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: #aaa;
        }
    </style>
</head>
<body>

    <header class="pulso-header">
        <img src="pulso.png" alt="PulsoSENAI" style="height:40px;">
    </header>

    <div class="hero">
        <div class="check-circle">
            <i class="fa fa-check"></i>
        </div>
        <h1>Resposta enviada com sucesso!</h1>
        <p>Sua contribuição foi registrada de forma totalmente anônima. Obrigado por participar.</p>
    </div>

    <div class="content">
        <div class="card-branco">
            <div class="card-topo"></div>
            <div class="card-inner">

                <?php if ($total > 0): ?>
                <div class="contador-bloco">
                    <span class="contador-num"><?= $total ?></span>
                    <div class="contador-label">
                        <?= $total === 1 ? 'pessoa respondeu' : 'pessoas já responderam' ?>
                        neste ciclo
                    </div>
                </div>
                <?php endif; ?>

                <div class="compromisso">
                    <h4><i class="fa fa-calendar-check-o"></i> Nosso compromisso com você</h4>
                    <ul>
                        <li>
                            <i class="fa fa-clock-o"></i>
                            <span>Em até <strong>7 dias</strong>, os resultados compilados chegam para toda a equipe</span>
                        </li>
                        <li>
                            <i class="fa fa-tasks"></i>
                            <span>Cada ponto levantado gera um item com <strong>responsável e prazo</strong></span>
                        </li>
                        <li>
                            <i class="fa fa-eye"></i>
                            <span>O <strong>status de cada compromisso</strong> fica público para a equipe acompanhar</span>
                        </li>
                    </ul>
                </div>

                <div class="acoes">
                    <a href="status.php" class="btn-status">
                        <i class="fa fa-bar-chart"></i>
                        Ver painel público de compromissos
                    </a>
                    <a href="index.php" class="btn-voltar">
                        <i class="fa fa-home"></i>
                        Voltar ao início
                    </a>
                </div>

            </div>
        </div>
    </div>

    <footer>
        PulsoSENAI &mdash; Sistema de Escuta Contínua &bull; SENAI Minas Gerais
    </footer>

</body>
</html>