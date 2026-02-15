<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$appTitle = 'AKAS | Admin Dashboard';
$baseUrl  = '/AKAS';

require_once __DIR__ . '/../includes/auth.php';
auth_require_role('clinic_admin', $baseUrl);

$pdo = db();

$stmt = $pdo->prepare(
  'SELECT
      a.email AS admin_email,
      c.id AS clinic_id,
      c.clinic_name, c.specialty, c.specialty_other, c.logo_path,
      c.business_id,
      c.email AS clinic_email,
      c.contact AS clinic_contact,
      c.approval_status,
      c.declined_reason,
      c.is_open,
      c.open_time,
      c.close_time
   FROM accounts a
   LEFT JOIN clinics c ON c.id = a.clinic_id
   WHERE a.id = ? AND a.role = "clinic_admin"
   LIMIT 1'
);

$stmt->execute([auth_user_id()]);
$me = $stmt->fetch() ?: [];

$clinicId = (int)($me['clinic_id'] ?? 0);

/* =========================================================
   ✅ CLINIC HOURS SAVE (CMS)
   - Saves clinics.is_open, open_time, close_time
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form'] ?? '') === 'clinic_hours') {

  $open_time  = trim((string)($_POST['open_time'] ?? ''));
  $close_time = trim((string)($_POST['close_time'] ?? ''));
  $is_open    = isset($_POST['is_open']) ? 1 : 0;

  // normalize HH:MM -> HH:MM:SS
  if (preg_match('/^\d{2}:\d{2}$/', $open_time))  $open_time  .= ':00';
  if (preg_match('/^\d{2}:\d{2}$/', $close_time)) $close_time .= ':00';

  // validate only if Open
  if ($is_open === 1) {

    $validOpen  = (bool)preg_match('/^\d{2}:\d{2}:\d{2}$/', $open_time);
    $validClose = (bool)preg_match('/^\d{2}:\d{2}:\d{2}$/', $close_time);

    if (!$validOpen || !$validClose) {
      $_SESSION['flash_error'] = 'Please set valid clinic operation hours.';
      header('Location: ' . $baseUrl . '/admin/dashboard.php');
      exit;
    }

    if (strtotime($close_time) <= strtotime($open_time)) {
      $_SESSION['flash_error'] = 'Close time must be later than open time.';
      header('Location: ' . $baseUrl . '/admin/dashboard.php');
      exit;
    }
  }

  if ($clinicId > 0) {
    $up = $pdo->prepare("UPDATE clinics SET is_open=?, open_time=?, close_time=? WHERE id=? LIMIT 1");
    $up->execute([$is_open, ($open_time !== '' ? $open_time : null), ($close_time !== '' ? $close_time : null), $clinicId]);

    $_SESSION['flash_success'] = 'Clinic operation hours updated.';
  }

  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

/**
 * =========================================================
 * ✅ DOCTOR STATUS INDICATORS (DECLINED + PENDING COUNTS)
 * =========================================================
 */
$declinedDoctors = 0;
$pendingDoctors  = 0;
$declinedRegDoctors = 0;
$pendingRegDoctors  = 0;

