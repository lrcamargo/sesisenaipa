<?php
error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
      
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];

    if($nivel == 'Professor') {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        /*$currentPath = $_SERVER['REQUEST_URI'];
        $isInLabsSection = (strpos($currentPath, "laboratorios") !== false);
        
        echo "<li class='item'>";
            echo "<a href='" . ($isInLabsSection ? '../principal.php' : '../reservaLaboratorio/principal.php') . "' class='menu-btn'>";
            echo "<i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span>";
            echo "</a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
    }
    if($nivel == "Instrutor") {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        /*$currentPath = $_SERVER['REQUEST_URI'];
        $isInLabsSection = (strpos($currentPath, "laboratorios") !== false);
        
        echo "<li class='item'>";
            echo "<a href='" . ($isInLabsSection ? '../principal.php' : '../reservaLaboratorio/principal.php') . "' class='menu-btn'>";
            echo "<i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span>";
            echo "</a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
       // echo "<a href='../manutencao.html' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        
    } else if($_SESSION['group'] == 'Instrutor') {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        /*$currentPath = $_SERVER['REQUEST_URI'];
        $isInLabsSection = (strpos($currentPath, "laboratorios") !== false);
        
        echo "<li class='item'>";
            echo "<a href='" . ($isInLabsSection ? '../principal.php' : '../reservaLaboratorio/principal.php') . "' class='menu-btn'>";
            echo "<i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span>";
            echo "</a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        //echo "<a href='../manutencao.html' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
    } else if($nivel == 3) { #sup tecnica
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        /*$currentPath = $_SERVER['REQUEST_URI'];
        $isInLabsSection = (strpos($currentPath, "laboratorios") !== false);
        
        echo "<li class='item'>";
            echo "<a href='" . ($isInLabsSection ? 'principal.php' : '../reservaLaboratorio/principal.php') . "' class='menu-btn'>";
            echo "<i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span>";
            echo "</a>";
        echo "</li>";
        //EM MANUTENÇÃO
        echo "<li class='item'>";
            echo "<a href='../manutencao.html' class='menu-btn'><i class='fas fa-users'></i><span> Gestão Usuários</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../compras/principal.php' class='menu-btn'><i class='fas fa-shopping-cart'></i><span>Compras</span></a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../controle/index.php' class='menu-btn'><i class='fa fa-fw fa-clock-o'></i> Registro</a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../graficos/graficosenergia.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Monitorar elétrica</span></a>";
        echo "</li>";*/


    } else if($nivel == 4) { #sup. pedagogica
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span> Gestão Usuários</span></a>";
        echo "</li>";
    } else if($nivel == 5) {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        //echo "<a href='../manutencao.html' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
    } else if($nivel == 6) {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../graficos/graficosenergia.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Monitorar elétrica</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividadesTeste/professor.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Atividades Professor</span></a>";
        echo "</li>";*/
        /*echo "<li class='item'>";
            echo "<a href='../compras/principal.php' class='menu-btn'><i class='fas fa-shopping-cart'></i><span>Compras</span></a>";
        echo "</li>";*/
    } else if($nivel == 7) {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../camera/camera.php' class='menu-btn'><i class='fas fa-camera-retro'></i><span> Câmera</span></a>";
        echo "</li>";*/
    } else if($nivel == 8) {
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        //echo "<a href='../manutencao.html' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "<li class='item'>";
            echo "<a href='../compras/lista.php' class='menu-btn'><i class='fas fa-shopping-cart'></i><span>Compras</span></a>";
        echo "</li>";
    } else if($nivel == 'admin' || $nivel = ' Sup. Tecnica' || $nivel = 'Sup. Pedagogica') { #administrator
        echo "<li class='item'>";
            echo "<a href='../../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../reservaLaboratorio/principal.php' class='menu-btn'><i class='fas fa-calendar-day'></i><span>Reserva Laboratório</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../../gestaoUsuarios/index.php' class='menu-btn'><i class='fas fa-users'></i><span> Gestão Usuários</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span> Sirene</span></a>";
        echo "</li>";
        ##desativados
        /*
        echo "<li class='item'>";
            echo "<a href='../graficos/graficosenergia.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Monitorar elétrica</span></a>";
        echo "</li>";
        echo "<li class='item' id='bloqueio'>";
            echo "<a href='#bloqueio' class='menu-btn'>";
                echo "<i class='fas fa-globe'></i><span>Controle de Internet <i class='fas fa-chevron-down drop-down'></i></span>";
            echo "</a>";
            echo "<div class='sub-menu'>";
                echo "<a href='controle_internet/controle.php'><i class='fas fa-lock'></i><span>Controle</span></a>";
                echo "<a href='registros.php'><i class='fas fa-clipboard-list'></i><span>Registro de Acesso</span></a>";
            echo "</div>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span> Registro</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividades/professor.php' class='menu-btn'><i class='fas fa-tasks'></i><span> Atividades</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../linksAulas/testeProf.php' class='menu-btn'><i class='fas fa-bell'></i><span> Teste</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../controleTablets/scan.php' class='menu-btn'><i class='fas fa-tablet-alt'></i><span> Controle Tablets</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../compras/principal.php' class='menu-btn'><i class='fas fa-shopping-cart'></i><span>Compras</span></a>";
        echo "</li>";*/
    }  
?>
