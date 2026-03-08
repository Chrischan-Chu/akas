<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=UTF-8");

$baseUrl  = '';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$doctorId = (int)($_GET['id'] ?? 0);
$clinicId = (int)($_GET['clinic_id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function to12(?string $t): string {
  $t = trim((string)$t);
  if ($t === '') return '';
  $ts = strtotime($t);
  if ($ts === false) return $t;
  return date('g:i A', $ts);
}

function schedule_to_lines(?string $raw): array {
  $raw = trim((string)$raw);
  if ($raw === '') return [];

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
  }

  if (isset($data['days'], $data['start'], $data['end']) && is_array($data['days'])) {
    $numToLabel = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
    $days = array_values(array_unique(array_map('intval', $data['days'])));
    sort($days);

    $labels = [];
    foreach ($days as $d) {
      if (isset($numToLabel[$d])) $labels[] = $numToLabel[$d];
    }

    $line = ($labels ? implode(', ', $labels) : 'Selected days') . ': ' . to12((string)$data['start']) . ' – ' . to12((string)$data['end']);
    $mins = (int)($data['slot_mins'] ?? 0);
    if ($mins > 0) $line .= " ({$mins} mins per slot)";
    return [$line];
  }

  $order = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  $lines = [];
  foreach ($order as $day) {
    if (!isset($data[$day]) || !is_array($data[$day]) || empty($data[$day]['enabled'])) continue;
    $start = trim((string)($data[$day]['start'] ?? ''));
    $end = trim((string)($data[$day]['end'] ?? ''));
    if ($start === '' || $end === '') continue;

    $line = $day . ': ' . to12($start) . ' – ' . to12($end);
    $mins = (int)($data[$day]['slot_mins'] ?? 0);
    if ($mins > 0) $line .= " ({$mins} mins per slot)";
    $lines[] = $line;
  }

  return $lines;
}

if ($doctorId <= 0 || $clinicId <= 0) {
  echo '<div class="rounded-3xl bg-white border border-slate-200 p-6 text-slate-700">Doctor not found.</div>';
  exit;
}

