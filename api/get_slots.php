<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

// ✅ Set timezone (important for server deploy)
date_default_timezone_set('Asia/Manila');

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$date     = trim((string)($_GET['date'] ?? ''));

if ($clinicId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
  exit;
}

// Load clinic hours
$stmt = $pdo->prepare('SELECT is_open, open_time, close_time FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ((int)($c['is_open'] ?? 0) !== 1 || empty($c['open_time']) || empty($c['close_time'])) {
  echo json_encode(['ok' => true, 'slots' => []]);
  exit;
}

$open  = substr((string)$c['open_time'], 0, 5);
$close = substr((string)$c['close_time'], 0, 5);

// Pull booked appointments (pending + approved = unavailable)
$stmt = $pdo->prepare("
  SELECT APT_Time
  FROM appointments
  WHERE APT_ClinicID = ?
    AND APT_Date = ?
    AND APT_Status IN ('pending','approved')
");
$stmt->execute([$clinicId, $date]);

$booked = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $t = substr((string)($r['APT_Time'] ?? ''), 0, 5);
  if ($t) $booked[$t] = true;
}

// Future support for blocked slots
$blocked = [];

// Generate slots
$start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $open);
$end   = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $close);

if (!$start || !$end || $end <= $start) {
  echo json_encode(['ok' => true, 'slots' => []]);
  exit;
}

// ✅ Past time handling
$now = new DateTime('now');
$isToday = ($date === $now->format('Y-m-d'));

$slots = [];

while ($start < $end) {
  $t = $start->format('H:i');

  $slotDT = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $t);

  $isPast = false;

  if ($isToday && $slotDT) {
      // Optional 5-minute grace:
      // $nowClone = clone $now;
      // $nowClone->modify('+5 minutes');
      // $isPast = ($slotDT < $nowClone);

      $isPast = ($slotDT < $now);
  }

  if ($isPast) {
      $status = 'NOT_AVAILABLE';
  } elseif (!empty($blocked[$t])) {
      $status = 'NOT_AVAILABLE';
  } elseif (!empty($booked[$t])) {
      $status = 'NOT_AVAILABLE';
  } else {
      $status = 'AVAILABLE';
  }

  $slots[] = [
      'time' => $t,
      'status' => $status
  ];

  $start->modify('+30 minutes');
}

echo json_encode(['ok' => true, 'slots' => $slots]);
exit;
