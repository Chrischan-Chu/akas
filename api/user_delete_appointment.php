<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/sms_templates.php';
if (!auth_is_logged_in() || auth_role() !== 'user') {
  http_response_code(401);
  echo json_encode(['error' => 'Login required']);
  exit;
}

$pdo = db();
$userId = (int)auth_user_id();

// Accept JSON body or form POST
$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$appointmentId = (int)($body['appointment_id'] ?? ($_POST['appointment_id'] ?? 0));

if ($appointmentId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid appointment id']);
  exit;
}

// Fetch details first for SMS (and to validate ownership/status)
$details = null;
try {
  $pdo->beginTransaction();

  $q = $pdo->prepare('
    SELECT
      a.APT_AppointmentID,
      a.APT_UserID,
      a.APT_DoctorID,
      a.APT_ClinicID,
      a.APT_Date,
      a.APT_Time,
      u.name AS user_name,
      u.phone AS user_phone,
      c.clinic_name,
      d.name AS doctor_name,
      d.contact_number AS doctor_phone
    FROM appointments a
    JOIN accounts u ON u.id = a.APT_UserID
    JOIN clinics c ON c.id = a.APT_ClinicID
    JOIN clinic_doctors d ON d.id = a.APT_DoctorID
    WHERE a.APT_AppointmentID = ?
      AND a.APT_UserID = ?
      AND a.APT_Status IN ("pending","approved")
    LIMIT 1
    FOR UPDATE
  ');
  $q->execute([$appointmentId, $userId]);
  $details = $q->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$details) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => 'Cannot cancel this appointment']);
    exit;
  }


  $stmt = $pdo->prepare('UPDATE appointments
    SET APT_Status = "cancelled"
    WHERE APT_AppointmentID = ?
      AND APT_UserID = ?
      AND APT_Status IN ("pending","approved")
    LIMIT 1');
  $stmt->execute([$appointmentId, $userId]);

  if ($stmt->rowCount() < 1) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => 'Cannot cancel this appointment']);
    exit;
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
  exit;
}

// ✅ SMS notification (non-blocking)
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

    // Notify user
    $userPhone = (string)($details['user_phone'] ?? '');
    if (trim($userPhone) !== '') {
      $userMsg = sms_template('cancel_by_user_user', [
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
        'event_type'     => 'cancel_by_user',
      ], 'user', $userPhone, $userMsg);
    }

    // Notify doctor
    $docPhone = (string)($details['doctor_phone'] ?? '');
    if (trim($docPhone) !== '') {
      $docMsg = sms_template('cancel_by_user_doctor', [
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
        'event_type'     => 'cancel_by_user',
      ], 'doctor', $docPhone, $docMsg);
    }
  }
} catch (Throwable $smsErr) {
  // ignore SMS errors
}

echo json_encode(['ok' => true]);
exit;
