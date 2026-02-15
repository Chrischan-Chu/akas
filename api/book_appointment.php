<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

date_default_timezone_set('Asia/Manila');

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

function day_key_from_date(string $ymd): ?string {
  $ts = strtotime($ymd);
  if ($ts === false) return null;
  return date('D', $ts); // Mon,Tue,Wed,Thu,Fri,Sat,Sun
}

function time_to_minutes(string $hhmm): int {
  [$h,$m] = array_map('intval', explode(':', $hhmm));
  return ($h * 60) + $m;
}

function resolve_schedule_for_date(array $schedule, string $date): ?array {
  $ts = strtotime($date);
  if ($ts === false) return null;

  $dow = (int)date('w', $ts); // 0=Sun
  $dayKey = date('D', $ts);   // Mon,Tue,...

  // ----- FORMAT B: Weekly object with day keys (Mon..Sun) -----
  if (isset($schedule[$dayKey]) && is_array($schedule[$dayKey])) {
    $row = $schedule[$dayKey];

    if (empty($row['enabled'])) return null;

    $start = (string)($row['start'] ?? '');
    $end   = (string)($row['end'] ?? '');

    // accept either key name
    $int   = (int)($row['slot_mins'] ?? $row['interval'] ?? 30);

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) return null;
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) return null;
    if (!in_array($int, [15,20,30], true)) $int = 30;

    return ['start'=>$start, 'end'=>$end, 'int'=>$int];
  }

  // ----- FORMAT A: Object with days[] + start/end -----
  if (isset($schedule['days'], $schedule['start'], $schedule['end'])) {
    $days = $schedule['days'];
    if (!is_array($days)) return null;

    $enabledToday = false;
    foreach ($days as $d) {
      $d = (int)$d;
      if ($d === $dow || ($d === 7 && $dow === 0)) { // support 1-7 Sunday=7
        $enabledToday = true;
        break;
      }
    }
    if (!$enabledToday) return null;

    $start = (string)($schedule['start'] ?? '');
    $end   = (string)($schedule['end'] ?? '');
    $int   = (int)($schedule['slot_mins'] ?? $schedule['interval'] ?? 30);

    if (!preg_match('/^\d{2}:\d{2}$/', $start)) return null;
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) return null;
    if (!in_array($int, [15,20,30], true)) $int = 30;

    return ['start'=>$start, 'end'=>$end, 'int'=>$int];
  }

  return null;
}

function is_valid_slot_in_schedule(array $schedule, string $date, string $time): bool {
  $win = resolve_schedule_for_date($schedule, $date);
  if (!$win) return false;

  $start = $win['start'];
  $end   = $win['end'];
  $int   = (int)$win['int'];

  $t  = time_to_minutes($time);
  $s  = time_to_minutes($start);
  $e  = time_to_minutes($end);

  // time must be within [start, end)
  if ($t < $s || $t >= $e) return false;

  // must align to interval from start time
  return (($t - $s) % $int) === 0;
}


