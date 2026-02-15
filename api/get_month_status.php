<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
date_default_timezone_set('Asia/Manila');

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$month    = trim((string)($_GET['month'] ?? '')); // "YYYY-MM"

if ($clinicId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
  echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
  exit;
}

// Clinic hours
$stmt = $pdo->prepare('SELECT is_open, open_time, close_time FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ((int)($c['is_open'] ?? 0) !== 1 || empty($c['open_time']) || empty($c['close_time'])) {
  echo json_encode(['ok' => true, 'days' => []]); // clinic closed
  exit;
}

$open  = substr((string)$c['open_time'], 0, 5);
$close = substr((string)$c['close_time'], 0, 5);

$monthStart = DateTime::createFromFormat('Y-m-d H:i', $month . '-01 00:00');
if (!$monthStart) {
  echo json_encode(['ok' => false, 'message' => 'Invalid month.']);
  exit;
}

$monthEnd = (clone $monthStart)->modify('first day of next month'); // exclusive
$daysInMonth = (int)$monthStart->format('t');

$startDateStr = $monthStart->format('Y-m-d');
$endDateStr   = (clone $monthEnd)->modify('-1 day')->format('Y-m-d');

// Pull appointment counts per date (pending+approved+done)
$stmt = $pdo->prepare("
  SELECT APT_Date, COUNT(*) AS cnt
  FROM appointments
  WHERE APT_ClinicID = ?
    AND APT_Date >= ?
    AND APT_Date <= ?
    AND APT_Status IN ('pending','approved','done')
  GROUP BY APT_Date
");
$stmt->execute([$clinicId, $startDateStr, $endDateStr]);

$bookedCounts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $d = (string)($r['APT_Date'] ?? '');
  $bookedCounts[$d] = (int)($r['cnt'] ?? 0);
}

// Compute total possible slots per day (clinic-level, 30-min)
$dtOpen  = DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 ' . $open);
$dtClose = DateTime::createFromFormat('Y-m-d H:i', '2000-01-01 ' . $close);

if (!$dtOpen || !$dtClose || $dtClose <= $dtOpen) {
  echo json_encode(['ok' => true, 'days' => []]);
  exit;
}

$mins = (int)(($dtClose->getTimestamp() - $dtOpen->getTimestamp()) / 60);
$totalSlotsPerDay = (int)floor($mins / 30);
if ($totalSlotsPerDay < 1) {
  echo json_encode(['ok' => true, 'days' => []]);
  exit;
}

$now = new DateTime('now');
$todayStr = $now->format('Y-m-d');

$days = [];

for ($day = 1; $day <= $daysInMonth; $day++) {
  $dateObj = new DateTime($month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT));
  $dstr = $dateObj->format('Y-m-d');

  // Past day -> disable
  if ($dstr < $todayStr) {
    $days[$dstr] = [
      'status' => 'PAST',
      'available' => 0,
      'total' => $totalSlotsPerDay,
    ];
    continue;
  }

  // For today: before (open - 1 hour) => LOCKED
  if ($dstr === $todayStr) {
    $openDT = DateTime::createFromFormat('Y-m-d H:i', $dstr . ' ' . $open);
    if ($openDT) {
      $unlock = (clone $openDT)->modify('-1 hour');
      if ($now < $unlock) {
        $days[$dstr] = [
          'status' => 'LOCKED',
          'available' => 0,
          'total' => $totalSlotsPerDay,
        ];
        continue;
      }
    }
  }

  $booked = (int)($bookedCounts[$dstr] ?? 0);
  $available = max(0, $totalSlotsPerDay - $booked);

  if ($available <= 0) {
    $days[$dstr] = [
      'status' => 'FULL',
      'available' => 0,
      'total' => $totalSlotsPerDay,
    ];
  } else {
    $days[$dstr] = [
      'status' => 'HAS_AVAILABILITY',
      'available' => $available,
      'total' => $totalSlotsPerDay,
    ];
  }
}

echo json_encode(['ok' => true, 'days' => $days]);
exit;
