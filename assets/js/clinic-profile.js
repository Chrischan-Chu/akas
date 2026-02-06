(function () {
  const root = document.body;
  const BASE_URL = root?.dataset?.baseUrl || "";
  const IS_USER = root?.dataset?.isUser === "1";


   // -----------------------------
// BOOKING MODAL OPEN/CLOSE
// -----------------------------
const bookingModal = document.getElementById("bookingModal");
const openBooking = document.getElementById("openBooking");
const closeBooking = document.getElementById("closeBooking");

if (bookingModal && openBooking && closeBooking) {

  // OPEN
  openBooking.addEventListener("click", () => {
    bookingModal.classList.remove("hidden");

    // ✅ prevent background scroll
    document.body.style.overflow = "hidden";
  });

  // CLOSE (only X button)
  closeBooking.addEventListener("click", () => {
    bookingModal.classList.add("hidden");

    // ✅ restore scroll
    document.body.style.overflow = "";
  });

  // ❌ no outside click close anymore
}



  // -----------------------------
  // CALENDAR (mock UI)
  // -----------------------------
  const calendarGrid = document.getElementById("calendarGrid");
  const monthLabel = document.getElementById("monthLabel");
  const selectedDateText = document.getElementById("selectedDateText");
  const slotGrid = document.getElementById("slotGrid");
  const bookBtn = document.getElementById("bookBtn");
  const prevMonthBtn = document.getElementById("prevMonth");
  const nextMonthBtn = document.getElementById("nextMonth");

  if (calendarGrid && monthLabel && selectedDateText && slotGrid && bookBtn && prevMonthBtn && nextMonthBtn) {
    let viewDate = new Date();
    let selectedDate = null;
    let selectedSlot = null;

    const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    const pad2 = (n) => String(n).padStart(2, "0");
    const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;

    function clearSlots(){
      slotGrid.innerHTML = "";
      selectedSlot = null;
      if (IS_USER) bookBtn.disabled = true;
    }

    function getMockSlots(){
      return ["09:00","09:30","10:00","10:30","11:00","13:00","13:30","14:00","14:30","15:00"];
    }

    function renderSlots(){
      clearSlots();

      getMockSlots().forEach((time) => {
        const b = document.createElement("button");
        b.type = "button";
        b.textContent = time;
        b.className = "rounded-lg border px-2 py-1 text-sm font-semibold hover:text-white transition";
        b.style.borderColor = "rgba(75,182,245,.45)";
        b.style.color = "#0f172a";

        b.addEventListener("click", () => {
          slotGrid.querySelectorAll("button").forEach((x) => {
            x.classList.remove("text-white");
            x.style.background = "transparent";
          });

          b.classList.add("text-white");
          b.style.background = getComputedStyle(document.documentElement).getPropertyValue("--primary");

          selectedSlot = time;
          if (IS_USER) bookBtn.disabled = false;
        });

        slotGrid.appendChild(b);
      });
    }

    function renderCalendar(){
      calendarGrid.innerHTML = "";
      clearSlots();

      const year = viewDate.getFullYear();
      const month = viewDate.getMonth();
      monthLabel.textContent = `${monthNames[month]} ${year}`;

      const firstDay = new Date(year, month, 1);
      const lastDay  = new Date(year, month + 1, 0);
      const startWeekday = firstDay.getDay();
      const daysInMonth = lastDay.getDate();

      for (let i=0;i<startWeekday;i++){
        const empty = document.createElement("div");
        empty.className = "h-10";
        calendarGrid.appendChild(empty);
      }

      const todayYMD = toYMD(new Date());

      for (let day=1; day<=daysInMonth; day++){
        const d = new Date(year, month, day);
        const ymd = toYMD(d);

        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = day;
        btn.className = "h-10 rounded-lg font-semibold transition border bg-white/90 hover:bg-white";
        btn.style.borderColor = "rgba(255,255,255,.35)";

        if (ymd === todayYMD){
          btn.style.outline = "2px solid rgba(255,190,138,.9)";
          btn.style.outlineOffset = "2px";
        }

        if (selectedDate && ymd === toYMD(selectedDate)){
          btn.style.background = "white";
          btn.style.borderColor = "rgba(255,190,138,.95)";
        }

        btn.addEventListener("click", () => {
          selectedDate = d;
          selectedDateText.textContent = ymd;
          renderCalendar();
          renderSlots();
        });

        calendarGrid.appendChild(btn);
      }
    }

    prevMonthBtn.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      renderCalendar();
    });

    bookBtn.addEventListener("click", () => {
      if (!IS_USER) {
        // ✅ Redirect to login WITHOUT localhost issues (relative path)
        window.location.href = `${BASE_URL}/pages/login.php`;
        return;
      }

      if (!selectedDate || !selectedSlot) {
        alert("Please select a date and time slot first.");
        return;
      }

      alert(`Booking (UI only)\nDate: ${toYMD(selectedDate)}\nTime: ${selectedSlot}`);
    });

    renderCalendar();
  }

  // -----------------------------
  // DOCTOR MODAL (this is the fix)
  // -----------------------------
  const doctorModal = document.getElementById("doctorModal");
  const doctorBody  = document.getElementById("doctorModalBody");
  const closeDoctorBtn = document.getElementById("closeDoctorModal");
  const doctorBackdrop = document.getElementById("doctorBackdrop");

  function openDoctorModal() {
    if (!doctorModal) return;
    doctorModal.classList.remove("hidden");
    doctorModal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeDoctorModal() {
    if (!doctorModal) return;
    doctorModal.classList.add("hidden");
    doctorModal.setAttribute("aria-hidden", "true");
    if (doctorBody) doctorBody.innerHTML = '<div class="text-slate-600 text-sm">Loading…</div>';
    document.body.style.overflow = "";
  }

  closeDoctorBtn?.addEventListener("click", closeDoctorModal);
  doctorBackdrop?.addEventListener("click", closeDoctorModal);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && doctorModal && !doctorModal.classList.contains("hidden")) closeDoctorModal();
  });

  // ✅ IMPORTANT: Intercept clicks on doctor cards
  document.addEventListener("click", async (e) => {
    const card = e.target.closest(".doctorCard");
    if (!card) return;

    // Stop the <a href="doctor-profile.php..."> from navigating away
    e.preventDefault();

    const doctorId = card.dataset.doctorId;
    const clinicId = card.dataset.clinicId || "";

    if (!doctorId) return;

    openDoctorModal();

    try {
      // Fetch the modal partial (doctor-modal.php)
      const url = `${BASE_URL}/pages/doctor-modal.php?id=${encodeURIComponent(doctorId)}&clinic_id=${encodeURIComponent(clinicId)}`;
      const res = await fetch(url, { cache: "no-store" });
      if (!res.ok) throw new Error("Failed to load doctor profile");

      if (doctorBody) doctorBody.innerHTML = await res.text();
    } catch (err) {
      console.error(err);
      if (doctorBody) {
        doctorBody.innerHTML = `
          <div class="rounded-2xl bg-slate-50 border border-slate-200 p-6">
            <p class="font-extrabold text-slate-900">Couldn’t load doctor profile.</p>
            <p class="text-sm text-slate-600 mt-1">Please try again.</p>
          </div>
        `;
      }
    }
  });
})();
