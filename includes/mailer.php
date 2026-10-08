<?php
// Sends emails through SMTP using PHPMailer.
// Usage:
//   require_once __DIR__ . '/../includes/mailer.php';
//   $ok = send_mail('a@b.com', 'Juan', 'Subject', mail_layout('Title', '<p>Hello</p>'), $error);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send one HTML email. Returns true on success.
 * On failure returns false and puts a readable reason in $error.
 * $debug = true prints the SMTP conversation (only for the test page).
 */
function send_mail(string $to_email, string $to_name, string $subject, string $html_body,
                   ?string &$error = null, bool $debug = false): bool {
    $error = null;

    $config_file = __DIR__ . '/mail_config.php';
    if (!is_file($config_file)) {
        $error = 'Email is not set up: includes/mail_config.php is missing.';
        return false;
    }
    $cfg = require $config_file;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->Port       = (int)$cfg['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = ($cfg['encryption'] === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = $debug ? 2 : 0;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = trim(strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $html_body)));

        $mail->send();
        return true;
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}

/** Wraps email content in a simple KaFoodie-branded layout (orange/cream, Poppins fallback). */
function mail_layout(string $title, string $content_html): string {
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return '
<div style="background:#FFF8F0;padding:32px 16px;font-family:Poppins,Arial,Helvetica,sans-serif;">
  <div style="max-width:480px;margin:0 auto;background:#FFFFFF;border:1px solid #ECD9C9;border-radius:16px;overflow:hidden;">
    <div style="background:#FF6B35;padding:20px 28px;">
      <span style="color:#FFFFFF;font-size:22px;font-weight:700;">KaFoodie</span>
    </div>
    <div style="padding:28px;color:#1F2933;font-size:15px;line-height:1.6;">
      <h2 style="margin:0 0 16px;font-size:20px;color:#1F2933;">' . $title . '</h2>
      ' . $content_html . '
    </div>
    <div style="padding:16px 28px;background:#FFF8F0;color:#6B7280;font-size:12px;">
      This is an automated message from KaFoodie. Please do not reply to this email.
    </div>
  </div>
</div>';
}
