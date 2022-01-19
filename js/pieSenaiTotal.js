$(document).ready(function(){
	$.ajax({
		url: "../graficos/SenaiConsultaTotal.php",
		method: "GET",
		success: function(data) {
            console.log(data);
            var dadosRem1 = data.replace('[','');
            var dadosRem2 = dadosRem1.replace(']','');
            var dados = dadosRem2.split(",");
            console.log(dados[1]);
			var chartdata = {
				labels: ["Aprendizagem", "Aperfeiçoamento", "Qualificação", "Técnico"],
				datasets : [
					{
                        data: [dados[0], dados[1], dados[2], dados[3]],
						backgroundColor: ["#3C60A7", "#A73C96", "#A7833C", "#3CA74E"],
                        hoverBackgroundColor: ["#253C68", "#68255E", "#685225", "#256831"],
                    }
                    ],
                }; 

			var ctxP = $("#pieChart");

            var ctxP = document.getElementById("pieChart").getContext('2d');
            var myPieChart = new Chart(ctxP, {
                                type: 'pie',
                                data: chartdata,
                                options: 
                                    {
                                        responsive: true,
                                        title: {
                                            display: true,
                                            text: 'Gráfico por Tipo'
                                        }
                                    }
			});
		},
		error: function(data) {
			console.log(data);
		}
	});
});