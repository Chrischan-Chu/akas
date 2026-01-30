<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$userId   = (int)($_POST['user_id'] ?? 0);    // from session in real system
$clinicId = (int)($_POST['clinic_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$date     = $_POST['date'] ?? '';
$time     = $_POST['time'] ?? '';            // "HH:MM" from UI
$notes    = trim($_POST['notes'] ?? '');

if (!$userId || !$clinicId || !$doctorId) {
  http_response_code(400);
  echo json_encode(["error" => "Missing ids"]);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid date/time"]);
  exit;
}

$timeSql = $time . ":00"; // "HH:MM:00"

try {
  $pdo->beginTransaction();

  // Lock check: prevent 2 users booking the same slot at the same time
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
    echo json_encode(["error" => "Slot already taken"]);
    exit;
  }

  // Insert as PENDING (admin approves later)
  $stmt = $pdo->prepare("
    INSERT INTO appointments
      (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
    VALUES
      (?, ?, ?, ?, ?, 'PENDING', ?, NOW())
  ");
  $stmt->execute([$userId, $doctorId, $clinicId, $date, $timeSql, $notes]);

  $pdo->commit();
  echo json_encode(["ok" => true, "message" => "Appointment requested (PENDING)"]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(["error" => "Server error"]);
}
