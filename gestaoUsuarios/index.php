<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    //include('../conexaosec.php');
    include('../conexao.php');
    session_start();
    
    if((!isset ($_SESSION['sLogin']) == true)) {
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../index.php');    
    }
    
    $logado = $_SESSION['user'];
    $nivel = $_SESSION['group'];
    
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <title>Gestão de Usuários</title>
        
        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
          
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>

        <style>
            .labs {
                margin-top: 15%;
                margin-left: -85%;
                text-align: center;
            }

            .labs li:before {
                display: inline-block;
                margin-left: -1.3em; /* same as padding-left set on li */
                width: 1.3em; /* same as padding-left set on li */
            }

            td.details-control {
                background: url('https://datatables.net/examples/resources/details_open.png') no-repeat center center;
                cursor: pointer;
            }
            tr.shown td.details-control {
                background: url('https://datatables.net/examples/resources/details_close.png') no-repeat center center;
            }

            .status-ativo {
                color: green;
            }

            .status-inativo {
                color: red;
            }
        </style>
    </head>
    <body>
        <!-- Início Wrapper -->
        <div class="wrapper">
        <!-- Início Cabeçalho -->
            <div class="header">
                <div class="header-menu">
                    <div class="title"><img src="../img/logo_white.svg"></div>
                    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
                    <ul>
                        <li><a href="#" class="user"><?php echo $logado; ?></a></li>
                        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
                    </ul>
                </div>
            </div>
        <!-- Fim Cabeçalho -->
        <!--Inicio sidebar-->
            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('../menu.php'); ?>
                </div>
            </div>
        <!--Fim sidebar-->
        <!--Inicio conteúdo-->
            <div class="main-container">
            Gestão Usuários - Página Inicial
            <br/>
            <button class="btn btn-blue"><a href='cadastrarUsuario.php'><i class="fas fa-user-plus"></i> Cadastrar Usuário</button>    </a>
            <p>
            <table id="usuarios" class="display">
                <thead>
                    <tr>
                        <th></th> <th>Registro</th>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Editar</th>
                        <th>Desativar</th>
                    </tr>
                </thead>

            </table>
            </div>
            </div>
        <!--Fim wrapper-->
        <script type="text/javascript" src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
        <script type="text/javascript" src="../js/menu.js"></script>

        <script>
        let table = new DataTable('#usuarios', {
            responsive: true, // Habilita a responsividade
            processing: true, // Exibe uma mensagem de "Processando"
            serverSide: true, // Habilita o processamento no lado do servidor
            ajax: {
                url: 'dadosUsuarios.php', // URL do script PHP que fornece os dados
                type: 'POST' // Método HTTP para a requisição
            },
            columns: [
                {
                    className: 'details-control', // Classe CSS para o botão de expandir
                    orderable: false, // Esta coluna não pode ser ordenada
                    data: null, // Não busca dados do banco, é apenas um controle
                    defaultContent: '' // Conteúdo padrão vazio
                },
                { data: 'registro' },
                { data: 'nome' },
                { data: 'usuario' },             
                { 
                    data: null, // Coluna de ação, não busca dados
                    orderable: false,
                    render: function(data, type, row) {
                        return '<a href="editarUsuario.php?id=' + row.id + '" class="btn-editar">Editar</a>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        // Verifica o status para decidir qual botão exibir
                        if (row.status == 1) {
                            return '<a href="desativaUsuario.php?id=' + row.id + '" class="btn-desativar">Desativar</a>';
                        } else {
                            return '<a href="ativaUsuario.php?id=' + row.id + '" class="btn-ativar">Ativar</a>';
                        }
                    }
                }

            ],
            order: [[2, 'asc']], // Define a ordenação padrão para a segunda coluna (id)
            language: {
                // Configurações de tradução para português
                sEmptyTable: "Nenhum registro encontrado",
                sInfo: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                sInfoEmpty: "Mostrando 0 até 0 de 0 registros",
                sInfoFiltered: "(Filtrados de _MAX_ registros)",
                sInfoPostFix: "",
                sInfoThousands: ".",
                sLengthMenu: "_MENU_ resultados por página",
                sLoadingRecords: "Carregando...",
                sProcessing: "Processando...",
                sZeroRecords: "Nenhum registro encontrado",
                sSearch: "Pesquisar",
                oPaginate: {
                    sNext: "Próximo",
                    sPrevious: "Anterior",
                    sFirst: "Primeiro",
                    sLast: "Último"
                },
                oAria: {
                    sSortAscending: ": Ordenar colunas de forma ascendente",
                    sSortDescending: ": Ordenar colunas de forma descendente"
                },
                select: {
                    rows: {
                        _: "Selecionado %d linhas",
                        0: "Nenhuma linha selecionada",
                        1: "Selecionado 1 linha"
                    }
                }
            }
        });

        // Função que formata o conteúdo da linha filha para o "collapse"
        function format(d) {
            let statusText = d.status == 1 ? 'Ativo' : 'Inativo';
            let statusClass = d.status == 1 ? 'status-ativo' : 'status-inativo';

            return `<p>Detalhes do perfil para ${d.nome}:</p>
                    <ul>
                        <li><strong>ID:</strong> ${d.id}</li>
                        <li><strong>Registro:</strong> ${d.registro}</li>
                        <li><strong>Nome:</strong> ${d.nome}</li>
                        <li><strong>Usuário:</strong> ${d.usuario}</li>
                        <li><strong>Status:</strong> <span class="${statusClass}">${statusText}</span></li>
                        <li><strong>Perfil:</strong> ${d.perfil}</li>
                    </ul>`;
        }

        // Evento de clique para expandir/esconder a linha filha
        $('#usuarios tbody').on('click', 'td.details-control', function() {
            var tr = $(this).closest('tr');
            var row = table.row(tr);

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
            } else {
                row.child(format(row.data())).show();
                tr.addClass('shown');
            }
        });

    </script>
    </body>
</html>