$stmt = $pdo->prepare('
  SELECT d.id, d.name, d.about, d.availability, d.image_path,
         d.birthdate, d.specialization, d.prc_no, d.schedule, d.email, d.contact_number,
         c.clinic_name, c.specialty, c.specialty_other
  FROM clinic_doctors d
  JOIN clinics c ON c.id = d.clinic_id
  WHERE d.id = ?
    AND d.clinic_id = ?
  LIMIT 1
');
$stmt->execute([$doctorId, $clinicId]);
$doctor = $stmt->fetch();

if (!$doctor) {
  echo '<div class="rounded-3xl bg-white border border-slate-200 p-6 text-slate-700">Doctor not found.</div>';
  exit;
}

$img = trim((string)($doctor['image_path'] ?? ''));
if ($img === '') {
  $img = $baseUrl . '/assets/img/doctor1.png';
}

$clinicName = trim((string)($doctor['clinic_name'] ?? 'Clinic'));
$spec = trim((string)($doctor['specialty'] ?? ''));
$specOther = trim((string)($doctor['specialty_other'] ?? ''));
$specialty = ($spec === 'Other' && $specOther !== '') ? $specOther : $spec;

$doctorSpecialization = trim((string)($doctor['specialization'] ?? ''));
if ($doctorSpecialization === '') {
  $doctorSpecialization = $specialty !== '' ? $specialty : 'Doctor';
}

$about = trim((string)($doctor['about'] ?? ''));
$scheduleLines = schedule_to_lines((string)($doctor['schedule'] ?? ''));
if (empty($scheduleLines)) {
  $scheduleLines = schedule_to_lines((string)($doctor['availability'] ?? ''));
}

$prc = trim((string)($doctor['prc_no'] ?? ''));
$email = trim((string)($doctor['email'] ?? ''));
$contact = trim((string)($doctor['contact_number'] ?? ''));
$birthdate = trim((string)($doctor['birthdate'] ?? ''));
?>

<div class="grid grid-cols-1 lg:grid-cols-[280px,1fr] gap-4 lg:gap-5 min-h-0">
  <aside class="rounded-[24px] bg-gradient-to-br from-sky-500 to-blue-600 text-white p-5 sm:p-6 shadow-sm">
    <div class="flex flex-col items-center text-center">
      <div class="w-24 h-24 rounded-full overflow-hidden ring-4 ring-white/25 bg-white/15">
        <img src="<?php echo h($img); ?>" alt="Doctor" class="w-full h-full object-cover" loading="lazy" decoding="async">
      </div>

      <h2 class="mt-4 text-2xl font-extrabold leading-tight break-words"><?php echo h((string)$doctor['name']); ?></h2>
      <p class="mt-1 text-sm font-semibold text-white/90 break-words"><?php echo h($doctorSpecialization); ?></p>
      <p class="mt-1 text-sm text-white/75 break-words"><?php echo h($clinicName); ?></p>

      <span class="mt-4 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wide">
        Doctor
      </span>
    </div>

    <div class="mt-5 space-y-3">
      <?php if ($about !== ''): ?>
        <div class="rounded-2xl bg-white/12 p-4 border border-white/15">
          <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">About</p>
          <p class="mt-2 text-sm leading-6 text-white/95 break-words"><?php echo nl2br(h($about)); ?></p>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 gap-3">
        <div class="rounded-2xl bg-white/12 p-4 border border-white/15">
          <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">Email</p>
          <p class="mt-2 text-sm font-semibold break-all"><?php echo h($email !== '' ? $email : '—'); ?></p>
        </div>

        <div class="rounded-2xl bg-white/12 p-4 border border-white/15">
          <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">Contact</p>
          <p class="mt-2 text-sm font-semibold break-words"><?php echo h($contact !== '' ? $contact : '—'); ?></p>
        </div>
      </div>
    </div>
  </aside>

  <section class="min-h-0 space-y-4 lg:space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="rounded-[22px] border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Specialization</p>
        <p class="mt-2 text-sm font-bold text-slate-900 break-words"><?php echo h($doctorSpecialization ?: '—'); ?></p>
      </div>
      <div class="rounded-[22px] border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">PRC Number</p>
        <p class="mt-2 text-sm font-bold text-slate-900 break-words"><?php echo h($prc !== '' ? $prc : '—'); ?></p>
      </div>
      <div class="rounded-[22px] border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Birthdate</p>
        <p class="mt-2 text-sm font-bold text-slate-900 break-words"><?php echo h($birthdate !== '' ? $birthdate : '—'); ?></p>
      </div>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white overflow-hidden">
      <div class="px-4 sm:px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
        <div>
          <h3 class="text-base sm:text-lg font-extrabold text-slate-900">Availability Schedule</h3>
          <p class="text-xs sm:text-sm text-slate-500">Simple schedule view</p>
        </div>
        <span class="hidden sm:inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">Clinic hours</span>
      </div>

      <?php if (empty($scheduleLines)): ?>
        <div class="px-4 sm:px-5 py-5 text-sm text-slate-500">No availability set yet.</div>
      <?php else: ?>
        <div class="divide-y divide-slate-200">
          <?php foreach ($scheduleLines as $line): ?>
            <div class="px-4 sm:px-5 py-3.5 text-sm font-semibold text-slate-700 break-words bg-white">
              <?php echo h($line); ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5">
      <h3 class="text-base sm:text-lg font-extrabold text-slate-900">Doctor Details</h3>
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="rounded-[20px] bg-slate-50 border border-slate-200 p-4">
          <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Clinic</p>
          <p class="mt-2 text-sm font-semibold text-slate-800 break-words"><?php echo h($clinicName); ?></p>
        </div>
        <div class="rounded-[20px] bg-slate-50 border border-slate-200 p-4">
          <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Contact Number</p>
          <p class="mt-2 text-sm font-semibold text-slate-800 break-words"><?php echo h($contact !== '' ? $contact : '—'); ?></p>
        </div>
        <div class="rounded-[20px] bg-slate-50 border border-slate-200 p-4 sm:col-span-2">
          <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Email Address</p>
          <p class="mt-2 text-sm font-semibold text-slate-800 break-all"><?php echo h($email !== '' ? $email : '—'); ?></p>
        </div>
      </div>
    </div>
  </section>
</div>