if ($clinicId > 0) {

  // ✅ All doctors counts
  $qAll = $pdo->prepare("
    SELECT
      SUM(approval_status='DECLINED') AS declined_all,
      SUM(approval_status='PENDING')  AS pending_all
    FROM clinic_doctors
    WHERE clinic_id = :cid
  ");
  $qAll->execute([':cid' => $clinicId]);
  $countsAll = $qAll->fetch(PDO::FETCH_ASSOC) ?: [];

  $declinedDoctors = (int)($countsAll['declined_all'] ?? 0);
  $pendingDoctors  = (int)($countsAll['pending_all'] ?? 0);

  // ✅ Registration-only counts
  $q = $pdo->prepare("
    SELECT
      SUM(approval_status='DECLINED' AND created_via='REGISTRATION') AS declined_reg,
      SUM(approval_status='PENDING'  AND created_via='REGISTRATION') AS pending_reg
    FROM clinic_doctors
    WHERE clinic_id = :cid
  ");
  $q->execute([':cid' => $clinicId]);
  $counts = $q->fetch(PDO::FETCH_ASSOC) ?: [];

  $declinedRegDoctors = (int)($counts['declined_reg'] ?? 0);
  $pendingRegDoctors  = (int)($counts['pending_reg'] ?? 0);
}

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-slate-50"
      data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>"
      data-clinic-id="<?php echo (int)$clinicId; ?>">

<main class="max-w-5xl mx-auto px-6 py-10">

  <!-- HEADER -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">

    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight"
          style="color: var(--secondary);">
        Admin Dashboard
      </h1>
      <p class="text-slate-600 mt-1">
        Welcome back, <?php echo htmlspecialchars(auth_name() ?? 'Admin'); ?> 👋
      </p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
      <a href="<?php echo $baseUrl; ?>/index.php"
         target="_blank" rel="noopener"
         class="h-11 px-5 rounded-xl font-semibold border border-slate-200 bg-white
                hover:bg-slate-50 transition flex items-center justify-center gap-2">
        View Website
      </a>

      <a href="<?php echo $baseUrl; ?>/logout.php"
         class="h-11 px-5 rounded-xl font-semibold text-white
                shadow-sm hover:opacity-90 transition flex items-center justify-center"
         style="background: var(--primary);">
        Logout
      </a>
    </div>

  </div>

  <!-- ✅ Flash messages -->
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="mb-6 rounded-3xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 font-semibold">
      <?php echo htmlspecialchars((string)$_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-700 font-semibold">
      <?php echo htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_success']); ?>
    </div>
  <?php endif; ?>

  <?php
    $clinicStatus = (string)($me['approval_status'] ?? '');
    $declineReason = (string)($me['declined_reason'] ?? '');
  ?>

  <!-- ✅ NEW: DOCTOR DECLINED INDICATOR -->
  <?php if ($declinedDoctors > 0): ?>
    <a href="<?php echo $baseUrl; ?>/admin/doctors.php?status=DECLINED"
       class="group mb-6 block rounded-3xl border border-rose-200 bg-rose-50 p-5 hover:bg-rose-100 hover:shadow-md transition cursor-pointer">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
          <div class="text-sm font-extrabold text-rose-700">Doctor Application Declined</div>
          <div class="text-sm text-slate-700 mt-1">
            You have <span class="font-bold"><?php echo (int)$declinedDoctors; ?></span>
            declined doctor<?php echo $declinedDoctors > 1 ? 's' : ''; ?>.
            Click the "Doctors" button to view the reason and reapply.
          </div>
          <div class="mt-2 text-xs text-rose-700 font-semibold underline-offset-4 group-hover:underline group-hover:translate-x-1 transition">
            Click here to review declined doctor/s →
          </div>
        </div>

        <span class="shrink-0 inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold
                     bg-white text-rose-700 border border-rose-200">
          <?php echo (int)$declinedDoctors; ?> Declined
        </span>
      </div>
    </a>
  <?php endif; ?>

  <?php if ($clinicStatus === 'DECLINED'): ?>
    <div class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="text-sm font-bold text-red-700">Clinic Application Declined</div>
          <div class="text-slate-700 mt-1 text-sm">
            <?php if ($declineReason !== ''): ?>
              Reason: <span class="font-semibold"><?= htmlspecialchars($declineReason, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
              Your clinic application was declined. Please review your details and reapply.
            <?php endif; ?>
          </div>

          <div class="mt-2 text-sm text-slate-700">
            Registration doctors declined: <span class="font-bold"><?= (int)$declinedRegDoctors ?></span>
          </div>
        </div>

        <form method="POST" action="<?= $baseUrl ?>/superadmin/action.php" class="shrink-0">
          <input type="hidden" name="clinic_id" value="<?= (int)$clinicId ?>">
          <input type="hidden" name="action" value="reapply">
          <button
            class="h-11 px-5 rounded-2xl font-bold text-white shadow-sm hover:opacity-95 transition"
            style="background: var(--secondary);">
            Reapply Now
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($clinicStatus === 'PENDING'): ?>
    <div class="mb-6 rounded-3xl border border-yellow-200 bg-yellow-50 p-5">
      <div class="text-sm font-bold text-yellow-700">Clinic Application Pending</div>
      <div class="text-sm text-slate-700 mt-1">
        Your clinic is under review by the super admin.
        <?php if ($pendingRegDoctors > 0): ?>
          Pending registration doctors: <span class="font-bold"><?= (int)$pendingRegDoctors ?></span>
        <?php endif; ?>
        <?php if ($pendingDoctors > 0): ?>
          <span class="ml-2">• Total pending doctors: <span class="font-bold"><?= (int)$pendingDoctors ?></span></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- CONTENT -->
  <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- CLINIC PROFILE -->
    <div class="lg:col-span-2 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
      <h2 class="text-xl font-bold" style="color: var(--secondary);">Clinic Profile</h2>

      <div class="mt-5 flex items-start gap-4">
        <div class="h-16 w-16 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center">
          <?php if (!empty($me['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars((string)$me['logo_path']); ?>" alt="Logo" class="h-full w-full object-cover" />
          <?php else: ?>
            <span class="text-slate-400 text-sm">No Logo</span>
          <?php endif; ?>
        </div>

        <div class="flex-1">
          <p class="text-lg font-semibold text-slate-900">
            <?php echo htmlspecialchars((string)($me['clinic_name'] ?? auth_name() ?? '')); ?>
          </p>

          <p class="text-sm text-slate-600 mt-0.5">
            <?php
              $type = (string)($me['specialty'] ?? '-');
              if ($type === 'Other' && !empty($me['specialty_other'])) {
                $type = (string)$me['specialty_other'];
              }
            ?>
            Type: <?php echo htmlspecialchars($type); ?>
          </p>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

        <div class="rounded-2xl border border-slate-200 p-4">
          <div class="text-slate-500">Email</div>
          <div class="font-semibold text-slate-900 break-all">
            <?php echo htmlspecialchars((string)($me['clinic_email'] ?? $me['admin_email'] ?? '—')); ?>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 p-4">
          <div class="text-slate-500">Contact</div>
          <div class="font-semibold text-slate-900">
            <?php
              $phone = preg_replace('/\D/', '', (string)($me['clinic_contact'] ?? ''));
              echo ($phone && strlen($phone) === 10)
                ? '+63 ' . htmlspecialchars($phone)
                : '—';
            ?>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 p-4 sm:col-span-2">
          <div class="text-slate-500">Business ID</div>
          <div class="font-semibold text-slate-900">
            <?php echo htmlspecialchars((string)($me['business_id'] ?? '—')); ?>
          </div>
        </div>

      </div>

      <!-- ✅ NEW: OPERATION HOURS (CMS) -->
      <?php
        $curIsOpen = (int)($me['is_open'] ?? 1);
        $curOpen   = (string)($me['open_time'] ?? '');
        $curClose  = (string)($me['close_time'] ?? '');

        $curOpenHHMM  = $curOpen ? substr($curOpen, 0, 5) : '';
        $curCloseHHMM = $curClose ? substr($curClose, 0, 5) : '';
      ?>

      <div class="mt-6 rounded-3xl border border-slate-200 p-5 bg-slate-50">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="font-extrabold text-slate-900">Clinic Operation Hours</h3>
            <p class="text-sm text-slate-600 mt-1">
              Required for the booking calendar to show available time slots.
            </p>
          </div>

          <span class="shrink-0 text-xs font-extrabold px-3 py-1 rounded-full border border-slate-200 bg-white text-slate-700">
            <?php echo $curIsOpen === 1 ? 'Open' : 'Closed'; ?>
          </span>
        </div>

        <form method="POST" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
          <input type="hidden" name="form" value="clinic_hours">

          <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700">
            <input type="checkbox" name="is_open" value="1"
              <?php echo $curIsOpen === 1 ? 'checked' : ''; ?>
              class="h-4 w-4 rounded border-slate-300">
            Clinic is Open
          </label>

          <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Open time</label>
            <input type="time" name="open_time" required
              value="<?php echo htmlspecialchars($curOpenHHMM, ENT_QUOTES, 'UTF-8'); ?>"
              class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-white">
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Close time</label>
            <input type="time" name="close_time" required
              value="<?php echo htmlspecialchars($curCloseHHMM, ENT_QUOTES, 'UTF-8'); ?>"
              class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-white">
          </div>

          <div class="sm:col-span-3 flex justify-end">
            <button type="submit"
              class="h-11 px-5 rounded-2xl font-bold text-white shadow-sm hover:opacity-95 transition"
              style="background: var(--primary);">
              Save Hours
            </button>
          </div>
        </form>

        <?php if ($curOpenHHMM === '' || $curCloseHHMM === ''): ?>
          <div class="mt-3 text-xs font-semibold text-rose-700">
            ⚠ Operation hours are not set. Patients will not see time slots until you set them.
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- QUICK ACTIONS -->
    <div class="rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
      <h2 class="text-xl font-bold" style="color: var(--secondary);">Quick Actions</h2>
      <p class="text-sm text-slate-600 mt-2">Manage what patients see on your clinic profile.</p>

      <div class="mt-5 grid gap-3">
        <a href="<?php echo $baseUrl; ?>/admin/clinic-details.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--secondary);">
          Clinic Details
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/doctors.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--primary);">
          Doctors
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/add-admin.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--primary);">
          Add Admin Account
        </a>
      </div>
    </div>

  </section>

  <?php
  // Fetch approved doctors for optional filter
  $doctors = [];
  if ($clinicId > 0) {
    $ds = $pdo->prepare("SELECT id, name FROM clinic_doctors WHERE clinic_id = :cid AND approval_status='APPROVED' ORDER BY name ASC");
    $ds->execute([':cid' => $clinicId]);
    $doctors = $ds->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
  ?>

  <section class="mt-8 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold" style="color: var(--secondary);">Appointments Calendar</h2>
        <p class="text-sm text-slate-600 mt-1">Click a day to see appointments booked for your clinic.</p>
      </div>

      <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
        <div class="text-sm font-semibold text-slate-700">Filter doctor:</div>
        <select id="adminDoctorFilter" class="h-11 px-3 rounded-xl border border-slate-200 bg-white">
          <option value="0">All doctors</option>
          <?php foreach ($doctors as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars((string)$d['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <button type="button" id="adminCreateBtn"
          class="h-11 px-4 rounded-xl font-extrabold text-white shadow-sm hover:opacity-95 transition"
          style="background: var(--primary);">
          Create Appointment
        </button>
      </div>
    </div>

    <div class="mt-5 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <button id="adminPrevMonth" type="button"
                class="h-10 px-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-bold">‹</button>

        <div id="adminMonthLabel" class="font-extrabold text-slate-900"></div>

        <button id="adminNextMonth" type="button"
                class="h-10 px-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-bold">›</button>
      </div>

      <div class="text-sm text-slate-600">
        Selected date: <span class="font-bold text-slate-900" id="adminSelectedDateText">None</span>
      </div>
    </div>

    <!-- calendar grid (7 columns) -->
    <div class="mt-4 grid grid-cols-7 gap-2 text-xs font-bold text-slate-500">
      <div class="text-center">Su</div><div class="text-center">Mo</div><div class="text-center">Tu</div>
      <div class="text-center">We</div><div class="text-center">Th</div><div class="text-center">Fr</div>
      <div class="text-center">Sa</div>
    </div>

    <div id="adminCalendarGrid" class="mt-2 grid grid-cols-7 gap-2"></div>

    <div class="mt-6">
      <h3 class="text-lg font-bold text-slate-900">Appointments</h3>
      <div id="adminApptList" class="mt-3"></div>
    </div>

    <!-- Appointment Modal -->
    <div id="adminApptModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
      <div class="w-full max-w-xl rounded-3xl bg-white shadow-xl border border-slate-200 overflow-hidden">

        <div class="px-6 py-5 flex items-start justify-between gap-3 border-b border-slate-200">
          <div>
            <div class="text-lg font-extrabold" style="color: var(--secondary);">Appointment Details</div>
            <div class="text-sm text-slate-600" id="admModalSub">—</div>
          </div>

          <button type="button" id="admModalClose"
                  class="h-10 w-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 font-bold">
            ✕
          </button>
        </div>

        <div class="px-6 py-5">
          <div class="grid gap-3 text-sm">
            <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50">
              <div class="text-slate-500">Patient</div>
              <div class="font-extrabold text-slate-900" id="admPatient">—</div>
              <div class="text-xs text-slate-500 mt-1" id="admContact">—</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="rounded-2xl border border-slate-200 p-4">
                <div class="text-slate-500">Doctor</div>
                <div class="font-bold text-slate-900" id="admDoctor">—</div>
              </div>
              <div class="rounded-2xl border border-slate-200 p-4">
                <div class="text-slate-500">Status</div>
                <div class="font-extrabold text-slate-900" id="admStatus">—</div>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="text-slate-500">Notes</div>
              <div class="text-slate-900 mt-1" id="admNotes">—</div>
            </div>
          </div>

          <div class="mt-6 flex flex-col sm:flex-row gap-3 sm:justify-end">
            <button type="button" id="admCancelBtn"
                    class="h-11 px-5 rounded-2xl font-bold border border-slate-200 bg-white hover:bg-slate-50">
              Cancel Appointment
            </button>

            <button type="button" id="admDoneBtn"
                    class="h-11 px-5 rounded-2xl font-bold text-white shadow-sm hover:opacity-95"
                    style="background: var(--primary);">
              Mark as Done
            </button>
          </div>

          <div class="mt-3 text-xs text-slate-500" id="admModalMsg"></div>
        </div>
      </div>
    </div>

    <!-- Create Appointment Modal -->
    <div id="adminCreateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4">
      <div class="w-full max-w-xl rounded-3xl bg-white shadow-xl border border-slate-200 overflow-hidden">

        <div class="px-6 py-5 flex items-start justify-between gap-3 border-b border-slate-200">
          <div>
            <div class="text-lg font-extrabold" style="color: var(--secondary);">Create Appointment</div>
            <div class="text-sm text-slate-600" id="admCreateSub">—</div>
          </div>

          <button type="button" id="admCreateClose"
                  class="h-10 w-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 font-bold">
            ✕
          </button>
        </div>

        <div class="px-6 py-5">
          <div class="grid gap-3 text-sm">
            <div>
              <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Patient email (must be an existing user)</label>
              <input id="admCreateEmail" type="email" class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-white" placeholder="name@email.com">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Doctor</label>
                <select id="admCreateDoctor" class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-white">
                  <option value="0">Select doctor</option>
                  <?php foreach ($doctors as $d): ?>
                    <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars((string)$d['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Time</label>
                <select id="admCreateTime" class="w-full h-11 px-3 rounded-xl border border-slate-200 bg-white">
                  <option value="">Select time</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1">Notes (optional)</label>
              <textarea id="admCreateNotes" rows="3" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white" placeholder="Follow-up / reschedule notes..."></textarea>
            </div>
          </div>

          <div class="mt-6 flex flex-col sm:flex-row gap-3 sm:justify-end">
            <button type="button" id="admCreateSave"
                    class="h-11 px-5 rounded-2xl font-bold text-white shadow-sm hover:opacity-95"
                    style="background: var(--primary);">
              Save Appointment
            </button>
          </div>

          <div class="mt-3 text-xs text-slate-500" id="admCreateMsg"></div>
        </div>
      </div>
    </div>

  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/admin-dashboard-calendar.js"></script>

</body>
</html>
