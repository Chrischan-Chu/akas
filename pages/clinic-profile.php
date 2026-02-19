<?php
declare(strict_types=1);

$baseUrl  = "";
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

$clinicId = (int)($_GET['id'] ?? 0); // clinics.id
if ($clinicId <= 0) {
  header('Location: ' . $baseUrl . '/index.php#clinics');
  exit;
}

$stmt = $pdo->prepare(
  "SELECT
      id,
      clinic_name,
      specialty,
      specialty_other,
      logo_path,
      business_id,
      contact,
      email,
      description,
      address,
      approval_status,
      is_open,
      open_time,
      close_time,
      updated_at
   FROM clinics
   WHERE id=?
   LIMIT 1"
);

$stmt->execute([$clinicId]);
$clinic = $stmt->fetch();
if (!$clinic) {
  header('Location: ' . $baseUrl . '/index.php#clinics');
  exit;
}

// Hide unapproved clinics from the public.
$approval = (string)($clinic['approval_status'] ?? 'APPROVED');
$viewerRole = auth_role();
$canViewUnapproved = ($viewerRole === 'super_admin')
  || ($viewerRole === 'clinic_admin' && auth_clinic_id() === $clinicId);

if ($approval !== 'APPROVED' && !$canViewUnapproved) {
  header('Location: ' . $baseUrl . '/index.php#clinics');
  exit;
}

$details = $clinic ?: [];

$isLoggedIn = auth_is_logged_in();
$role = auth_role();
$isUser = $isLoggedIn && $role === 'user';
$isAdminViewer = $isLoggedIn && in_array($role, ['clinic_admin','super_admin'], true);

// Logged-in user info (for prefill)
$viewer = [
  'name' => '',
  'email' => '',
  'phone' => '',
];

if ($isUser) {
  $uid = (int)auth_user_id();

  $st = $pdo->prepare("SELECT name, email, phone FROM accounts WHERE id = ? LIMIT 1");
  $st->execute([$uid]);
  $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];

  $viewer['name']  = (string)($u['name'] ?? '');
  $viewer['email'] = (string)($u['email'] ?? '');
  $viewer['phone'] = (string)($u['phone'] ?? '');
}

// -------------------------
// Helpers
// -------------------------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function to12(?string $t): string {
  $t = trim((string)$t);
  if ($t === '') return '';
  $ts = strtotime($t);
  if ($ts === false) return $t; // fallback if not parseable
  return date('g:i A', $ts);    // 12-hour format
}


/**
 * Converts JSON schedule into readable text.
 * Supports:
 * A) {"days":[1,2,3], "start":"09:00","end":"12:00","slot_mins":15}
 * B) {"Mon":{"enabled":true,"start":"12:00","end":"16:00","slot_mins":20}, ...}
 * If not JSON => return raw text (human written).
 */
function schedule_to_text(?string $raw): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '';

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    // not JSON -> assume it's already human text
    return $raw;
  }

  // -------- Format A
  if (isset($data['days'], $data['start'], $data['end']) && is_array($data['days'])) {
    $numToLabel = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
    $days = array_values(array_unique(array_map('intval', $data['days'])));
    sort($days);

    $labels = [];
    foreach ($days as $d) {
      if (isset($numToLabel[$d])) $labels[] = $numToLabel[$d];
    }

    $start = (string)($data['start'] ?? '');
    $end   = (string)($data['end'] ?? '');
    $mins  = (int)($data['slot_mins'] ?? 0);

    $dayText = $labels ? implode(', ', $labels) : '—';
    $timeText = ($start && $end) ? (to12($start) . ' – ' . to12($end)) : '—';
    $minsText = $mins ? " ($mins mins)" : '';

    return $dayText . ': ' . $timeText . $minsText;
  }

  // -------- Format B
  $order = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  $lines = [];

  foreach ($order as $day) {
    if (!isset($data[$day]) || !is_array($data[$day])) continue;

    $row = $data[$day];
    $enabled = !empty($row['enabled']);
    if (!$enabled) continue;

    $start = (string)($row['start'] ?? '');
    $end   = (string)($row['end'] ?? '');
    $mins  = (int)($row['slot_mins'] ?? 0);

    if ($start === '' || $end === '') continue;

    $minsText = $mins ? " ($mins mins)" : '';
    $lines[] = "$day: " . to12($start) . " – " . to12($end) . $minsText;

  }

  return $lines ? implode("\n", $lines) : '';
}

/**
 * Prefer NEW column `schedule` (CMS output).
 * Fallback to old `availability` if schedule is empty.
 */
