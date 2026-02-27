<?php
declare(strict_types=1);

require_once __DIR__ . '/sms_iprog.php';

/**
 * Send an SMS and log the result into `sms_logs`.
 *
 * @param PDO   $pdo
 * @param array $meta keys: appointment_id, clinic_id, user_id, doctor_id, event_type
 * @param string $recipientType 'user' | 'doctor'
 * @param string $phone
 * @param string $message
 * @return array result from iprog_send_sms + ['logged' => bool]
 */
function sms_send_and_log(PDO $pdo, array $meta, string $recipientType, string $phone, string $message): array
{
  $result = iprog_send_sms($phone, $message);

  // Try to log, but never block the main flow
  try {
    $stmt = $pdo->prepare("
      INSERT INTO sms_logs
        (appointment_id, clinic_id, user_id, doctor_id, event_type, recipient_type, phone_number, message, is_ok, http_status, raw_response)
      VALUES
        (:appointment_id, :clinic_id, :user_id, :doctor_id, :event_type, :recipient_type, :phone_number, :message, :is_ok, :http_status, :raw_response)
    ");

    $raw = $result['response'] ?? null;
    $rawStr = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);

    $stmt->execute([
      ':appointment_id' => isset($meta['appointment_id']) ? (int)$meta['appointment_id'] : null,
      ':clinic_id'      => isset($meta['clinic_id']) ? (int)$meta['clinic_id'] : null,
      ':user_id'        => isset($meta['user_id']) ? (int)$meta['user_id'] : null,
      ':doctor_id'      => isset($meta['doctor_id']) ? (int)$meta['doctor_id'] : null,
      ':event_type'     => (string)($meta['event_type'] ?? ''),
      ':recipient_type' => $recipientType,
      ':phone_number'   => sms_normalize_phone($phone),
      ':message'        => $message,
      ':is_ok'          => !empty($result['ok']) ? 1 : 0,
      ':http_status'    => isset($result['status']) ? (int)$result['status'] : 0,
      ':raw_response'   => $rawStr,
    ]);

    $result['logged'] = true;
  } catch (Throwable $e) {
    $result['logged'] = false;
  }

  return $result;
}
