<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms_logger.php';
require_once __DIR__ . '/../includes/sms_templates.php';
require_once __DIR__ . '/../includes/appointment_mailer.php';

header('Content-Type: application/json; charset=UTF-8');

$baseUrl = '';
auth_require_role('user', $baseUrl);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

$appointmentId = (int)($body['appointment_id'] ?? 0);
$decision      = strtolower(trim((string)($body['decision'] ?? '')));
$userId        = (int)auth_user_id();

if ($appointmentId <= 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Invalid appointment ID']);
  exit;
}

if (!in_array($decision, ['accept', 'decline'], true)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Invalid decision']);
  exit;
}

try {
  $pdo = db();
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    SELECT
      APT_AppointmentID,
      APT_UserID,
      APT_ClinicID,
      APT_DoctorID,
      APT_Status,
      APT_Date,
      APT_Time,
      APT_OldDate,
      APT_OldTime
    FROM appointments
    WHERE APT_AppointmentID = ?
      AND APT_UserID = ?
      AND APT_Status = 'RESCHEDULE_PENDING'
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$appointmentId, $userId]);
  $appt = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$appt) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode([
      'ok' => false,
      'error' => 'Pending reschedule request not found'
    ]);
    exit;
  }

  $oldDate = (string)($appt['APT_OldDate'] ?? '');
  $oldTime = (string)($appt['APT_OldTime'] ?? '');

  if ($decision === 'accept') {
    $update = $pdo->prepare("
      UPDATE appointments
      SET APT_Status = 'APPROVED',
          APT_RescheduleRespondedAt = NOW(),
          APT_OldDate = NULL,
          APT_OldTime = NULL
      WHERE APT_AppointmentID = ?
        AND APT_UserID = ?
        AND APT_Status = 'RESCHEDULE_PENDING'
      LIMIT 1
    ");
    $update->execute([$appointmentId, $userId]);

    if ($update->rowCount() !== 1) {
      $pdo->rollBack();
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'Failed to accept reschedule']);
      exit;
    }

    $pdo->commit();

    $detailStmt = $pdo->prepare("
      SELECT
        a.APT_AppointmentID,
        a.APT_UserID,
        a.APT_ClinicID,
        a.APT_DoctorID,
        a.APT_Date,
        a.APT_Time,
        u.name AS user_name,
        u.phone AS user_phone,
        u.email AS user_email,
        c.clinic_name,
        d.name AS doctor_name,
        d.contact_number AS doctor_phone,
        d.email AS doctor_email
      FROM appointments a
      LEFT JOIN accounts u ON u.id = a.APT_UserID
      LEFT JOIN clinics c ON c.id = a.APT_ClinicID
      LEFT JOIN clinic_doctors d ON d.id = a.APT_DoctorID
      WHERE a.APT_AppointmentID = ?
        AND a.APT_UserID = ?
      LIMIT 1
    ");
    $detailStmt->execute([$appointmentId, $userId]);
    $details = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($details) {
      try {
        $dateFmt = !empty($details['APT_Date']) ? date('M d, Y', strtotime((string)$details['APT_Date'])) : '';
        $patientPhone = trim((string)($details['user_phone'] ?? ''));
        $doctorPhone = trim((string)($details['doctor_phone'] ?? ''));

        if ($patientPhone !== '') {
          $msgU = sms_template('reschedule_accepted_user', [
            'clinic_name'  => (string)($details['clinic_name'] ?? ''),
            'patient_name' => (string)($details['user_name'] ?? ''),
            'doctor_name'  => (string)($details['doctor_name'] ?? ''),
            'date'         => $dateFmt,
            'time'         => (string)($details['APT_Time'] ?? ''),
            'old_date'     => $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '',
            'old_time'     => $oldTime,
          ]);

          sms_send_and_log($pdo, [
            'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
            'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
            'user_id'        => (int)($details['APT_UserID'] ?? 0),
            'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
            'event_type'     => 'reschedule_accept',
          ], 'user', $patientPhone, $msgU);
        }

        if ($doctorPhone !== '') {
          $msgD = sms_template('reschedule_accepted_doctor', [
            'clinic_name'  => (string)($details['clinic_name'] ?? ''),
            'patient_name' => (string)($details['user_name'] ?? ''),
            'doctor_name'  => (string)($details['doctor_name'] ?? ''),
            'date'         => $dateFmt,
            'time'         => (string)($details['APT_Time'] ?? ''),
            'old_date'     => $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '',
            'old_time'     => $oldTime,
          ]);

          sms_send_and_log($pdo, [
            'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
            'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
            'user_id'        => (int)($details['APT_UserID'] ?? 0),
            'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
            'event_type'     => 'reschedule_accept',
          ], 'doctor', $doctorPhone, $msgD);
        }
      } catch (Throwable $smsErr) {
        // never block response if SMS fails
      }

      try {
        akas_send_reschedule_accepted_emails($details, $oldDate, $oldTime);
      } catch (Throwable $mailErr) {
        // never block response if email fails
      }
    }

    echo json_encode([
      'ok' => true,
      'message' => 'Rescheduled appointment accepted',
      'appointment_id' => $appointmentId,
      'status' => 'APPROVED'
    ]);
    exit;
  }

  // decline = cancel appointment
  $update = $pdo->prepare("
    UPDATE appointments
    SET APT_Status = 'CANCELLED',
        APT_RescheduleRespondedAt = NOW()
    WHERE APT_AppointmentID = ?
      AND APT_UserID = ?
      AND APT_Status = 'RESCHEDULE_PENDING'
    LIMIT 1
  ");
  $update->execute([$appointmentId, $userId]);

  if ($update->rowCount() !== 1) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Failed to decline reschedule']);
    exit;
  }

  $pdo->commit();

  $detailStmt = $pdo->prepare("
    SELECT
      a.APT_AppointmentID,
      a.APT_UserID,
      a.APT_ClinicID,
      a.APT_DoctorID,
      a.APT_Date,
      a.APT_Time,
      u.name AS user_name,
      u.phone AS user_phone,
      u.email AS user_email,
      c.clinic_name,
      d.name AS doctor_name,
      d.contact_number AS doctor_phone,
      d.email AS doctor_email
    FROM appointments a
    LEFT JOIN accounts u ON u.id = a.APT_UserID
    LEFT JOIN clinics c ON c.id = a.APT_ClinicID
    LEFT JOIN clinic_doctors d ON d.id = a.APT_DoctorID
    WHERE a.APT_AppointmentID = ?
      AND a.APT_UserID = ?
    LIMIT 1
  ");
  $detailStmt->execute([$appointmentId, $userId]);
  $details = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: [];

  if ($details) {
    try {
      $dateFmt = !empty($details['APT_Date']) ? date('M d, Y', strtotime((string)$details['APT_Date'])) : '';
      $patientPhone = trim((string)($details['user_phone'] ?? ''));
      $doctorPhone = trim((string)($details['doctor_phone'] ?? ''));

      if ($patientPhone !== '') {
        $msgU = sms_template('reschedule_declined_user', [
          'clinic_name'  => (string)($details['clinic_name'] ?? ''),
          'patient_name' => (string)($details['user_name'] ?? ''),
          'doctor_name'  => (string)($details['doctor_name'] ?? ''),
          'date'         => $dateFmt,
          'time'         => (string)($details['APT_Time'] ?? ''),
          'old_date'     => $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '',
          'old_time'     => $oldTime,
        ]);

        sms_send_and_log($pdo, [
          'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
          'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($details['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
          'event_type'     => 'reschedule_decline',
        ], 'user', $patientPhone, $msgU);
      }

      if ($doctorPhone !== '') {
        $msgD = sms_template('reschedule_declined_doctor', [
          'clinic_name'  => (string)($details['clinic_name'] ?? ''),
          'patient_name' => (string)($details['user_name'] ?? ''),
          'doctor_name'  => (string)($details['doctor_name'] ?? ''),
          'date'         => $dateFmt,
          'time'         => (string)($details['APT_Time'] ?? ''),
          'old_date'     => $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '',
          'old_time'     => $oldTime,
        ]);

        sms_send_and_log($pdo, [
          'appointment_id' => (int)($details['APT_AppointmentID'] ?? 0),
          'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
          'user_id'        => (int)($details['APT_UserID'] ?? 0),
          'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
          'event_type'     => 'reschedule_decline',
        ], 'doctor', $doctorPhone, $msgD);
      }
    } catch (Throwable $smsErr) {
      // never block response if SMS fails
    }

    try {
      akas_send_reschedule_declined_emails($details, $oldDate, $oldTime);
    } catch (Throwable $mailErr) {
      // never block response if email fails
    }
  }

  echo json_encode([
    'ok' => true,
    'message' => 'Rescheduled appointment declined',
    'appointment_id' => $appointmentId,
    'status' => 'CANCELLED'
  ]);
  exit;
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Server error',
    'details' => $e->getMessage()
  ]);
  exit;
}