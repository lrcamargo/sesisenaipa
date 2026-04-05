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
                        <span>Painel de<br>Ocupação</span>
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

                    } elseif($nivelNorm === 'sup tecnica'){
                        include('supTecnicaContainer.php');

                    } elseif($nivelNorm === 'sup pedagogica'){
                        include('supPedContainer.php');

                    } elseif($nivelNorm === 'gerencia'){
                        include('gerContainer.php');

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
    </body>
</html>