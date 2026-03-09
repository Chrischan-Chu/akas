<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms_templates.php';
require_once __DIR__ . '/../includes/sms_logger.php';

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

$action = strtoupper(trim((string)($payload['action'] ?? '')));

/**
 * NEW: allow CREATE / RESCHEDULE in addition to CANCELLED / DONE
 */
if (!in_array($action, ['CANCELLED','DONE','CREATE','RESCHEDULE'], true)) {
  json_out(['ok' => false, 'message' => 'Invalid action'], 422);
}

$details = null;

try {
  $pdo->beginTransaction();

  /**
   * ==========================================================
   * EXISTING: CANCELLED / DONE on an existing appointment
   * ==========================================================
   */
  if ($action === 'CANCELLED' || $action === 'DONE') {
    $apptId = (int)($payload['appointment_id'] ?? 0);
    if ($apptId <= 0) json_out(['ok' => false, 'message' => 'Invalid appointment_id'], 422);

    $chk = $pdo->prepare("
      SELECT
        a.APT_AppointmentID,
        a.APT_UserID AS user_id,
        a.APT_DoctorID,
        a.APT_ClinicID,
        a.APT_Status,
        a.APT_Date,
        a.APT_Time,
        u.name AS user_name,
        u.phone AS user_phone,
        d.name AS doctor_name,
        d.contact_number AS doctor_phone,
        c.clinic_name AS clinic_name
      FROM appointments a
      JOIN accounts u ON u.id = a.APT_UserID
      JOIN clinic_doctors d ON d.id = a.APT_DoctorID
      LEFT JOIN clinics c ON c.id = a.APT_ClinicID
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

    // ✅ Send DONE SMS to both patient and doctor (only once)
    if ($action === 'DONE') {
      try {
        $apptId2 = (int)($details['APT_AppointmentID'] ?? 0);

        $whenDate = !empty($details['APT_Date'])
          ? date('M d, Y', strtotime((string)$details['APT_Date']))
          : '';
        $whenTimeRaw = (string)($details['APT_Time'] ?? '');

        // If BOTH user+doctor done notifications already exist, skip.
        $chk2 = $pdo->prepare("SELECT recipient_type FROM sms_logs WHERE appointment_id = :id AND event_type = 'done'");
        $chk2->execute([':id' => $apptId2]);
        $existing2 = $chk2->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $alreadyUser2 = in_array('user', $existing2, true);
        $alreadyDoc2  = in_array('doctor', $existing2, true);

        $patientName = (string)($details['user_name'] ?? '');
        $doctorName  = (string)($details['doctor_name'] ?? '');

        if (!$alreadyUser2) {
          $userPhone = (string)($details['user_phone'] ?? '');
          if (trim($userPhone) !== '') {
            $msg = sms_template('done_user', [
              'clinic_name'  => (string)($details['clinic_name'] ?? ''),
              'patient_name' => $patientName,
              'doctor_name'  => $doctorName,
              'date'         => $whenDate,
              'time'         => $whenTimeRaw,
            ]);
            sms_send_and_log($pdo, [
              'appointment_id' => $apptId2,
              'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
              'user_id'        => (int)($details['APT_UserID'] ?? 0),
              'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
              'event_type'     => 'done',
            ], 'user', $userPhone, $msg);
          }
        }

        if (!$alreadyDoc2) {
          $docPhone = (string)($details['doctor_phone'] ?? '');
          if (trim($docPhone) !== '') {
            $msg = sms_template('done_doctor', [
              'clinic_name'  => (string)($details['clinic_name'] ?? ''),
              'patient_name' => $patientName,
              'doctor_name'  => $doctorName,
              'date'         => $whenDate,
              'time'         => $whenTimeRaw,
            ]);
            sms_send_and_log($pdo, [
              'appointment_id' => $apptId2,
              'clinic_id'      => (int)($details['APT_ClinicID'] ?? 0),
              'user_id'        => (int)($details['APT_UserID'] ?? 0),
              'doctor_id'      => (int)($details['APT_DoctorID'] ?? 0),
              'event_type'     => 'done',
            ], 'doctor', $docPhone, $msg);
          }
        }
      } catch (Throwable $e) {
        // Never block admin action if SMS fails
      }
    }

  }

  /**
   * ==========================================================
   * NEW: CREATE a new appointment (no appointment_id)
   * ==========================================================
   */
  else if ($action === 'CREATE') {
    $email    = trim((string)($payload['email'] ?? ''));
    $doctorId = (int)($payload['doctor_id'] ?? 0);
    $date     = trim((string)($payload['date'] ?? ''));
    $time     = trim((string)($payload['time'] ?? ''));

    if ($email === '') json_out(['ok' => false, 'message' => 'Missing email'], 422);
    if ($doctorId <= 0) json_out(['ok' => false, 'message' => 'Invalid doctor_id'], 422);
    if ($date === '') json_out(['ok' => false, 'message' => 'Missing date'], 422);
    if ($time === '') json_out(['ok' => false, 'message' => 'Missing time'], 422);

    // find user by email
    $u = $pdo->prepare("SELECT id, name, phone FROM accounts WHERE email = :e LIMIT 1");
    $u->execute([':e' => $email]);
    $user = $u->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'User not found'], 404);
    }

    $userId = (int)$user['id'];

    // enforce one active booking per user (clinic-scoped)
    $one = $pdo->prepare("
      SELECT APT_AppointmentID
      FROM appointments
      WHERE APT_ClinicID = :cid
        AND APT_UserID = :uid
        AND UPPER(APT_Status) NOT IN ('DONE','CANCELLED')
      LIMIT 1
      FOR UPDATE
    ");
    $one->execute([':cid' => $clinicId, ':uid' => $userId]);
    $existingActive = $one->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($existingActive) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'This user already has an active booking. Use Reschedule.'], 409);
    }

    // slot conflict check
    $conf = $pdo->prepare("
      SELECT APT_AppointmentID
      FROM appointments
      WHERE APT_ClinicID = :cid
        AND APT_DoctorID = :did
        AND APT_Date = :dt
        AND APT_Time = :tm
        AND UPPER(APT_Status) NOT IN ('DONE','CANCELLED')
      LIMIT 1
      FOR UPDATE
    ");
    $conf->execute([':cid' => $clinicId, ':did' => $doctorId, ':dt' => $date, ':tm' => $time]);
    $hit = $conf->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($hit) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'Selected slot is not available'], 409);
    }

    // insert
    $ins = $pdo->prepare("
      INSERT INTO appointments (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Status, APT_Date, APT_Time)
      VALUES (:uid, :did, :cid, :st, :dt, :tm)
    ");
    $ok = $ins->execute([
      ':uid' => $userId,
      ':did' => $doctorId,
      ':cid' => $clinicId,
      ':st'  => 'PENDING', // change only if your system uses a different default
      ':dt'  => $date,
      ':tm'  => $time,
    ]);

    if (!$ok) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'Insert failed'], 500);
    }

    $newId = (int)$pdo->lastInsertId();

    // build $details minimally for realtime
    $details = [
      'APT_AppointmentID' => $newId,
      'APT_UserID'        => $userId,
      'APT_DoctorID'      => $doctorId,
      'APT_ClinicID'      => $clinicId,
      'APT_Status'        => 'PENDING',
      'APT_Date'          => $date,
      'APT_Time'          => $time,
      'user_name'         => (string)($user['name'] ?? ''),
      'user_phone'        => (string)($user['phone'] ?? ''),
    ];

    $pdo->commit();
  }

  /**
   * ==========================================================
   * NEW: RESCHEDULE an existing appointment (appointment_id required)
   * ==========================================================
   */
  else if ($action === 'RESCHEDULE') {
    $apptId   = (int)($payload['appointment_id'] ?? 0);
    $doctorId = (int)($payload['doctor_id'] ?? 0);
    $date     = trim((string)($payload['date'] ?? ''));
    $time     = trim((string)($payload['time'] ?? ''));

    if ($apptId <= 0) json_out(['ok' => false, 'message' => 'Invalid appointment_id'], 422);
    if ($doctorId <= 0) json_out(['ok' => false, 'message' => 'Invalid doctor_id'], 422);
    if ($date === '') json_out(['ok' => false, 'message' => 'Missing date'], 422);
    if ($time === '') json_out(['ok' => false, 'message' => 'Missing time'], 422);

    // lock + verify appointment belongs to clinic
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
        d.contact_number AS doctor_phone,
        c.clinic_name AS clinic_name
      FROM appointments a
      JOIN accounts u ON u.id = a.APT_UserID
      JOIN clinic_doctors d ON d.id = a.APT_DoctorID
      LEFT JOIN clinics c ON c.id = a.APT_ClinicID
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

    // conflict check excluding itself
    $conf = $pdo->prepare("
      SELECT APT_AppointmentID
      FROM appointments
      WHERE APT_ClinicID = :cid
        AND APT_DoctorID = :did
        AND APT_Date = :dt
        AND APT_Time = :tm
        AND UPPER(APT_Status) NOT IN ('DONE','CANCELLED')
        AND APT_AppointmentID <> :self
      LIMIT 1
      FOR UPDATE
    ");
    $conf->execute([
      ':cid'  => $clinicId,
      ':did'  => $doctorId,
      ':dt'   => $date,
      ':tm'   => $time,
      ':self' => $apptId,
    ]);
    $hit = $conf->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($hit) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'Selected slot is not available'], 409);
    }

    // update date/time/doctor (status unchanged)
    $upd = $pdo->prepare("
      UPDATE appointments
      SET APT_DoctorID = :did,
          APT_Date = :dt,
          APT_Time = :tm
      WHERE APT_AppointmentID = :id AND APT_ClinicID = :cid
      LIMIT 1
    ");
    $upd->execute([
      ':did' => $doctorId,
      ':dt'  => $date,
      ':tm'  => $time,
      ':id'  => $apptId,
      ':cid' => $clinicId,
    ]);

    if ($upd->rowCount() < 1) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'Update failed'], 500);
    }

    // update $details for realtime/SMS consistency if needed later
    $details['APT_DoctorID'] = $doctorId;
    $details['APT_Date']     = $date;
    $details['APT_Time']     = $time;

    $pdo->commit();
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['ok' => false, 'message' => 'Server error'], 500);
}

