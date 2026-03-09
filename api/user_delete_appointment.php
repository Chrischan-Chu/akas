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
  
    // ===============================
    // Cancel count + blacklist logic
    // ===============================
    $u = $pdo->prepare("
      SELECT cancel_count, is_blacklisted
      FROM accounts
      WHERE id = ?
      LIMIT 1
      FOR UPDATE
    ");
    $u->execute([$userId]);
    $userRow = $u->fetch(PDO::FETCH_ASSOC) ?: ['cancel_count' => 0, 'is_blacklisted' => 0];
    
    $cancelCount = (int)($userRow['cancel_count'] ?? 0);
    $isBlacklisted = (int)($userRow['is_blacklisted'] ?? 0);
    
    if ($isBlacklisted === 1) {
      $pdo->rollBack();
      http_response_code(403);
      echo json_encode(['error' => 'Your account is blacklisted from booking appointments.']);
      exit;
    }
    
    $newCancelCount = $cancelCount + 1;
    
    $updUser = $pdo->prepare("
      UPDATE accounts
      SET cancel_count = ?,
          is_blacklisted = CASE WHEN ? >= 4 THEN 1 ELSE is_blacklisted END,
          blacklisted_at = CASE WHEN ? >= 4 THEN NOW() ELSE blacklisted_at END,
          blacklist_reason = CASE
            WHEN ? >= 4 THEN 'Exceeded cancellation limit'
            ELSE blacklist_reason
          END
      WHERE id = ?
      LIMIT 1
    ");
    $updUser->execute([$newCancelCount, $newCancelCount, $newCancelCount, $newCancelCount, $userId]);


  $stmt = $pdo->prepare('UPDATE appointments
    SET APT_Status = "CANCELLED"
    WHERE APT_AppointmentID = ?
      AND APT_UserID = ?
      AND APT_Status IN ("PENDING","APPROVED")
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
    // 🔔 REALTIME: notify admin dashboard calendar
    try {
      require_once __DIR__ . '/../includes/realtime_ably.php';
      if (function_exists('publish_slots_updated')) {
        $clinicId = (int)($details['APT_ClinicID'] ?? 0);
        $date = (string)($details['APT_Date'] ?? '');
        if ($clinicId > 0 && $date !== '') {
          publish_slots_updated($clinicId, $date);
        }
      }
    } catch (Throwable $rt) {
      // ignore
    }
// ==========================================
// 🔔 REAL-TIME (USER CANCEL -> ADMIN DASHBOARD)
// publish "slots-updated" so the existing admin listener reacts
// ==========================================
    try {
      $clinicId = (int)($details['APT_ClinicID'] ?? 0);
      $date     = (string)($details['APT_Date'] ?? '');
    
      if ($clinicId > 0 && $date !== '') {
        $ablyKey = getenv('ABLY_API_KEY') ?: 'KtC7rw.-df6sw:fEo5tVYYnOpj_RrNA450TNLUaST_v6qYWplSC79SdmU';
    
        $url = 'https://rest.ably.io/channels/clinic-' . $clinicId . '/messages';
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, $ablyKey);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
          'name' => 'slots-updated',
          'data' => [
            'action' => 'user_cancel',
            'appointment_id' => (int)$appointmentId,
            'date' => $date
          ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_exec($ch);
        curl_close($ch);
      }
    } catch (Throwable $e) {
      // ignore
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
        'clinic_name'  => (string)($details['clinic_name'] ?? ''),
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
        'clinic_name'  => (string)($details['clinic_name'] ?? ''),
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

//NEW
echo json_encode([
  'ok' => true,
  'cancel_count' => $newCancelCount,
  'warning' => $newCancelCount === 3
      ? 'Warning: You have reached 3 cancellations. Cancelling one more appointment will result in your account being blacklisted from booking.'
      : null,
  'blacklisted' => $newCancelCount >= 4
]);
exit;
