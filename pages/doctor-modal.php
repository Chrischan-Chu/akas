<?php
declare(strict_types=1);

header("Content-Type: text/html; charset=UTF-8");

$baseUrl  = '';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$doctorId = (int)($_GET['id'] ?? 0);
$clinicId = (int)($_GET['clinic_id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($doctorId <= 0 || $clinicId <= 0) {
  echo '<div class="rounded-2xl bg-slate-50 border border-slate-200 p-6"><p class="font-extrabold text-slate-900">Doctor not found.</p></div>';
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
  echo '<div class="rounded-2xl bg-slate-50 border border-slate-200 p-6"><p class="font-extrabold text-slate-900">Doctor not found.</p></div>';
  exit;
}

$img = $doctor['image_path'] ?? ($baseUrl . '/assets/img/doctor1.png');
$clinicName = (string)($doctor['clinic_name'] ?? 'Clinic');
$spec = trim((string)($doctor['specialty'] ?? ''));
$specOther = trim((string)($doctor['specialty_other'] ?? ''));
$specialty = ($spec === 'Other' && $specOther !== '') ? $specOther : $spec;

$availabilityLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($doctor['availability'] ?? '')))));
?>

<div class="max-h-[70vh] sm:max-h-[75vh] overflow-y-auto pr-2 sm:pr-3 overscroll-contain">
  <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-7 flex flex-col items-center text-center border border-slate-100">
      <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full overflow-hidden flex items-center justify-center" style="background:rgba(255,161,84,.35)">
        <img src="<?php echo h((string)$img); ?>" loading="lazy" decoding="async" class="w-full h-full object-cover" alt="Doctor" />
      </div>

      <h2 class="mt-4 sm:mt-5 text-xl sm:text-2xl font-extrabold text-slate-900"><?php echo h((string)$doctor['name']); ?></h2>
      <p class="mt-1 text-slate-600 font-semibold"><?php echo h($specialty !== '' ? $specialty : 'Doctor'); ?></p>

      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" style="background:var(--primary)">Doctor</span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold text-slate-900" style="background:rgba(255,161,84,.45)"><?php echo h($clinicName); ?></span>
      </div>
    </div>

    <div class="lg:col-span-3 rounded-3xl p-6 sm:p-7 text-white"
         style="background:linear-gradient(135deg, rgba(64,183,255,.96), rgba(11,56,105,.92));">

      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-xl sm:text-2xl font-extrabold leading-tight break-words"><?php echo h((string)$doctor['name']); ?></h3>
          <p class="text-white/90 break-words"><?php echo h($clinicName); ?></p>
        </div>

        <div class="bg-white/15 rounded-2xl px-4 py-3 shrink-0">
          <p class="text-xs uppercase tracking-wider text-white/80">Availability</p>
          <p class="text-sm font-bold"><?php echo !empty($availabilityLines) ? h($availabilityLines[0]) : '—'; ?></p>
        </div>
      </div>

      <div class="mt-6">
        <h4 class="font-bold text-base sm:text-lg">About the Doctor</h4>
        <p class="mt-2 text-white/90 leading-relaxed text-sm sm:text-base">
          <?php echo !empty($doctor['about']) ? nl2br(h((string)$doctor['about'])) : 'No description yet.'; ?>
        </p>
      </div>

      <div class="mt-6">
        <h4 class="font-bold text-base sm:text-lg">Availability Schedule</h4>
        <div class="mt-3 rounded-2xl bg-white/10 overflow-hidden border border-white/20">
          <?php if (empty($availabilityLines)): ?>
            <div class="px-4 py-3 text-white/90 text-sm">No availability set.</div>
          <?php else: ?>
            <?php foreach ($availabilityLines as $line): ?>
              <div class="px-4 py-3 border-b border-white/10 last:border-b-0">
                <span class="text-white/95 text-sm font-semibold"><?php echo h($line); ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
             
       <div class="mt-6">
        <h4 class="font-bold text-base sm:text-lg">Contact Information</h4>
         <div class="mt-3 rounded-2xl bg-white/10 overflow-hidden border border-white/20 p-4 space-y-2">
      <div class="mt-3 text-sm text-slate-700 space-y-1">
        <div><b>Specialization:</b> <?php echo h($doctor['specialization'] ?? '—'); ?></div>
        <div><b>PRC:</b> <?php echo h($doctor['prc_no'] ?? '—'); ?></div>
        <div><b>Birthdate:</b> <?php echo h($doctor['birthdate'] ?? '—'); ?></div>
        <div><b>Email:</b> <?php echo h($doctor['email'] ?? '—'); ?></div>
        <div><b>Contact:</b> <?php echo h($doctor['contact_number'] ?? '—'); ?></div>
        <div><b>Schedule:</b> <?php echo nl2br(h($doctor['schedule'] ?? '—')); ?></div>
      </div>


    </div>
  </div>
</div>
