<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

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

$stmt = $pdo->prepare('UPDATE appointments
  SET APT_Status = "cancelled"
  WHERE APT_AppointmentID = ?
    AND APT_UserID = ?
    AND APT_Status IN ("pending","approved")
  LIMIT 1');
$stmt->execute([$appointmentId, $userId]);

if ($stmt->rowCount() < 1) {
  http_response_code(400);
  echo json_encode(['error' => 'Cannot cancel this appointment']);
  exit;
}

echo json_encode(['ok' => true]);
exit;
