$(document).ready(function(){
    readData();
    setInterval(readData(), 50000);
	function readData() {
        $.ajax({
            url: "../compras/graficos/grupos.php",
            method: "GET",
            success: function(data) {
                var grupo = [];
                var quantidade = []
                
                for (var i = 0; i < data.length; i++) {
                    grupo[i] = data[i]['grupo'];
                }

                for (var i = 0; i < data.length; i++) {
                    quantidade[i] = data[i]['quantidade'];
                }
                        
                //inicio grupos
                var gruposChartData = {
                    labels: [grupo[4],grupo[3],grupo[2],grupo[1],grupo[0]],
                    datasets: [{
                        label: 'Grupos',
                        borderColor: '#7E2B80',
                        backgroundColor: '#7E2B80',
                        borderWidth: 1,
                        data: [
                            quantidade[4],quantidade[3],quantidade[2],quantidade[1],quantidade[0]
                        ],
                        //yAxisID: 'y-axis-1',
                    }]
                };
            
                    var ctx = document.getElementById('grupos').getContext('2d');
                    var myChart = new Chart(ctx, {
                        type: 'bar',
                        data: gruposChartData,
                        options: {
                            responsive: true,
                            hoverMode: 'index',
                            title: {
                                display: true,
                                text: '5 grupos mais comprados'
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

