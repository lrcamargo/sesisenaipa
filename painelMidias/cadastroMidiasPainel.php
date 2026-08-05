<?php
/*
 * cadastroMidiasPainel.php
 * Cadastro das mídias (imagens/vídeos) exibidas no painel.php (TV de entrada),
 * intercaladas com a ocupação dos ambientes.
 *
 * O banco guarda apenas o ENDEREÇO do arquivo (caminho relativo à raiz do site,
 * ex: uploads/painelMidias/midia_x.jpg). Os arquivos físicos ficam todos na
 * pasta /uploads/painelMidias/.
 */

require_once('../conexao.php');
session_start();
if(!isset($_SESSION['sLogin'])){ header('location:../index.php'); exit; }
$nivelNorm = strtolower(str_replace('.', '', $_SESSION['group']));
if(!in_array($nivelNorm,['admin','administrator','sup tecnica','sup pedagogica','gerencia','administrativo'])){
    header('location:../index.php'); exit;
}

define('PASTA_UPLOAD_FS', __DIR__ . '/../uploads/painelMidias/'); // caminho físico no servidor
define('PASTA_UPLOAD_REL', 'uploads/painelMidias/');              // endereço relativo à raiz do site (guardado no banco)
define('EXT_IMG', ['jpg','jpeg','png','gif','webp']);
define('EXT_VID', ['mp4','webm','ogg']);

/* Normaliza um link de YouTube (qualquer formato comum) para a URL de embed
   com autoplay/sem controles. Links que não são do YouTube (ex: Canva) são
   devolvidos como vieram — nesse caso espera-se que o usuário já cole o link
   de "Inserir/Embed" (não o link comum de compartilhar). */
function normalizarEmbedUrl($url){
    $url = trim($url);
    if(preg_match('#youtu\.be/([a-zA-Z0-9_-]{6,})#', $url, $m)
        || preg_match('#youtube\.com/watch\?v=([a-zA-Z0-9_-]{6,})#', $url, $m)
        || preg_match('#youtube\.com/shorts/([a-zA-Z0-9_-]{6,})#', $url, $m)
        || preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]{6,})#', $url, $m)
    ){
        $id = $m[1];
        return 'https://www.youtube.com/embed/'.$id.'?autoplay=1&mute=0&controls=0&rel=0&modestbranding=1&playsinline=1';
    }
    return $url;
}

