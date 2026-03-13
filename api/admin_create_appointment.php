<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_logger.php';
require_once __DIR__ . '/../includes/sms_templates.php';
require_once __DIR__ . '/../includes/appointment_mailer.php';

if (!auth_is_logged_in() || auth_role() !== 'clinic_admin') {
  http_response_code(401);
  echo json_encode(['error' => 'Clinic admin login required']);
  exit;
}

$pdo = db();
date_default_timezone_set('Asia/Manila');

$clinicId = (int)auth_clinic_id();
if ($clinicId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid clinic']);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$reason = trim((string)($body['reason'] ?? ''));
if (!is_array($body)) $body = $_POST;

$action = strtoupper(trim((string)($body['action'] ?? 'CREATE')));
if ($action === 'RESCHEDULE' && $reason === '') {
  http_response_code(422);
  echo json_encode(['error' => 'Reschedule reason is required.']);
  exit;
}
$appointmentId = (int)($body['appointment_id'] ?? 0);

if (!in_array($action, ['CREATE', 'RESCHEDULE'], true)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid action']);
  exit;
}
if ($action === 'RESCHEDULE' && $appointmentId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing appointment_id']);
  exit;
}

$patientEmail = trim((string)($body['patient_email'] ?? ''));
$doctorId     = (int)($body['doctor_id'] ?? 0);
$date         = trim((string)($body['date'] ?? ''));
$time         = trim((string)($body['time'] ?? ''));
$notes        = trim((string)($body['notes'] ?? ''));

$oldDate = '';
$oldTime = '';

if ($patientEmail === '' || $doctorId <= 0 || $date === '' || $time === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields']);
  exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid date']);
  exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $time) && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid time']);
  exit;
}
if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ':00';

// Patient must exist (user account)
$st = $pdo->prepare('SELECT id FROM accounts WHERE email = ? AND role = "user" LIMIT 1');
$st->execute([$patientEmail]);
$userId = (int)(($st->fetch(PDO::FETCH_ASSOC) ?: [])['id'] ?? 0);
if ($userId <= 0) {
  http_response_code(404);
  echo json_encode(['error' => 'Patient email not found (must be an existing user)']);
  exit;
}

// Doctor must belong to this clinic and be approved
$st = $pdo->prepare('SELECT id FROM clinic_doctors WHERE id = ? AND clinic_id = ? AND approval_status = "APPROVED" LIMIT 1');
$st->execute([$doctorId, $clinicId]);
if (!$st->fetch(PDO::FETCH_ASSOC)) {
  http_response_code(404);
  echo json_encode(['error' => 'Doctor not found or not approved']);
  exit;
}

