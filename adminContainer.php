<!DOCTYPE html>
<?php
    $totalDoisN = explode('/',buscaDois());
    $doisNSesi =  $totalDoisN[0];
    $doisNSenai = $totalDoisN[1];
    $countSenai = buscaAlunosSenai() + $doisNSenai;
    $countSesi = buscaAlunosSesi() + $doisNSesi;
    $total = buscaAlunosTotal() + $doisNSesi + $doisNSenai;
?>
<html>

<!--Inicio grid-->
<div class="info-container">
    <!--Inicio info-box-->
    <div class="info-box info-bg-green">
        <span class="info-box-icon"><i class="fas fa-users"></i></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <span class="info-box-text">Total de Alunos</span>
                <span class="info-box-number"><?php echo $total;?></span>
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
                <span class="info-box-number"><?php echo $countSesi; ?></span>
            <!-- Barra de progresso - opcional -->
            <div class="progress">
                <div class="progress-bar info-bg-faux" style="width: <?php echo porcentagem($countSesi, $total); ?>%"></div>
            </div>
            <!-- Fim da barra de progresso -->
            <span class="progress-description">
                <?php echo round(porcentagem($countSesi, $total), 0); ?>%
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
                <span class="info-box-number"><?php echo $countSenai; ?></span>
            <!-- Barra de progresso - opcional -->
            <div class="progress">
                <div class="progress-bar info-bg-faux" style="width: <?php echo porcentagem($countSenai, $total); ?>%"></div>
            </div>
            <!-- Fim da barra de progresso -->
            <span class="progress-description">
                <?php echo round(porcentagem($countSenai, $total), 0); ?>%<a href="graficos/alunosSenai.php" class="info-box-label info-box-txt-purple"><i class ="fas fa-plus-square"></i></a>
            </span>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->
    <!--Inicio info-box-->
    <div class="info-box info-bg-deepgreen">
        <span class="info-box-icon"><i class="fas fa-phone"></i></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <span class="info-box-text">Troncos | Ramais Online</span>
                <span class="info-box-number"><?php echo buscaQuantTroncos(); echo " | "; echo buscaQuantRamais();?><a href="telefone.php" class="info-box-label info-box-txt-deepgreen"><i class ="fas fa-plus-square"></i></a>
            </span>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->
</div>
<!--Fim grid-->
    <!--Inicio info-box-->
    <div class="info-box info-box-eletric info-bg-deepgreen">
        <span class="info-box-icon"><img src="../img/subestacao.png"></span>
        <!--Inicio info-box conteúdo-->
        <div class="info-box-content info-bg-white">
            <h4>Consumo de energia</h4><a href="../graficos/graficosenergia.php" class="info-box-label info-box-txt-deepgreen"><i class ="fas fa-plus-square"></i></a>
            <div class="eletrica-dados">
                <canvas class="gauge-speed" id="tensao1-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="v" data-title="Tensão R/N" data-min-value="0"
                data-max-value="220" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200,220" data-minor-ticks="2" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 140, "color": "#4caf50" },
                                { "from": 130, "to": 180, "color": "#ff9800" },
                                { "from": 181, "to": 220, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
                <canvas class="gauge-speed" id="tensao2-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="v" data-title="Tensão S/N" data-min-value="0"
                data-max-value="220" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200,220" data-minor-ticks="2" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 140, "color": "#4caf50" },
                                    { "from": 130, "to": 180, "color": "#ff9800" },
                                    { "from": 181, "to": 220, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
                <canvas class="gauge-speed" id="tensao3-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="v" data-title="Tensão T/N" data-min-value="0"
                data-max-value="220" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200,220" data-minor-ticks="2" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 130, "color": "#4caf50" },
                                    { "from": 130, "to": 180, "color": "#ff9800" },
                                    { "from": 181, "to": 220, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
                <canvas class="gauge-speed" id="frequencia-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="Hz" data-title="Frequência" data-min-value="0"
                data-max-value="100" data-major-ticks="0,10,20,30,40,50,60,70,80,90,100" data-minor-ticks="10" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 66, "color": "#4caf50" },
                                    { "from": 66, "to": 80, "color": "#ff9800" },
                                    { "from": 80, "to": 100, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
            </div>
            <div class="eletrica-dados">
                <canvas class="gauge-speed" id="corrente1-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="A" data-title="Corrente R" data-min-value="0"
                data-max-value="200" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200" data-minor-ticks="10" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 100, "color": "#4caf50" },
                                    { "from": 100, "to": 160, "color": "#ff9800" },
                                    { "from": 160, "to": 200, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
            <canvas class="gauge-speed" id="corrente2-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="A" data-title="Corrente S" data-min-value="0"
            data-max-value="200" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200" data-minor-ticks="10" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 100, "color": "#4caf50" },
                                    { "from": 100, "to": 160, "color": "#ff9800" },
                                    { "from": 160, "to": 200, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
            <canvas class="gauge-speed" id="corrente3-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="A" data-title="Corrente T" data-min-value="0"
                data-max-value="200" data-major-ticks="0,20,40,60,80,100,120,140,160,180,200" data-minor-ticks="10" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 100, "color": "#4caf50" },
                                    { "from": 100, "to": 160, "color": "#ff9800" },
                                    { "from": 160, "to": 200, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
                &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
                <canvas class="gauge-speed" id="somacorrentes-gauge" style="margin-top:20px;" data-type="radial-gauge" data-width="180" ata-glow="false" data-height="180" data-units="A" data-title="Soma das Correntes" data-min-value="0"
                data-max-value="600" data-major-ticks="50,100,150,200,250,300,350,400,450,500,550,600" data-minor-ticks="10" data-value="0" data-stroke-ticks="false" data-highlights='[{ "from": 0, "to": 300, "color": "#4caf50" },
                                    { "from": 300, "to": 450, "color": "#ff9800" },
                                    { "from": 450, "to": 600, "color": "#f44336" }]'
                Udata-color-needle-start="rgba(240, 128, 128, 1)" data-color-needle-end="rgba(255, 160, 122, .9)"
                data-value-box="true" data-animation-rule="linear" data-animation-duration="500"
                data-needle-shadow="false"></canvas>
            </div>
        </div>
        <!--Fim info-box conteúdo-->
    </div>
    <!--Fim info box-->

    <script src="../js/energia.js"></script>
    <script src="../libs/gauge.js/dist/gauge.min.js"></script>
</html>