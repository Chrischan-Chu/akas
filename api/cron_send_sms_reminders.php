<?php
declare(strict_types=1);

/**
 * Cron: Send 2-hour SMS reminders for upcoming APPROVED appointments.
 *
 * How to run (recommended):
 *   php -d detect_unicode=0 api/cron_send_sms_reminders.php
 *
 * If you must call via HTTP, set an environment variable CRON_TOKEN (or define it in config.*)
 * and call:
 *   /api/cron_send_sms_reminders.php?token=YOUR_TOKEN
 */

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms_logger.php';
require_once __DIR__ . '/../includes/sms_templates.php';

function out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

// --- Guard ---
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
  $expected = getenv('CRON_TOKEN');
  if (!is_string($expected) || $expected === '') {
    out(['ok' => false, 'message' => 'Forbidden (missing CRON_TOKEN).'], 403);
  }
  $given = (string)($_GET['token'] ?? '');
  if (!hash_equals($expected, $given)) {
    out(['ok' => false, 'message' => 'Forbidden (bad token).'], 403);
  }
}

if (!defined('IPROGSMS_API_TOKEN') || (string)IPROGSMS_API_TOKEN === '') {
  out(['ok' => false, 'message' => 'IPROGSMS_API_TOKEN not configured.'], 500);
}

$pdo = db();

// Look for appointments starting ~2 hours from now (window helps avoid missing due to cron timing)
$windowStart = (new DateTime('now'))->modify('+1 hour 55 minutes')->format('Y-m-d H:i:s');
$windowEnd   = (new DateTime('now'))->modify('+2 hour 05 minutes')->format('Y-m-d H:i:s');

$sql = "
  SELECT
    a.APT_AppointmentID,
    a.APT_UserID,
    a.APT_DoctorID,
    a.APT_ClinicID,
    a.APT_Date,
    a.APT_Time,
    a.APT_Status,
    u.name AS user_name,
    u.phone AS user_phone,
    d.name AS doctor_name,
    d.contact_number AS doctor_phone,
    c.clinic_name AS clinic_name
  FROM appointments a
  JOIN accounts u ON u.id = a.APT_UserID
  JOIN clinic_doctors d ON d.id = a.APT_DoctorID
  LEFT JOIN clinics c ON c.id = a.APT_ClinicID
  WHERE UPPER(a.APT_Status) = 'APPROVED'
    AND TIMESTAMP(a.APT_Date, a.APT_Time) >= :ws
    AND TIMESTAMP(a.APT_Date, a.APT_Time) <= :we
  ORDER BY a.APT_Date ASC, a.APT_Time ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':ws' => $windowStart, ':we' => $windowEnd]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sent = [
  'appointments' => 0,
  'user_sms' => 0,
  'doctor_sms' => 0,
  'skipped_already_sent' => 0,
  'errors' => 0,
];

foreach ($rows as $r) {
  $sent['appointments']++;

  $apptId = (int)($r['APT_AppointmentID'] ?? 0);
  if ($apptId <= 0) continue;

  $patientName = (string)($r['user_name'] ?? '');
  $doctorName  = (string)($r['doctor_name'] ?? '');

  $whenDate = !empty($r['APT_Date'])
    ? date('M d, Y', strtotime((string)$r['APT_Date']))
    : '';
  $whenTimeRaw = (string)($r['APT_Time'] ?? '');

  // If BOTH user+doctor reminders already exist, skip early.
  $chk = $pdo->prepare("SELECT recipient_type FROM sms_logs WHERE appointment_id = :id AND event_type = 'reminder_2h'");
  $chk->execute([':id' => $apptId]);
  $existing = $chk->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $alreadyUser = in_array('user', $existing, true);
  $alreadyDoc  = in_array('doctor', $existing, true);

  if ($alreadyUser && $alreadyDoc) {
    $sent['skipped_already_sent']++;
    continue;
  }

  // Send to user
  try {
    $userPhone = (string)($r['user_phone'] ?? '');
    if (!$alreadyUser && trim($userPhone) !== '') {
      $msg = sms_template('reminder_2h_user', [
        'clinic_name'  => (string)($r['clinic_name'] ?? ''),
        'patient_name' => $patientName,
        'doctor_name'  => $doctorName,
        'date'         => $whenDate,
        'time'         => $whenTimeRaw,
      ]);
      sms_send_and_log($pdo, [
        'appointment_id' => $apptId,
        'clinic_id'      => (int)($r['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($r['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($r['APT_DoctorID'] ?? 0),
        'event_type'     => 'reminder_2h',
      ], 'user', $userPhone, $msg);
      $sent['user_sms']++;
    }
  } catch (Throwable $e) {
    $sent['errors']++;
  }

  // Send to doctor
  try {
    $docPhone = (string)($r['doctor_phone'] ?? '');
    if (!$alreadyDoc && trim($docPhone) !== '') {
      $msg = sms_template('reminder_2h_doctor', [
        'clinic_name'  => (string)($r['clinic_name'] ?? ''),
        'patient_name' => $patientName,
        'doctor_name'  => $doctorName,
        'date'         => $whenDate,
        'time'         => $whenTimeRaw,
      ]);
      sms_send_and_log($pdo, [
        'appointment_id' => $apptId,
        'clinic_id'      => (int)($r['APT_ClinicID'] ?? 0),
        'user_id'        => (int)($r['APT_UserID'] ?? 0),
        'doctor_id'      => (int)($r['APT_DoctorID'] ?? 0),
        'event_type'     => 'reminder_2h',
      ], 'doctor', $docPhone, $msg);
      $sent['doctor_sms']++;
    }
  } catch (Throwable $e) {
    $sent['errors']++;
  }
}

out([
  'ok' => true,
  'window_start' => $windowStart,
  'window_end' => $windowEnd,
  'stats' => $sent,
]);
