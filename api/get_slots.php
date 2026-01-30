<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php'; // your PDO connection

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$date     = $_GET['date'] ?? ''; // YYYY-MM-DD

if (!$clinicId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid clinic_id or date"]);
  exit;
}

$dayOfWeek = (int)date('w', strtotime($date)); // 0-6

// 1) if clinic is closed on that date, return []
$stmt = $pdo->prepare("SELECT 1 FROM clinic_closed_dates WHERE clinic_id=? AND closed_date=? LIMIT 1");
$stmt->execute([$clinicId, $date]);
if ($stmt->fetchColumn()) {
  echo json_encode(["slots" => []]);
  exit;
}

// 2) get weekly hours
$stmt = $pdo->prepare("
  SELECT start_time, end_time, slot_minutes, is_open
  FROM clinic_hours
  WHERE clinic_id=? AND day_of_week=? LIMIT 1
");
$stmt->execute([$clinicId, $dayOfWeek]);
$hours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hours || (int)$hours['is_open'] !== 1) {
  echo json_encode(["slots" => []]);
  exit;
}

$start = strtotime("$date " . $hours['start_time']);
$end   = strtotime("$date " . $hours['end_time']);
$slotMins = (int)$hours['slot_minutes'];

// 3) get booked times for that date (pending/approved blocks)
$stmt = $pdo->prepare("
  SELECT APT_Time
  FROM appointments
  WHERE APT_ClinicID=? AND APT_Date=? AND APT_Status IN ('PENDING','APPROVED')
");
$stmt->execute([$clinicId, $date]);
$booked = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
  $booked[$t] = true; // e.g. "10:00:00"
}

// 4) generate slots
$slots = [];
for ($t = $start; $t + ($slotMins*60) <= $end; $t += $slotMins*60) {
  $timeStr = date('H:i:s', $t);
  if (!isset($booked[$timeStr])) {
    $slots[] = date('H:i', $t); // return "10:00"
  }
}

echo json_encode(["slots" => $slots]);
