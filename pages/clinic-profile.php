<?php
$clinicName = "Name of Clinic";
$appTitle = $clinicName . " | AKAS";
$baseUrl  = "/AKAS";

require_once __DIR__ . '/../includes/auth.php';
$isLoggedIn = auth_is_logged_in();
$role = auth_role();
$isUser = $isLoggedIn && $role === 'user';

$return = $_GET['return'] ?? ($baseUrl . "/index.php#clinics");
if (strpos($return, $baseUrl) !== 0) {
  $return = $baseUrl . "/index.php#clinics";
}

include "../includes/partials/head.php";
?>

<body class="bg-blue-100"
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
        <?php echo strtoupper(htmlspecialchars($clinicName)); ?>
      </h1>

      <div class="justify-self-end w-10 sm:w-12"></div>
    </div>
  </div>
</section>

<!-- MAIN INFO -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">

    <div class="bg-white rounded-2xl p-6 flex items-center justify-center">
      <img src="https://cdn-icons-png.flaticon.com/512/2967/2967350.png" class="w-48" alt="Clinic">
    </div>

    <!-- ✅ Everyone can open and view -->
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

<!-- BOOKING MODAL -->
<section id="bookingModal"
         class="hidden fixed inset-0 z-50 bg-black/40 px-4 flex items-center justify-center">
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

      <!-- LEFT -->
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

      <!-- RIGHT -->
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

      <!-- CALENDAR -->
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

            <!-- ✅ Login required note + buttons -->
            <?php if (!$isUser): ?>
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

<!-- DOCTORS (unchanged) -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php for($i=1;$i<=3;$i++): ?>
        <a
          href="<?php echo $baseUrl; ?>/pages/doctor-profile.php?id=<?php echo urlencode($i); ?>&clinic_id=<?php echo urlencode($_GET['id'] ?? 1); ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
          class="doctorCard block"
          data-doctor-id="<?php echo (int)$i; ?>"
          data-clinic-id="<?php echo (int)($_GET['id'] ?? 1); ?>"
        >
          <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition">
            <div class="flex items-center justify-center py-6">
              <img src="../assets/img/doctor1.png" class="w-20" alt="Doctor">
            </div>
            <div class="p-6 text-white" style="background:var(--primary)">
              <h5 class="font-semibold">Doctor <?php echo $i; ?></h5>
              <p class="text-sm">General Practitioner</p>
            </div>
          </div>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- Doctor Modal (unchanged in this file) -->
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
