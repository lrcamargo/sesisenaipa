<?php
// pulso/gestao/_layout_head.php
// Emite <!DOCTYPE html> até </head> com todos os estilos compartilhados.
// Inclua ANTES do <body> em cada página de gestão.
// Requer: $titulo_pagina definido antes.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina ?? 'Gestão') ?> — PulsoSENAI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <?php
    // Inclui os estilos do _layout.php sem o HTML (só o <style>)
    // O _layout.php tem toda a CSS do sistema de gestão
    ob_start();
    include '_layout.php';
    $layout_html = ob_get_clean();
    // Extrai apenas o bloco <style>
    preg_match('/<style>(.*?)<\/style>/s', $layout_html, $m);
    if ($m) echo '<style>' . $m[1] . '</style>';
    ?>
</head>