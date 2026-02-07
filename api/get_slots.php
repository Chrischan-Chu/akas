<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$date     = trim((string)($_GET['date'] ?? ''));

if ($clinicId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
  exit;
}

// Load clinic hours
$stmt = $pdo->prepare('SELECT is_open, open_time, close_time FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$c = $stmt->fetch();

if (!$c || (int)($c['is_open'] ?? 0) !== 1 || empty($c['open_time']) || empty($c['close_time'])) {
  echo json_encode(['ok' => true, 'slots' => []]);
  exit;
}

$open = substr((string)$c['open_time'], 0, 5);  // HH:MM
$close = substr((string)$c['close_time'], 0, 5);

// Existing appointments for that day (exclude cancelled)
$stmt = $pdo->prepare(
  "SELECT apt_time FROM appointments
   WHERE clinic_id = ? AND apt_date = ? AND status <> 'cancelled'"
);
$stmt->execute([$clinicId, $date]);
$booked = [];
foreach ($stmt->fetchAll() as $r) {
  $t = substr((string)($r['apt_time'] ?? ''), 0, 5);
  if ($t) $booked[$t] = true;
}

// Generate 30-min slots
$slots = [];
$start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $open);
$end   = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $close);

if (!$start || !$end || $end <= $start) {
  echo json_encode(['ok' => true, 'slots' => []]);
  exit;
}

while ($start < $end) {
  $t = $start->format('H:i');
  if (empty($booked[$t])) {
    $slots[] = $t;
  }
  $start->modify('+30 minutes');
}

echo json_encode(['ok' => true, 'slots' => $slots]);
exit;
