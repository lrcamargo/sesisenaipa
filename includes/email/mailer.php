<?php
// includes/email/mailer.php
// Classe de e-mail reutilizável para toda a intranet.
// Usa PHPMailer via Composer.
//
// INSTALAÇÃO (uma vez no servidor):
//   cd /caminho/para/intranet
//   composer require phpmailer/phpmailer
//
// USO:
//   require_once '/caminho/includes/email/mailer.php';
//   $ok = Mailer::enviar(
//       'destino@email.com',
//       'Assunto aqui',
//       '<h1>Corpo HTML</h1>',
//       'Corpo texto simples'  // opcional
//   );

require_once __DIR__ . '/config.php';

// Autoload do Composer — ajuste o caminho se necessário
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    // Tenta caminhos alternativos comuns
    $alternativas = [
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../../../vendor/autoload.php',
    ];
    foreach ($alternativas as $alt) {
        if (file_exists($alt)) { $autoload = $alt; break; }
    }
}
require_once $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {

    /**
     * Envia um e-mail.
     *
     * @param string|array $para     Email(s) do destinatário. Array para múltiplos.
     * @param string       $assunto  Assunto do e-mail.
     * @param string       $html     Corpo em HTML.
     * @param string       $texto    Corpo em texto simples (opcional — gerado do HTML se omitido).
     * @param array        $anexos   Caminhos de arquivos para anexar (opcional).
     * @return bool        true se enviado com sucesso.
     */
    public static function enviar(
        string|array $para,
        string $assunto,
        string $html,
        string $texto = '',
        array  $anexos = []
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // Servidor SMTP
            $mail->isSMTP();
            $mail->Host        = MAIL_HOST;
            $mail->SMTPAuth    = true;
            $mail->Username    = MAIL_USER;
            $mail->Password    = MAIL_PASS;
            $mail->SMTPSecure  = MAIL_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port        = MAIL_PORT;
            $mail->CharSet     = 'UTF-8';

            // Remetente
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

            // Destinatários
            if (is_array($para)) {
                foreach ($para as $email) {
                    $mail->addAddress(trim($email));
                }
            } else {
                $mail->addAddress(trim($para));
            }

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = self::wrapTemplate($html, $assunto);
            $mail->AltBody = $texto ?: strip_tags(str_replace(['<br>', '<br/>', '</p>', '</h1>', '</h2>', '</h3>'], "\n", $html));

            // Anexos
            foreach ($anexos as $arquivo) {
                if (file_exists($arquivo)) {
                    $mail->addAttachment($arquivo);
                }
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('Mailer erro: ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Template HTML padrão da intranet.
     */
    private static function wrapTemplate(string $conteudo, string $titulo): string {
        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { margin:0; padding:0; background:#f4f6fa; font-family:\'Segoe UI\',sans-serif; }
  .wrap { max-width:600px; margin:32px auto; background:#fff;
          border-radius:12px; overflow:hidden;
          box-shadow:0 4px 16px rgba(22,65,148,.12); }
  .header { background:#164194; padding:24px 32px; }
  .header img { height:40px; }
  .header .titulo { color:#fff; font-size:18px; font-weight:700;
                    margin-top:12px; line-height:1.3; }
  .body { padding:28px 32px; font-size:14px; color:#333; line-height:1.7; }
  .footer { background:#f8faff; padding:16px 32px; font-size:12px;
            color:#aaa; border-top:1px solid #e8ecf2; text-align:center; }
  .btn { display:inline-block; background:#164194; color:#fff;
         padding:12px 24px; border-radius:8px; text-decoration:none;
         font-weight:600; margin:16px 0; }
  .badge { display:inline-block; padding:4px 12px; border-radius:12px;
           font-size:12px; font-weight:600; }
  .badge-ok  { background:#eafaf1; color:#1a9e4a; }
  .badge-err { background:#fff0eb; color:#c0392b; }
  hr { border:none; border-top:1px solid #eef0f5; margin:20px 0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:3px;height:32px;background:#E84910;border-radius:2px;"></div>
      <div>
        <div style="font-size:18px;font-weight:800;color:#fff;">PulsoSENAI</div>
        <div style="font-size:11px;color:rgba(255,255,255,.6);letter-spacing:.8px;">SISTEMA DE ESCUTA CONTÍNUA</div>
      </div>
    </div>
    <div class="titulo">' . htmlspecialchars($titulo) . '</div>
  </div>
  <div class="body">' . $conteudo . '</div>
  <div class="footer">
    SENAI Pouso Alegre — CFP Orlando Chiarini<br>
    Este é um e-mail automático. Não responda a esta mensagem.
  </div>
</div>
</body>
</html>';
    }

    /**
     * Testa a conexão SMTP. Útil para diagnóstico.
     * Retorna array ['ok' => bool, 'msg' => string]
     */
    public static function testar(): array {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPDebug  = 0;

            $mail->smtpConnect();
            $mail->smtpClose();
            return ['ok' => true, 'msg' => 'Conexão SMTP bem-sucedida'];
        } catch (Exception $e) {
            return ['ok' => false, 'msg' => $mail->ErrorInfo];
        }
    }
}