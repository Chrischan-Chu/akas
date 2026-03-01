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

// Only clinic admins can use this
if ((string)auth_role() !== 'clinic_admin') {
  json_out(['ok' => false, 'message' => 'Forbidden'], 403);
}

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  json_out(['ok' => false, 'message' => 'Clinic not linked to this admin account.'], 400);
}

// Auto-mark past APPROVED appointments as DONE
try {
  $upd = $pdo->prepare("UPDATE appointments
                         SET APT_Status='DONE'
                         WHERE APT_ClinicID = ?
                           AND APT_Status = 'APPROVED'
                           AND TIMESTAMP(APT_Date, APT_Time) < NOW()");
  $upd->execute([$clinicId]);
} catch (Throwable $e) {
  // don't fail the dashboard if this update fails
}

// Doctor availability (overlay)
function parse_availability_days(?string $json): array {
  if (!$json) return [];
  $data = json_decode($json, true);
  if (!is_array($data)) return [];

  // Expecting something like:
  // { "days": {"mon":true,"tue":false,...}, "time_from":"09:00","time_to":"17:00" }
  $days = $data['days'] ?? [];
  if (!is_array($days)) $days = [];

  // Normalize keys
  $map = [
    'sun' => (bool)($days['sun'] ?? false),
    'mon' => (bool)($days['mon'] ?? false),
    'tue' => (bool)($days['tue'] ?? false),
    'wed' => (bool)($days['wed'] ?? false),
    'thu' => (bool)($days['thu'] ?? false),
    'fri' => (bool)($days['fri'] ?? false),
    'sat' => (bool)($days['sat'] ?? false),
  ];

  return [
    'days' => $map,
    'time_from' => (string)($data['time_from'] ?? ''),
    'time_to' => (string)($data['time_to'] ?? ''),
  ];
}

$date = trim((string)($_GET['date'] ?? '')); // optional YYYY-MM-DD
$month = trim((string)($_GET['month'] ?? '')); // optional YYYY-MM
$doctorId = (int)($_GET['doctor_id'] ?? 0);

$out = [
  'ok' => true,
  'appointments' => [],
  'month_counts' => new stdClass(),
  'doctor_overlay' => new stdClass(),
];

$whereDoctor = '';
$paramsDoctor = [];
if ($doctorId > 0) {
  $whereDoctor = ' AND ap.APT_DoctorID = :did ';
  $paramsDoctor[':did'] = $doctorId;

  // doctor overlay
  $ds = $pdo->prepare("
        SELECT availability
        FROM clinic_doctors
        WHERE id = :id AND clinic_id = :cid
        LIMIT 1
    ");
  $ds->execute([':id' => $doctorId, ':cid' => $clinicId]);
  $dr = $ds->fetch(PDO::FETCH_ASSOC) ?: [];
  $out['doctor_overlay'] = parse_availability_days((string)($dr['availability'] ?? ''));
} else {
  $out['doctor_overlay'] = new stdClass();
}

/**
 * Month counts
 */
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
  $start = $month . '-01';
  $end = (new DateTime($start))->modify('last day of this month')->format('Y-m-d');

  $sql = "
    SELECT
      ap.APT_Date AS d,
      COUNT(*) AS total,
      SUM(ap.APT_Status='PENDING')  AS pending,
      SUM(ap.APT_Status='APPROVED') AS approved,
      SUM(ap.APT_Status='CANCELLED') AS cancelled,
      SUM(ap.APT_Status='DONE') AS done
    FROM appointments ap
    WHERE ap.APT_ClinicID = :cid
      AND ap.APT_Date BETWEEN :start AND :end
      $whereDoctor
    GROUP BY ap.APT_Date
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge([
    ':cid' => $clinicId,
    ':start' => $start,
    ':end' => $end,
  ], $paramsDoctor));

  $map = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ymd = (string)$r['d'];
    $map[$ymd] = [
      'total' => (int)$r['total'],
      'pending' => (int)$r['pending'],
      'approved' => (int)$r['approved'],
      'cancelled' => (int)$r['cancelled'],
      'done' => (int)$r['done'],
    ];
  }
  $out['month_counts'] = $map;
}

/**
 * Appointments list for a day
 */
if ($date !== '') {
  $sql = "
    SELECT
      ap.APT_AppointmentID AS id,
      ap.APT_DoctorID AS doctor_id,   /* <--- THIS IS THE MISSING PIECE! */
      ap.APT_Date AS date,
      ap.APT_Time AS time,
      ap.APT_Status AS status,
      ap.APT_Notes AS notes,
      u.name AS patient_name,
      u.email AS patient_email,
      u.phone AS patient_phone,
      d.name AS doctor_name
    FROM appointments ap
    JOIN accounts u ON u.id = ap.APT_UserID
    JOIN clinic_doctors d ON d.id = ap.APT_DoctorID
    WHERE ap.APT_ClinicID = :cid
      AND ap.APT_Date = :dt
      $whereDoctor
    ORDER BY ap.APT_Time ASC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge([
    ':cid' => $clinicId,
    ':dt' => $date,
  ], $paramsDoctor));

  $out['appointments'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

json_out($out);
