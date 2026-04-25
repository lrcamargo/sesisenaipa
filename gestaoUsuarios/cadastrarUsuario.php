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

    // [#3] Quem pode ver todos os perfis (incluindo Administrator, Sup. Tecnica, Gerencia)
    $permTodosPeris = in_array($nivelNorm, [
        'admin', 'administrator', 'sup tecnica', 'gerencia'
    ]);

    // Mensagens de retorno do cadUser.php
    $erros = [
        1 => "Preencha todos os campos obrigatórios.",
        2 => "Já existe um usuário com este e-mail ou nome de usuário.",
        3 => "Erro ao salvar o usuário. Tente novamente.",
    ];
    $oks = [
        1 => "Usuário cadastrado com sucesso e sincronizado com a catraca.",
        2 => "Usuário cadastrado no sistema. <strong>Atenção:</strong> não foi possível contatar a catraca agora — o cadastro foi adicionado à fila e será enviado automaticamente.",
        3 => "Usuário cadastrado no sistema. O registro já existia na catraca — nenhuma ação necessária lá.",
    ];
?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastrar Usuário</title>

    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/telefone.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" charset="utf-8"></script>

    <style>
        .form-container { max-width:600px; margin:-10px auto; padding:0; }
        .form-header { text-align:left; margin-bottom:20px; }
        .form-header h2 { color:#333; font-size:28px; margin:0; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:8px; color:#555; font-weight:bold; }
        .form-group input {
            width:80%; padding:12px; border:1px solid #ccc; border-radius:5px;
            box-sizing:border-box; font-size:16px; transition:border-color .3s ease;
        }
        .form-group input:focus { border-color:#007bff; outline:none; box-shadow:0 0 5px rgba(0,123,255,.2); }
        .submit-button {
            width:50%; padding:12px; background-color:#007bff; color:#fff;
            border:none; border-radius:5px; font-size:18px; font-weight:bold;
            cursor:pointer; transition:background-color .3s ease;
        }
        .submit-button:hover { background-color:#0056b3; }
        .profile-tags { display:flex; flex-wrap:wrap; gap:10px; }
        .profile-tags input[type="radio"] { display:none; }
        .profile-tag {
            cursor:pointer; padding:8px 15px; border-radius:20px; font-size:14px;
            font-weight:bold; transition:all .3s ease; border:2px solid transparent;
        }
        .tag-administrator  { background-color:#fce4ec; color:#c2185b; }
        .tag-suppedagogica  { background-color:#e3f2fd; color:#1976d2; }
        .tag-suptecnica     { background-color:#e0f2f1; color:#00897b; }
        .tag-instrutor      { background-color:#fff3e0; color:#ef6c00; }
        .tag-professor      { background-color:#ede7f6; color:#673ab7; }
        .tag-administrativo { background-color:#f1f8e9; color:#558b2f; }
        .tag-gerencia       { background-color:#ffebee; color:#c62828; }
        .tag-secretaria     { background-color:#e8eaf6; color:#3949ab; }
        .tag-consultoria    { background-color:#fafafa; color:#616161; border:1px solid #e0e0e0; }
        .tag-sesicat        { background-color:#fffde7; color:#f9a825; }
        .profile-tags input[type="radio"]:checked + .profile-tag { transform:scale(1.05); box-shadow:0 0 5px rgba(0,0,0,.2); border:2px solid; }
        .profile-tags input[type="radio"]:checked + .tag-administrator  { border-color:#c2185b; }
        .profile-tags input[type="radio"]:checked + .tag-suppedagogica  { border-color:#1976d2; }
        .profile-tags input[type="radio"]:checked + .tag-suptecnica     { border-color:#00897b; }
        .profile-tags input[type="radio"]:checked + .tag-instrutor      { border-color:#ef6c00; }
        .profile-tags input[type="radio"]:checked + .tag-professor      { border-color:#673ab7; }
        .profile-tags input[type="radio"]:checked + .tag-administrativo { border-color:#558b2f; }
        .profile-tags input[type="radio"]:checked + .tag-gerencia       { border-color:#c62828; }
        .profile-tags input[type="radio"]:checked + .tag-secretaria     { border-color:#3949ab; }
        .profile-tags input[type="radio"]:checked + .tag-consultoria    { border-color:#616161; }
        .profile-tags input[type="radio"]:checked + .tag-sesicat        { border-color:#f9a825; }
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
        <div class="sidebar-menu"><?php include_once('../menu.php'); ?></div>
    </div>

    <div class="main-container">
    <div class="form-container">

        <?php
        if(isset($_GET['erro'])){
            $cod = intval($_GET['erro']);
            if(isset($erros[$cod])){
                echo "<div class='alert alert-danger text-center mb-3'>{$erros[$cod]}</div>";
            }
        }
        if(isset($_GET['ok'])){
            $cod = intval($_GET['ok']);
            if($cod === 1) echo "<div class='alert alert-success text-center mb-3'>{$oks[1]}</div>";
            if($cod === 2) echo "<div class='alert alert-warning text-center mb-3'>{$oks[2]}</div>";
            if($cod === 3) echo "<div class='alert alert-info text-center mb-3'>{$oks[3]}</div>";
        }
        ?>

        <div class="form-header">
            <h2>Cadastrar Usuário</h2>
        </div>

        <form action="cadUser.php" method="POST">

            <div class="form-group">
                <label for="registro">Registro</label>
                <input type="text" id="registro" name="registro" placeholder="Número de Registro" required>
            </div>
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" placeholder="Nome completo" required>
            </div>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="text" id="email" name="email" placeholder="E-mail corporativo" required>
            </div>
            <div class="form-group">
                <label for="user">Usuário</label>
                <input type="text" id="user" name="user" placeholder="Usuario" readonly required>
            </div>
            <div class="form-group">
                <label for="apelido">Apelido</label>
                <input type="text" id="apelido" name="apelido" placeholder="Apelido" readonly required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Crie uma senha temporária" required>
            </div>

            <div class="form-group">
                <label>Perfil</label>
                <div class="profile-tags">
                    <?php
                    // [#3] Lista de perfis filtrada pelo nível do usuário logado
                    $todosPeris = [
                        ['value'=>'Administrator',  'label'=>'Administrator',   'class'=>'tag-administrator'],
                        ['value'=>'Sup. Tecnica',   'label'=>'Sup. Tecnica',    'class'=>'tag-suptecnica'],
                        ['value'=>'Gerencia',       'label'=>'Gerencia',        'class'=>'tag-gerencia'],
                    ];
                    $perisComuns = [
                        ['value'=>'Sup. Pedagogica','label'=>'Sup. Pedagogica', 'class'=>'tag-suppedagogica'],
                        ['value'=>'Instrutor',      'label'=>'Instrutor',       'class'=>'tag-instrutor'],
                        ['value'=>'Professor',      'label'=>'Professor',       'class'=>'tag-professor'],
                        ['value'=>'Administrativo', 'label'=>'Administrativo',  'class'=>'tag-administrativo'],
                        ['value'=>'Secretaria',     'label'=>'Secretaria',      'class'=>'tag-secretaria'],
                        ['value'=>'Consultoria',    'label'=>'Consultoria',     'class'=>'tag-consultoria'],
                        ['value'=>'SESI CAT',       'label'=>'SESI CAT',        'class'=>'tag-sesicat'],
                    ];

                    $listaPeris = $permTodosPeris
                        ? array_merge($todosPeris, $perisComuns)
                        : $perisComuns;

                    foreach($listaPeris as $p){
                        $id = 'perfil_' . strtolower(str_replace([' ','.'], '', $p['value']));
                        echo "<input type='radio' id='{$id}' name='perfil' value='{$p['value']}' required>";
                        echo "<label for='{$id}' class='profile-tag {$p['class']}'>{$p['label']}</label>";
                    }
                    ?>
                </div>
            </div>

            <button type="submit" class="submit-button">Cadastrar</button>
        </form>
    </div>
    </div>
</div>

<script>
    document.getElementById("email").addEventListener("input", function(){
        let email = this.value;
        if(email.includes("@")){
            document.getElementById("user").value = email.split("@")[0];
        }
    });
</script>
<script type="text/javascript" src="../js/menu.js"></script>
</body>
</html>