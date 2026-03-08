<?php
declare(strict_types=1);

/**
 * Cron: Auto-mark past APPROVED appointments as DONE, and send post-appointment SMS
 * to BOTH patient and doctor (only once).
 *
 * Recommended run:
 *   php api/cron_auto_mark_done.php
 *
 * Optional HTTP run (secure it with CRON_TOKEN env var):
 *   /api/cron_auto_mark_done.php?token=YOUR_TOKEN
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

// Fetch a batch of past APPROVED appointments.
// (If you have a duration/end-time field later, switch to using that.)
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
    AND TIMESTAMP(a.APT_Date, a.APT_Time) < NOW()
  ORDER BY a.APT_Date ASC, a.APT_Time ASC
  LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stats = [
  'candidates' => count($rows),
  'marked_done' => 0,
  'user_sms' => 0,
  'doctor_sms' => 0,
  'skipped_already_sent' => 0,
  'errors' => 0,
];

foreach ($rows as $r) {
  $apptId = (int)($r['APT_AppointmentID'] ?? 0);
  if ($apptId <= 0) continue;

  // Re-check + mark done atomically-ish
  try {
    $pdo->beginTransaction();
    $u = $pdo->prepare("UPDATE appointments
                         SET APT_Status='DONE'
                         WHERE APT_AppointmentID = :id
                           AND UPPER(APT_Status) = 'APPROVED'
                           AND TIMESTAMP(APT_Date, APT_Time) < NOW()");
    $u->execute([':id' => $apptId]);
    $updated = $u->rowCount();
    $pdo->commit();
  } catch (Throwable $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    $stats['errors']++;
    continue;
  }

  if ($updated <= 0) {
    // Another process/admin already updated it.
    continue;
  }

  $stats['marked_done']++;

  $patientName = (string)($r['user_name'] ?? '');
  $doctorName  = (string)($r['doctor_name'] ?? '');
  $whenDate = !empty($r['APT_Date']) ? date('M d, Y', strtotime((string)$r['APT_Date'])) : '';
  $whenTimeRaw = (string)($r['APT_Time'] ?? '');

  // If BOTH user+doctor done notifications already exist, skip.
  // (Normally they won't, since we just marked it done, but this protects against retries.)
  try {
    $chk = $pdo->prepare("SELECT recipient_type FROM sms_logs WHERE appointment_id = :id AND event_type = 'done'");
    $chk->execute([':id' => $apptId]);
    $existing = $chk->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $alreadyUser = in_array('user', $existing, true);
    $alreadyDoc  = in_array('doctor', $existing, true);
    if ($alreadyUser && $alreadyDoc) {
      $stats['skipped_already_sent']++;
      continue;
    }
  } catch (Throwable $e) {
    // If log lookup fails, still attempt sending (logger will best-effort)
    $alreadyUser = false;
    $alreadyDoc = false;
  }

  // Send to user
  try {
    $userPhone = (string)($r['user_phone'] ?? '');
    if (!$alreadyUser && trim($userPhone) !== '') {
      $msg = sms_template('done_user', [
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
        'event_type'     => 'done',
      ], 'user', $userPhone, $msg);
      $stats['user_sms']++;
    }
  } catch (Throwable $e) {
    $stats['errors']++;
  }

  // Send to doctor
  try {
    $docPhone = (string)($r['doctor_phone'] ?? '');
    if (!$alreadyDoc && trim($docPhone) !== '') {
      $msg = sms_template('done_doctor', [
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
        'event_type'     => 'done',
      ], 'doctor', $docPhone, $msg);
      $stats['doctor_sms']++;
    }
  } catch (Throwable $e) {
    $stats['errors']++;
  }
}

out([
  'ok' => true,
  'now' => (new DateTime('now'))->format('Y-m-d H:i:s'),
  'stats' => $stats,
]);
