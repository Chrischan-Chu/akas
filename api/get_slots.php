<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();
date_default_timezone_set('Asia/Manila');

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

$clinicId = (int)($_GET['clinic_id'] ?? 0);
$date     = trim((string)($_GET['date'] ?? ''));
$doctorId = (int)($_GET['doctor_id'] ?? 0);

$allowedIntervals = [15, 20, 30];

if ($clinicId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  json_out(['ok' => false, 'message' => 'Invalid request.'], 400);
}

// If doctor not selected yet, return empty (front-end can show "select doctor")
if ($doctorId <= 0) {
  json_out([
    'ok' => true,
    'slots' => [],
    'meta' => [
      'clinic_id' => $clinicId,
      'date' => $date,
      'clinic_status' => 'NO_DOCTOR_SELECTED'
    ]
  ]);
}

/* ===========================================================
   Helper: Resolve Doctor Schedule (Format A + B)
=========================================================== */
function resolve_schedule(string $raw, string $date, array $allowedIntervals): ?array {
  $raw = trim($raw);
  if ($raw === '') return null;

  $schedule = json_decode($raw, true);
  if (!is_array($schedule)) return null;

  $dow = (int)(new DateTime($date))->format('w'); // 0=Sun
  $map = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
  $key = $map[$dow] ?? null;

  // ---------- FORMAT A ----------
  // {"days":[1,2,3], "start":"09:00","end":"12:00","slot_mins":15}
  if (isset($schedule['days'], $schedule['start'], $schedule['end']) && is_array($schedule['days'])) {

    $days = array_map('intval', $schedule['days']);

    // supports 0-6 OR 1-7 (with 7 meaning Sunday)
    $enabledToday = false;
    foreach ($days as $d) {
      if ($d === $dow || ($d === 7 && $dow === 0)) {
        $enabledToday = true;
        break;
      }
    }
    if (!$enabledToday) return null;

    $start = (string)($schedule['start'] ?? '');
    $end   = (string)($schedule['end'] ?? '');
    $mins  = (int)($schedule['slot_mins'] ?? 30);

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) return null;
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) return null;

    if (!in_array($mins, $allowedIntervals, true)) $mins = 30;

    return [
      'start' => $start,
      'end' => $end,
      'slot_mins' => $mins,
      'meta' => $schedule
    ];
  }

  // ---------- FORMAT B ----------
  // {"Mon":{"enabled":true,"start":"12:00","end":"16:00","slot_mins":20}, ...}
  if ($key && isset($schedule[$key]) && is_array($schedule[$key])) {

    $row = $schedule[$key];
    if (empty($row['enabled'])) return null;

    $start = (string)($row['start'] ?? '');
    $end   = (string)($row['end'] ?? '');
    $mins  = (int)($row['slot_mins'] ?? 30);

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) return null;
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) return null;

    if (!in_array($mins, $allowedIntervals, true)) $mins = 30;

    return [
      'start' => $start,
      'end' => $end,
      'slot_mins' => $mins,
      'meta' => $schedule
    ];
  }

  return null;
}

/* Prefer JSON schedule in `schedule`; fallback to `availability` (signup format). */
function resolve_schedule_from_row(array $row, string $date, array $allowedIntervals): ?array {
  $raw = (string)($row['schedule'] ?? '');
  $resolved = resolve_schedule($raw, $date, $allowedIntervals);
  if ($resolved) return $resolved;

  $old = (string)($row['availability'] ?? '');
  return resolve_schedule($old, $date, $allowedIntervals);
}

/* ===========================================================
   1) Clinic Hours
=========================================================== */

$stmt = $pdo->prepare('SELECT is_open, open_time, close_time FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ((int)($c['is_open'] ?? 0) !== 1) {
  json_out(['ok'=>true,'slots'=>[],'meta'=>['clinic_status'=>'CLINIC_CLOSED']]);
}

$clinicOpen  = substr((string)($c['open_time'] ?? ''), 0, 5);
$clinicClose = substr((string)($c['close_time'] ?? ''), 0, 5);

if (!preg_match('/^\d{2}:\d{2}$/', $clinicOpen) || !preg_match('/^\d{2}:\d{2}$/', $clinicClose)) {
  json_out(['ok'=>true,'slots'=>[],'meta'=>['clinic_status'=>'INVALID_CLINIC_HOURS']]);
}

