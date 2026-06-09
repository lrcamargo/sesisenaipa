<?php
// includes/email/config.php
// Configurações de e-mail para toda a intranet.
// Use uma conta Gmail dedicada para envios — ex: notificacoes.senaipouso@gmail.com
//
// CONFIGURAÇÃO DO GMAIL:
// 1. Crie uma conta Gmail dedicada (recomendado) ou use uma existente
// 2. Ative a verificação em duas etapas na conta
// 3. Acesse: myaccount.google.com → Segurança → Senhas de app
// 4. Gere uma "Senha de app" para "Outro (nome personalizado)" → "Intranet SENAI"
// 5. Copie a senha de 16 caracteres gerada e cole em MAIL_PASS abaixo
//    (NÃO use a senha normal da conta — só funciona com Senha de App)

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USER',     'orion.sspa@gmail.com');      // ← sua conta Gmail
define('MAIL_PASS',     'zjfd pdrb lsay hbmg');      // ← senha de app (16 chars)
define('MAIL_FROM',     'rion.sspa@gmail.com');      // ← mesmo email
define('MAIL_FROM_NAME','SENAI Pouso Alegre');
define('MAIL_ENCRYPTION','tls');

// URL base da intranet (para links nos emails)
define('INTRANET_URL', 'http://172.16.95.254');