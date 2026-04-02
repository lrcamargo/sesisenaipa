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

    // [#3] Quem pode ver todos os perfis
    $permTodosPeris = in_array($nivelNorm, [
        'admin', 'administrator', 'sup tecnica', 'gerencia'
    ]);

    // [#1] Busca o usuário pelo ?id= enviado pelo botão Editar da listagem
    $usuario = null;
    $idUsuario = intval($_GET['id'] ?? 0);

    if($idUsuario > 0){
        $stmt = $pdo->prepare("
            SELECT id, registro, nome, email, usuario, perfil
            FROM usuarios
            WHERE id = ?
        ");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mensagens de retorno do editUser.php
    $msgs = [
        'ok'           => ['tipo' => 'success', 'texto' => 'Usuário atualizado com sucesso.'],
        'erro_vazio'   => ['tipo' => 'danger',  'texto' => 'Preencha os campos obrigatórios.'],
        'erro_db'      => ['tipo' => 'danger',  'texto' => 'Erro ao atualizar. Tente novamente.'],
        'nao_encontrado'=> ['tipo'=> 'danger',  'texto' => 'Usuário não encontrado.'],
    ];
?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Usuário</title>

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
        .senha-hint { font-size:0.82em; color:#888; margin-top:4px; }
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
        // Mensagens de retorno
        if(isset($_GET['msg'])){
            $cod = $_GET['msg'];
            if(isset($msgs[$cod])){
                $m = $msgs[$cod];
                echo "<div class='alert alert-{$m['tipo']} text-center mb-3'>{$m['texto']}</div>";
            }
        }

        // Usuário não encontrado pelo id
        if($idUsuario > 0 && !$usuario){
            echo "<div class='alert alert-danger text-center mb-3'>Usuário não encontrado.</div>";
        }
        ?>

        <div class="form-header">
            <h2>Editar Usuário</h2>
            <?php if($usuario){ ?>
                <small class="text-muted">Editando: <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong></small>
            <?php } ?>
        </div>

        <form action="editUser.php" method="POST">

            <!-- [#1] id oculto — identifica o usuário no banco sem depender do registro -->
            <input type="hidden" name="id" value="<?php echo $usuario['id'] ?? ''; ?>">

            <div class="form-group">
                <label for="registro">Registro</label>
                <input type="text" id="registro" name="registro"
                       placeholder="Número de Registro"
                       value="<?php echo htmlspecialchars($usuario['registro'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome"
                       placeholder="Nome completo"
                       value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="text" id="email" name="email"
                       placeholder="E-mail corporativo"
                       value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="user">Usuário</label>
                <input type="text" id="user" name="user"
                       placeholder="usuario"
                       value="<?php echo htmlspecialchars($usuario['usuario'] ?? ''); ?>"
                       required>
            </div>

            <!-- [#4] Senha não obrigatória ao editar -->
            <div class="form-group">
                <label for="senha">Nova Senha</label>
                <input type="password" id="senha" name="senha"
                       placeholder="Deixe em branco para manter a senha atual">
                <div class="senha-hint">Deixe em branco para não alterar a senha.</div>
            </div>

            <div class="form-group">
                <label>Perfil</label>
                <div class="profile-tags">

                    <?php
                    $perfilAtual = $usuario['perfil'] ?? '';

                    // [#3] Perfis visíveis conforme o nível do usuário logado
                    // Admin, Sup. Técnica e Gerência veem todos
                    // Os demais veem apenas o subconjunto definido
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
                        $checked = ($perfilAtual === $p['value']) ? 'checked' : '';
                        $id      = 'perfil_' . strtolower(str_replace([' ','.'], '', $p['value']));
                        echo "<input type='radio' id='{$id}' name='perfil' value='{$p['value']}' {$checked} required>";
                        echo "<label for='{$id}' class='profile-tag {$p['class']}'>{$p['label']}</label>";
                    }
                    ?>

                </div>
            </div>

            <button type="submit" class="submit-button">Salvar alterações</button>

        </form>
    </div>
    </div>
</div>

<script type="text/javascript" src="../js/menu.js"></script>
</body>
</html>