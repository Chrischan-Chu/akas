<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an HTML email via SMTP.
 *
 * ✅ Backward-compatible:
 *   - Old calls still work.
 *   - Optional Reply-To lets clinics reply directly to the user's email.
 */
function akas_send_mail(
  string $toEmail,
  string $toName,
  string $subject,
  string $htmlBody,
  string $replyToEmail = '',
  string $replyToName  = ''
): bool {
  $cfgPath = __DIR__ . '/smtp_config.php';
  $cfg = file_exists($cfgPath) ? require $cfgPath : [];

  $host = (string)($cfg['host'] ?? '');
  $port = (int)($cfg['port'] ?? 587);
  $user = (string)($cfg['username'] ?? '');
  $pass = (string)($cfg['password'] ?? '');
  $fromEmail = (string)($cfg['from_email'] ?? '');
  $fromName  = (string)($cfg['from_name'] ?? 'AKAS');

  if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
    return false;
  }

  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->Port = $port;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail, $toName);

    // ✅ Reply-To so the clinic's reply goes to the user (not the Brevo sender)
    $replyToEmail = trim($replyToEmail);
    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
      $replyToName = trim($replyToName);
      $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[mailer] ' . $mail->ErrorInfo);
    return false;
  }
}
