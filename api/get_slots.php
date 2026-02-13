<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

// ✅ Philippines timezone
date_default_timezone_set('Asia/Manila');

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$date     = trim((string)($_GET['date'] ?? ''));

// Optional: slot size (mins). Default 30.
$slotMins = (int)($_GET['slot_mins'] ?? 30);
if (!in_array($slotMins, [15, 30, 60], true)) $slotMins = 30;

// Optional: filter by doctor_id (future use)
$doctorId = (int)($_GET['doctor_id'] ?? 0);

if ($clinicId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
  exit;
}

// Load clinic hours
$stmt = $pdo->prepare('SELECT is_open, open_time, close_time FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$isOpen = ((int)($c['is_open'] ?? 0) === 1);
$openTimeRaw  = (string)($c['open_time'] ?? '');
$closeTimeRaw = (string)($c['close_time'] ?? '');

if (!$isOpen || $openTimeRaw === '' || $closeTimeRaw === '') {
  echo json_encode([
    'ok' => true,
    'slots' => [],
    'meta' => [
      'clinic_id' => $clinicId,
      'date' => $date,
      'slot_mins' => $slotMins,
      'open' => null,
      'close' => null,
      'clinic_status' => 'CLINIC_CLOSED',
      'timezone' => 'Asia/Manila',
    ]
  ]);
  exit;
}

$open  = substr($openTimeRaw, 0, 5);  // HH:MM
$close = substr($closeTimeRaw, 0, 5); // HH:MM

$start = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $open);
$end   = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $close);

if (!$start || !$end || $end <= $start) {
  echo json_encode([
    'ok' => true,
    'slots' => [],
    'meta' => [
      'clinic_id' => $clinicId,
      'date' => $date,
      'slot_mins' => $slotMins,
      'open' => $open,
      'close' => $close,
      'clinic_status' => 'INVALID_HOURS',
      'timezone' => 'Asia/Manila',
    ]
  ]);
  exit;
}

// ✅ Server now in PH time
$now = new DateTime('now'); // uses Asia/Manila because we set timezone
$isToday = ($date === $now->format('Y-m-d'));

// ✅ Booking opens 1 hour before clinic opens (only applies for TODAY)
$bookingGateOpen = null;
$bookingIsAllowedToday = true;

if ($isToday) {
  $bookingGateOpen = clone $start;
  $bookingGateOpen->modify('-1 hour');

  // If it's earlier than (open_time - 1hr), booking should be disabled
  if ($now < $bookingGateOpen) {
    $bookingIsAllowedToday = false;
  }
}

// Pull booked appointments with their statuses
$params = [$clinicId, $date];

$sql = "
  SELECT APT_Time, APT_Status
  FROM appointments
  WHERE APT_ClinicID = ?
    AND APT_Date = ?
";

if ($doctorId > 0) {
  $sql .= " AND APT_DoctorID = ? ";
  $params[] = $doctorId;
}

$sql .= " AND APT_Status IN ('pending','approved')";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$occupied = []; // time(HH:MM) => approved|pending (approved wins)
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $t = substr((string)($r['APT_Time'] ?? ''), 0, 5);
  $st = strtolower((string)($r['APT_Status'] ?? ''));
  if (!$t) continue;

  if (!isset($occupied[$t])) {
    $occupied[$t] = $st;
  } else {
    if ($occupied[$t] !== 'approved' && $st === 'approved') {
      $occupied[$t] = 'approved';
    }
  }
}

// Future support for blocked slots
$blocked = []; // time(HH:MM) => true

// Build slots with full status
$slots = [];
$availableCount = 0;

$cursor = clone $start;
while ($cursor < $end) {
  $t = $cursor->format('H:i');

  $slotDT = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $t);
  $isPast = false;

  if ($isToday && $slotDT) {
    $isPast = ($slotDT < $now);
  }

  // Priority:
  // 1) PAST
  // 2) NOT_YET_OPEN (today only, before open-1hr gate)
  // 3) BLOCKED
  // 4) BOOKED_APPROVED
  // 5) BOOKED_PENDING
  // 6) AVAILABLE
  if ($isPast) {
    $status = 'PAST';
    $canBook = false;
  } elseif ($isToday && !$bookingIsAllowedToday) {
    // Booking gate not open yet, so nothing is bookable
    $status = 'NOT_YET_OPEN';
    $canBook = false;
  } elseif (!empty($blocked[$t])) {
    $status = 'BLOCKED';
    $canBook = false;
  } elseif (!empty($occupied[$t])) {
    $status = ($occupied[$t] === 'approved') ? 'BOOKED_APPROVED' : 'BOOKED_PENDING';
    $canBook = false;
  } else {
    $status = 'AVAILABLE';
    $canBook = true;
    $availableCount++;
  }

  $slots[] = [
    'time' => $t,
    'status' => $status,
    'can_book' => $canBook,
  ];

  $cursor->modify('+' . $slotMins . ' minutes');
}

// ✅ If no available slots at all, treat as CLOSED/FULL for that date
$clinicStatus = 'OPEN';
if ($availableCount === 0) {
  $clinicStatus = 'CLOSED_FULL';
} elseif ($isToday && !$bookingIsAllowedToday) {
  // Gate not open yet (1 hour before open)
  $clinicStatus = 'CLOSED_NOT_YET_OPEN';
}

// Return
echo json_encode([
  'ok' => true,
  'slots' => $slots,
  'meta' => [
    'clinic_id' => $clinicId,
    'date' => $date,
    'slot_mins' => $slotMins,
    'open' => $open,
    'close' => $close,
    'clinic_status' => $clinicStatus,
    'timezone' => 'Asia/Manila',
    'server_now' => $now->format('Y-m-d H:i:s'),
    'booking_gate_open' => ($bookingGateOpen ? $bookingGateOpen->format('Y-m-d H:i:s') : null),
    'doctor_id' => ($doctorId > 0 ? $doctorId : null),
    'available_count' => $availableCount,
  ]
]);
exit;
