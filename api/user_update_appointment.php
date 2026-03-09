<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_logger.php';
require_once __DIR__ . '/../includes/sms_templates.php';

$baseUrl = '';
auth_require_role('user', $baseUrl);

$pdo = db();
date_default_timezone_set('Asia/Manila');

$aptId = (int)($_POST['appointment_id'] ?? 0);
$newDate = (string)($_POST['date'] ?? '');
$newTime = (string)($_POST['time'] ?? '');

if ($aptId <= 0 || $newDate === '' || $newTime === '') {
  http_response_code(400);
  exit('Missing fields.');
}

if (preg_match('/^\d{2}:\d{2}$/', $newTime)) $newTime .= ':00';

$userId = (int)auth_user_id();

try {
  $pdo->beginTransaction();

  // Load current appointment (must be owned by user and still PENDING)
  $chk = $pdo->prepare("
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
    WHERE a.APT_AppointmentID = :id
      AND a.APT_UserID = :uid
      AND a.APT_Status = 'PENDING'
    LIMIT 1
    FOR UPDATE
  ");
  $chk->execute([':id' => $aptId, ':uid' => $userId]);
  $row = $chk->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$row) {
    $pdo->rollBack();
    http_response_code(404);
    exit('Appointment not found or not reschedulable.');
  }

  $oldDate = (string)($row['APT_Date'] ?? '');
  $oldTime = (string)($row['APT_Time'] ?? '');
  // ✅ Idempotency: if user submits the same schedule again, treat as no-op (prevents spam double-click)
  if ($oldDate === $newDate && (string)$oldTime === (string)$newTime) {
    $pdo->commit();
    http_response_code(200);
    exit('No changes.');
  }


  // Update schedule
  $upd = $pdo->prepare("
    UPDATE appointments
    SET APT_Date = :d, APT_Time = :t
    WHERE APT_AppointmentID = :id
      AND APT_UserID = :uid
      AND APT_Status = 'PENDING'
    LIMIT 1
  ");
  try {
    $upd->execute([
      ':d' => $newDate,
      ':t' => $newTime,
      ':id' => $aptId,
      ':uid' => $userId,
    ]);
  } catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
      $pdo->rollBack();
      http_response_code(409);
      exit('Slot already taken.');
    }
    throw $e;
  }

  if ($upd->rowCount() < 1) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Update failed.');
  }

  $pdo->commit();

  // ✅ SMS: notify patient + doctor on RESCHEDULE (dedupe by identical message)
  try {
    $patientName = (string)($row['user_name'] ?? '');
    $patientPhone = (string)($row['user_phone'] ?? '');
    $doctorName  = (string)($row['doctor_name'] ?? '');
    $doctorPhone = (string)($row['doctor_phone'] ?? '');

    $newDateFmt = $newDate !== '' ? date('M d, Y', strtotime($newDate)) : '';
    $oldDateFmt = $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '';

    // patient
    if (trim($patientPhone) !== '') {
      $msgU = sms_template('reschedule_user', [
        'clinic_name'  => (string)($row['clinic_name'] ?? ''),
        'patient_name' => $patientName,
        'doctor_name'  => $doctorName,
        'date'         => $newDateFmt,
        'time'         => $newTime,
        'old_date'     => $oldDateFmt,
        'old_time'     => $oldTime,
      ]);

      $q = $pdo->prepare("SELECT 1 FROM sms_logs WHERE appointment_id = :id AND event_type = 'reschedule' AND recipient_type = 'user' AND message = :msg LIMIT 1");
      $q->execute([':id' => $aptId, ':msg' => $msgU]);
      if (!$q->fetchColumn()) {
        sms_send_and_log($pdo, [
          'appointment_id' => $aptId,
          'clinic_id'      => (int)($row['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($row['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($row['APT_DoctorID'] ?? 0),
          'event_type'     => 'reschedule',
        ], 'user', $patientPhone, $msgU);
      }
    }

    // doctor
    if (trim($doctorPhone) !== '') {
      $msgD = sms_template('reschedule_doctor', [
        'clinic_name'  => (string)($row['clinic_name'] ?? ''),
        'patient_name' => $patientName,
        'doctor_name'  => $doctorName,
        'date'         => $newDateFmt,
        'time'         => $newTime,
        'old_date'     => $oldDateFmt,
        'old_time'     => $oldTime,
      ]);

      $q = $pdo->prepare("SELECT 1 FROM sms_logs WHERE appointment_id = :id AND event_type = 'reschedule' AND recipient_type = 'doctor' AND message = :msg LIMIT 1");
      $q->execute([':id' => $aptId, ':msg' => $msgD]);
      if (!$q->fetchColumn()) {
        sms_send_and_log($pdo, [
          'appointment_id' => $aptId,
          'clinic_id'      => (int)($row['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($row['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($row['APT_DoctorID'] ?? 0),
          'event_type'     => 'reschedule',
        ], 'doctor', $doctorPhone, $msgD);
      }
    }
  } catch (Throwable $e) {
    // never block response if SMS fails
  }

  echo "OK";
} catch (Throwable $e) {
  try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
  http_response_code(500);
  echo "Server error";
}