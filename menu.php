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
        Acesso: Dashboard + Reserva Laboratório + Gestão Usuários
    */
    elseif($nivelNorm == 'gerencia'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
    }

    /* SUP. TÉCNICA
        Acesso: Dashboard + Reserva Laboratório + Gestão Usuários + Sirene
    */
    elseif($nivelNorm == 'sup tecnica'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
        echo "<li class='item'><a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span>Sirene</span></a></li>";
    }

    /* ADMINISTRATOR / ADMIN
        Acesso completo: todos os itens ativos do sistema
    */
    elseif($nivelNorm == 'administrator' || $nivelNorm == 'admin'){
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
        echo "<li class='item'><a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a></li>";
        echo "<li class='item'><a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span>Gestão Usuários</span></a></li>";
        echo "<li class='item'><a href='../deploy.php' class='menu-btn'><i class='fas fa-rocket mr-1'></i><span>Deploy</span></a></li>";
        echo "<li class='item'><a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span>Sirene</span></a></li>";
    }
    /* GRUPO NÃO RECONHECIDO
        Exibe apenas Dashboard como fallback seguro.
    */
    else {
        echo "<li class='item'><a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span>Dashboard</span></a></li>";
    }
?>