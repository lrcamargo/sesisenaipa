$(document).ready(function(){
    readData();
    setInterval(readData(), 50000);
	function readData() {
        $.ajax({
            url: "../compras/graficos/solicitante.php",
            method: "GET",
            success: function(data) {
                var solicitante = [];
                var quantidade = []
                var dataHora = [];
                

                for (var i = 0; i < data.length; i++) {
                    solicitante[i] = data[i]['solicitante'];
                }

                for (var i = 0; i < data.length; i++) {
                    quantidade[i] = data[i]['quantidade'];
                }
                        
                for (var i = 0; i < data.length; i++) {
                    //solicitante[i] = data[i]['dataHora'].substr(11,8);
                    dataHora[i] = data[i]['dataHora'];
                }
                //inicio maior solicitante
                var solicitanteChartData = {
                    labels: [solicitante[4],solicitante[3],solicitante[2],solicitante[1],solicitante[0]],
                    datasets: [{
                        label: 'Solicitante',
                        borderColor: '#4b2d73',
                        backgroundColor: '#4b2d73',
                        borderWidth: 1,
                        data: [
                            quantidade[4],quantidade[3],quantidade[2],quantidade[1],quantidade[0]
                        ],
                        //yAxisID: 'y-axis-1',
                    }]
                };
            
                    var ctx = document.getElementById('solicitante').getContext('2d');
                    var myChart = new Chart(ctx, {
                        type: 'bar',
                        data: solicitanteChartData,
                        options: {
                            responsive: true,
                            hoverMode: 'index',
                            title: {
                                display: true,
                                text: '5 maiores solicitantes x quantidade de compras'
                            },
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true
                                    }
                                }],
                            }
                        }
                    });

            //fim success
            },
            error: function(data) {
                console.log("ERRO");
            }
        });
    }  
            
});

