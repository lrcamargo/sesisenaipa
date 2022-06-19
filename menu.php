<?php

/*
Menu
<li class="item">
    <a href="#" class="menu-btn"><i class="fas fa-home"></i><span>Dashboard</span></a>
</li>
Submenu
<li class="item" id="bloqueio">
    <a href="#bloqueio" class="menu-btn">
        <i class="fas fa-globe"></i><span>Controle de Internet <i class="fas fa-chevron-down drop-down"></i></span>
    </a>
    <div class="sub-menu">
        <a href="#"><i class="fas fa-image"></i><span>Bloqueio</span></a>
            <a href="#"><i class="fas fa-address-card"></i><span>Registro de Acesso</span></a>
    </div>
</li>

*/
    if($nivel == 0) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividades/aluno.php' class='menu-btn'><i class='fas fa-tasks'></i><span> Atividades</span></a>";
        echo "</li>";
    }
    if($nivel == 1) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividades/professor.php' class='menu-btn'><i class='fas fa-tasks'></i><span> Atividades</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../controle_internet/controle.php' class='menu-btn'><i class='fas fa-lock'></i><span> Controle de Internet</span></a>";
        echo "</li>";*/
    } else if($nivel == 2) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
    } else if($nivel == 3) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../graficos/graficosenergia.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Monitorar elétrica</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../controle/index.php' class='menu-btn'><i class='fa fa-fw fa-clock-o'></i> Registro</a>";
        echo "</li>";
    } else if($nivel == 4) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividades/professor.php' class='menu-btn'><i class='fas fa-tasks'></i><span> Atividades</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../controleTablets/scan.php' class='menu-btn'><i class='fas fa-tablet-alt'></i><span> Controle Tablets</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../sirene/index.php' class='menu-btn'><i class='fa fa-fw fa-bell'></i> Sirene</a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../catraca/index.php' class='menu-btn'><i class='fa fa-fw fa-calendar'></i> AcessoNet</a>";
        echo "</li>";*/
        echo "<li class='item'>";
            echo "<a href='../camera/camera.php' class='menu-btn'><i class='fas fa-camera-retro'></i><span>Câmera</span></a>";
        echo "</li>";
    } else if($nivel == 5) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../camera/camera.php' class='menu-btn'><i class='fas fa-camera-retro'></i><span> Câmera</span></a>";
        echo "</li>";
        /*echo "<li class='item'>";
            echo "<a href='../catraca/index.php' class='menu-btn'><i class='fa fa-fw fa-calendar'></i> AcessoNet</a>";
        echo "</li>";*/
    } else if($nivel == 6) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../graficos/graficosenergia.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Monitorar elétrica</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividadesTeste/professor.php' class='menu-btn'><i class='fas fa-bolt'></i><span> Atividades Professor</span></a>";
        echo "</li>";
    } else if($nivel == 7) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../camera/camera.php' class='menu-btn'><i class='fas fa-camera-retro'></i><span> Câmera</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../atividades/senha.php' class='menu-btn'><i class='fas fa-tasks'></i><span> Atividades</span></a>";
        echo "</li>";
    } else if($nivel == 8) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
    } else if($nivel == 9) {
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> Dashboard</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../camera/camera.php' class='menu-btn'><i class='fas fa-camera-retro'></i><span> Câmera</span></a>";
        echo "</li>";
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
            echo "<a href='../sirene/index.php' class='menu-btn'><i class='fas fa-bell'></i><span> Sirene</span></a>";
        echo "</li>";
        echo "<li class='item'>";
            echo "<a href='../main.php' class='menu-btn'><i class='fas fa-home'></i><span> AcessoNet</span></a>";
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
    } 
?>
