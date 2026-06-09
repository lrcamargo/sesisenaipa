<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    session_start();
    
    if(!isset($_SESSION['sLogin'])){
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:index.php');
        exit;
    }
    
    $logado    = $_SESSION['user'];
    $nivel     = $_SESSION['group'];
    $nivelNorm = strtolower(str_replace('.', '', $nivel));
    //include('functions.php');
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Tela principal</title>
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>
        <style>
        /* ── Área de atalhos do dashboard ──────────────────────────
           Centralizada, acima do container de conteúdo por nível.
           Adicione novos botões aqui conforme necessário.         */
        .dashboard-atalhos {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            padding: 24px 16px 8px;
        }

        .btn-atalho {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 140px;
            height: 110px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 700;
            font-size: .85rem;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .btn-atalho:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,.18);
            text-decoration: none;
        }
        .btn-atalho:active {
            transform: translateY(0);
        }
        .btn-atalho i {
            font-size: 1.8rem;
        }

        /* Variantes de cor — adicione mais conforme novos botões */
        .btn-atalho-painel {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
        }
        .btn-atalho-painel:hover { color: #fff; }
        .btn-atalho-usuarios { background: linear-gradient(135deg, #E84910, #FFB347); color:#fff; }
        .btn-atalho-usuarios:hover { color: #fff; }
        /* Futuro — exemplo de cores para outros atalhos:
           .btn-atalho-reservas { background: linear-gradient(135deg,#155724,#28a745); color:#fff; }
           .btn-atalho-usuarios { background: linear-gradient(135deg,#721c24,#dc3545); color:#fff; }
        */
        </style>
    </head>
    <body>
        <div class="wrapper">

            <!-- Cabeçalho -->
            <div class="header">
                <div class="header-menu">
                    <div class="title"><img src="../img/logo_white.svg"></div>
                    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
                    <ul>
                        <li><a href="#" class="user"><?php echo htmlspecialchars($logado); ?></a></li>
                        <li><a href="sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
                    </ul>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('menu.php'); ?>
                </div>
            </div>

            <!-- Conteúdo -->
            <div class="main-container">

                <!-- ══════════════════════════════════════════════════
                     ATALHOS DO DASHBOARD
                     Visíveis para todos os usuários logados.
                     ══════════════════════════════════════════════════ -->
                <div class="dashboard-atalhos">

                    <!-- Painel de Ocupação — visível para todos -->
                    <a href="gestaoTurmas/painelInterno.php"
                       target="_blank"
                       class="btn-atalho btn-atalho-painel"
                       title="Abre o painel de ocupação em nova aba">
                        <i class="fas fa-th-large"></i>
                        <center>
                            <span>Ocupação de<br>Ambientes</span>
                        </center>
                    </a>

                    <?php
                    /*
                     * Adicione aqui outros botões condicionais por nível.
                     * Exemplo:
                     *
                     * if(in_array($nivelNorm, ['admin','administrator','sup tecnica'])){
                     *     echo "<a href='gestaoUsuarios/index.php' class='btn-atalho btn-atalho-usuarios'>
                     *               <i class='fas fa-users'></i>
                     *               <span>Gestão de<br>Usuários</span>
                     *           </a>";
                     * }
                     */
                     if(in_array($nivelNorm, ['admin','administrator','sup tecnica','gerencia','sup pedagogica'])){
                          echo "<a href='gestaoTurmas/painelDocentes.php' class='btn-atalho btn-atalho-usuarios'>
                                    <i class='fas fa-university'></i>
                                    <center>
                                        <span>Gestão de<br>Instrutores</span>
                                    </center>
                                </a>";
                          echo "<a href='gestaoTurmas/consultaHorario.php' class='btn-atalho' 
                                    style='background:linear-gradient(135deg,#1565c0,#1976d2);color:#fff'
                                    title='Consultar horário de instrutores e turmas'>
                                    <i class='fas fa-calendar-alt'></i>
                                    <center><span>Consulta de<br>Horários</span></center>
                                </a>";
                          /*echo "<a href='pulso/status.php' class='btn-atalho' 
                            style='background:linear-gradient(180deg, #ffffff, #ff9800);color:#fff' title='Ver status de pesquisa de Pulso Trimestral'>
                                  <img src='img/pulso.png' style='width: 70%'></i>    
                              </a>";*/
                              //<!-- ── BOTÃO PULSOSENAI COM BADGE DE NOTIFICAÇÃO ── -->
                                echo "<div style='position:relative;display:inline-block;'>
                                    <a href='pulso/status.php'
                                    id='btnPulso'
                                    onclick='marcarPulsoLido()'
                                    class='btn-atalho'
                                    style='background:linear-gradient(135deg, #0d2d5e, #E84910);color:#fff'
                                    title='PulsoSENAI — Pesquisa de Clima'>
                                        <img src='img/pulso.png' style='width:70%;max-height:52px;object-fit:contain;'>
                                    </a>
                                    <!-- Badge de notificação — aparece via JS quando há novidades -->
                                    <span id='pulsoBadge'
                                        style='display:none;position:absolute;top:-8px;right:-8px;
                                                background:#e53935;color:#fff;
                                                font-size:11px;font-weight:800;
                                                min-width:20px;height:20px;
                                                border-radius:10px;padding:0 5px;
                                                display:none;align-items:center;justify-content:center;
                                                box-shadow:0 2px 6px rgba(0,0,0,.3);
                                                border:2px solid #fff;
                                                pointer-events:none;
                                                z-index:10;'>
                                        0
                                    </span>
                                </div>";
                     }
                     /*if(in_array($nivelNorm,['sup tecnica','sup pedagogica','gerencia','admin','administrator'])){
                            echo "<a href='gestaoTurmas/consultaHorario.php' class='btn-atalho' 
                                    style='background:linear-gradient(135deg,#1565c0,#1976d2);color:#fff'
                                    title='Consultar horário de instrutores e turmas'>
                                    <i class='fas fa-calendar-search'></i>
                                    <span>Consulta de<br>Horários</span>
                                </a>";
                        }*/
                     if($nivelNorm === 'instrutor'){
                        echo "<a href='gestaoTurmas/meuHorario.php' class='btn-atalho' 
                        style='background:linear-gradient(135deg,#1565c0,#1976d2);color:#fff' title='Ver meu calendário de aulas'>
                                  <i class='fas fa-calendar-alt'></i>
                                  <center>
                                    <span>Meu<br>Horário</span>
                                  </center>
                              </a>";
                        //<!-- ── BOTÃO PULSOSENAI COM BADGE DE NOTIFICAÇÃO ── -->
                                echo "<div style='position:relative;display:inline-block;'>
                                    <a href='pulso/status.php'
                                    id='btnPulso'
                                    onclick='marcarPulsoLido()'
                                    class='btn-atalho'
                                    style='background:linear-gradient(135deg, #0d2d5e, #E84910);color:#fff'
                                    title='PulsoSENAI — Pesquisa de Clima'>
                                        <img src='img/pulso.png' style='width:70%;max-height:52px;object-fit:contain;'>
                                    </a>
                                    <!-- Badge de notificação — aparece via JS quando há novidades -->
                                    <span id='pulsoBadge'
                                        style='display:none;position:absolute;top:-8px;right:-8px;
                                                background:#e53935;color:#fff;
                                                font-size:11px;font-weight:800;
                                                min-width:20px;height:20px;
                                                border-radius:10px;padding:0 5px;
                                                display:none;align-items:center;justify-content:center;
                                                box-shadow:0 2px 6px rgba(0,0,0,.3);
                                                border:2px solid #fff;
                                                pointer-events:none;
                                                z-index:10;'>
                                        0
                                    </span>
                                </div>";
                    }
                    ?>

                </div>
                <!-- /dashboard-atalhos -->

                <!-- ══════════════════════════════════════════════════
                     CONTAINER POR NÍVEL
                     TODO: atualizar condições para usar $nivelNorm
                     quando os containers forem revisados.
                     ══════════════════════════════════════════════════ -->
                <?php
                    /*
                     * Containers por nível (nivelNorm).
                     */
                    if($nivelNorm === 'professor'){
                        // include('./atividades/professor.php');

                    } elseif($nivelNorm === 'instrutor'){
                        include('containers/instrutoresContainer.php');
                    } elseif($nivelNorm === 'sup tecnica'){
                        include('containers/supTecnicaContainer.php');

                    } elseif($nivelNorm === 'sup pedagogica'){
                        include('containers/supPedContainer.php');

                    } elseif($nivelNorm === 'gerencia'){
                        include('containers/gerContainer.php');

                    } elseif($nivelNorm === 'secretaria'){
                        include('containers/secContainer.php');

                    } elseif(in_array($nivelNorm, ['admin','administrator'])){
                        include('containers/adminContainer.php');

                    }
                    // else → nível não mapeado: exibe apenas os atalhos acima
                ?>

            </div>
        </div>
        <script type="text/javascript" src="../js/menu.js"></script>
        <script>
                (function() {
                    // Consulta o endpoint a cada 60 segundos
                    function verificarNotifPulso() {
                        fetch('pulso/notif_count.php')
                            .then(r => r.json())
                            .then(data => {
                                const badge = document.getElementById('pulsoBadge');
                                if (!badge) return;
                                if (data.count > 0) {
                                    badge.textContent = data.count > 99 ? '99+' : data.count;
                                    badge.style.display = 'flex';
                                } else {
                                    badge.style.display = 'none';
                                }
                            })
                            .catch(() => {}); // falha silenciosa
                    }
                
                    // Marca como lido ao clicar
                    window.marcarPulsoLido = function() {
                        fetch('pulso/notif_count.php?action=marcar')
                            .then(() => {
                                const badge = document.getElementById('pulsoBadge');
                                if (badge) badge.style.display = 'none';
                            })
                            .catch(() => {});
                    };
                
                    // Verifica imediatamente e depois a cada 60s
                    verificarNotifPulso();
                    setInterval(verificarNotifPulso, 60000);
                })();
                </script>
    </body>
</html>