/* ── POST AJAX ── */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acao'])){
    ob_clean(); header('Content-Type: application/json');

    if(!is_dir(PASTA_UPLOAD_FS)) @mkdir(PASTA_UPLOAD_FS, 0775, true);

    /* Salvar/editar mídia */
    if($_POST['acao']==='salvar_midia'){
        $id         = intval($_POST['id'] ?? 0);
        $titulo     = trim($_POST['titulo'] ?? '');
        $duracao    = max(2, intval($_POST['duracaoSegundos'] ?? 8));
        $dataInicio = trim($_POST['dataInicio'] ?? '');
        $dataFim    = trim($_POST['dataFim'] ?? '');
        $dataInicio = $dataInicio !== '' ? $dataInicio : null;
        $dataFim    = $dataFim !== '' ? $dataFim : null;

        if($dataInicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataInicio)){
            echo json_encode(['ok'=>false,'msg'=>'Data de início inválida.']); exit;
        }
        if($dataFim && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dataFim)){
            echo json_encode(['ok'=>false,'msg'=>'Data de término inválida.']); exit;
        }
        if($dataInicio && $dataFim && $dataFim < $dataInicio){
            echo json_encode(['ok'=>false,'msg'=>'A data de término não pode ser anterior à data de início.']); exit;
        }

        $temArquivoNovo = isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] !== UPLOAD_ERR_NO_FILE;
        $linkUrl      = trim($_POST['linkUrl'] ?? '');
        $enderecoNovo = null;
        $tipo         = null;

        if($linkUrl !== ''){
            if(!preg_match('#^https?://#i', $linkUrl)){
                echo json_encode(['ok'=>false,'msg'=>'Link inválido. Cole uma URL começando com http:// ou https://.']); exit;
            }
            $tipo = 'embed';
            $enderecoNovo = normalizarEmbedUrl($linkUrl);
        } elseif($temArquivoNovo){
            if($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK){
                echo json_encode(['ok'=>false,'msg'=>'Erro ao receber o arquivo (verifique o tamanho/upload_max_filesize do PHP).']); exit;
            }
            $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, EXT_IMG))       $tipo = 'imagem';
            elseif(in_array($ext, EXT_VID))   $tipo = 'video';
            else {
                echo json_encode(['ok'=>false,'msg'=>'Formato não suportado. Use JPG, PNG, GIF, WEBP (imagem) ou MP4, WEBM, OGG (vídeo).']); exit;
            }
            $tamanhoMax = $tipo === 'video' ? 80*1024*1024 : 10*1024*1024;
            if($_FILES['arquivo']['size'] > $tamanhoMax){
                echo json_encode(['ok'=>false,'msg'=>'Arquivo muito grande (máx '.($tipo==='video'?'80MB para vídeo':'10MB para imagem').').']); exit;
            }
            $nomeArquivo = uniqid('midia_', true);
            $nomeArquivo = preg_replace('/[^a-zA-Z0-9_]/','',$nomeArquivo) . '.' . $ext;
            if(!move_uploaded_file($_FILES['arquivo']['tmp_name'], PASTA_UPLOAD_FS . $nomeArquivo)){
                echo json_encode(['ok'=>false,'msg'=>'Falha ao salvar o arquivo no servidor. Verifique permissões da pasta uploads/painelMidias.']); exit;
            }
            $enderecoNovo = PASTA_UPLOAD_REL . $nomeArquivo; // endereço relativo guardado no banco
        }

        try {
            if($id > 0){
                if($enderecoNovo){
                    $st = $pdo->prepare("SELECT arquivo FROM painel_midias WHERE id=?");
                    $st->execute([$id]);
                    $enderecoAntigo = $st->fetchColumn();
                    $pdo->prepare("
                        UPDATE painel_midias
                        SET titulo=?, tipo=?, arquivo=?, duracaoSegundos=?, dataInicio=?, dataFim=?
                        WHERE id=?
                    ")->execute([$titulo, $tipo, $enderecoNovo, $duracao, $dataInicio, $dataFim, $id]);
                    if($enderecoAntigo && $enderecoAntigo !== $enderecoNovo){
                        $caminhoAntigo = __DIR__ . '/../' . $enderecoAntigo;
                        if(file_exists($caminhoAntigo)) @unlink($caminhoAntigo);
                    }
                } else {
                    $pdo->prepare("
                        UPDATE painel_midias
                        SET titulo=?, duracaoSegundos=?, dataInicio=?, dataFim=?
                        WHERE id=?
                    ")->execute([$titulo, $duracao, $dataInicio, $dataFim, $id]);
                }
            } else {
                if(!$enderecoNovo){
                    echo json_encode(['ok'=>false,'msg'=>'Selecione um arquivo (imagem/vídeo) ou cole um link (YouTube/Canva).']); exit;
                }
                $novaOrdem = (int)$pdo->query("SELECT COALESCE(MAX(ordem),-1) FROM painel_midias")->fetchColumn() + 1;
                $pdo->prepare("
                    INSERT INTO painel_midias (titulo,tipo,arquivo,duracaoSegundos,ordem,ativo,dataInicio,dataFim)
                    VALUES (?,?,?,?,?,1,?,?)
                ")->execute([$titulo, $tipo, $enderecoNovo, $duracao, $novaOrdem, $dataInicio, $dataFim]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]);
        } catch(PDOException $e){
            echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar: '.$e->getMessage()]);
        }
        exit;
    }

    /* Excluir mídia */
    if($_POST['acao']==='excluir_midia'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $st = $pdo->prepare("SELECT arquivo FROM painel_midias WHERE id=?");
            $st->execute([$id]);
            $endereco = $st->fetchColumn();
            $pdo->prepare("DELETE FROM painel_midias WHERE id=?")->execute([$id]);
            if($endereco){
                $caminho = __DIR__ . '/../' . $endereco;
                if(file_exists($caminho)) @unlink($caminho);
            }
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* Ativar/Desativar */
    if($_POST['acao']==='toggle_ativo'){
        $id = intval($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE painel_midias SET ativo = 1-ativo WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    /* Mover (reordenar) */
    if($_POST['acao']==='mover'){
        $id  = intval($_POST['id'] ?? 0);
        $dir = $_POST['direcao'] ?? '';
        try {
            $lista = $pdo->query("SELECT id, ordem FROM painel_midias ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $idx = null;
            foreach($lista as $i=>$row){ if($row['id']==$id){ $idx=$i; break; } }
            if($idx === null){ echo json_encode(['ok'=>false,'msg'=>'Item não encontrado.']); exit; }
            $alvo = $dir === 'up' ? $idx-1 : $idx+1;
            if($alvo < 0 || $alvo >= count($lista)){ echo json_encode(['ok'=>true]); exit; }
            $a = $lista[$idx]; $b = $lista[$alvo];
            $pdo->prepare("UPDATE painel_midias SET ordem=? WHERE id=?")->execute([$b['ordem'], $a['id']]);
            $pdo->prepare("UPDATE painel_midias SET ordem=? WHERE id=?")->execute([$a['ordem'], $b['id']]);
            echo json_encode(['ok'=>true]);
        } catch(PDOException $e){ echo json_encode(['ok'=>false,'msg'=>'Erro.']); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida.']); exit;
}

/* ── Dados ── */
$midias = $pdo->query("SELECT * FROM painel_midias ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$hojeStr = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mídias do Painel — TV</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="stylesheet" href="../css/telefone.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
<style>
.midia-table th{background:#343a40;color:#fff;font-size:.8rem;white-space:nowrap;}
.midia-table td{font-size:.83rem;vertical-align:middle;}
.thumb{width:84px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;background:#f1f3f5;}
.thumb-video{width:84px;height:54px;border-radius:6px;border:1px solid #dee2e6;background:#161b22;
    display:flex;align-items:center;justify-content:center;color:#58a6ff;font-size:1.4rem;}
.badge-tipo{border-radius:4px;padding:2px 8px;font-size:.7rem;font-weight:700;}
.badge-tipo.imagem{background:#e8f5e9;color:#1b5e20;}
.badge-tipo.video{background:#e3f2fd;color:#0d47a1;}
.badge-tipo.embed{background:#f3e5f5;color:#6a1b9a;}
.thumb-embed{width:84px;height:54px;border-radius:6px;border:1px solid #dee2e6;background:#161b22;
    display:flex;align-items:center;justify-content:center;color:#ce93d8;font-size:1.4rem;overflow:hidden;}
.thumb-embed img{width:100%;height:100%;object-fit:cover;}
.badge-status{border-radius:4px;padding:2px 8px;font-size:.7rem;font-weight:700;display:inline-block;}
.badge-status.vigente{background:#e8f5e9;color:#1b5e20;}
.badge-status.agendada{background:#fff3cd;color:#664d03;}
.badge-status.expirada{background:#f8d7da;color:#721c24;}
.periodo-txt{font-size:.74rem;color:#6c757d;display:block;margin-top:2px;}
.mover-btns button{padding:2px 6px;font-size:.7rem;}
#toast{position:fixed;bottom:24px;right:24px;z-index:9999;min-width:250px;display:none;
    padding:12px 18px;border-radius:6px;font-weight:600;font-size:.875rem;
    box-shadow:0 4px 12px rgba(0,0,0,.18);}
#toast.ok {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
#toast.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.empty-state{text-align:center;padding:40px 20px;color:#adb5bd;}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:10px;}
.info-box{background:#e7f1ff;border:1px solid #b6d4fe;border-radius:8px;padding:10px 14px;
    font-size:.82rem;color:#084298;margin-bottom:16px;}
.form-tipo-btn{padding:6px 14px;border-radius:6px;border:2px solid #dee2e6;background:#fff;
    font-size:.82rem;font-weight:600;cursor:pointer;color:#495057;transition:all .12s;}
.form-tipo-btn.sel{border-color:#0d6efd;background:#cfe2ff;color:#084298;}
</style>
</head>
<body>
<div class="wrapper">
<div class="header"><div class="header-menu">
    <div class="title"><img src="../img/logo_white.svg"></div>
    <div class="sidebar-btn"><i class="fas fa-bars"></i></div>
    <ul>
        <li><a href="#" class="user"><?php echo htmlspecialchars($_SESSION['user']); ?></a></li>
        <li><a href="../sair.php" class="logout"><i class="fas fa-power-off"></i></a></li>
    </ul>
</div></div>
<div class="sidebar"><div class="sidebar-menu"><?php include_once('../menu.php'); ?></div></div>
<div class="main-container">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="fas fa-photo-video mr-2"></i>Mídias do Painel (TV)</h4>
    <button class="btn btn-primary btn-sm" onclick="abrirNovaMidia()">
        <i class="fas fa-plus mr-1"></i>Nova Mídia
    </button>
</div>

<div class="info-box">
    <i class="fas fa-info-circle mr-1"></i>
    As mídias <strong>ativas</strong> e dentro do período cadastrado aparecem em rotação no painel da TV, intercaladas com
    as páginas de ocupação dos ambientes. Deixe as datas em branco para a mídia ficar sem prazo de validade.
</div>

<?php if(empty($midias)): ?>
<div class="empty-state">
    <i class="fas fa-photo-video"></i>
    Nenhuma mídia cadastrada ainda.<br>
    <button class="btn btn-primary btn-sm mt-3" onclick="abrirNovaMidia()">
        <i class="fas fa-plus mr-1"></i>Cadastrar primeira mídia
    </button>
</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-bordered midia-table bg-white">
    <thead>
        <tr>
            <th style="width:100px">Prévia</th>
            <th>Título</th>
            <th style="width:90px">Tipo</th>
            <th style="width:90px">Duração</th>
            <th style="width:170px">Período</th>
            <th style="width:90px">Ordem</th>
            <th style="width:80px" class="text-center">Ativa</th>
            <th style="width:110px" class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($midias as $m):
        $status = 'vigente';
        if($m['dataInicio'] && $m['dataInicio'] > $hojeStr)      $status = 'agendada';
        elseif($m['dataFim'] && $m['dataFim'] < $hojeStr)        $status = 'expirada';
        $statusLabel = ['vigente'=>'Vigente','agendada'=>'Agendada','expirada'=>'Expirada'][$status];
        $thumbYoutube = null;
        if($m['tipo']==='embed' && preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]+)#', $m['arquivo'], $ytm)){
            $thumbYoutube = 'https://img.youtube.com/vi/'.$ytm[1].'/hqdefault.jpg';
        }
    ?>
    <tr id="mrow-<?php echo $m['id']; ?>">
        <td>
            <?php if($m['tipo']==='imagem'): ?>
            <img class="thumb" src="../<?php echo htmlspecialchars($m['arquivo']); ?>" alt="">
            <?php elseif($m['tipo']==='embed'): ?>
            <div class="thumb-embed">
                <?php if($thumbYoutube): ?>
                <img src="<?php echo htmlspecialchars($thumbYoutube); ?>" alt="">
                <?php else: ?>
                <i class="fas fa-link"></i>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="thumb-video"><i class="fas fa-file-video"></i></div>
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($m['titulo'] ?: '—'); ?></td>
        <td><span class="badge-tipo <?php echo $m['tipo']; ?>"><?php echo ['imagem'=>'Imagem','video'=>'Vídeo','embed'=>'Link'][$m['tipo']]; ?></span></td>
        <td><?php echo (int)$m['duracaoSegundos']; ?>s</td>
        <td>
            <span class="badge-status <?php echo $status; ?>"><?php echo $statusLabel; ?></span>
            <span class="periodo-txt">
                <?php
                if(!$m['dataInicio'] && !$m['dataFim']) echo 'Sem prazo';
                elseif($m['dataInicio'] && $m['dataFim']) echo date('d/m/Y',strtotime($m['dataInicio'])).' – '.date('d/m/Y',strtotime($m['dataFim']));
                elseif($m['dataInicio']) echo 'A partir de '.date('d/m/Y',strtotime($m['dataInicio']));
                else echo 'Até '.date('d/m/Y',strtotime($m['dataFim']));
                ?>
            </span>
        </td>
        <td class="mover-btns">
            <button class="btn btn-outline-secondary" onclick="moverMidia(<?php echo $m['id']; ?>,'up')" title="Mover para cima">
                <i class="fas fa-arrow-up"></i>
            </button>
            <button class="btn btn-outline-secondary" onclick="moverMidia(<?php echo $m['id']; ?>,'down')" title="Mover para baixo">
                <i class="fas fa-arrow-down"></i>
            </button>
        </td>
        <td class="text-center">
            <div class="custom-control custom-switch d-flex justify-content-center">
                <input type="checkbox" class="custom-control-input toggle-ativo" id="sw-<?php echo $m['id']; ?>"
                       <?php echo $m['ativo'] ? 'checked' : ''; ?> onchange="toggleAtivo(<?php echo $m['id']; ?>)">
                <label class="custom-control-label" for="sw-<?php echo $m['id']; ?>"></label>
            </div>
        </td>
        <td class="text-center">
            <button class="btn btn-outline-primary btn-sm"
                    onclick="editarMidia(<?php echo $m['id']; ?>,'<?php echo addslashes($m['titulo']); ?>',<?php echo (int)$m['duracaoSegundos']; ?>,'<?php echo $m['dataInicio'] ?: ''; ?>','<?php echo $m['dataFim'] ?: ''; ?>','<?php echo $m['tipo']; ?>','<?php echo $m['tipo']==='embed' ? addslashes($m['arquivo']) : ''; ?>')">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm ml-1" onclick="excluirMidia(<?php echo $m['id']; ?>)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

</div></div>

<!-- Modal Nova/Editar Mídia -->
<div class="modal fade" id="modalMidia" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="modalMidiaTitle"><i class="fas fa-photo-video mr-2"></i>Nova Mídia</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="mdId" value="0">
        <div class="form-group mb-3">
            <label class="font-weight-bold" style="font-size:.82rem">Título <small class="text-muted">(opcional, exibido como legenda)</small></label>
            <input type="text" id="mdTitulo" class="form-control form-control-sm" placeholder="Ex: Inscrições abertas - Curso Técnico">
        </div>
        <div class="form-group mb-3">
            <label class="font-weight-bold d-block mb-2" style="font-size:.82rem">Conteúdo</label>
            <div style="display:flex;gap:6px">
                <button type="button" class="form-tipo-btn" id="btnModoArquivo" onclick="selModo('arquivo')">
                    <i class="fas fa-upload mr-1"></i>Enviar arquivo
                </button>
                <button type="button" class="form-tipo-btn" id="btnModoLink" onclick="selModo('link')">
                    <i class="fas fa-link mr-1"></i>Link (YouTube/Canva)
                </button>
            </div>
        </div>
        <div class="form-group mb-3" id="wrapArquivo">
            <label class="font-weight-bold" style="font-size:.82rem">
                Arquivo (imagem ou vídeo) <span id="mdArquivoObrigatorio" class="text-danger">*</span>
            </label>
            <input type="file" id="mdArquivo" class="form-control-file" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.ogg">
            <small class="text-muted">Imagem até 10MB ou vídeo até 80MB. Ao editar, deixe em branco para manter o arquivo atual.</small>
        </div>
        <div class="form-group mb-3" id="wrapLink" style="display:none">
            <label class="font-weight-bold" style="font-size:.82rem">Link do YouTube ou Canva</label>
            <input type="text" id="mdLinkUrl" class="form-control form-control-sm" placeholder="https://www.youtube.com/watch?v=... ou link de embed do Canva">
            <small class="text-muted">
                YouTube: cole o link normal do vídeo (qualquer formato). Canva: use "Compartilhar → Inserir/Embed"
                e cole o link gerado lá (não o link comum de compartilhar). Atualizar o link aqui troca o conteúdo na hora,
                sem precisar editar o painel.
            </small>
        </div>
        <div class="form-row mb-3">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Duração na tela (segundos)</label>
                <input type="number" id="mdDuracao" class="form-control form-control-sm" min="2" step="1" value="8">
                <small class="text-muted">Vale para imagem e link. Vídeo enviado por arquivo toca a duração real dele, com som, e avança sozinho ao terminar.</small>
            </div>
        </div>
        <div class="form-row mb-0">
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Exibir a partir de <small class="text-muted">(opcional)</small></label>
                <input type="date" id="mdDataInicio" class="form-control form-control-sm">
            </div>
            <div class="col">
                <label class="font-weight-bold" style="font-size:.82rem">Exibir até <small class="text-muted">(opcional)</small></label>
                <input type="date" id="mdDataFim" class="form-control form-control-sm">
            </div>
        </div>
        <small class="text-muted d-block mt-2">Deixe as duas datas em branco para a mídia ficar sem prazo de validade.</small>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="salvarMidia()">
            <i class="fas fa-save mr-1"></i>Salvar
        </button>
    </div>
</div></div>
</div>

<div id="toast"></div>
<script src="../js/menu.js"></script>
<script>
var _modoMidia = 'arquivo';
function selModo(modo){
    _modoMidia = modo;
    document.getElementById('btnModoArquivo').className = 'form-tipo-btn'+(modo==='arquivo'?' sel':'');
    document.getElementById('btnModoLink').className = 'form-tipo-btn'+(modo==='link'?' sel':'');
    document.getElementById('wrapArquivo').style.display = modo==='arquivo' ? '' : 'none';
    document.getElementById('wrapLink').style.display = modo==='link' ? '' : 'none';
}
function abrirNovaMidia(){
    document.getElementById('mdId').value='0';
    document.getElementById('mdTitulo').value='';
    document.getElementById('mdArquivo').value='';
    document.getElementById('mdLinkUrl').value='';
    document.getElementById('mdDuracao').value='8';
    document.getElementById('mdDataInicio').value='';
    document.getElementById('mdDataFim').value='';
    document.getElementById('mdArquivoObrigatorio').style.display='';
    document.getElementById('modalMidiaTitle').innerHTML='<i class="fas fa-plus mr-2"></i>Nova Mídia';
    selModo('arquivo');
    $('#modalMidia').modal('show');
}
function editarMidia(id,titulo,duracao,dataInicio,dataFim,tipo,linkUrlAtual){
    document.getElementById('mdId').value=id;
    document.getElementById('mdTitulo').value=titulo;
    document.getElementById('mdArquivo').value='';
    document.getElementById('mdLinkUrl').value = tipo==='embed' ? (linkUrlAtual||'') : '';
    document.getElementById('mdDuracao').value=duracao;
    document.getElementById('mdDataInicio').value=dataInicio||'';
    document.getElementById('mdDataFim').value=dataFim||'';
    document.getElementById('mdArquivoObrigatorio').style.display='none';
    document.getElementById('modalMidiaTitle').innerHTML='<i class="fas fa-edit mr-2"></i>Editar Mídia';
    selModo(tipo==='embed' ? 'link' : 'arquivo');
    $('#modalMidia').modal('show');
}
function salvarMidia(){
    var id         = document.getElementById('mdId').value;
    var titulo     = document.getElementById('mdTitulo').value.trim();
    var duracao    = document.getElementById('mdDuracao').value || '8';
    var dataInicio = document.getElementById('mdDataInicio').value;
    var dataFim    = document.getElementById('mdDataFim').value;
    var arquivo    = document.getElementById('mdArquivo').files[0];
    var linkUrl    = document.getElementById('mdLinkUrl').value.trim();
    if(dataInicio && dataFim && dataFim < dataInicio){ alert('A data de término não pode ser anterior à data de início.'); return; }
    if(_modoMidia==='link'){
        if(!linkUrl){ alert('Cole o link do YouTube ou Canva.'); return; }
        if(!/^https?:\/\//i.test(linkUrl)){ alert('Link inválido. Cole uma URL começando com http:// ou https://.'); return; }
    } else if(id==='0' && !arquivo){
        alert('Selecione um arquivo de imagem ou vídeo.'); return;
    }
    var fd = new FormData();
    fd.append('acao','salvar_midia');
    fd.append('id', id);
    fd.append('titulo', titulo);
    fd.append('duracaoSegundos', duracao);
    fd.append('dataInicio', dataInicio);
    fd.append('dataFim', dataFim);
    if(_modoMidia==='link'){
        fd.append('linkUrl', linkUrl);
    } else if(arquivo){
        fd.append('arquivo', arquivo);
    }
    fetch('cadastroMidiasPainel.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Salvo!','ok');
            $('#modalMidia').modal('hide');
            setTimeout(function(){ location.reload(); },600);
        }).catch(function(){ toast('Erro.','err'); });
}
function excluirMidia(id){
    if(!confirm('Remover esta mídia do painel?')) return;
    var fd=new FormData(); fd.append('acao','excluir_midia'); fd.append('id',id);
    fetch('cadastroMidiasPainel.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            var el=document.getElementById('mrow-'+id); if(el) el.remove();
            toast('Removida.','ok');
        }).catch(function(){ toast('Erro.','err'); });
}
function toggleAtivo(id){
    var fd=new FormData(); fd.append('acao','toggle_ativo'); fd.append('id',id);
    fetch('cadastroMidiasPainel.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            toast('Atualizado.','ok');
        }).catch(function(){ toast('Erro.','err'); });
}
function moverMidia(id,direcao){
    var fd=new FormData(); fd.append('acao','mover'); fd.append('id',id); fd.append('direcao',direcao);
    fetch('cadastroMidiasPainel.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(res){
            if(!res.ok){ toast(res.msg||'Erro.','err'); return; }
            location.reload();
        }).catch(function(){ toast('Erro.','err'); });
}
var _tt=null;
function toast(msg,tipo){
    var el=document.getElementById('toast');
    el.textContent=msg; el.className=tipo==='ok'?'ok':'err'; el.style.display='block';
    if(_tt)clearTimeout(_tt); _tt=setTimeout(function(){el.style.display='none';},3000);
}
</script>
</body>
</html>