function doctor_schedule_text(array $d): string {
  $raw = (string)($d['schedule'] ?? '');
  if (trim($raw) !== '') return schedule_to_text($raw);

  $old = (string)($d['availability'] ?? '');
  if (trim($old) !== '') return schedule_to_text($old);

  return '';
}

// -------------------------
// Doctors (IMPORTANT FIX)
// - include `schedule` column (new)
// - keep `availability` (old fallback)
// -------------------------
if ($isAdminViewer) {
  $stmt = $pdo->prepare('
    SELECT id, name, about, schedule, availability, image_path
    FROM clinic_doctors
    WHERE clinic_id=?
    ORDER BY id DESC
  ');
  $stmt->execute([$clinicId]);
} else {
  $stmt = $pdo->prepare('
    SELECT id, name, about, schedule, availability, image_path
    FROM clinic_doctors
    WHERE clinic_id=?
      AND approval_status="APPROVED"
    ORDER BY id DESC
  ');
  $stmt->execute([$clinicId]);
}

$doctors = $stmt->fetchAll() ?: [];

$clinicName = (string)($clinic['clinic_name'] ?? 'Clinic');
$appTitle = $clinicName . " | AKAS";

$return = $_GET['return'] ?? ($baseUrl . "/index.php#clinics");
if (strpos((string)$return, $baseUrl) !== 0) {
  $return = $baseUrl . "/index.php#clinics";
}

include "../includes/partials/head.php";
?>

<body class="bg-blue-100 overflow-x-hidden"
      data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES); ?>"
      data-is-user="<?php echo $isUser ? '1' : '0'; ?>"
      data-clinic-id="<?php echo (int)$clinicId; ?>">

<?php include "../includes/partials/navbar.php"; ?>

<section class="py-6 text-white" style="background:var(--secondary)">
  <div class="max-w-6xl mx-auto px-4">
    <div class="grid grid-cols-3 items-center">
      <div class="justify-self-start">
        <a id="backLink"
           href="<?php echo htmlspecialchars((string)$return); ?>"
           class="inline-flex items-center justify-center p-2 rounded-full bg-white/20 hover:bg-white/30 transition"
           aria-label="Back">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
      </div>

      <h1 class="justify-self-center text-center text-2xl sm:text-3xl tracking-widest font-light truncate">
        <?php echo strtoupper(h($clinicName)); ?>
      </h1>

      <div class="justify-self-end w-10 sm:w-12"></div>
    </div>
  </div>
</section>

<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl p-6 flex items-center justify-center">
      <img src="<?php echo h((string)($clinic['logo_path'] ?? '')); ?>"
           onerror="this.onerror=null;this.src='https://cdn-icons-png.flaticon.com/512/2967/2967350.png';"
           class="w-48"
           alt="Clinic">
    </div>

    <div class="rounded-2xl p-6 flex flex-col" style="background:var(--primary)">

      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div class="text-white font-extrabold tracking-wide">Schedule Preview</div>
        <button id="openBooking"
          class="px-4 h-9 rounded-full text-xs font-extrabold text-white border border-white/30 hover:bg-white/10 transition">
          Book / Open Modal
        </button>
      </div>

      <!-- Doctor select -->
      <div class="bg-white rounded-2xl p-4">
        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Select doctor</label>
        <select id="previewDoctorSelect"
          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-200">
          <option value="">Select a doctor</option>
          <?php foreach ($doctors as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>"><?php echo h((string)$d['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <div class="mt-4 flex items-center justify-between mb-2">
          <button type="button" id="previewPrevMonth"
            class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-extrabold">‹</button>

          <div id="previewMonthLabel" class="text-xs font-extrabold text-slate-700"></div>

          <button type="button" id="previewNextMonth"
            class="h-9 w-9 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-extrabold">›</button>
        </div>

        <div class="grid grid-cols-7 text-xs font-bold text-slate-500 mb-2">
          <div class="text-center">Su</div><div class="text-center">Mo</div><div class="text-center">Tu</div>
          <div class="text-center">We</div><div class="text-center">Th</div><div class="text-center">Fr</div>
          <div class="text-center">Sa</div>
        </div>

        <!-- Preview calendar grid -->
        <div id="previewCalendarGrid" class="grid grid-cols-7 gap-2"></div>

        <!-- Preview slots -->
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between">
            <div class="text-sm font-extrabold text-slate-900">Available slots</div>
            <div class="text-xs text-slate-500" id="previewSelectedDate">No date selected</div>
          </div>
          <div id="previewSlotGrid" class="mt-3"></div>

          <div class="mt-3 text-xs text-slate-600">
            Click a slot to open booking modal (booking requires login).
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="px-4 pb-10">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 min-w-0 bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
      <h3 class="text-xl font-extrabold" style="color:var(--secondary)">About the Clinic</h3>

      <p class="mt-2 text-slate-600 leading-relaxed whitespace-normal break-words"
         style="overflow-wrap:anywhere; word-break:break-word;">
        <?php echo !empty($details['description']) ? nl2br(h((string)$details['description'])) : 'No clinic description yet.'; ?>
      </p>

      <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-xl p-4 min-w-0" style="background: rgba(64,183,255,.10);">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-700">Address</p>
          <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words"
             style="overflow-wrap:anywhere; word-break:break-word;">
            <?php echo !empty($details['address']) ? h((string)$details['address']) : '—'; ?>
          </p>
        </div>

        <div class="rounded-xl p-4 min-w-0" style="background: rgba(255,161,84,.12);">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-700">Contact</p>

          <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words"
             style="overflow-wrap:anywhere; word-break:break-word;">
            <?php
              $phone = preg_replace('/\D/', '', (string)($details['contact'] ?? ''));
              echo ($phone && strlen($phone) === 10) ? '+63 ' . h($phone) : '—';
            ?>
          </p>

          <?php if (!empty($details['email'])): ?>
            <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words"
               style="overflow-wrap:anywhere; word-break:break-word;">
              <?php echo h((string)$details['email']); ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="self-start lg:sticky lg:top-24">
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <h3 class="text-lg font-extrabold" style="color:var(--secondary)">Clinic Info</h3>

        <div class="mt-4 space-y-3 text-sm text-slate-700">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Status</span>
            <?php $isOpen = (int)($details['is_open'] ?? 1) === 1; ?>
            <span class="font-semibold"><?php echo $isOpen ? 'Open' : 'Closed'; ?></span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-slate-500">Hours</span>
            <?php
              $ot = $details['open_time'] ?? null;
              $ct = $details['close_time'] ?? null;
              $hours = ($ot && $ct)
                ? (date('g:i A', strtotime((string)$ot)) . ' – ' . date('g:i A', strtotime((string)$ct)))
                : '—';
            ?>
            <span class="font-semibold"><?php echo h($hours); ?></span>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-slate-500">Type</span>
            <span class="font-semibold">
              <?php echo h((string)($clinic['specialty'] ?? '')); ?>
              <?php if (!empty($clinic['specialty_other'])): ?>
                (<?php echo h((string)$clinic['specialty_other']); ?>)
              <?php endif; ?>
            </span>
          </div>
        </div>

        <div class="mt-5 rounded-xl p-4 text-xs text-slate-600" style="background: rgba(15,23,42,.04);">
          <?php if ($isAdminViewer): ?>
            Admin accounts can view schedules, but can’t book appointments.
          <?php else: ?>
            Tip: Log in to book an appointment. You can still view the schedule without logging in.
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ✅ BOOKING MODAL -->
<section id="bookingModal"
  class="hidden fixed inset-0 z-[20000] bg-black/50 px-4 py-6
         flex items-start justify-center overflow-y-auto overscroll-contain"
  style="-webkit-overflow-scrolling: touch;">

  <div class="w-full max-w-5xl my-auto bg-white rounded-3xl shadow-2xl border border-white/30
              flex flex-col max-h-[calc(100vh-3rem)] overflow-hidden">

    <!-- Header (sticky) -->
    <div class="sticky top-0 z-20 flex items-start justify-between gap-4 px-6 py-5 border-b border-slate-200"
         style="background: rgba(64,183,255,.10);">
      <div>
        <h2 class="text-lg sm:text-xl font-extrabold tracking-tight" style="color: var(--secondary);">
          Book a Visit / Checkup
        </h2>
        <p class="text-sm text-slate-600 mt-1">
          Select a doctor, choose a date, then pick an available time slot.
        </p>
      </div>

      <button type="button" id="closeBooking"
        class="h-10 w-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 transition flex items-center justify-center shrink-0"
        aria-label="Close booking modal">
        ✕
      </button>
    </div>

    <!-- Body (scrolls) -->
    <div class="flex-1 overflow-y-auto p-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Patient / Doctor -->
        <div class="lg:col-span-1 space-y-6">

          <!-- Patient Info -->
          <div class="rounded-3xl border border-slate-200 p-5 bg-white">
            <div class="flex items-center justify-between">
              <h3 class="font-extrabold text-slate-900">Patient Information</h3>
              <?php if ($isUser): ?>
                <span class="text-xs font-bold px-3 py-1 rounded-full border border-slate-200 bg-slate-50 text-slate-600">
                  Prefilled
                </span>
              <?php endif; ?>
            </div>

            <div class="mt-4 space-y-3">
              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Full name</label>
                <input id="patientName" name="patient_name" type="text"
                  class="w-full rounded-2xl px-4 py-2.5 border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                  placeholder="Full name"
                  value="<?php echo h($viewer['name']); ?>"
                  <?php echo $isUser ? 'readonly' : ''; ?>
                  required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Contact number</label>
                <input id="patientContact" name="patient_contact" type="text"
                  class="w-full rounded-2xl px-4 py-2.5 border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                  placeholder="09xx xxx xxxx"
                  value="<?php echo h($viewer['phone']); ?>"
                  <?php echo $isUser ? 'readonly' : ''; ?>
                  required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Email</label>
                <input id="patientEmail" name="patient_email" type="email"
                  class="w-full rounded-2xl px-4 py-2.5 border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                  placeholder="name@email.com"
                  value="<?php echo h($viewer['email']); ?>"
                  <?php echo $isUser ? 'readonly' : ''; ?>
                  required>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Age</label>
                <input id="patientAge" name="patient_age" type="number" min="0" max="120"
                  class="w-full rounded-2xl px-4 py-2.5 border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                  placeholder="Age"
                  >
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                Appointment type is fixed to <span class="font-bold">Visit / Checkup</span>.
              </div>
            </div>
          </div>

          <!-- Doctor Select -->
          <div class="rounded-3xl border border-slate-200 p-5 bg-white">
            <h3 class="font-extrabold text-slate-900">Step 1 — Select a Doctor</h3>
            <p class="text-sm text-slate-600 mt-1">Approved doctors only.</p>

            <select id="doctorSelect"
              class="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-200">
              <option value="">Select a doctor</option>
              <?php foreach ($doctors as $d): ?>
                <option value="<?php echo (int)$d['id']; ?>">
                  <?php echo h((string)$d['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>



            <?php if (!$isLoggedIn): ?>
              <p class="mt-3 text-xs text-rose-600 font-semibold">
                You must log in first to book. You can still view schedules.
              </p>
            <?php elseif ($isAdminViewer): ?>
              <p class="mt-3 text-xs text-slate-600">
                You’re logged in as an admin. You can view schedules, but you can’t book appointments.
              </p>
            <?php endif; ?>
          </div>

        </div>

        <!-- RIGHT: Calendar + Slots -->
        <div class="lg:col-span-2 rounded-3xl border border-slate-200 bg-white overflow-hidden">

          <!-- Schedule header -->
          <div class="px-6 py-5 border-b border-slate-200"
               style="background: var(--primary);">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 class="text-white font-extrabold text-lg">Step 2 — Choose Date & Time</h3>
                <p class="text-white/80 text-sm mt-1">
                  Selected date: <span id="selectedDateText" class="font-extrabold text-white">None</span>
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button type="button" id="prevMonth"
                  class="h-10 w-10 rounded-2xl bg-white/95 hover:bg-white transition font-extrabold">
                  ‹
                </button>

                <div class="h-10 px-4 rounded-2xl flex items-center font-extrabold text-sm"
                     style="background: var(--accent);">
                  <span id="monthLabel">Month</span>
                </div>

                <button type="button" id="nextMonth"
                  class="h-10 w-10 rounded-2xl bg-white/95 hover:bg-white transition font-extrabold">
                  ›
                </button>
              </div>
            </div>
          </div>

          <!-- Schedule body -->
          <div class="p-6">

            <div class="grid grid-cols-7 text-xs font-bold text-slate-500 mb-2">
              <div class="text-center">Su</div><div class="text-center">Mo</div><div class="text-center">Tu</div>
              <div class="text-center">We</div><div class="text-center">Th</div><div class="text-center">Fr</div>
              <div class="text-center">Sa</div>
            </div>

            <div id="calendarGrid" class="grid grid-cols-7 gap-2"></div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-5">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h4 class="font-extrabold text-slate-900">Step 3 — Pick a Time Slot</h4>
                  <p class="text-sm text-slate-600 mt-1">
                    Select a doctor first, then choose a date.
                  </p>
                </div>

                <span class="text-xs font-bold px-3 py-1 rounded-full border border-slate-200 bg-white text-slate-600">
                  Intervals vary per doctor
                </span>
              </div>

              <div id="slotGrid" class="mt-4"></div>

              <div class="mt-5">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Notes (optional)</label>
                <textarea id="notes"
                  class="w-full rounded-2xl border border-slate-200 px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                  rows="3"
                  placeholder="Any additional notes…"
                  <?php echo (!$isUser ? 'disabled' : ''); ?>></textarea>
              </div>

              <!-- Actions -->
              <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php if (!$isLoggedIn): ?>
                  <button type="button" id="bookBtn"
                    class="h-12 rounded-2xl font-extrabold text-white hover:opacity-95 transition"
                    style="background: var(--primary);">
                    Login to Book
                  </button>

                  <a href="<?php echo $baseUrl; ?>/pages/signup.php"
                    class="h-12 rounded-2xl font-extrabold flex items-center justify-center border border-slate-200 bg-white hover:bg-slate-50 transition"
                    style="color: var(--secondary);">
                    Create an Account
                  </a>

                <?php elseif ($isAdminViewer): ?>
                  <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                    Admin accounts can view schedules, but can’t book appointments.
                  </div>

                <?php else: ?>
                  <button type="button" id="bookBtn" disabled
                    class="h-12 rounded-2xl font-extrabold text-white disabled:opacity-40 transition"
                    style="background: var(--primary);">
                    Confirm Booking
                  </button>

                  <button type="button" id="closeBookingAlt"
                    class="h-12 rounded-2xl font-extrabold border border-slate-200 bg-white hover:bg-slate-50 transition"
                    style="color: var(--secondary);">
                    Cancel
                  </button>
                <?php endif; ?>
              </div>

            </div>
          </div>

        </div>

      </div>
    </div>

  </div>
</section>

<!-- Doctor cards -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto">
    <?php if (empty($doctors)): ?>
      <div class="bg-white rounded-2xl p-6 border border-slate-100 text-slate-600">
        No doctors have been added yet.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($doctors as $d): ?>
          <?php
            $schedText = doctor_schedule_text((array)$d);
            $about = trim((string)($d['about'] ?? ''));
            $shortAbout = mb_strlen($about) > 70 ? mb_substr($about, 0, 70) . '...' : $about;

            // one-line schedule preview for the card
            $schedPreview = '';
            if ($schedText !== '') {
              $firstLine = explode("\n", $schedText)[0] ?? '';
              $schedPreview = $firstLine;
            }
          ?>
          <a href="#"
             class="doctorCard block group"
             data-doctor-id="<?php echo (int)$d['id']; ?>"
             data-clinic-id="<?php echo (int)$clinicId; ?>"
             data-schedule-text="<?php echo h($schedText); ?>">
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition">
              <div class="flex items-center justify-center py-6">
                <?php if (!empty($d['image_path'])): ?>
                  <img src="<?php echo h((string)$d['image_path']); ?>" class="w-20 h-20 rounded-2xl object-cover" alt="Doctor">
                <?php else: ?>
                  <img src="<?php echo $baseUrl; ?>/assets/img/doctor1.png" class="w-20" alt="Doctor">
                <?php endif; ?>
              </div>

              <div class="p-6 text-white h-32 flex flex-col justify-center" style="background:var(--primary)">
                <h5 class="font-semibold"><?php echo h((string)$d['name']); ?></h5>

                <p class="text-sm text-white/90 mt-1">
                  <?php echo h($shortAbout); ?>
                </p>

                <?php if ($schedPreview !== ''): ?>
                  <p class="text-xs text-white/80 mt-2 truncate">
                    🗓 <?php echo h($schedPreview); ?>
                  </p>
                <?php endif; ?>

                <span class="mt-2 inline-block text-sm font-semibold cursor-pointer group-hover:underline transition"
                      style="color: var(--secondary);">
                  Read Full Profile →
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<div id="doctorModal" class="fixed inset-0 z-[9999] hidden" aria-hidden="true">
  <div id="doctorBackdrop" class="absolute inset-0 bg-black/55"></div>
  <div class="relative h-full w-full flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-6xl bg-white rounded-3xl shadow-2xl overflow-hidden border border-white/40">
      <div class="flex items-center justify-between px-5 py-4" style="background: rgba(64,183,255,.12);">
        <h3 class="font-extrabold" style="color: var(--secondary);">Doctor Profile</h3>
        <button id="closeDoctorModal"
                class="h-10 w-10 rounded-full bg-white border border-slate-200 hover:bg-slate-50 flex items-center justify-center"
                aria-label="Close">✕</button>
      </div>
      <div id="doctorModalBody" class="p-5 sm:p-6">
        <div class="text-slate-600 text-sm">Loading…</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/clinic-profile.js"></script>

<script>
  (function () {
    const alt = document.getElementById("closeBookingAlt");
    const main = document.getElementById("closeBooking");
    if (alt && main) alt.addEventListener("click", () => main.click());
  })();
</script>

<?php include "../includes/partials/footer.php"; ?>
