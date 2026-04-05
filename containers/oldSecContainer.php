<!DOCTYPE html>

<html>

<!--Inicio grid-->
<div class="info-container">
    <!--Inicio info-box-->
    <div class="info-box info-bg-green">
        <span class="info-box-icon"><i class="fas fa-users"></i></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <span class="info-box-text">Total de Alunos</span>
                <span class="info-box-number"><?php echo buscaAlunosTotal();?></span>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->
    <!--Inicio info-box-->
    <div class="info-box info-bg-faux">
        <span class="info-box-icon"><i class="fas fa-users"></i></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <span class="info-box-text">Total de Alunos SESI</span>
                <span class="info-box-number"><?php echo buscaAlunosSesi(); ?></span>
            <!-- Barra de progresso - opcional -->
            <div class="progress">
                <div class="progress-bar info-bg-faux" style="width: <?php echo porcentagem(buscaAlunosSesi(), buscaAlunosTotal()); ?>%"></div>
            </div>
            <!-- Fim da barra de progresso -->
            <span class="progress-description">
                <?php echo round(porcentagem(buscaAlunosSesi(), buscaAlunosTotal()), 0); ?>%
            </span>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->
    <!--Inicio info-box-->
    <div class="info-box info-bg-purple">
        <span class="info-box-icon"><i class="fas fa-users"></i></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <span class="info-box-text">Total de Alunos SENAI</span>
                <span class="info-box-number"><?php echo buscaAlunosSenai(); ?></span>
            <!-- Barra de progresso - opcional -->
            <div class="progress">
                <div class="progress-bar info-bg-faux" style="width: <?php echo porcentagem(buscaAlunosSenai(), buscaAlunosTotal()); ?>%"></div>
            </div>
            <!-- Fim da barra de progresso -->
            <span class="progress-description">
                <?php echo round(porcentagem(buscaAlunosSenai(), buscaAlunosTotal()), 0); ?>%
            </span>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->
</div>
<!--Fim grid-->

</html>