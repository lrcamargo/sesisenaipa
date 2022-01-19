$(document).ready(function(){
    readData();
    //setTimeout(readData(),2000);
    setInterval(readData, 5000);
	function readData() {
        $.ajax({
            url: "../energia/lista.php",
            method: "GET",
            success: function(data) {
                var obj = JSON.parse(data);
                var graph1 = document.getElementById("tensao1-gauge");
                graph1.dataset.value = obj["tensaol1neutro"];

                var graph2 = document.getElementById("tensao2-gauge");
                graph2.dataset.value = obj["tensaol2neutro"];

                var graph3 = document.getElementById("tensao3-gauge");
                graph3.dataset.value = obj["tensaol3neutro"];

                var graph4 = document.getElementById("corrente1-gauge");
                graph4.dataset.value = obj["correntei1"];

                var graph5 = document.getElementById("corrente2-gauge");
                graph5.dataset.value = obj["correntei2"];

                var graph6 = document.getElementById("corrente3-gauge");
                graph6.dataset.value = obj["correntei3"];

                var graph7 = document.getElementById("frequencia-gauge");
                graph7.dataset.value = obj["frequencia"];

                var graph8 = document.getElementById("somacorrentes-gauge");
                graph8.dataset.value = obj["somacorrentes"];

            },
            error: function(data) {
                console.log("ERRO");
            }
        });
    }
});
