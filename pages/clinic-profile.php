<?php
declare(strict_types=1);

$baseUrl  = "/AKAS";

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

// Doctors
if ($isAdminViewer) {
  // clinic admin + super admin: see all doctors (including pending/declined registration doctors)
  $stmt = $pdo->prepare('
    SELECT id, name, about, availability, image_path
    FROM clinic_doctors
    WHERE clinic_id=?
    ORDER BY id DESC
  ');
  $stmt->execute([$clinicId]);
} else {
  // public users: only approved registration doctors + CMS doctors
  $stmt = $pdo->prepare('
    SELECT id, name, about, availability, image_path
    FROM clinic_doctors
    WHERE clinic_id=?
      AND approval_status="APPROVED"
    ORDER BY id DESC
  ');
  $stmt->execute([$clinicId]);
}

$doctors = $stmt->fetchAll();



$clinicName = (string)($clinic['clinic_name'] ?? 'Clinic');
$appTitle = $clinicName . " | AKAS";


$return = $_GET['return'] ?? ($baseUrl . "/index.php#clinics");
if (strpos($return, $baseUrl) !== 0) {
  $return = $baseUrl . "/index.php#clinics";
}

include "../includes/partials/head.php";

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<body class="bg-blue-100 overflow-x-hidden"
      data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES); ?>"
      data-is-user="<?php echo $isUser ? '1' : '0'; ?>">

<?php include "../includes/partials/navbar.php"; ?>

<section class="py-6 text-white" style="background:var(--secondary)">
  <div class="max-w-6xl mx-auto px-4">
    <div class="grid grid-cols-3 items-center">

      <div class="justify-self-start">
        <a id="backLink"
           href="<?php echo htmlspecialchars($return); ?>"
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

  
    <div id="openBooking" class="rounded-2xl p-6 cursor-pointer select-none flex flex-col" style="background:var(--primary)">
      <div class="bg-white rounded-xl flex-1 min-h-[200px] flex items-center justify-center">
        <span class="text-gray-400 font-semibold">CALENDAR</span>
      </div>
      <p class="mt-3 text-center">
        <span class="inline-block px-4 py-2 rounded-full text-xs font-semibold text-white" style="background: rgba(255,255,255,0.18);">
          Click to view schedule
        </span>
      </p>
    </div>

  </div>
</section>


<section class="px-4 pb-10">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

   
    <div class="lg:col-span-2 min-w-0 bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
      <h3 class="text-xl font-extrabold" style="color:var(--secondary)">About the Clinic</h3>

      <p
        class="mt-2 text-slate-600 leading-relaxed whitespace-normal break-words"
        style="overflow-wrap:anywhere; word-break:break-word;"
      >
        <?php echo !empty($details['description']) ? nl2br(h($details['description'])) : 'No clinic description yet.'; ?>
      </p>

      <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-xl p-4 min-w-0" style="background: rgba(64,183,255,.10);">
          <p class="text-xs font-bold uppercase tracking-wide text-slate-700">Address</p>
          <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words" style="overflow-wrap:anywhere; word-break:break-word;">
            <?php echo !empty($details['address']) ? h($details['address']) : '—'; ?>
          </p>
        </div>

        <div class="rounded-xl p-4 min-w-0" style="background: rgba(255,161,84,.12);">
         <p class="text-xs font-bold uppercase tracking-wide text-slate-700">Contact</p>

        <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words"
          style="overflow-wrap:anywhere; word-break:break-word;">
          <?php
            $phone = preg_replace('/\D/', '', (string)($details['contact'] ?? ''));

            echo ($phone && strlen($phone) === 10)
              ? '+63 ' . h($phone)
              : '—';
          ?>
        </p>


          <?php if (!empty($details['email'])): ?>
            <p class="mt-1 text-sm text-slate-700 whitespace-normal break-words" style="overflow-wrap:anywhere; word-break:break-word;">
              <?php echo h($details['email']); ?>
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


<section id="bookingModal"
         class="hidden fixed inset-0 transition-opacity duration-200 z-50 bg-black/40 px-4 flex items-center justify-center">
  <div class="max-w-6xl w-full max-h-[90vh] overflow-auto bg-white rounded-2xl shadow-lg p-6 md:p-8 relative">

    <button type="button" id="closeBooking"
            class="absolute top-4 right-4 w-10 h-10 rounded-full flex items-center justify-center
                   bg-gray-100 hover:bg-gray-200 transition"
            aria-label="Close booking modal">
      ✕
    </button>

    <h2 class="text-xl font-semibold mb-6" style="color:var(--primary)">
      Book an Appointment
    </h2>

    <form class="grid grid-cols-1 md:grid-cols-2 gap-8">

   
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input type="text" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="Full name">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type of appointment</label>
          <input type="text" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="Checkup / Consultation">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason for the visit</label>
          <input type="text" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="Describe your concern">
        </div>
      </div>


      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
          <input type="text" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="09xx xxx xxxx">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
          <input type="email" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="name@email.com">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
          <input type="number" class="w-full rounded-md px-4 py-2 text-white placeholder-white/70"
                 style="background:var(--primary)" placeholder="Age">
        </div>
      </div>


      <div class="md:col-span-2">
        <div class="rounded-xl p-4 md:p-5" style="background:var(--primary)">
          <div class="flex items-center justify-between mb-3">
            <p class="text-white font-semibold">Calendar</p>

            <div class="flex items-center gap-2">
              <button type="button" id="prevMonth"
                      class="px-3 py-1 rounded-md text-sm font-semibold bg-white/90 hover:bg-white transition">‹</button>

              <div class="px-4 py-1 rounded-md text-sm font-semibold" style="background:var(--accent)">
                <span id="monthLabel">Month</span>
              </div>

              <button type="button" id="nextMonth"
                      class="px-3 py-1 rounded-md text-sm font-semibold bg-white/90 hover:bg-white transition">›</button>
            </div>
          </div>

          <div class="grid grid-cols-7 text-xs text-white/90 mb-2">
            <div class="text-center">Su</div><div class="text-center">Mo</div><div class="text-center">Tu</div>
            <div class="text-center">We</div><div class="text-center">Th</div><div class="text-center">Fr</div>
            <div class="text-center">Sa</div>
          </div>

          <div id="calendarGrid" class="grid grid-cols-7 gap-2"></div>

          <div class="mt-4 bg-white rounded-xl p-4">
            <p class="text-sm font-semibold text-gray-800">
              Selected date:
              <span id="selectedDateText" class="font-bold" style="color:var(--primary)">None</span>
            </p>

            <p class="mt-3 text-xs text-gray-500">Time slots (mock UI for now):</p>
            <div id="slotGrid" class="mt-2 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2"></div>

            
            <?php if (!$isLoggedIn): ?>
              <p class="mt-3 text-xs text-red-600">
                You have to login first before booking an appointment.
              </p>

              <button type="button" id="bookBtn"
                      class="mt-4 w-full rounded-xl py-2 font-semibold text-white transition"
                      style="background:var(--primary)">
                Login
              </button>

              <a href="<?php echo $baseUrl; ?>/pages/signup.php"
                 class="mt-3 block w-full text-center rounded-xl py-2 font-semibold transition border"
                 style="border-color: rgba(15,23,42,.18); color: white; background-color: var(--accent);">
                Sign Up
              </a>

            <?php elseif ($isAdminViewer): ?>

              <p class="mt-3 text-xs text-slate-600">
                You’re logged in as an admin. You can view schedules, but you can’t book appointments.
              </p>

            <?php else: ?>

              <button type="button" id="bookBtn" disabled
                      class="mt-4 w-full rounded-xl py-2 font-semibold text-white disabled:opacity-40 transition"
                      style="background:var(--primary)">
                Book Appointment
              </button>

            <?php endif; ?>
          </div>
        </div>
      </div>

    </form>
  </div>
</section>

<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto">
    <?php if (empty($doctors)): ?>
      <div class="bg-white rounded-2xl p-6 border border-slate-100 text-slate-600">
        No doctors have been added yet.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($doctors as $d): ?>
          <a
            href="#"
            class="doctorCard block group"
            data-doctor-id="<?php echo (int)$d['id']; ?>"
            data-clinic-id="<?php echo (int)$clinicId; ?>"
          >
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition">
              <div class="flex items-center justify-center py-6">
                <?php if (!empty($d['image_path'])): ?>
                  <img src="<?php echo h((string)$d['image_path']); ?>" class="w-20 h-20 rounded-2xl object-cover" alt="Doctor">
                <?php else: ?>
                  <img src="<?php echo $baseUrl; ?>/assets/img/doctor1.png" class="w-20" alt="Doctor">
                <?php endif; ?>
              </div>
            <div class="p-6 text-white h-28 flex flex-col justify-center"
                  style="background:var(--primary)">
              <h5 class="font-semibold">
  <?php echo h((string)$d['name']); ?>
</h5>

<?php
  $about = trim((string)($d['about'] ?? ''));
  $short = mb_strlen($about) > 80
      ? mb_substr($about, 0, 80) . '...'
      : $about;
?>

<p class="text-sm text-white/90 mt-1">
  <?php echo h($short); ?>
</p>

<span
  class="mt-2 inline-block text-sm font-semibold cursor-pointer
         group-hover:underline transition"
  style="color: var(--secondary);"
>
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

<script src="<?php echo $baseUrl; ?>/assets/js/clinic-profile.js"></script>
<?php include "../includes/partials/footer.php"; ?>
