<?php

    $nivelNorm = strtolower(str_replace('.', '', $_SESSION['group'] ?? ''));

    /* PROFESSOR
        Acesso: Dashboard + Reserva Laboratório
    */
    if($nivelNorm == 'professor'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
    }

    /* INSTRUTOR
        Acesso: Dashboard + Reserva Laboratório
    */
    elseif($nivelNorm == 'instrutor'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
    }

    /* ADMINISTRATIVO
        Acesso: Dashboard + Reserva Laboratório
    */
    elseif($nivelNorm == 'administrativo'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span>
                </a>
            </li>";

    }
    /* SECRETARIA
        Acesso: Dashboard + Reserva Laboratório
    */
    elseif($nivelNorm == 'secretaria'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
    }
    /* CONSULTORIA
        Acesso: Dashboard + Reserva Laboratório
    */
    elseif($nivelNorm == 'consultoria'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
    }

    /* SESI CAT
        Acesso: Dashboard + Reserva Laboratório
    */
    elseif($nivelNorm == 'sesi cat'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
    }

    /* SUP. PEDAGÓGICA
        Acesso: Dashboard + Reserva Laboratório + Gestão Usuários
    */
    elseif($nivelNorm == 'sup pedagogica'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
    }

    /* GERÊNCIA
        Acesso: Dashboard + Reserva Laboratório + Gestão Usuários +Gestão Ambientes e turmas
    */
    elseif($nivelNorm == 'gerencia'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
        //echo "<li class='item'><a href='../gestaoAmbientes/index.php' class='menu-btn'><i class='fas fa-map-signs'></i><span>Gestão Ambientes</span></a></li>";
         // GESTÃO DE AMBIENTES, TURMAS E FERIADOS
        echo "<li class='item' id='gestao-item'>
            <a href='#gestao-item' class='menu-btn'>
                <i class='fas fa-cogs'></i><span>Gestão <i class='fas fa-chevron-down drop-down'></i></span>
            </a>
            <div class='sub-menu'>
                <a href='../gestaoAmbientes/index.php'>
                    <i class='fas fa-door-open'></i><span>Ambientes</span>
                </a>
                <a href='../gestaoTurmas/index.php'>
                    <i class='fas fa-chalkboard'></i><span>Turmas</span>
                </a>
                <a href='../gestaoTurmas/cadastroFeriados.php' class='menu-btn'>
                    <i class='fa fa-sun'></i><span>Feriados</span>
                </a>
            </div>
        </li>";
    }

    /* SUP. TÉCNICA
        Acesso: Dashboard + Reserva Laboratório + Gestão Usuários + Gestão Ambientes + Sirene (desativado temporariamente)
    */
    elseif($nivelNorm == 'sup tecnica'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
        //echo "<li class='item'><a href='../gestaoAmbientes/index.php' class='menu-btn'><i class='fas fa-map-signs'></i><span>Gestão Ambientes</span></a></li>";
        // GESTÃO DE AMBIENTES, TURMAS E FERIADOS
        echo "<li class='item' id='gestao-item'>
            <a href='#gestao-item' class='menu-btn'>
                <i class='fas fa-cogs'></i><span>Gestão <i class='fas fa-chevron-down drop-down'></i></span>
            </a>
            <div class='sub-menu'>
                <a href='../gestaoAmbientes/index.php'>
                    <i class='fas fa-door-open'></i><span>Ambientes</span>
                </a>
                <a href='../gestaoTurmas/index.php'>
                    <i class='fas fa-chalkboard'></i><span>Turmas</span>
                </a>
                <a href='../gestaoTurmas/cadastroFeriados.php' class='menu-btn'>
                    <i class='fa fa-sun'></i><span>Feriados</span>
                </a>
            </div>
        </li>";
        //echo "<li class='item'><a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span>Sirene</span></a></li>";
    }

    /* ADMINISTRATOR / ADMIN
        Acesso completo: todos os itens ativos do sistema
    */
    elseif($nivelNorm == 'administrator' || $nivelNorm == 'admin'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
        //echo "<li class='item'><a href='../gestaoAmbientes/index.php' class='menu-btn'><i class='fas fa-map-signs'></i><span>Gestão Ambientes</span></a></li>";
        //echo "<li class='item'><a href='../gestaoTurmas/index.php' class='menu-btn'><i class='fas fa-search'></i><span>Gestão Turmas</span></a></li>";
        // GESTÃO DE AMBIENTES, TURMAS E FERIADOS
        echo "<li class='item' id='gestao-item'>
            <a href='#gestao-item' class='menu-btn'>
                <i class='fas fa-cogs'></i><span>Gestão <i class='fas fa-chevron-down drop-down'></i></span>
            </a>
            <div class='sub-menu'>
                <a href='../gestaoAmbientes/index.php'>
                    <i class='fas fa-door-open'></i><span>Ambientes</span>
                </a>
                <a href='../gestaoTurmas/index.php'>
                    <i class='fas fa-chalkboard'></i><span>Turmas</span>
                </a>
                <a href='../gestaoTurmas/cadastroFeriados.php' class='menu-btn'>
                    <i class='fa fa-sun'></i><span>Feriados</span>
                </a>
            </div>
        </li>";
        echo "<li class='item'><a href='main.php' class='menu-btn'><i class='fas fa-archive'></i><span>Controle de Estoque</span></a></li>";
        echo "<li class='item'><a href='../scripts/deploy.php' class='menu-btn'><i class='fas fa-rocket mr-1'></i><span>Deploy</span></a></li>";
        echo "<li class='item'><a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span>Sirene</span></a></li>";
    }
    /* GRUPO NÃO RECONHECIDO
        Exibe apenas Dashboard como fallback seguro.
    */
    else {
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
    }
?>