/* ===========================================================
   2) Doctor Schedule
=========================================================== */

$st = $pdo->prepare("SELECT schedule, availability FROM clinic_doctors WHERE id=? AND clinic_id=? LIMIT 1");
$st->execute([$doctorId, $clinicId]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  json_out(['ok'=>false,'message'=>'Doctor not found.'], 404);
}

$resolved = resolve_schedule_from_row($row, $date, $allowedIntervals);

if (!$resolved) {
  json_out([
    'ok'=>true,
    'slots'=>[],
    'meta'=>[
      'clinic_status'=>'DOCTOR_NOT_AVAILABLE_TODAY',
      'doctor_id'=>$doctorId
    ]
  ]);
}

$effectiveOpen  = (string)$resolved['start'];
$effectiveClose = (string)$resolved['end'];
$slotMins       = (int)$resolved['slot_mins'];
$doctorScheduleMeta = $resolved['meta'];

/* Clamp inside clinic hours */
if ($effectiveOpen < $clinicOpen) $effectiveOpen = $clinicOpen;
if ($effectiveClose > $clinicClose) $effectiveClose = $clinicClose;

$start = new DateTime("$date $effectiveOpen");
$end   = new DateTime("$date $effectiveClose");

if ($end <= $start) {
  json_out(['ok'=>true,'slots'=>[],'meta'=>['clinic_status'=>'INVALID_HOURS']]);
}

/* ===========================================================
   3) Booking Rules
=========================================================== */

$now = new DateTime();
$isToday = ($date === $now->format('Y-m-d'));

$bookingGateOpen = null;
$bookingAllowed = true;

if ($isToday) {
  $clinicStart = new DateTime("$date $clinicOpen");
  $bookingGateOpen = (clone $clinicStart)->modify('-1 hour');
  if ($now < $bookingGateOpen) $bookingAllowed = false;
}

/* ===========================================================
   4) Occupied Slots
=========================================================== */

$params = [$clinicId, $date, $doctorId];
$sql = "SELECT APT_Time, APT_Status FROM appointments
        WHERE APT_ClinicID=? AND APT_Date=? AND APT_DoctorID=?
          AND APT_Status IN ('pending','approved','done')";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$occupied = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $t = substr((string)$r['APT_Time'],0,5);
  $occupied[$t] = strtolower((string)$r['APT_Status']);
}

/* ===========================================================
   5) Build Slots
=========================================================== */

$slots = [];
$availableCount = 0;

while ($start < $end) {

  $t = $start->format('H:i');
  $slotDT = new DateTime("$date $t");

  if ($date < $now->format('Y-m-d') || ($isToday && $slotDT <= $now)) {
    $status = 'PAST';
    $canBook = false;
  }
  elseif ($isToday && !$bookingAllowed) {
    $status = 'NOT_YET_OPEN';
    $canBook = false;
  }
  elseif (!empty($occupied[$t])) {
    if ($occupied[$t] === 'approved') $status = 'BOOKED_APPROVED';
    elseif ($occupied[$t] === 'done') $status = 'BOOKED_DONE';
    else $status = 'BOOKED_PENDING';
    $canBook = false;
  }
  else {
    $status = 'AVAILABLE';
    $canBook = true;
    $availableCount++;
  }

  $slots[] = ['time'=>$t,'status'=>$status,'can_book'=>$canBook];
  $start->modify("+{$slotMins} minutes");
}

/* ===========================================================
   6) Day Status
=========================================================== */

$clinicStatus = 'OPEN';
if ($availableCount === 0) $clinicStatus = 'CLOSED_FULL';
if ($isToday && !$bookingAllowed) $clinicStatus = 'CLOSED_NOT_YET_OPEN';

json_out([
  'ok'=>true,
  'slots'=>$slots,
  'meta'=>[
    'clinic_id'=>$clinicId,
    'doctor_id'=>$doctorId,
    'date'=>$date,
    'slot_mins'=>$slotMins,
    'clinic_status'=>$clinicStatus,
    'booking_gate_open'=>($bookingGateOpen? $bookingGateOpen->format('Y-m-d H:i:s'):null),
    'doctor_schedule'=>$doctorScheduleMeta
  ]
]);
