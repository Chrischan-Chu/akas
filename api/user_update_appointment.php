<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '';
auth_require_role('user', $baseUrl);

$pdo = db();

$aptId = (int)($_POST['appointment_id'] ?? 0);
$newDate = (string)($_POST['date'] ?? '');
$newTime = (string)($_POST['time'] ?? '');

if ($aptId <= 0 || $newDate === '' || $newTime === '') {
  http_response_code(400);
  exit('Missing fields.');
}

$userId = (int)auth_user_id();

// Only allow update if appointment is owned by user AND still PENDING
$stmt = $pdo->prepare("
  UPDATE appointments
  SET APT_Date=:d, APT_Time=:t
  WHERE APT_AppointmentID=:id
    AND APT_UserID=:uid
    AND APT_Status='PENDING'
");
$stmt->execute([
  ':d' => $newDate,
  ':t' => $newTime,
  ':id' => $aptId,
  ':uid' => $userId,
]);

echo "OK";
