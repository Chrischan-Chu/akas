<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

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

$patientEmail = trim((string)($body['patient_email'] ?? ''));
$doctorId = (int)($body['doctor_id'] ?? 0);
$date = trim((string)($body['date'] ?? ''));
$time = trim((string)($body['time'] ?? ''));
$notes = trim((string)($body['notes'] ?? ''));

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

  // Slot taken? (treat done as taken too)
  $st = $pdo->prepare('SELECT APT_AppointmentID FROM appointments
    WHERE APT_ClinicID = ? AND APT_DoctorID = ? AND APT_Date = ? AND APT_Time = ?
      AND APT_Status IN ("pending","approved","done")
    LIMIT 1 FOR UPDATE');
  $st->execute([$clinicId, $doctorId, $date, $time]);
  if ($st->fetch(PDO::FETCH_ASSOC)) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => 'Slot already taken']);
    exit;
  }

  $finalNotes = trim('ADMIN: ' . ($notes !== '' ? $notes : 'Follow-up / reschedule'));

  $st = $pdo->prepare('INSERT INTO appointments
    (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
    VALUES (?, ?, ?, ?, ?, "approved", ?, NOW())');
  $st->execute([$userId, $doctorId, $clinicId, $date, $time, $finalNotes]);

  $pdo->commit();
  
  // --- REAL-TIME TRIGGER ---
  try {
    require_once __DIR__ . '/../includes/realtime_ably.php';
    if (function_exists('publish_slots_updated')) {
      publish_slots_updated($clinicId, $date); 
    }
  } catch (Throwable $rt) {
    // silently ignore realtime errors so it doesn't break the response
  }
  // -------------------------
  
  echo json_encode(['ok' => true]);
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
  exit;
}
