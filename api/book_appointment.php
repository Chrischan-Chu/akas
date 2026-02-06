<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';

// ✅ Must be logged in as a USER to book
if (!auth_is_logged_in() || auth_role() !== 'user') {
  http_response_code(401);
  echo json_encode([
    'error' => 'Login required. Please login first before booking an appointment.'
  ]);
  exit;
}

$pdo = db();

// ✅ Always trust the session user id (NOT the posted user_id)
$userId   = (int)auth_user_id();
$clinicId = (int)($_POST['clinic_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$date     = (string)($_POST['date'] ?? '');
$time     = (string)($_POST['time'] ?? ''); // "HH:MM"
$notes    = trim((string)($_POST['notes'] ?? ''));

if (!$clinicId || !$doctorId) {
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

  $stmt = $pdo->prepare("
    SELECT APT_AppointmentID
    FROM appointments
    WHERE APT_ClinicID=? AND APT_Date=? AND APT_Time=? AND APT_Status IN ('PENDING','APPROVED')
    FOR UPDATE
  ");
  $stmt->execute([$clinicId, $date, $timeSql]);
  if ($stmt->fetch()) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => 'Slot already taken']);
    exit;
  }

  $stmt = $pdo->prepare("
    INSERT INTO appointments
      (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
    VALUES
      (?, ?, ?, ?, ?, 'PENDING', ?, NOW())
  ");
  $stmt->execute([$userId, $doctorId, $clinicId, $date, $timeSql, $notes]);

  $pdo->commit();
  echo json_encode(['ok' => true, 'message' => 'Appointment requested (PENDING)']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