/**
 * EXISTING: SMS only for CANCELLED (unchanged)
 */
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
          'event_type'     => 'cancel_by_admin',
        ], 'user', $userPhone, $userMsg);
      }

      $docPhone = (string)($details['doctor_phone'] ?? '');
      if (trim($docPhone) !== '') {
        $docMsg = sms_template('cancel_by_admin_doctor', [
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
          'event_type'     => 'cancel_by_admin',
        ], 'doctor', $docPhone, $docMsg);
      }
    }
  } catch (Throwable $smsErr) {
    // ignore SMS errors
  }
}

/**
 * ==========================================================
 * EXISTING: Ably real-time trigger (kept)
 * Now it will also fire for CREATE / RESCHEDULE
 * ==========================================================
 */
try {
  if (!empty($details['APT_UserID'])) {
    $patientId = (int)$details['APT_UserID'];
    error_log("Publishing to channel user-{$patientId}");

    $ablyKey = getenv('ABLY_API_KEY') ?: 'KtC7rw.-df6sw:fEo5tVYYnOpj_RrNA450TNLUaST_v6qYWplSC79SdmU';

    $ch = curl_init('https://rest.ably.io/channels/user-' . $patientId . '/messages');
    curl_setopt($ch, CURLOPT_USERPWD, $ablyKey);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
      'name' => 'notif-updated',
      'data' => ['action' => 'admin_update']
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    error_log("ABLY publish HTTP={$http} err={$err} body={$res}");

    curl_close($ch);
  }
} catch (Throwable $e) {
  error_log("ABLY exception: " . $e->getMessage());
}

/**
 * Keep response simple & consistent.
 * For CREATE: return appointment_id too.
 */
$out = ['ok' => true, 'status' => $action];
if ($action === 'CREATE' && !empty($details['APT_AppointmentID'])) {
  $out['appointment_id'] = (int)$details['APT_AppointmentID'];
}
json_out($out);