try {
  $pdo->beginTransaction();

  if ($action === 'RESCHEDULE') {
    $chk = $pdo->prepare("
      SELECT APT_AppointmentID, APT_Status, APT_Date, APT_Time, APT_DoctorID, APT_ClinicID
      FROM appointments
      WHERE APT_AppointmentID = ?
        AND APT_ClinicID = ?
        AND APT_UserID = ?
      LIMIT 1
      FOR UPDATE
    ");
    $chk->execute([$appointmentId, $clinicId, $userId]);
    $row = $chk->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) {
      $oldDate = (string)($row['APT_Date'] ?? '');
      $oldTime = (string)($row['APT_Time'] ?? '');
    }

    if (!$row) {
      $pdo->rollBack();
      http_response_code(404);
      echo json_encode(['error' => 'Appointment not found']);
      exit;
    }

    $cur = strtoupper((string)($row['APT_Status'] ?? ''));
    if ($cur === 'DONE' || $cur === 'CANCELLED') {
      $pdo->rollBack();
      http_response_code(409);
      echo json_encode(['error' => 'This appointment is already closed.']);
      exit;
    }
  }

  if ($action === 'RESCHEDULE') {
    $curDoctor = (int)($row['APT_DoctorID'] ?? 0);
    $curClinic = (int)($row['APT_ClinicID'] ?? 0);
    $curDate   = (string)($row['APT_Date'] ?? '');
    $curTime   = (string)($row['APT_Time'] ?? '');
    if ($curClinic === $clinicId && $curDoctor === $doctorId && $curDate === $date && $curTime === $time) {
      $pdo->commit();
      echo json_encode(['ok' => true, 'message' => 'No changes.']);
      exit;
    }
  }

  $slotSql = "
    SELECT APT_AppointmentID
    FROM appointments
    WHERE APT_ClinicID = ?
      AND APT_DoctorID = ?
      AND APT_Date = ?
      AND APT_Time = ?
      AND APT_Status IN ('PENDING','APPROVED')
  ";
  $params = [$clinicId, $doctorId, $date, $time];

  if ($action === 'RESCHEDULE') {
    $slotSql .= " AND APT_AppointmentID <> ? ";
    $params[] = $appointmentId;
  }

  $slotSql .= " LIMIT 1 FOR UPDATE ";

  $slot = $pdo->prepare($slotSql);
  $slot->execute($params);
  if ($slot->fetch(PDO::FETCH_ASSOC)) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => 'Slot already taken']);
    exit;
  }

  $finalNotes = trim('ADMIN: ' . ($notes !== '' ? $notes : 'Follow-up / reschedule'));

  if ($action === 'RESCHEDULE') {
    $upd = $pdo->prepare("
      UPDATE appointments
      SET APT_ClinicID = ?,
          APT_DoctorID = ?,
          APT_Date     = ?,
          APT_Time     = ?,
          APT_Status   = 'RESCHEDULE_PENDING',
          APT_OldDate  = ?,
          APT_OldTime  = ?,
          APT_RescheduleReason = ?,
          APT_RescheduledBy = 'admin',
          APT_Notes    = ?
      WHERE APT_AppointmentID = ?
        AND APT_UserID = ?
        AND APT_ClinicID = ?
      LIMIT 1
    ");
    try {
      $upd->execute([
        $clinicId,
        $doctorId,
        $date,
        $time,
        $oldDate,
        $oldTime,
        $reason,
        $finalNotes,
        $appointmentId,
        $userId,
        $clinicId
      ]);
    } catch (PDOException $e) {
      if ((string)$e->getCode() === '23000') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Slot already taken']);
        exit;
      }
      throw $e;
    }

    if ($upd->rowCount() < 1) {
      $pdo->rollBack();
      http_response_code(500);
      echo json_encode(['error' => 'Server error']);
      exit;
    }

    $apptId = $appointmentId;
    $mode = 'rescheduled';
  } else {
    $today = (new DateTime('now'))->format('Y-m-d');

    $stmt = $pdo->prepare("
      SELECT APT_AppointmentID
      FROM appointments
      WHERE APT_UserID = ?
        AND APT_ClinicID = ?
        AND APT_Status IN ('PENDING','APPROVED')
        AND APT_Date >= ?
      LIMIT 1
      FOR UPDATE
    ");

    $stmt->execute([$userId, $clinicId, $today]);

    if ($stmt->fetch()) {
      $pdo->rollBack();
      json_out([
        'error' => 'You already have an active appointment in this clinic. Please cancel it first before booking another here.'
      ], 409);
    }

    $ins = $pdo->prepare("
      INSERT INTO appointments
        (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
      VALUES
        (?, ?, ?, ?, ?, 'APPROVED', ?, NOW())
    ");
    try {
      $ins->execute([$userId, $doctorId, $clinicId, $date, $time, $finalNotes]);
    } catch (PDOException $e) {
      if ((string)$e->getCode() === '23000') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Slot already taken']);
        exit;
      }
      throw $e;
    }

    $apptId = (int)$pdo->lastInsertId();
    $mode = 'created';
  }

  $pdo->commit();

  // Load full details once after commit for notifications
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
      AND a.APT_ClinicID = ?
      AND a.APT_UserID = ?
    LIMIT 1
  ");
  $detailStmt->execute([$apptId, $clinicId, $userId]);
  $details = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: [];

  // SMS
  try {
    if ($details) {
      $patientName = (string)($details['user_name'] ?? '');
      $patientPhone = trim((string)($details['user_phone'] ?? ''));
      $doctorName = (string)($details['doctor_name'] ?? '');
      $doctorPhone = trim((string)($details['doctor_phone'] ?? ''));
      $clinicName = (string)($details['clinic_name'] ?? '');

      $newDateFmt = $date !== '' ? date('M d, Y', strtotime($date)) : '';
      $oldDateFmt = $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '';

      if ($action === 'RESCHEDULE') {
        // Pending reschedule request = USER ONLY
        if ($patientPhone !== '') {
          $msgU = sms_template('reschedule_request_user', [
            'clinic_name'  => $clinicName,
            'patient_name' => $patientName,
            'doctor_name'  => $doctorName,
            'date'         => $newDateFmt,
            'time'         => $time,
            'old_date'     => $oldDateFmt,
            'old_time'     => $oldTime,
            'reason'       => $reason,
          ]);

          $q = $pdo->prepare("
            SELECT 1
            FROM sms_logs
            WHERE appointment_id = :id
              AND event_type = 'reschedule_request'
              AND recipient_type = 'user'
              AND message = :msg
            LIMIT 1
          ");
          $q->execute([':id' => $apptId, ':msg' => $msgU]);

          if (!$q->fetchColumn()) {
            sms_send_and_log($pdo, [
              'appointment_id' => $apptId,
              'clinic_id'      => $clinicId,
              'user_id'        => $userId,
              'doctor_id'      => $doctorId,
              'event_type'     => 'reschedule_request',
            ], 'user', $patientPhone, $msgU);
          }
        }
      } else {
        // Booking = USER + DOCTOR
        if ($patientPhone !== '') {
          $msgU = sms_template('booking_user', [
            'clinic_name'  => $clinicName,
            'patient_name' => $patientName,
            'doctor_name'  => $doctorName,
            'date'         => $newDateFmt,
            'time'         => $time,
          ]);

          $q = $pdo->prepare("
            SELECT 1
            FROM sms_logs
            WHERE appointment_id = :id
              AND event_type = 'booking'
              AND recipient_type = 'user'
              AND message = :msg
            LIMIT 1
          ");
          $q->execute([':id' => $apptId, ':msg' => $msgU]);

          if (!$q->fetchColumn()) {
            sms_send_and_log($pdo, [
              'appointment_id' => $apptId,
              'clinic_id'      => $clinicId,
              'user_id'        => $userId,
              'doctor_id'      => $doctorId,
              'event_type'     => 'booking',
            ], 'user', $patientPhone, $msgU);
          }
        }

        if ($doctorPhone !== '') {
          $msgD = sms_template('booking_doctor', [
            'clinic_name'  => $clinicName,
            'patient_name' => $patientName,
            'doctor_name'  => $doctorName,
            'date'         => $newDateFmt,
            'time'         => $time,
          ]);

          $q = $pdo->prepare("
            SELECT 1
            FROM sms_logs
            WHERE appointment_id = :id
              AND event_type = 'booking'
              AND recipient_type = 'doctor'
              AND message = :msg
            LIMIT 1
          ");
          $q->execute([':id' => $apptId, ':msg' => $msgD]);

          if (!$q->fetchColumn()) {
            sms_send_and_log($pdo, [
              'appointment_id' => $apptId,
              'clinic_id'      => $clinicId,
              'user_id'        => $userId,
              'doctor_id'      => $doctorId,
              'event_type'     => 'booking',
            ], 'doctor', $doctorPhone, $msgD);
          }
        }
      }
    }
  } catch (Throwable $e) {
    // Never block response if SMS fails
  }

  // Email
  try {
    if ($details) {
      if ($action === 'RESCHEDULE') {
        akas_send_reschedule_request_emails($details, $oldDate, $oldTime, $reason);
      } else {
        akas_send_booking_emails($details);
      }
    }
  } catch (Throwable $mailErr) {
    // Never block response if email fails
  }

  // realtime: user badge refresh
  try {
    $patientId = $userId;
    $ablyKey = getenv('ABLY_API_KEY') ?: 'YOUR_FALLBACK_KEY';

    $ch = curl_init('https://rest.ably.io/channels/user-' . $patientId . '/messages');
    curl_setopt($ch, CURLOPT_USERPWD, $ablyKey);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
      'name' => 'notif-updated',
      'data' => [
        'action' => 'admin_' . $mode,
        'appointment_id' => $apptId,
        'date' => $date,
        'time' => $time
      ]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_exec($ch);
    curl_close($ch);
  } catch (Throwable $e) {
    // ignore
  }

  echo json_encode(['ok' => true, 'mode' => $mode, 'appointment_id' => $apptId]);
  exit;
} catch (Throwable $e) {
  error_log("admin_create_appointment ERROR: " . $e->getMessage());
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode([
    'error' => 'Server error',
    'details' => $e->getMessage()
  ]);
  exit;
}