<?php
// pulso/gestao/_layout.php
// Uso: include '_layout.php'; no início do <body> de cada página de gestão.
// Variável $pagina_ativa deve ser definida antes do include.
// Ex: $pagina_ativa = 'perguntas';
$pagina_ativa = $pagina_ativa ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?? 'Gestão' ?> — PulsoSENAI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <style>
        :root {
            --azul:    #164194;
            --azul-esc:#0d2d5e;
            --laranja: #E84910;
            --azul-c:  #008BD2;
            --cinza-f: #F4F6FA;
            --texto:   #1a1a2e;
            --verde:   #1a9e4a;
            --sidebar: 230px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cinza-f);
            color: var(--texto);
        }

        /* ── TOP BAR ── */
        .g-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            height: 54px;
            background: var(--azul-esc);
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 20px 0 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .g-topbar .logo img { height: 36px; }
        .g-topbar .unidade-tag {
            font-size: 12px; color: rgba(255,255,255,.6);
            display: flex; align-items: center; gap: 6px;
        }
        .g-topbar .unidade-tag strong { color: rgba(255,255,255,.9); }
        .g-topbar .user-info {
            display: flex; align-items: center; gap: 12px;
        }
        .g-topbar .perfil-badge {
            font-size: 11px; font-weight: 600;
            padding: 3px 10px; border-radius: 12px;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.85);
            text-transform: capitalize;
        }
        .g-topbar .user-nome {
            font-size: 13px; color: rgba(255,255,255,.8);
        }
        .g-topbar .btn-sair {
            font-size: 12px; color: rgba(255,255,255,.5);
            text-decoration: none; margin-left: 4px;
            display: flex; align-items: center; gap: 4px;
            transition: color .2s;
        }
        .g-topbar .btn-sair:hover { color: #fff; }

        /* ── SIDEBAR ── */
        .g-sidebar {
            position: fixed; top: 54px; left: 0; bottom: 0;
            width: var(--sidebar);
            background: var(--azul);
            overflow-y: auto;
            z-index: 100;
            padding-bottom: 32px;
        }
        .g-sidebar .nav-section {
            padding: 20px 16px 6px;
            font-size: 10px; font-weight: 700;
            color: rgba(255,255,255,.4);
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .g-sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 18px;
            font-size: 13.5px; color: rgba(255,255,255,.75);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all .15s;
        }
        .g-sidebar a:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }
        .g-sidebar a.ativo {
            background: rgba(255,255,255,.12);
            color: #fff; font-weight: 600;
            border-left-color: var(--laranja);
        }
        .g-sidebar a .fa {
            width: 18px; text-align: center;
            font-size: 14px; opacity: .8;
        }
        .g-sidebar a.ativo .fa { opacity: 1; }

        /* ── MAIN CONTENT ── */
        .g-main {
            margin-left: var(--sidebar);
            margin-top: 54px;
            padding: 28px 28px 60px;
            min-height: calc(100vh - 54px);
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: flex-start;
            justify-content: space-between; flex-wrap: wrap;
            gap: 16px; margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 22px; font-weight: 700; color: var(--azul);
            margin: 0;
        }
        .page-header .page-sub {
            font-size: 13px; color: #888; margin-top: 4px;
        }

        /* ── CARD GENÉRICO ── */
        .g-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(22,65,148,.07);
            overflow: hidden;
        }
        .g-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eef0f5;
            display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
        }
        .g-card-header h2 {
            font-size: 15px; font-weight: 700;
            color: var(--azul); margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .g-card-body { padding: 20px; }

        /* ── BOTÕES ── */
        .btn-pri {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px;
            background: var(--azul); color: #fff;
            font-size: 13px; font-weight: 600;
            border: none; border-radius: 8px;
            cursor: pointer; text-decoration: none;
            transition: background .2s;
        }
        .btn-pri:hover { background: #1a52c4; color: #fff; text-decoration: none; }

        .btn-sec {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 16px;
            background: transparent; color: var(--azul);
            font-size: 13px; font-weight: 600;
            border: 1.5px solid var(--azul); border-radius: 8px;
            cursor: pointer; text-decoration: none;
            transition: all .2s;
        }
        .btn-sec:hover { background: var(--azul); color: #fff; text-decoration: none; }

        .btn-danger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            background: transparent; color: #c0392b;
            font-size: 12px; font-weight: 600;
            border: 1.5px solid #e8b4ae; border-radius: 8px;
            cursor: pointer; text-decoration: none;
            transition: all .2s;
        }
        .btn-danger:hover { background: #fff0eb; border-color: #c0392b; }

        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }

        /* ── TABELA PADRÃO ── */
        .g-table { width: 100%; border-collapse: collapse; }
        .g-table th {
            font-size: 11px; font-weight: 700;
            color: #888; text-transform: uppercase;
            letter-spacing: .6px;
            padding: 10px 14px;
            border-bottom: 2px solid #eef0f5;
            text-align: left; white-space: nowrap;
        }
        .g-table td {
            padding: 12px 14px;
            font-size: 13.5px; color: #444;
            border-bottom: 1px solid #f3f4f8;
            vertical-align: middle;
        }
        .g-table tr:last-child td { border-bottom: none; }
        .g-table tr:hover td { background: #fafbff; }

        /* ── BADGES ── */
        .badge-cat {
            display: inline-block;
            font-size: 11px; font-weight: 600;
            padding: 3px 9px; border-radius: 10px;
            background: #eef3fd; color: var(--azul);
        }
        .badge-tipo {
            display: inline-block;
            font-size: 11px; font-weight: 600;
            padding: 3px 9px; border-radius: 10px;
        }
        .badge-escolha  { background: #eef3fd; color: #164194; }
        .badge-escala   { background: #fef9e7; color: #b7770d; }
        .badge-aberta   { background: #eafaf1; color: #1a9e4a; }
        .badge-fixa     { background: #f3eeff; color: #7c3aed; }
        .badge-variavel { background: #f0f3f8; color: #666; }

        /* ── ALERTAS ── */
        .alerta-ok {
            background: #eafaf1; border: 1px solid #a9dfbf;
            border-radius: 8px; padding: 12px 16px;
            font-size: 13px; color: var(--verde);
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .alerta-erro {
            background: #fff0eb; border: 1px solid #f5c6bb;
            border-radius: 8px; padding: 12px 16px;
            font-size: 13px; color: #c0392b;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }

        /* ── FORMULÁRIO ── */
        .form-label-g {
            font-size: 12px; font-weight: 600;
            color: #666; text-transform: uppercase;
            letter-spacing: .5px; margin-bottom: 6px;
            display: block;
        }
        .form-input-g {
            width: 100%; padding: 10px 13px;
            font-size: 14px; font-family: inherit;
            border: 1.5px solid #d0d9e8; border-radius: 8px;
            background: #fafbfd; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-input-g:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(22,65,148,.1);
            background: #fff;
        }
        .form-group-g { margin-bottom: 18px; }

        @media (max-width: 768px) {
            .g-sidebar { transform: translateX(-100%); }
            .g-main { margin-left: 0; padding: 20px 16px 60px; }
        }
    </style>
<?php /* HEAD fecha na página */ ?>