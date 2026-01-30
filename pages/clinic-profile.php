
<?php
$clinicName = "Name of Clinic";
$appTitle = $clinicName . " | AKAS";
$baseUrl  = "/AKAS";
$return = $_GET['return'] ?? ($baseUrl . "/index.php#clinics");
if (strpos($return, $baseUrl) !== 0) {
  $return = $baseUrl . "/index.php#clinics";
}
include "../includes/partials/head.php";
?>
<body class="bg-blue-100">

<!-- HEADER -->
<section class="py-6 text-center text-white" style="background:var(--secondary)">
  <div class="max-w-6xl mx-auto px-4 relative">

    <!-- ✅ Back: history.back() via JS, fallback href if needed -->
    <a id="backLink"
       href="<?php echo htmlspecialchars($return); ?>"
       class="absolute left-0 top-1/2 -translate-y-1/2"
       aria-label="Back">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </a>

    <h1 class="text-3xl tracking-widest font-light">
      <?php echo strtoupper(htmlspecialchars($clinicName)); ?>
    </h1>
  </div>
</section>

<!-- MAIN INFO -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Clinic Image -->
    <div class="bg-white rounded-2xl p-6 flex items-center justify-center">
      <img src="https://cdn-icons-png.flaticon.com/512/2967/2967350.png" class="w-48" alt="Clinic">
    </div>

    <!-- Calendar Placeholder (CLICK TO OPEN BOOKING) -->
    <div id="openBooking" class="rounded-2xl p-6 cursor-pointer select-none flex flex-col" style="background:var(--primary)">
      <div class="bg-white rounded-xl flex-1 min-h-[200px] flex items-center justify-center">
        <span class="text-gray-400 font-semibold">CALENDAR</span>
      </div>
      <p class="mt-3 text-center">
        <span class="inline-block px-4 py-2 rounded-full text-xs font-semibold text-white" style="background: rgba(255,255,255,0.18);">
          Click to book an appointment
        </span>
      </p>
    </div>

  </div>
</section>

<!-- BOOKING MODAL (hidden until calendar clicked) -->
<section id="bookingModal"
         class="hidden fixed inset-0 z-50 bg-black/40 px-4 flex items-center justify-center">
  <div class="max-w-6xl w-full max-h-[90vh] overflow-auto bg-white rounded-2xl shadow-lg p-6 md:p-8 relative">

    <!-- Close button -->
    <button type="button" id="closeBooking"
            class="absolute top-4 right-4 w-10 h-10 rounded-full flex items-center justify-center
                   bg-gray-100 hover:bg-gray-200 transition"
            aria-label="Close booking modal">
      ✕
    </button>

    <h2 class="text-xl font-semibold mb-6" style="color:var(--primary)">
      Book an Appointment
    </h2>

    <!-- Form + Calendar -->
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

      <!-- CALENDAR AREA -->
      <div class="md:col-span-2">
        <div class="rounded-xl p-4 md:p-5" style="background:var(--primary)">
          <div class="flex items-center justify-between mb-3">
            <p class="text-white font-semibold">Calendar</p>

            <div class="flex items-center gap-2">
              <button type="button" id="prevMonth"
                      class="px-3 py-1 rounded-md text-sm font-semibold bg-white/90 hover:bg-white transition">
                ‹
              </button>

              <div class="px-4 py-1 rounded-md text-sm font-semibold" style="background:var(--accent)">
                <span id="monthLabel">Month</span>
              </div>

              <button type="button" id="nextMonth"
                      class="px-3 py-1 rounded-md text-sm font-semibold bg-white/90 hover:bg-white transition">
                ›
              </button>
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

            <button type="button" id="bookBtn" disabled
                    class="mt-4 w-full rounded-xl py-2 font-semibold text-white disabled:opacity-40 transition"
                    style="background:var(--primary)">
              Book Appointment
            </button>
          </div>
        </div>
      </div>

    </form>
  </div>
</section>

<!-- CONTACT CARD -->
<section class="px-4 mb-6">
  <div class="max-w-6xl mx-auto bg-white rounded-xl flex items-center gap-4 p-4">
    <div class="p-4 rounded-lg" style="background:var(--accent)">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    </div>
    <p class="text-gray-600">Clinic contact information or short description goes here.</p>
  </div>
</section>

<!-- DOCTORS -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <?php for($i=1;$i<=3;$i++): ?>
        <a href="doctor-profile.php?id=<?php echo urlencode($i); ?>&clinic_id=<?php echo urlencode($_GET['id'] ?? 1); ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
          <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition">
            <div class="flex items-center justify-center py-6">
              <img src="https://cdn-icons-png.flaticon.com/512/387/387561.png" class="w-20" alt="Doctor">
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