// Must be logged in as USER
if (!auth_is_logged_in() || auth_role() !== 'user') {
  json_out(['error' => 'Login required. Please login first before booking an appointment.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['error' => 'Method not allowed.'], 405);
}

$pdo = db();

$userId   = (int)auth_user_id();
$clinicId = (int)($_POST['clinic_id'] ?? 0);
$doctorId = (int)($_POST['doctor_id'] ?? 0);
$date     = trim((string)($_POST['date'] ?? ''));
$time     = trim((string)($_POST['time'] ?? '')); // expect HH:MM
$notes    = trim((string)($_POST['notes'] ?? ''));

if ($userId <= 0) json_out(['error' => 'Invalid user session.'], 401);
if ($clinicId <= 0 || $doctorId <= 0) json_out(['error' => 'Missing clinic_id or doctor_id'], 400);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['error' => 'Invalid date.'], 400);
if (!preg_match('/^\d{2}:\d{2}$/', $time)) json_out(['error' => 'Invalid time.'], 400);

$timeSql = substr($time, 0, 5);

// basic notes limit (avoid huge payloads)
if (mb_strlen($notes) > 500) {
  json_out(['error' => 'Notes must be 500 characters or less.'], 400);
}

// prevent past date / past time today
$today = date('Y-m-d');
if ($date < $today) {
  json_out(['error' => 'You cannot book a past date.'], 400);
}
if ($date === $today) {
  $nowHHMM = date('H:i');
  if ($timeSql <= $nowHHMM) {
    json_out(['error' => 'You cannot book a past time.'], 400);
  }
}

try {
  $pdo->beginTransaction();

  // ✅ Clinic must be APPROVED and OPEN (optional but recommended)
  $c = $pdo->prepare("
    SELECT approval_status, is_open, open_time, close_time
    FROM clinics
    WHERE id = ?
    LIMIT 1
    FOR UPDATE
  ");
  $c->execute([$clinicId]);
  $clinic = $c->fetch(PDO::FETCH_ASSOC);

  if (!$clinic) {
    $pdo->rollBack();
    json_out(['error' => 'Clinic not found.'], 404);
  }

  if ((string)($clinic['approval_status'] ?? '') !== 'APPROVED') {
    $pdo->rollBack();
    json_out(['error' => 'Clinic is not approved for booking.'], 403);
  }

  if ((int)($clinic['is_open'] ?? 1) !== 1) {
    $pdo->rollBack();
    json_out(['error' => 'Clinic is currently closed.'], 403);
  }

  // ✅ enforce clinic hours if set (open_time/close_time expected like "09:00:00")
  $ot = (string)($clinic['open_time'] ?? '');
  $ct = (string)($clinic['close_time'] ?? '');
  if ($ot !== '' && $ct !== '') {
    $open = substr($ot, 0, 5);
    $close = substr($ct, 0, 5);
    if ($timeSql < $open || $timeSql >= $close) {
      $pdo->rollBack();
      json_out(['error' => "Selected time is outside clinic hours ($open–$close)."], 400);
    }
  }

  // ✅ Doctor must belong to clinic + must be APPROVED + fetch JSON schedule
  $d = $pdo->prepare("
    SELECT id, approval_status, schedule, availability
    FROM clinic_doctors
    WHERE id = ? AND clinic_id = ?
    LIMIT 1
    FOR UPDATE
  ");
  $d->execute([$doctorId, $clinicId]);
  $doc = $d->fetch(PDO::FETCH_ASSOC);

  if (!$doc) {
    $pdo->rollBack();
    json_out(['error' => 'Selected doctor does not belong to this clinic.'], 400);
  }

  if (strtoupper((string)($doc['approval_status'] ?? '')) !== 'APPROVED') {
    $pdo->rollBack();
    json_out(['error' => 'Selected doctor is not available for booking.'], 403);
  }

  // ✅ Validate against doctor weekly schedule JSON
  $dayKey = day_key_from_date($date);
  if (!$dayKey) {
    $pdo->rollBack();
    json_out(['error' => 'Invalid booking date.'], 400);
  }

  $scheduleRaw = (string)($doc['schedule'] ?? '');
  $schedule = [];
  if ($scheduleRaw !== '') {
    $decoded = json_decode($scheduleRaw, true);
    if (is_array($decoded)) $schedule = $decoded;
  }
  // Fallback: doctors created during registration store JSON in `availability` (and `schedule` is readable text)
  if (empty($schedule)) {
    $availabilityRaw = (string)($doc['availability'] ?? '');
    if ($availabilityRaw !== '') {
      $decoded2 = json_decode($availabilityRaw, true);
      if (is_array($decoded2)) $schedule = $decoded2;
    }
  }


  if (empty($schedule) || !is_valid_slot_in_schedule($schedule, $date, $timeSql)) {
    $pdo->rollBack();
    json_out(['error' => 'Selected time is not in the doctor’s schedule.'], 400);
  }

  // ✅ Prevent overlapping booking for same clinic+doctor+date+time
  $stmt = $pdo->prepare("
    SELECT APT_AppointmentID
    FROM appointments
    WHERE APT_ClinicID = ?
      AND APT_DoctorID = ?
      AND APT_Date = ?
      AND APT_Time = ?
      AND APT_Status IN ('pending','approved','done')
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$clinicId, $doctorId, $date, $timeSql]);

  if ($stmt->fetch()) {
    $pdo->rollBack();
    json_out(['error' => 'Slot already taken'], 409);
  }

  // ✅ Only 1 active booking per user account (unless they cancel)
  // Active = pending/approved, and date is today or future
  $today = (new DateTime('now'))->format('Y-m-d');
  $stmt = $pdo->prepare("
    SELECT APT_AppointmentID
    FROM appointments
    WHERE APT_UserID = ?
      AND APT_Status IN ('pending','approved')
      AND APT_Date >= ?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$userId, $today]);
  if ($stmt->fetch()) {
    $pdo->rollBack();
    json_out(['error' => 'You already have an active appointment. Please cancel it first before booking another.'], 409);
  }

  // ✅ Insert
  $stmt = $pdo->prepare("
    INSERT INTO appointments
      (APT_UserID, APT_DoctorID, APT_ClinicID, APT_Date, APT_Time, APT_Status, APT_Notes, APT_Created)
    VALUES
      (?, ?, ?, ?, ?, 'approved', ?, NOW())
  ");
  $stmt->execute([$userId, $doctorId, $clinicId, $date, $timeSql, $notes]);

  $pdo->commit();

  // realtime signal after commit (don’t block booking if realtime fails)
  try {
    require_once __DIR__ . '/../includes/realtime_ably.php';
    if (function_exists('publish_slots_updated')) {
      publish_slots_updated($clinicId, $date);
    }
  } catch (Throwable $rt) {
    // ignore realtime errors
  }

  json_out(['ok' => true, 'message' => 'Approved, please check notification for your checkup appointment.']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['error' => 'Server error: ' . $e->getMessage()], 500);
}