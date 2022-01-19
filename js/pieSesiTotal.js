$(document).ready(function(){
	$.ajax({
		url: "../graficos/SesiConsultaTotal.php",
		method: "GET",
		success: function(data) {
            var dadosRem1 = data.replace('[','');
            var dadosRem2 = dadosRem1.replace(']','');
            var dados = dadosRem2.split(",");
			var chartdata = {
				labels: ["6º ano", "7º ano", "8º ano", "1º ano", "2º ano", "3º ano"],
				datasets : [
					{
                        data: [dados[0], dados[1], dados[2], dados[3], dados[4], dados[5]],
						backgroundColor: ["#3C60A7", "#A73C96", "#A7833C", "#3CA74E", "#FF5733", "#C70039"],
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

$(document).ready(function(){
	$.ajax({
		url: "../graficos/SesiConsultaTurmas.php",
		method: "GET",
		success: function(data) {
            console.log(data);
            var dadosRem1 = data.replace('[','');
            var dadosRem2 = dadosRem1.replace(']','');
            var dados = dadosRem2.split(",");
            console.log(dados[5]);
            console.log(dados[0]['descricao']);
			var chartdata = {
				labels: ["6º ano", "7º ano", "8º ano", "1º ano", "2º ano", "3º ano"],
				datasets : [
					{
                        data: [dados[0], dados[1], dados[2], dados[3], dados[4], dados[5]],
						backgroundColor: ["#3C60A7", "#A73C96", "#A7833C", "#3CA74E", "#FF5733", "#C70039"],
                    }
                    ],
                }; 

			var ctxP = $("#pieChartTeste");

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