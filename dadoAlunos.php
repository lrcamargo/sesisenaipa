<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box bg-purple">
            <span class="info-box-icon"><i class="fa fa-comments-o"></i></span>
            <div class="info-box-content">
                <span class="pull-right-container">
                    <small class="label pull-right bg-purple-dark" onclick="window.open('main.php');">+</button></small>
                </span>
                <span class="info-box-text">SESI</span>
                                            
                <span class="info-box-number"><?php echo $somasesi; ?></span>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo porcentagem($somasesi, $total); ?>%"></div>
                </div>
                <span class="progress-description">
                    <?php echo round(porcentagem($somasesi, $total), 2)."%"; ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">   
        <div class="info-box bg-blue">
            <span class="info-box-icon"><i class="fa fa-comments-o"></i></span>
            <div class="info-box-content">
                <span class="pull-right-container">
                    <small class="label pull-right bg-blue-dark" onclick="window.open('main.php');">+</button></small>
                </span>
                <span class="info-box-text">SENAI</span>
                <span class="info-box-number"><?php echo $somasenai; ?></span>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo porcentagem($somasenai, $total); ?>%"></div>
                </div>
                <span class="progress-description">
                    <?php echo round(porcentagem($somasenai, $total), 2) . "%"; ?>
                </span>
            </div> 
        </div>                        
    </div>
</div>

<div class="clearfix visible-sm-block"></div>

</div>
