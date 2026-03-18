<!DOCTYPE html>
    <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
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
                .form-container {
                    max-width: 600px;
                    margin: -10px auto ;
                    padding: 0 0px;
                }

                .form-header {
                    text-align: left;
                    margin-bottom: 20px;
                }

                .form-header h2 {
                    color: #333;
                    font-size: 28px;
                    margin: 0;
                }

                .form-group {
                    margin-bottom: 20px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    color: #555;
                    font-weight: bold;
                }

                .form-group input {
                    width: 80%;
                    padding: 12px;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    box-sizing: border-box;
                    font-size: 16px;
                    transition: border-color 0.3s ease;
                }

                .form-group input:focus {
                    border-color: #007bff;
                    outline: none;
                    box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
                }

                .submit-button {
                    width: 50%;
                    padding: 12px;
                    background-color: #007bff;
                    color: #fff;
                    border: none;
                    border-radius: 5px;
                    font-size: 18px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }

                .submit-button:hover {
                    background-color: #0056b3;
                }

                .profile-tags {
                    display: flex;
                    flex-wrap: wrap; /* Permite que as etiquetas quebrem a linha */
                    gap: 10px; /* Espaço entre as etiquetas */
                }

                /* Esconde os radio buttons */
                .profile-tags input[type="radio"] {
                    display: none;
                }

                .profile-tag {
                    cursor: pointer;
                    padding: 8px 15px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: bold;
                    transition: all 0.3s ease;
                    border: 2px solid transparent;
                }

                /* Estilos de cor para cada perfil */
                .tag-administrator { background-color: #fce4ec; color: #c2185b; }
                .tag-suppedagogica { background-color: #e3f2fd; color: #1976d2; }
                .tag-suptecnica { background-color: #e0f2f1; color: #00897b; }
                .tag-instrutor { background-color: #fff3e0; color: #ef6c00; }
                .tag-professor { background-color: #ede7f6; color: #673ab7; }
                .tag-administrativo { background-color: #f1f8e9; color: #558b2f; }
                .tag-gerencia { background-color: #ffebee; color: #c62828; }
                .tag-secretaria { background-color: #e8eaf6; color: #3949ab; }
                .tag-consultoria { background-color: #fafafa; color: #616161; border: 1px solid #e0e0e0; }
                .tag-sesicat { background-color: #fffde7; color: #f9a825; }

                /* Estilo para quando a etiqueta é selecionada */
                .profile-tags input[type="radio"]:checked + .profile-tag {
                    transform: scale(1.05); /* Pequeno zoom */
                    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
                    border: 2px solid; /* Adiciona uma borda */
                }
                .profile-tags input[type="radio"]:checked + .tag-administrator { border-color: #c2185b; }
                .profile-tags input[type="radio"]:checked + .tag-suppedagogica { border-color: #1976d2; }
                .profile-tags input[type="radio"]:checked + .tag-suptecnica { border-color: #00897b; }
                .profile-tags input[type="radio"]:checked + .tag-instrutor { border-color: #ef6c00; }
                .profile-tags input[type="radio"]:checked + .tag-professor { border-color: #673ab7; }
                .profile-tags input[type="radio"]:checked + .tag-administrativo { border-color: #558b2f; }
                .profile-tags input[type="radio"]:checked + .tag-gerencia { border-color: #c62828; }
                .profile-tags input[type="radio"]:checked + .tag-secretaria { border-color: #3949ab; }
                .profile-tags input[type="radio"]:checked + .tag-consultoria { border-color: #616161; }
                .profile-tags input[type="radio"]:checked + .tag-sesicat { border-color: #f9a825; }
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
                <div class="form-container">
                    <div class="form-header">
                        <h2>Editar Usuário</h2>
                    </div>
                    <form action="editUser.php" method="POST">
                        <div class="form-group">
                            <label for="registro">Registro (é possível encontrar na intranet)</label>
                            <input type="text" id="registro" name="registro" placeholder="Número de Registro" required>
                        </div>
                        <div class="form-group">
                            <label for="nome">Nome Completo</label>
                            <input type="text" id="nome" name="nome" placeholder="Nome completo" required>
                        </div>
                        <div class="form-group">
                            <label for="user">Usuário (começo do e-mail/usuário da intranet)</label>
                            <input type="text" id="user" name="user" placeholder="usuario" required>
                        </div>
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" placeholder="Crie uma senha temporária" required>
                        </div>
                        <div class="form-group">
                            <label>Perfil</label>
                            <div class="profile-tags">
                                <input type="radio" id="perfil_administrator" name="perfil" value="Administrator" required>
                                <label for="perfil_administrator" class="profile-tag tag-administrator">Administrator</label>
                                
                                <input type="radio" id="perfil_suppedagogica" name="perfil" value="Sup. Pedagogica" required>
                                <label for="perfil_suppedagogica" class="profile-tag tag-suppedagogica">Sup. Pedagogica</label>
                                
                                <input type="radio" id="perfil_suptecnica" name="perfil" value="Sup. Tecnica" required>
                                <label for="perfil_suptecnica" class="profile-tag tag-suptecnica">Sup. Tecnica</label>

                                <input type="radio" id="perfil_instrutor" name="perfil" value="Instrutor" required>
                                <label for="perfil_instrutor" class="profile-tag tag-instrutor">Instrutor</label>
                                
                                <input type="radio" id="perfil_professor" name="perfil" value="Professor" required>
                                <label for="perfil_professor" class="profile-tag tag-professor">Professor</label>
                                
                                <input type="radio" id="perfil_administrativo" name="perfil" value="Administrativo" required>
                                <label for="perfil_administrativo" class="profile-tag tag-administrativo">Administrativo</label>

                                <input type="radio" id="perfil_gerencia" name="perfil" value="Gerencia" required>
                                <label for="perfil_gerencia" class="profile-tag tag-gerencia">Gerencia</label>
                                
                                <input type="radio" id="perfil_secretaria" name="perfil" value="Secretaria" required>
                                <label for="perfil_secretaria" class="profile-tag tag-secretaria">Secretaria</label>
                                
                                <input type="radio" id="perfil_consultoria" name="perfil" value="Consultoria" required>
                                <label for="perfil_consultoria" class="profile-tag tag-consultoria">Consultoria</label>

                                <input type="radio" id="perfil_sesicat" name="perfil" value="SESI CAT" required>
                                <label for="perfil_sesicat" class="profile-tag tag-sesicat">SESI CAT</label>
                            </div>
                        </div>
        
                        <button type="submit" class="submit-button">Editar</button>
                    </form>
                </div>
                </div>
                </div>
            <!--Fim wrapper-->
            
            <script type="text/javascript" src="../js/menu.js"></script>
    
            <script>

                document.getElementById("registro").addEventListener("blur", function(){

                    let registro = this.value;

                    if(registro == "") return;

                    fetch("buscarUsuario.php?registro=" + registro)
                    .then(res => res.json())
                    .then(dados => {

                        if(dados.erro){
                            alert("Usuário não encontrado");
                            return;
                        }

                        document.getElementById("nome").value = dados.nome;
                        document.getElementById("user").value = dados.usuario;

                        // marcar perfil
                        let perfil = dados.perfil;
                        let radio = document.querySelector('input[name="perfil"][value="'+perfil+'"]');
                        if(radio){
                            radio.checked = true;
                        }

                    });

                });

                </script>
        </body>
    </html>
