<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/**
 * Send an HTML email via SMTP.
 * Returns true on success, false on failure.
 */
function akas_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
  $cfgPath = __DIR__ . '/smtp_config.php';
  $cfg = file_exists($cfgPath) ? require $cfgPath : [];

  $host = (string)($cfg['host'] ?? '');
  $port = (int)($cfg['port'] ?? 587);
  $user = (string)($cfg['username'] ?? '');
  $pass = (string)($cfg['password'] ?? '');
  $fromEmail = (string)($cfg['from_email'] ?? '');
  $fromName  = (string)($cfg['from_name'] ?? 'AKAS');

  if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
    // Not configured
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
