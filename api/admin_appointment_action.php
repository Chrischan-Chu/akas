<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}

if (auth_role() !== 'clinic_admin') {
  json_out(['ok' => false, 'message' => 'Forbidden'], 403);
}

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  json_out(['ok' => false, 'message' => 'Clinic not linked.'], 400);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

$apptId = (int)($payload['appointment_id'] ?? 0);
$action = strtoupper(trim((string)($payload['action'] ?? '')));

if ($apptId <= 0) json_out(['ok' => false, 'message' => 'Invalid appointment_id'], 422);
if (!in_array($action, ['CANCELLED','DONE'], true)) {
  json_out(['ok' => false, 'message' => 'Invalid action'], 422);
}

$details = null;
try {
  $pdo->beginTransaction();

  $chk = $pdo->prepare("
    SELECT
      a.APT_AppointmentID,
      a.APT_UserID,
      a.APT_DoctorID,
      a.APT_ClinicID,
      a.APT_Status,
      a.APT_Date,
      a.APT_Time,
      u.name AS user_name,
      u.phone AS user_phone,
      d.name AS doctor_name,
      d.contact_number AS doctor_phone
    FROM appointments a
    JOIN accounts u ON u.id = a.APT_UserID
    JOIN clinic_doctors d ON d.id = a.APT_DoctorID
    WHERE a.APT_AppointmentID = :id AND a.APT_ClinicID = :cid
    LIMIT 1
    FOR UPDATE
  ");
  $chk->execute([':id' => $apptId, ':cid' => $clinicId]);
  $details = $chk->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$details) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Appointment not found'], 404);
  }

  $cur = strtoupper((string)($details['APT_Status'] ?? ''));
  if ($cur === 'DONE' || $cur === 'CANCELLED') {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'This appointment is already closed.'], 409);
  }

  $upd = $pdo->prepare("
    UPDATE appointments
    SET APT_Status = :st
    WHERE APT_AppointmentID = :id AND APT_ClinicID = :cid
    LIMIT 1
  ");
  $upd->execute([':st' => $action, ':id' => $apptId, ':cid' => $clinicId]);

  if ($upd->rowCount() < 1) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Update failed'], 500);
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['ok' => false, 'message' => 'Server error'], 500);
}

if ($action === 'CANCELLED') {
  try {
    require_once __DIR__ . '/../includes/sms_logger.php';
    require_once __DIR__ . '/../includes/sms_templates.php';

    if (defined('IPROGSMS_API_TOKEN') && (string)IPROGSMS_API_TOKEN !== '' && is_array($details)) {

      $patientName = (string)($details['user_name'] ?? '');
      $doctorName  = (string)($details['doctor_name'] ?? '');

      $whenDate = !empty($details['APT_Date'])
        ? date('M d, Y', strtotime((string)$details['APT_Date']))
        : '';

      $whenTimeRaw = (string)($details['APT_Time'] ?? '');

      $userPhone = (string)($details['user_phone'] ?? '');
      if (trim($userPhone) !== '') {
        $userMsg = sms_template('cancel_by_admin_user', [
          'patient_name' => $patientName,
          'doctor_name'  => $doctorName,
          'date'         => $whenDate,
          'time'         => $whenTimeRaw,
        ]);
        sms_send_and_log($pdo, [
          'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
          'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($details['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
          'event_type'     => 'cancel_by_admin',
        ], 'user', $userPhone, $userMsg);
      }

      $docPhone = (string)($details['doctor_phone'] ?? '');
      if (trim($docPhone) !== '') {
        $docMsg = sms_template('cancel_by_admin_doctor', [
          'patient_name' => $patientName,
          'doctor_name'  => $doctorName,
          'date'         => $whenDate,
          'time'         => $whenTimeRaw,
        ]);
        sms_send_and_log($pdo, [
          'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
          'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($details['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
          'event_type'     => 'cancel_by_admin',
        ], 'doctor', $docPhone, $docMsg);
      }
    }
  } catch (Throwable $smsErr) {
    // ignore SMS errors
  }
}


// --- REAL-TIME TRIGGER ---
$date = (string)($row['APT_Date'] ?? '');
if ($date !== '') {
  try {
    require_once __DIR__ . '/../includes/realtime_ably.php';
    if (function_exists('publish_slots_updated')) {
      publish_slots_updated($clinicId, $date); 
    }
  } catch (Throwable $rt) {
    // silently ignore realtime errors so it doesn't break the response
  }
}
// -------------------------

json_out(['ok' => true, 'status' => $action]);