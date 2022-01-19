$(document).ready(function(){
    readData();
    setInterval(readData(), 50000);
	function readData() {
        $.ajax({
            url: "../energia/listadia.php",
            method: "GET",
            success: function(data) {
                var cos1 = [];
                var cos2 = [];
                var cos3 = [];
                var cost = [];
                var p1 = [];
                var pf1 = [];
                var p2 = [];
                var pf2 = [];
                var p3 = [];
                var pf3 = [];
                var pt = [];
                var pft = [];
                var s1 = [];
                var sf1 = [];
                var s2 = [];
                var sf2 = [];
                var s3 = [];
                var sf3 = [];
                var st = [];
                var sft = [];
                var q1 = [];
                var qf1 = [];
                var q2 = [];
                var qf2 = [];
                var q3 = [];
                var qf3 = [];
                var qt = [];
                var qft = [];
                var consw = [];
                var fornw = [];
                var consv = [];
                var fornv = [];
                var freq = [];
                var freqf = [];
                var dataHora = [];

                for (var i = 0; i < data.length; i++) {
                    cos1[i] = data[i]['cosphi1'];
                }
                        
                for (var i = 0; i < data.length; i++) {
                    cos2[i] = data[i]['cosphi2'];
                }

                for (var i = 0; i < data.length; i++) {
                    cos3[i] = data[i]['cosphi3'];
                }

                for (var i = 0; i < data.length; i++) {
                    cost[i] = data[i]['cosphit'];
                }

                for (var i = 0; i < data.length; i++) {
                    p1[i] = data[i]['potativap1'];
                    pf1[i]=parseFloat(p1[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    p2[i] = data[i]['potativap2'];
                    pf2[i]=parseFloat(p2[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    p3[i] = data[i]['potativap3'];
                    pf3[i]=parseFloat(p3[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    pt[i] = data[i]['potativatotal'];
                    pft[i]=parseFloat(pt[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    s1[i] = data[i]['potaparentes1'];
                    sf1[i]=parseFloat(s1[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    s2[i] = data[i]['potaparentes2'];
                    sf2[i]=parseFloat(s2[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    s3[i] = data[i]['potaparentes3'];
                    sf3[i]=parseFloat(s3[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    st[i] = data[i]['potaparentetotalst'];
                    sft[i]=parseFloat(st[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    q1[i] = data[i]['potreativaq1'];
                    qf1[i]=parseFloat(q1[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    q2[i] = data[i]['potreativaq2'];
                    qf2[i]=parseFloat(q2[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    q3[i] = data[i]['potreativaq3'];
                    qf3[i]=parseFloat(q3[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    qt[i] = data[i]['potreativatotal'];
                    qft[i]=parseFloat(qt[i].replace(',',''));
                }

                for (var i = 0; i < data.length; i++) {
                    consw[i] = data[i]['consumidawh'];
                }

                for (var i = 0; i < data.length; i++) {
                    fornw[i] = data[i]['fornecidawh'];
                }

                for (var i = 0; i < data.length; i++) {
                    consv[i] = data[i]['consumidavarh'];
                }

                for (var i = 0; i < data.length; i++) {
                    fornv[i] = data[i]['fornecidavarh'];
                }

                for (var i = 0; i < data.length; i++) {
                    freq[i] = data[i]['frequencia'];
                }

                for (var i = 0; i < data.length; i++) {
                    //dataHora[i] = data[i]['dataHora'].substr(11,8);
                    dataHora[i] = data[i]['hora'];
                }
                //inicio fator potencia
                var cosphiChartData = {
                    labels: [dataHora[24],dataHora[23],dataHora[22],dataHora[21],dataHora[20],dataHora[19],dataHora[18],dataHora[17],dataHora[16],dataHora[15],dataHora[14],dataHora[13],dataHora[12],dataHora[11],dataHora[10],dataHora[9],dataHora[8],dataHora[7],dataHora[6],dataHora[5],dataHora[4],dataHora[3],dataHora[2],dataHora[1],dataHora[0]],
                    datasets: [{
                        label: 'CosPhi1',
                        borderColor: '#4b2d73',
                        backgroundColor: '#4b2d73',
                        fill: false,
                        data: [
                            cos1[24],cos1[23],cos1[22],cos1[21],cos1[20],cos1[19],cos1[18],cos1[17],cos1[16],cos1[15],cos1[14],cos1[13],cos1[12],cos1[11],cos1[10],cos1[9],cos1[8],cos1[7],cos1[6],cos1[5],cos1[4],cos1[3],cos1[2],cos1[1],cos1[0]
                        ],
                        yAxisID: 'y-axis-1',
                    } , {
                        label: 'CosPhi2',
                        borderColor: '#A73C60',
                        backgroundColor: '#A73C60',
                        fill: false,
                        data: [
                            cos2[24],cos2[23],cos2[22],cos2[21],cos2[20],cos2[19],cos2[18],cos2[17],cos2[16],cos2[15],cos2[14],cos2[13],cos2[12],cos2[11],cos2[10],cos2[9],cos2[8],cos2[7],cos2[6],cos2[5],cos2[4],cos2[3],cos2[2],cos2[1],cos2[0]
                        ],
                        yAxisID: 'y-axis-1'
                    }, {
                        label: 'CosPhi3',
                        borderColor: ' #3CA74E',
                        backgroundColor: ' #3CA74E',
                        fill: false,
                        data: [
                            cos3[24],cos3[23],cos3[22],cos3[21],cos3[20],cos3[19],cos3[18],cos3[17],cos3[16],cos3[15],cos3[14],cos3[13],cos3[12],cos3[11],cos3[10],cos3[9],cos3[8],cos3[7],cos3[6],cos3[5],cos3[4],cos3[3],cos3[2],cos3[1],cos3[0]
                        ],
                        yAxisID: 'y-axis-1'
                    }, {
                        label: 'CosPhiTotal',
                        borderColor: ' #E80CEF',
                        backgroundColor: ' #E80CEF',
                        fill: false,
                        data: [
                            cost[24],cost[23],cost[22],cost[21],cost[20],cost[19],cost[18],cost[17],cost[16],cost[15],cost[14],cost[13],cost[12],cost[11],cost[10],cost[9],cost[8],cost[7],cost[6],cost[5],cost[4],cost[3],cost[2],cost[1],cost[0]
                        ],
                        yAxisID: 'y-axis-1'
                    }
                ]
                };
            
                    var ctx = document.getElementById('cosphimulti').getContext('2d');
                    window.myLine = Chart.Line(ctx, {
                        data: cosphiChartData,
                        options: {
                            responsive: true,
                            hoverMode: 'index',
                            stacked: false,
                            title: {
                                display: true,
                                text: 'Fator de Potência - Últimos 25 registros'
                            },
                            scales: {
                                yAxes: [{
                                    type: 'linear', 
                                    display: true,
                                    position: 'left',
                                    id: 'y-axis-1',
                                }],
                            }
                        }
                    });

                    //fim graph fator potencia
                    //inicio graph potencia ativa
                    var ativaChartData = {
                        labels: [dataHora[24],dataHora[23],dataHora[22],dataHora[21],dataHora[20],dataHora[19],dataHora[18],dataHora[17],dataHora[16],dataHora[15],dataHora[14],dataHora[13],dataHora[12],dataHora[11],dataHora[10],dataHora[9],dataHora[8],dataHora[7],dataHora[6],dataHora[5],dataHora[4],dataHora[3],dataHora[2],dataHora[1],dataHora[0]],
                        datasets: [{
                            label: 'Ativa P1',
                            borderColor: '#4b2d73',
                            backgroundColor: '#4b2d73',
                            fill: false,
                            data: [
                                pf1[24],pf1[23],pf1[22],pf1[21],pf1[20],pf1[19],pf1[18],pf1[17],pf1[16],pf1[15],pf1[14],pf1[13],pf1[12],pf1[11],pf1[10],pf1[9],pf1[8],pf1[7],pf1[6],pf1[5],pf1[4],pf1[3],pf1[2],pf1[1],pf1[0]
                            ],
                            yAxisID: 'y-axis-1',
                        } , {
                            label: 'Ativa P2',
                            borderColor: '#A73C60',
                            backgroundColor: '#A73C60',
                            fill: false,
                            data: [
                                pf2[24],pf2[23],pf2[22],pf2[21],pf2[20],pf2[19],pf2[18],pf2[17],pf2[16],pf2[15],pf2[14],pf2[13],pf2[12],pf2[11],pf2[10],pf2[9],pf2[8],pf2[7],pf2[6],pf2[5],pf2[4],pf2[3],pf2[2],pf2[1],pf2[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Ativa P3',
                            borderColor: ' #3CA74E',
                            backgroundColor: ' #3CA74E',
                            fill: false,
                            data: [
                                pf3[24],pf3[23],pf3[22],pf3[21],pf3[20],pf3[19],pf3[18],pf3[17],pf3[16],pf3[15],pf3[14],pf3[13],pf3[12],pf3[11],pf3[10],pf3[9],pf3[8],pf3[7],pf3[6],pf3[5],pf3[4],pf3[3],pf3[2],pf3[1],pf3[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Ativa Total PT',
                            borderColor: ' #E80CEF',
                            backgroundColor: ' #E80CEF',
                            fill: false,
                            data: [
                                pft[4],pft[3],pft[2],pft[1],pft[0],pft[9],pft[8],pft[7],pft[6],pft[5],pft[4],pft[3],pft[2],pft[1],pft[0],pft[9],pft[8],pft[7],pft[6],pft[5],pft[4],pft[3],pft[2],pft[1],pft[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Apa S1',
                            borderColor: '#EF4836',
                            backgroundColor: '#EF4836',
                            fill: false,
                            data: [
                                sf1[4],sf1[3],sf1[2],sf1[1],sf1[0],sf1[9],sf1[8],sf1[7],sf1[6],sf1[5],sf1[4],sf1[3],sf1[2],sf1[1],sf1[0],sf1[9],sf1[8],sf1[7],sf1[6],sf1[5],sf1[4],sf1[3],sf1[2],sf1[1],sf1[0]
                            ],
                            yAxisID: 'y-axis-1',
                        } , {
                            label: 'Apa S2',
                            borderColor: '#36DDEF',
                            backgroundColor: '#36DDEF',
                            fill: false,
                            data: [
                                sf2[4],sf2[3],sf2[2],sf2[1],sf2[0],sf2[9],sf2[8],sf2[7],sf2[6],sf2[5],sf2[4],sf2[3],sf2[2],sf2[1],sf2[0],sf2[9],sf2[8],sf2[7],sf2[6],sf2[5],sf2[4],sf2[3],sf2[2],sf2[1],sf2[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Apa S3',
                            borderColor: '#4836EF',
                            backgroundColor: '#4836EF',
                            fill: false,
                            data: [
                                sf3[24],sf3[23],sf3[22],sf3[21],sf3[20],sf3[19],sf3[18],sf3[17],sf3[16],sf3[15],sf3[14],sf3[13],sf3[12],sf3[11],sf3[10],sf3[9],sf3[8],sf3[7],sf3[6],sf3[5],sf3[4],sf3[3],sf3[2],sf3[1],sf3[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Apa Total ST',
                            borderColor: '#EFA536',
                            backgroundColor: '#EFA536',
                            fill: false,
                            data: [
                                sft[24],sft[23],sft[22],sft[21],sft[20],sft[19],sft[18],sft[17],sft[16],sft[15],sft[14],sft[13],sft[12],sft[11],sft[10],sft[9],sft[8],sft[7],sft[6],sft[5],sft[4],sft[3],sft[2],sft[1],sft[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }
                    ]
                    };
                
                        var ctx = document.getElementById('potativamulti').getContext('2d');
                        window.myLine = Chart.Line(ctx, {
                            data: ativaChartData,
                            options: {
                                responsive: true,
                                hoverMode: 'index',
                                stacked: false,
                                title: {
                                    display: true,
                                    text: 'Potência Ativa e Aparente - Últimos 25 registros'
                                },
                                scales: {
                                    yAxes: [{
                                        type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
                                        display: true,
                                        position: 'left',
                                        id: 'y-axis-1',
                                    }],
                                }
                            }
                        });
                    //fim graph fator potencia              
                    //inicio graph potencia reativa
                    var reativaChartData = {
                        labels: [dataHora[24],dataHora[23],dataHora[22],dataHora[21],dataHora[20],dataHora[19],dataHora[18],dataHora[17],dataHora[16],dataHora[15],dataHora[14],dataHora[13],dataHora[12],dataHora[11],dataHora[10],dataHora[9],dataHora[8],dataHora[7],dataHora[6],dataHora[5],dataHora[4],dataHora[3],dataHora[2],dataHora[1],dataHora[0]],
                        datasets: [{
                            label: 'Reativa Q1',
                            borderColor: '#4b2d73',
                            backgroundColor: '#4b2d73',
                            fill: false,
                            data: [
                                qf1[24],qf1[23],qf1[22],qf1[21],qf1[20],qf1[19],qf1[18],qf1[17],qf1[16],qf1[15],qf1[14],qf1[13],qf1[12],qf1[11],qf1[10],qf1[9],qf1[8],qf1[7],qf1[6],qf1[5],qf1[4],qf1[3],qf1[2],qf1[1],qf1[0]
                            ],
                            yAxisID: 'y-axis-1',
                        } , {
                            label: 'Reativa Q2',
                            borderColor: '#A73C60',
                            backgroundColor: '#A73C60',
                            fill: false,
                            data: [
                                qf2[24],qf2[23],qf2[22],qf2[21],qf2[20],qf2[19],qf2[18],qf2[17],qf2[16],qf2[15],qf2[14],qf2[13],qf2[12],qf2[11],qf2[10],qf2[9],qf2[8],qf2[7],qf2[6],qf2[5],qf2[4],qf2[3],qf2[2],qf2[1],qf2[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Reativa Q3',
                            borderColor: ' #3CA74E',
                            backgroundColor: ' #3CA74E',
                            fill: false,
                            data: [
                                qf3[24],qf3[23],qf3[22],qf3[21],qf3[20],qf3[19],qf3[18],qf3[17],qf3[16],qf3[15],qf3[14],qf3[13],qf3[12],qf3[11],qf3[10],qf3[9],qf3[8],qf3[7],qf3[6],qf3[5],qf3[4],qf3[3],qf3[2],qf3[1],qf3[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }, {
                            label: 'Reativa Total QT',
                            borderColor: ' #E80CEF',
                            backgroundColor: ' #E80CEF',
                            fill: false,
                            data: [
                                qft[24],qft[23],qft[22],qft[21],qft[20],qft[19],qft[18],qft[17],qft[16],qft[15],qft[14],qft[13],qft[12],qft[11],qft[10],qft[9],qft[8],qft[7],qft[6],qft[5],qft[4],qft[3],qft[2],qft[1],qft[0]
                            ],
                            yAxisID: 'y-axis-1'
                        }
                    ]
                    };
                
                        var ctx = document.getElementById('potreatmulti').getContext('2d');
                        window.myLine = Chart.Line(ctx, {
                            data: reativaChartData,
                            options: {
                                responsive: true,
                                hoverMode: 'index',
                                stacked: false,
                                title: {
                                    display: true,
                                    text: 'Potência Reativa - Últimos 25 registros'
                                },
                                scales: {
                                    yAxes: [{
                                        type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
                                        display: true,
                                        position: 'left',
                                        id: 'y-axis-1',
                                    }],
                                }
                            }
                        });
                //fim
                //inicio graph 
                var frequencia = {
                    labels: [dataHora[24],dataHora[23],dataHora[22],dataHora[21],dataHora[20],dataHora[19],dataHora[18],dataHora[17],dataHora[16],dataHora[15],dataHora[14],dataHora[13],dataHora[12],dataHora[11],dataHora[10],dataHora[9],dataHora[8],dataHora[7],dataHora[6],dataHora[5],dataHora[4],dataHora[3],dataHora[2],dataHora[1],dataHora[0]],
                    datasets: [{
                        label: 'Frequência',
                        borderColor: '#4b2d73',
                        backgroundColor: '#4b2d73',
                        fill: false,
                        data: [
                            freq[24],freq[23],freq[22],freq[21],freq[20],freq[19],freq[18],freq[17],freq[16],freq[15],freq[14],freq[13],freq[12],freq[11],freq[10],freq[9],freq[8],freq[7],freq[6],freq[5],freq[4],freq[3],freq[2],freq[1],freq[0]
                        ],
                        yAxisID: 'y-axis-1',
                    } 
                ]
                };
            
                    var ctx = document.getElementById('frequencia').getContext('2d');
                    window.myLine = Chart.Line(ctx, {
                        data: frequencia,
                        options: {
                            responsive: true,
                            hoverMode: 'index',
                            stacked: false,
                            title: {
                                display: true,
                                text: 'Frequência - Últimos 25 registros'
                            },
                            scales: {
                                yAxes: [{
                                    type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
                                    display: true,
                                    position: 'left',
                                    id: 'y-axis-1',
                                }],
                            }
                        }
                    });
                     //fim
            //fim success
            },
            error: function(data) {
                console.log("ERRO");
            }
        });
    }  
            
});

