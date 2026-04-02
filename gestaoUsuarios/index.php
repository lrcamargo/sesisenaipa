<!DOCTYPE html>
<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    include('../conexao.php');
    session_start();

    if(!isset($_SESSION['sLogin'])){
        unset($_SESSION['sLogin']);
        unset($_SESSION['user']);
        unset($_SESSION['group']);
        header('location:../index.php');
        exit;
    }

    $logado    = $_SESSION['user'];
    $nivel     = $_SESSION['group'];
    $nivelNorm = strtolower(str_replace('.', '', $nivel));

    $permReset = in_array($nivelNorm, [
        'admin', 'administrator', 'sup tecnica', 'gerencia', 'sup pedagogica'
    ]);

    // Mensagens de retorno de ações
    $msgs = [
        'senha_resetada'  => ['tipo'=>'success', 'texto'=>'Senha redefinida. O usuário será solicitado a criar nova senha no próximo login.'],
        'nao_encontrado'  => ['tipo'=>'danger',  'texto'=>'Usuário não encontrado.'],
        'erro_permissao'  => ['tipo'=>'danger',  'texto'=>'Você não tem permissão para esta ação.'],
        'erro_db'         => ['tipo'=>'danger',  'texto'=>'Erro ao processar. Tente novamente.'],
        'erro_param'      => ['tipo'=>'danger',  'texto'=>'Requisição inválida.'],
    ];
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Gestão de Usuários</title>

        <link rel="stylesheet" href="../css/main.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <link rel="stylesheet" href="../css/telefone.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>

        <style>
            td.details-control {
                background: url('https://datatables.net/examples/resources/details_open.png') no-repeat center center;
                cursor: pointer;
            }
            tr.shown td.details-control {
                background: url('https://datatables.net/examples/resources/details_close.png') no-repeat center center;
            }
            .status-ativo   { color: green; }
            .status-inativo { color: red;   }

            /* ── Botões de ação da tabela ────────────────────────────
               Usam <button> em vez de <a> para evitar o efeito de
               link visitado (cor roxa) que confundia os usuários.   */
            .btn-acao {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 10px;
                border: none;
                border-radius: 4px;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                transition: filter .15s ease;
                white-space: nowrap;
            }
            .btn-acao:hover  { filter: brightness(.88); }
            .btn-acao:active { filter: brightness(.78); }

            .btn-editar   { background:#007bff; color:#fff; }
            .btn-desativar{ background:#dc3545; color:#fff; }
            .btn-ativar   { background:#28a745; color:#fff; }
            .btn-reset    { background:#fd7e14; color:#fff; }
        </style>
    </head>
    <body>
        <div class="wrapper">

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

            <div class="sidebar">
                <div class="sidebar-menu">
                    <?php include_once('../menu.php'); ?>
                </div>
            </div>

            <div class="main-container">

                <?php
                if(isset($_GET['msg'])){
                    $cod = $_GET['msg'];
                    if(isset($msgs[$cod])){
                        $m = $msgs[$cod];
                        echo "<div class='alert alert-{$m['tipo']} text-center'>{$m['texto']}</div>";
                    }
                }
                ?>

                Gestão Usuários — Página Inicial
                <br><br>

                <!-- Botão de cadastro corrigido: <button> real, sem <a> dentro -->
                <button class="btn btn-primary mb-3"
                        onclick="location.href='cadastrarUsuario.php'">
                    <i class="fas fa-user-plus mr-1"></i> Cadastrar Usuário
                </button>

                <table id="usuarios" class="display">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Registro</th>
                            <th>Nome</th>
                            <th>Usuário</th>
                            <th>Editar</th>
                            <?php if($permReset){ echo '<th>Resetar Senha</th>'; } ?>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>

            </div>
        </div>

        <script type="text/javascript" src="//cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
        <script type="text/javascript" src="../js/menu.js"></script>

        <script>
        var permReset = <?php echo $permReset ? 'true' : 'false'; ?>;

        // Monta as colunas dinamicamente conforme a permissão
        var colunas = [
            {
                className: 'details-control',
                orderable: false,
                data: null,
                defaultContent: ''
            },
            { data: 'registro' },
            { data: 'nome'     },
            { data: 'usuario'  },
            {
                data: null,
                orderable: false,
                render: function(data, type, row){
                    return '<button class="btn-acao btn-editar" '
                         + 'onclick="location.href=\'editarUsuario.php?id=' + row.id + '\'">'
                         + '<i class="fas fa-pen"></i> Editar'
                         + '</button>';
                }
            },
        ];

        // Coluna de reset só para quem tem permissão
        if(permReset){
            colunas.push({
                data: null,
                orderable: false,
                render: function(data, type, row){
                    return '<button class="btn-acao btn-reset" '
                         + 'onclick="confirmarReset(' + row.id + ', \'' + escHtml(row.nome) + '\')">'
                         + '<i class="fas fa-key"></i> Resetar Senha'
                         + '</button>';
                }
            });
        }

        // Coluna de ativar/desativar
        colunas.push({
            data: null,
            orderable: false,
            render: function(data, type, row){
                if(row.status == 1){
                    return '<button class="btn-acao btn-desativar" '
                         + 'onclick="location.href=\'desativaUsuario.php?id=' + row.id + '\'">'
                         + '<i class="fas fa-user-slash"></i> Desativar'
                         + '</button>';
                } else {
                    return '<button class="btn-acao btn-ativar" '
                         + 'onclick="location.href=\'ativaUsuario.php?id=' + row.id + '\'">'
                         + '<i class="fas fa-user-check"></i> Ativar'
                         + '</button>';
                }
            }
        });

        let table = new DataTable('#usuarios', {
            responsive:  true,
            processing:  true,
            serverSide:  true,
            ajax: { url: 'dadosUsuarios.php', type: 'POST' },
            columns: colunas,
            order: [[2, 'asc']],
            language: {
                sEmptyTable:    "Nenhum registro encontrado",
                sInfo:          "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                sInfoEmpty:     "Mostrando 0 até 0 de 0 registros",
                sInfoFiltered:  "(Filtrados de _MAX_ registros)",
                sInfoThousands: ".",
                sLengthMenu:    "_MENU_ resultados por página",
                sLoadingRecords:"Carregando...",
                sProcessing:    "Processando...",
                sZeroRecords:   "Nenhum registro encontrado",
                sSearch:        "Pesquisar",
                oPaginate: {
                    sNext: "Próximo", sPrevious: "Anterior",
                    sFirst: "Primeiro", sLast: "Último"
                }
            }
        });

        // Confirmação antes de resetar a senha
        function confirmarReset(id, nome){
            if(confirm('Resetar a senha de "' + nome + '"?\n')){
                location.href = 'resetSenha.php?id=' + id;
            }
        }

        // Escapa HTML para uso em atributos
        function escHtml(str){
            return String(str)
                .replace(/&/g,"&amp;").replace(/</g,"&lt;")
                .replace(/>/g,"&gt;").replace(/"/g,"&quot;")
                .replace(/'/g,"&#39;");
        }

        // Expand/collapse da linha de detalhes
        function format(d){
            var statusText  = d.status == 1 ? 'Ativo'   : 'Inativo';
            var statusClass = d.status == 1 ? 'status-ativo' : 'status-inativo';
            return '<p>Detalhes do perfil para ' + escHtml(d.nome) + ':</p>'
                 + '<ul>'
                 + '<li><strong>ID:</strong> '       + d.id       + '</li>'
                 + '<li><strong>Registro:</strong> ' + d.registro + '</li>'
                 + '<li><strong>Nome:</strong> '     + escHtml(d.nome)    + '</li>'
                 + '<li><strong>Usuário:</strong> '  + escHtml(d.usuario) + '</li>'
                 + '<li><strong>Status:</strong> <span class="' + statusClass + '">' + statusText + '</span></li>'
                 + '<li><strong>Perfil:</strong> '   + escHtml(d.perfil)  + '</li>'
                 + '</ul>';
        }

        $('#usuarios tbody').on('click', 'td.details-control', function(){
            var tr  = $(this).closest('tr');
            var row = table.row(tr);
            if(row.child.isShown()){
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