<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_logger.php';
require_once __DIR__ . '/../includes/sms_templates.php';

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
if (!is_array($body)) $body = $_POST;

$action = strtoupper(trim((string)($body['action'] ?? 'CREATE')));
$appointmentId = (int)($body['appointment_id'] ?? 0);

if (!in_array($action, ['CREATE','RESCHEDULE'], true)) {
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

  // If RESCHEDULE, verify appt belongs to clinic + user, and not closed (lock)
  if ($action === 'RESCHEDULE') {
    $chk = $pdo->prepare("
      SELECT APT_AppointmentID, APT_Status, APT_Date, APT_Time
      FROM appointments
      WHERE APT_AppointmentID = ?
        AND APT_ClinicID = ?
        AND APT_UserID = ?
      LIMIT 1
      FOR UPDATE
    ");
    $chk->execute([$appointmentId, $clinicId, $userId]);
    $row = $chk->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) { $oldDate = (string)($row['APT_Date'] ?? ''); $oldTime = (string)($row['APT_Time'] ?? ''); }

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

  // ✅ Idempotency: if RESCHEDULE is submitted with the same doctor/date/time again, treat as no-op
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

  // 0) Slot taken? (lock) — exclude itself for RESCHEDULE
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
    // ✅ RESCHEDULE explicit appointment_id
    $upd = $pdo->prepare("
      UPDATE appointments
      SET APT_ClinicID = ?,
          APT_DoctorID = ?,
          APT_Date     = ?,
          APT_Time     = ?,
          APT_Status   = 'APPROVED',
          APT_Notes    = ?
      WHERE APT_AppointmentID = ?
        AND APT_UserID = ?
        AND APT_ClinicID = ?
      LIMIT 1
    ");
    try {
      $upd->execute([$clinicId, $doctorId, $date, $time, $finalNotes, $appointmentId, $userId, $clinicId]);
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
    // ✅ Only 1 active booking per user PER CLINIC
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


  // ✅ SMS: notify patient + doctor on RESCHEDULE (dedupe by identical message)
  if ($action === 'RESCHEDULE') {
    try {
      // fetch recipient details
      $u2 = $pdo->prepare("SELECT id, name, phone FROM accounts WHERE id = ? LIMIT 1");
      $u2->execute([$userId]);
      $user = $u2->fetch(PDO::FETCH_ASSOC) ?: null;

      $d2 = $pdo->prepare("SELECT id, name, contact_number FROM clinic_doctors WHERE id = ? LIMIT 1");
      $d2->execute([$doctorId]);
      $doc = $d2->fetch(PDO::FETCH_ASSOC) ?: null;

      $c2 = $pdo->prepare("SELECT clinic_name FROM clinics WHERE id = ? LIMIT 1");
      $c2->execute([$clinicId]);
      $cl = $c2->fetch(PDO::FETCH_ASSOC) ?: null;
      $clinicName = (string)($cl['clinic_name'] ?? '');


      $patientName = (string)($user['name'] ?? '');
      $patientPhone = (string)($user['phone'] ?? '');
      $doctorName  = (string)($doc['name'] ?? '');
      $doctorPhone = (string)($doc['contact_number'] ?? '');

      $newDateFmt = $date !== '' ? date('M d, Y', strtotime($date)) : '';
      $oldDateFmt = $oldDate !== '' ? date('M d, Y', strtotime($oldDate)) : '';

      // patient
      if (trim($patientPhone) !== '') {
        $msgU = sms_template('reschedule_user', [
          'clinic_name'  => $clinicName,
          'patient_name' => $patientName,
          'doctor_name'  => $doctorName,
          'date'         => $newDateFmt,
          'time'         => $time,
          'old_date'     => $oldDateFmt,
          'old_time'     => $oldTime,
        ]);

        $q = $pdo->prepare("SELECT 1 FROM sms_logs WHERE appointment_id = :id AND event_type = 'reschedule' AND recipient_type = 'user' AND message = :msg LIMIT 1");
        $q->execute([':id' => $apptId, ':msg' => $msgU]);
        if (!$q->fetchColumn()) {
          sms_send_and_log($pdo, [
            'appointment_id' => $apptId,
            'clinic_id'      => $clinicId,
            'user_id'        => $userId,
            'doctor_id'      => $doctorId,
            'event_type'     => 'reschedule',
          ], 'user', $patientPhone, $msgU);
        }
      }

      // doctor
      if (trim($doctorPhone) !== '') {
        $msgD = sms_template('reschedule_doctor', [
          'clinic_name'  => $clinicName,
          'patient_name' => $patientName,
          'doctor_name'  => $doctorName,
          'date'         => $newDateFmt,
          'time'         => $time,
          'old_date'     => $oldDateFmt,
          'old_time'     => $oldTime,
        ]);

        $q = $pdo->prepare("SELECT 1 FROM sms_logs WHERE appointment_id = :id AND event_type = 'reschedule' AND recipient_type = 'doctor' AND message = :msg LIMIT 1");
        $q->execute([':id' => $apptId, ':msg' => $msgD]);
        if (!$q->fetchColumn()) {
          sms_send_and_log($pdo, [
            'appointment_id' => $apptId,
            'clinic_id'      => $clinicId,
            'user_id'        => $userId,
            'doctor_id'      => $doctorId,
            'event_type'     => 'reschedule',
          ], 'doctor', $doctorPhone, $msgD);
        }
      }
    } catch (Throwable $e) {
      // never block response if SMS fails
    }
  }

  // realtime: user badge refresh (unchanged)
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
  echo json_encode(['error' => 'Server error']);
  exit;
}