<script>
(function () {
  function initBack() {
    const back = document.getElementById("backLink");
    if (!back) return;
    back.addEventListener("click", (e) => {
      try {
        if (document.referrer) {
          const ref = new URL(document.referrer);
          if (ref.origin === location.origin) {
            e.preventDefault();
            history.back();
            return;
          }
        }
      } catch (_) {}
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initBack);
  } else {
    initBack();
  }
  const modal = document.getElementById("bookingModal");
  const openBooking = document.getElementById("openBooking");
  const closeBooking = document.getElementById("closeBooking");
  if (modal && openBooking && closeBooking) {
    openBooking.addEventListener("click", () => modal.classList.remove("hidden"));
    closeBooking.addEventListener("click", () => modal.classList.add("hidden"));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.add("hidden");
    });
  }
  const calendarGrid = document.getElementById("calendarGrid");
  const monthLabel = document.getElementById("monthLabel");
  const selectedDateText = document.getElementById("selectedDateText");
  const slotGrid = document.getElementById("slotGrid");
  const bookBtn = document.getElementById("bookBtn");
  const prevMonthBtn = document.getElementById("prevMonth");
  const nextMonthBtn = document.getElementById("nextMonth");
  if (!calendarGrid || !monthLabel || !selectedDateText || !slotGrid || !bookBtn || !prevMonthBtn || !nextMonthBtn) return;
  let viewDate = new Date();
  let selectedDate = null;
  let selectedSlot = null;
  const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
  function pad2(n){ return String(n).padStart(2, "0"); }
  function toYMD(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }
  function clearSlots(){ slotGrid.innerHTML = ""; selectedSlot = null; bookBtn.disabled = true; }
  function getMockSlots(){ return ["09:00","09:30","10:00","10:30","11:00","13:00","13:30","14:00","14:30","15:00"]; }
  function renderSlots(){ clearSlots(); getMockSlots().forEach((time) => { const b = document.createElement("button"); b.type = "button"; b.textContent = time; b.className = "rounded-lg border px-2 py-1 text-sm font-semibold hover:text-white transition"; b.style.borderColor = "rgba(75,182,245,.45)"; b.style.color = "#0f172a"; b.addEventListener("click", () => { slotGrid.querySelectorAll("button").forEach((x) => { x.classList.remove("text-white"); x.style.background = "transparent"; }); b.classList.add("text-white"); b.style.background = getComputedStyle(document.documentElement).getPropertyValue("--primary"); selectedSlot = time; bookBtn.disabled = false; }); slotGrid.appendChild(b); }); }
  function renderCalendar(){ calendarGrid.innerHTML = ""; clearSlots(); const year = viewDate.getFullYear(); const month = viewDate.getMonth(); monthLabel.textContent = `${monthNames[month]} ${year}`; const firstDay = new Date(year, month, 1); const lastDay  = new Date(year, month + 1, 0); const startWeekday = firstDay.getDay(); const daysInMonth = lastDay.getDate(); for (let i=0;i<startWeekday;i++){ const empty = document.createElement("div"); empty.className = "h-10"; calendarGrid.appendChild(empty); } const today = new Date(); const todayYMD = toYMD(today); for (let day=1; day<=daysInMonth; day++){ const d = new Date(year, month, day); const ymd = toYMD(d); const btn = document.createElement("button"); btn.type = "button"; btn.textContent = day; btn.className = "h-10 rounded-lg font-semibold transition border bg-white/90 hover:bg-white"; btn.style.borderColor = "rgba(255,255,255,.35)"; if (ymd === todayYMD){ btn.style.outline = "2px solid rgba(255,190,138,.9)"; btn.style.outlineOffset = "2px"; } if (selectedDate && ymd === toYMD(selectedDate)){ btn.style.background = "white"; btn.style.borderColor = "rgba(255,190,138,.95)"; } btn.addEventListener("click", () => { selectedDate = d; selectedDateText.textContent = ymd; renderCalendar(); renderSlots(); }); calendarGrid.appendChild(btn); } }
  prevMonthBtn.addEventListener("click", () => { viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1); renderCalendar(); });
  nextMonthBtn.addEventListener("click", () => { viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1); renderCalendar(); });
  bookBtn.addEventListener("click", () => { alert(`Booking (UI only)\nDate: ${selectedDate ? toYMD(selectedDate) : "None"}\nTime: ${selectedSlot || "None"}`); });
  renderCalendar();
})();
</script>
</body>
</html>
