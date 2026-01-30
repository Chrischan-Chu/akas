
// assets/js/clinic-profile.js
// Handles booking modal and calendar UI for the Clinic Profile page
(function () {
  // Back button: keeps scroll position if coming from same site
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
  // Modal logic
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
  // Calendar UI
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
