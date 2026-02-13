<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

// Must be logged in as USER
if (!auth_is_logged_in() || auth_role() !== 'user') {
  http_response_code(401);
  echo json_encode(['error' => 'Login required. Please login first before booking an appointment.']);
  exit;
}

$pdo = db();

$userId   = (int)auth_user_id();
$clinicId = (int)($_POST['clinic_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$date     = (string)($_POST['date'] ?? '');
$time     = (string)($_POST['time'] ?? ''); // HH:MM
$notes    = trim((string)($_POST['notes'] ?? ''));

if ($clinicId <= 0 || $doctorId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing clinic_id or doctor_id']);
  exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid date/time']);
  exit;
}

$timeSql = $time . ':00';

try {
  $pdo->beginTransaction();

  // lock same slot so no overlapping
  $stmt = $pdo->prepare("
    SELECT APT_AppointmentID
    FROM appointments
    WHERE APT_ClinicID=? AND APT_Date=? AND APT_Time=? AND APT_Status IN ('pending','approved')
    FOR UPDATE
  ");
  $stmt->execute([$clinicId, $date, $timeSql]);

  if ($stmt->fetch()) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => 'Slot already taken']);
    exit;
  }

  // immediate approved (lowercase)
  $stmt = $pdo->prepare("
    INSERT INTO appointments
      (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
    VALUES
      (?, ?, ?, ?, ?, 'approved', ?, NOW())
  ");
  $stmt->execute([$userId, $doctorId, $clinicId, $date, $timeSql, $notes]);

  $pdo->commit();

  // realtime signal after commit
  require_once __DIR__ . '/../includes/realtime_ably.php';
  publish_slots_updated($clinicId, $date);

  echo json_encode(['ok' => true, 'message' => 'Appointment booked (approved).']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
