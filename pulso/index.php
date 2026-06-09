<?php
// pulso/index.php — Tela inicial pública do PulsoSENAI
// SEM login. SEM sessão. SEM identificação.
include('../conexao.php');

// Busca unidades ativas que têm ciclo aberto
$stmt = $pdo->query("
    SELECT u.id, u.nome, u.slug, c.id AS ciclo_id, c.titulo
    FROM pulso_unidades u
    INNER JOIN pulso_ciclos c ON c.unidade_id = u.id AND c.status = 'aberto'
    WHERE u.ativa = 1
    ORDER BY u.nome
");
$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PulsoSENAI — Pesquisa de Clima</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <style>
        :root {
            --azul:    #164194;
            --laranja: #E84910;
            --azul-c:  #008BD2;
            --cinza-f: #F4F6FA;
            --texto:   #1a1a2e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cinza-f);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        .pulso-header {
            background: var(--azul);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }
        /* logo substitui texto — CSS de marca removido */

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, var(--azul) 0%, #1a52c4 100%);
            color: #fff;
            padding: 64px 24px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(232,73,16,.18) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(0,139,210,.2) 0%, transparent 45%);
        }
        .hero-content { position: relative; z-index: 1; max-width: 560px; margin: 0 auto; }
        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.25);
            color: rgba(255,255,255,.9);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 20px;
            margin-bottom: 24px;
        }
        .hero h1 {
            font-size: clamp(26px, 5vw, 38px);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 16px;
        }
        .hero h1 em {
            font-style: normal;
            color: #fbbf7a;
        }
        .hero p {
            font-size: 15px;
            color: rgba(255,255,255,.8);
            line-height: 1.6;
            max-width: 440px;
            margin: 0 auto;
        }

        /* ── SELO ANÔNIMO ── */
        .anonimato-strip {
            background: #fff;
            border-bottom: 1px solid #e8ecf2;
            padding: 12px 24px;
            text-align: center;
        }
        .anonimato-strip .selos {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 24px;
        }
        .selo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #555;
        }
        .selo .fa {
            color: #1a9e4a;
            font-size: 15px;
        }

        /* ── CARD PRINCIPAL ── */
        .main-card {
            max-width: 540px;
            margin: -32px auto 48px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(22,65,148,.12);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        .main-card .card-top {
            height: 4px;
            background: linear-gradient(90deg, var(--azul), var(--azul-c), var(--laranja));
        }
        .main-card .card-body-inner {
            padding: 36px 36px 40px;
        }
        .card-titulo {
            font-size: 17px;
            font-weight: 700;
            color: var(--texto);
            margin-bottom: 6px;
        }
        .card-sub {
            font-size: 13px;
            color: #888;
            margin-bottom: 28px;
        }

        /* ── SELECT DE UNIDADE ── */
        .select-wrapper {
            position: relative;
        }
        .select-wrapper select {
            width: 100%;
            padding: 14px 48px 14px 18px;
            font-size: 15px;
            font-family: inherit;
            border: 2px solid #d0d9e8;
            border-radius: 10px;
            background: #fafbfd;
            color: var(--texto);
            appearance: none;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .select-wrapper select:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 4px rgba(22,65,148,.1);
            background: #fff;
        }
        .select-wrapper::after {
            content: '\f107';
            font-family: FontAwesome;
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--azul);
            font-size: 18px;
            pointer-events: none;
        }

        /* ── BOTÃO ── */
        .btn-pulso {
            width: 100%;
            margin-top: 20px;
            padding: 15px;
            background: var(--azul);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background .2s, transform .1s, box-shadow .2s;
            letter-spacing: .3px;
        }
        .btn-pulso:hover {
            background: #1a52c4;
            box-shadow: 0 4px 16px rgba(22,65,148,.3);
        }
        .btn-pulso:active { transform: scale(.98); }
        .btn-pulso .fa { font-size: 16px; }

        /* ── SEM CICLO ATIVO ── */
        .sem-ciclo {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .sem-ciclo .fa {
            font-size: 48px;
            color: #ccc;
            display: block;
            margin-bottom: 16px;
        }
        .sem-ciclo p { font-size: 14px; line-height: 1.6; }

        /* ── LINK STATUS ── */
        .status-link {
            text-align: center;
            margin-top: 20px;
        }
        .status-link a {
            font-size: 13px;
            color: var(--azul-c);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-link a:hover { text-decoration: underline; }

        /* ── FOOTER ── */
        footer {
            margin-top: auto;
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: #aaa;
        }

        @media (max-width: 600px) {
            .main-card .card-body-inner { padding: 28px 20px 32px; }
            .hero { padding: 48px 20px 64px; }
            .anonimato-strip .selos { gap: 14px; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="pulso-header">
        <div class="marca">
            <img src="pulso.png" alt="PulsoSENAI" style="height:44px;">
        </div>
    </header>

    <!-- SELOS DE ANONIMATO -->
    <div class="anonimato-strip">
        <div class="selos">
            <div class="selo">
                <i class="fa fa-lock"></i>
                <span>100% anônimo — sem coleta de identidade</span>
            </div>
            <div class="selo">
                <i class="fa fa-check-circle"></i>
                <span>Sem login necessário</span>
            </div>
            <!--<div class="selo">
                <i class="fa fa-clock-o"></i>
                <span>Leva menos de 6 minutos</span>
            </div>-->
        </div>
    </div>

    <!-- HERO -->
    <div class="hero">
        <div class="hero-content">
            <div class="hero-badge">Pesquisa de Clima — Ciclo Trimestral</div>
            <h1>Sua voz importa.<br><em>E vai gerar ação.</em></h1>
            <p>Cada resposta é registrada de forma totalmente anônima e gera um compromisso real da gestão — com prazo e status públicos.</p>
        </div>
    </div>

    <!-- CARD PRINCIPAL -->
    <div class="main-card">
        <div class="card-top"></div>
        <div class="card-body-inner">

            <?php if (empty($unidades)): ?>
                <div class="sem-ciclo">
                    <i class="fa fa-calendar-times-o"></i>
                    <p>Nenhuma pesquisa está aberta no momento.<br>Quando um novo ciclo for iniciado, ele aparecerá aqui.</p>
                </div>
            <?php else: ?>
                <p class="card-titulo">Selecione sua unidade</p>
                <p class="card-sub">Escolha a unidade onde você trabalha para acessar a pesquisa do ciclo atual.</p>

                <form action="responder.php" method="GET">
                    <div class="select-wrapper">
                        <select name="unidade" required>
                            <option value="" disabled selected>— Selecione a unidade —</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= htmlspecialchars($u['slug']) ?>">
                                    <?= htmlspecialchars($u['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-pulso">
                        <i class="fa fa-arrow-right"></i>
                        Iniciar a pesquisa
                    </button>
                </form>
            <?php endif; ?>

            <div class="status-link">
                <a href="status.php">
                    <i class="fa fa-bar-chart"></i>
                    Ver painel público de status dos compromissos
                </a>
            </div>
        </div>
    </div>

    <footer>
        PulsoSENAI &mdash; Sistema de Escuta Contínua &bull; SENAI Minas Gerais
    </footer>

</body>
</html>