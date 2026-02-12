<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '/AKAS';
auth_require_role('user', $baseUrl);

$pdo = db();

$aptId = (int)($_POST['appointment_id'] ?? 0);
if ($aptId <= 0) {
  http_response_code(400);
  exit('Invalid appointment.');
}

$userId = (int)auth_user_id();

// Only allow delete if owned by user AND still PENDING
$stmt = $pdo->prepare("
  DELETE FROM appointments
  WHERE APT_AppointmentID=:id
    AND APT_UserID=:uid
    AND APT_Status='PENDING'
");
$stmt->execute([
  ':id' => $aptId,
  ':uid' => $userId,
]);

echo "OK";
