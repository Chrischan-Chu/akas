<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an HTML email via SMTP.
 *
 * Backward-compatible:
 * - Old calls still work.
 * - Optional Reply-To lets clinics reply directly to the user's email.
 * - Optional $logContext writes to email_logs when the table exists.
 */
function akas_send_mail(
  string $toEmail,
  string $toName,
  string $subject,
  string $htmlBody,
  string $replyToEmail = '',
  string $replyToName  = '',
  array $logContext = []
): bool {
  $cfgPath = __DIR__ . '/smtp_config.php';
  $cfg = file_exists($cfgPath) ? require $cfgPath : [];

  $host = (string)($cfg['host'] ?? '');
  $port = (int)($cfg['port'] ?? 587);
  $user = (string)($cfg['username'] ?? '');
  $pass = (string)($cfg['password'] ?? '');
  $fromEmail = (string)($cfg['from_email'] ?? '');
  $fromName  = (string)($cfg['from_name'] ?? 'AKAS');

  $context = akas_build_email_log_context($toEmail, $toName, $subject, $htmlBody, $logContext);

  if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
    akas_log_email_attempt(array_merge($context, [
      'is_ok' => 0,
      'error_message' => 'SMTP configuration is incomplete.',
    ]));
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

    $replyToEmail = trim($replyToEmail);
    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
      $replyToName = trim($replyToName);
      $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)))) ?: $subject;

    $mail->send();

    akas_log_email_attempt(array_merge($context, [
      'is_ok' => 1,
      'error_message' => null,
    ]));
    return true;
  } catch (Exception $e) {
    $err = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
    error_log('[mailer] ' . $err);
    akas_log_email_attempt(array_merge($context, [
      'is_ok' => 0,
      'error_message' => $err,
    ]));
    return false;
  }
}

function akas_email_log_message_from_html(string $htmlBody): string {
  $search = [
    '<br>', '<br/>', '<br />',
    '</p>', '</div>', '</li>', '</tr>', '</table>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>',
  ];
  $normalized = str_ireplace($search, "
", $htmlBody);
  $text = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace("/
{3,}/", "

", $text) ?? $text;
  return trim($text);
}

function akas_build_email_log_context(string $toEmail, string $toName, string $subject, string $htmlBody, array $logContext = []): array {
  return [
    'appointment_id'   => isset($logContext['appointment_id']) ? (int)$logContext['appointment_id'] : null,
    'clinic_id'        => isset($logContext['clinic_id']) ? (int)$logContext['clinic_id'] : null,
    'user_id'          => isset($logContext['user_id']) ? (int)$logContext['user_id'] : null,
    'doctor_id'        => isset($logContext['doctor_id']) ? (int)$logContext['doctor_id'] : null,
    'event_type'       => trim((string)($logContext['event_type'] ?? 'general')),
    'recipient_type'   => trim((string)($logContext['recipient_type'] ?? 'system')),
    'recipient_name'   => trim($toName),
    'recipient_email'  => trim($toEmail),
    'subject'          => trim($subject),
    'message'          => akas_email_log_message_from_html($htmlBody),
    'error_message'    => null,
    'is_ok'            => 0,
  ];
}

function akas_log_email_attempt(array $row): void {
  static $tableExists = null;

  try {
    $pdo = akas_email_logs_pdo();
    if (!$pdo) {
      return;
    }

    if ($tableExists === null) {
      $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
      $tableExists = (bool)$stmt->fetchColumn();
    }
    if (!$tableExists) {
      return;
    }

    $sql = "INSERT INTO email_logs (
      appointment_id, clinic_id, user_id, doctor_id,
      event_type, recipient_type, recipient_name, recipient_email,
      subject, message, is_ok, error_message
    ) VALUES (
      :appointment_id, :clinic_id, :user_id, :doctor_id,
      :event_type, :recipient_type, :recipient_name, :recipient_email,
      :subject, :message, :is_ok, :error_message
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':appointment_id' => $row['appointment_id'] ?: null,
      ':clinic_id'      => $row['clinic_id'] ?: null,
      ':user_id'        => $row['user_id'] ?: null,
      ':doctor_id'      => $row['doctor_id'] ?: null,
      ':event_type'     => $row['event_type'] !== '' ? $row['event_type'] : 'general',
      ':recipient_type' => $row['recipient_type'] !== '' ? $row['recipient_type'] : 'system',
      ':recipient_name' => $row['recipient_name'] ?? '',
      ':recipient_email'=> $row['recipient_email'] ?? '',
      ':subject'        => $row['subject'] ?? '',
      ':message'        => $row['message'] ?? '',
      ':is_ok'          => !empty($row['is_ok']) ? 1 : 0,
      ':error_message'  => $row['error_message'] ?? null,
    ]);
  } catch (Throwable $e) {
    error_log('[email_logs] ' . $e->getMessage());
  }
}

function akas_email_logs_pdo(): ?PDO {
  static $pdo = false;
  if ($pdo instanceof PDO) {
    return $pdo;
  }
  if ($pdo === null) {
    return null;
  }
  try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Match the rest of the application timezone for CURRENT_TIMESTAMP columns
    $pdo->exec("SET time_zone = '+08:00'");

    return $pdo;
  } catch (Throwable $e) {
    error_log('[email_logs_pdo] ' . $e->getMessage());
    $pdo = null;
    return null;
  }
}