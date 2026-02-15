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
date_default_timezone_set('Asia/Manila');

$userId = (int)auth_user_id();
$now = new DateTime('now');
$today = $now->format('Y-m-d');
$nowTime = $now->format('H:i:s');

$stmt = $pdo->prepare("
  SELECT
    a.APT_AppointmentID AS appointment_id,
    a.APT_Date AS appt_date,
    a.APT_Time AS appt_time,
    c.clinic_name,
    d.name AS doctor_name
  FROM appointments a
  LEFT JOIN clinics c ON c.id = a.APT_ClinicID
  LEFT JOIN clinic_doctors d ON d.id = a.APT_DoctorID
  WHERE a.APT_UserID = :uid
    AND a.APT_Status = 'approved'
    AND (
      a.APT_Date > :today
      OR (a.APT_Date = :today AND a.APT_Time >= :nowTime)
    )
  ORDER BY a.APT_Date ASC, a.APT_Time ASC
  LIMIT 10
");

$stmt->execute([
  ':uid' => $userId,
  ':today' => $today,
  ':nowTime' => $nowTime,
]);

$items = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $time = substr((string)($r['appt_time'] ?? ''), 0, 5);
  $time12 = $time ? date('g:i A', strtotime('2000-01-01 ' . $time)) : '';
  $items[] = [
    'appointment_id' => (int)($r['appointment_id'] ?? 0),
    'date' => (string)($r['appt_date'] ?? ''),
    'time_12' => $time12,
    'clinic_name' => (string)($r['clinic_name'] ?? ''),
    'doctor_name' => (string)($r['doctor_name'] ?? ''),
  ];
}

echo json_encode(['ok' => true, 'items' => $items]);
exit;
