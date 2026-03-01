(function () {
  const root = document.body;
  const BASE_URL = root?.dataset?.baseUrl || "";
  const CLINIC_ID = parseInt(root?.dataset?.clinicId || "0", 10);

  const calGrid = document.getElementById("adminCalendarGrid");
  const monthLabel = document.getElementById("adminMonthLabel");
  const prevBtn = document.getElementById("adminPrevMonth");
  const nextBtn = document.getElementById("adminNextMonth");
  const selectedText = document.getElementById("adminSelectedDateText");
  const listWrap = document.getElementById("adminApptList");
  const doctorFilter = document.getElementById("adminDoctorFilter");

  const dayBtn = document.getElementById("adminDayBtn");
  const monthBtn = document.getElementById("adminMonthBtn");

  const todayBtn = document.getElementById("adminTodayBtn");
  const datePicker = document.getElementById("adminDatePicker");
  
  // Layout wrapping elements
  const weekdaysRow = document.getElementById("adminWeekdaysRow");
  const rightSidebar = listWrap?.closest("aside");
  const scheduleGridWrap = rightSidebar?.parentElement;

  // list of approved doctors (id,name) injected from PHP
  const DOCTORS = window.AKAS_DOCTORS || [];

  // Modal refs
  const modal = document.getElementById("adminApptModal");
  const mClose = document.getElementById("admModalClose");
  const mSub = document.getElementById("admModalSub");
  const mPatient = document.getElementById("admPatient");
  const mContact = document.getElementById("admContact");
  const mDoctor = document.getElementById("admDoctor");
  const mStatus = document.getElementById("admStatus");
  const mNotes = document.getElementById("admNotes");
  const mCancel = document.getElementById("admCancelBtn");
  const mDone = document.getElementById("admDoneBtn");
  const mMsg = document.getElementById("admModalMsg");

  if (!CLINIC_ID) return;
  if (!calGrid || !monthLabel || !prevBtn || !nextBtn || !selectedText || !listWrap) return;

  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];

  const pad2 = (n) => String(n).padStart(2, "0");
  const toYM = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}`;
  const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;

  function to12(timeHHMM) {
    const t = String(timeHHMM || "").trim();
    if (!t) return "";
    const parts = t.split(":");
    const hh = parseInt(parts[0] || "0", 10);
    const mm = parts[1] || "00";
    const ampm = hh >= 12 ? "PM" : "AM";
    const h12 = ((hh + 11) % 12) + 1;
    return `${h12}:${mm} ${ampm}`;
  }

  function h(s) {
    return String(s ?? "").replace(/[&<>"']/g, (m) => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[m]));
  }

  function getDoctorId() {
    const v = parseInt(doctorFilter?.value || "0", 10);
    return Number.isFinite(v) ? v : 0;
  }

  function dowKey(dateObj) {
    const keys = ["sun","mon","tue","wed","thu","fri","sat"];
    return keys[dateObj.getDay()];
  }

  function badgeClassForDay(counts) {
    if (!counts || counts.total <= 0) {
      return "border-slate-200 bg-slate-50 text-slate-600";
    }
    if ((counts.pending || 0) > 0) {
      return "border-amber-200 bg-amber-50 text-amber-700";
    }
    if ((counts.approved || 0) > 0) {
      return "border-emerald-200 bg-emerald-50 text-emerald-700";
    }
    if ((counts.done || 0) > 0) {
      return "border-sky-200 bg-sky-50 text-sky-700";
    }
    if ((counts.cancelled || 0) > 0) {
      return "border-rose-200 bg-rose-50 text-rose-700";
    }
    return "border-slate-200 bg-slate-100 text-slate-700";
  }

  function statusPillClass(st) {
    st = String(st || "").toUpperCase();
    if (st === "PENDING") return "border-amber-200 bg-amber-50 text-amber-700";
    if (st === "APPROVED") return "border-emerald-200 bg-emerald-50 text-emerald-700";
    if (st === "DONE") return "border-sky-200 bg-sky-50 text-sky-700";
    if (st === "CANCELLED") return "border-rose-200 bg-rose-50 text-rose-700";
    return "border-slate-200 bg-slate-50 text-slate-700";
  }

  function applyMonthLayout() {
    calGrid.classList.remove("overflow-auto", "overflow-x-auto");
    calGrid.classList.add("grid", "grid-cols-7", "gap-2");
    weekdaysRow?.classList.remove("hidden");
    
    // Show the sidebar and restore the 2-column layout
    rightSidebar?.classList.remove("hidden");
    scheduleGridWrap?.classList.add("xl:grid-cols-[1fr_360px]");
  }

  function applyDayLayout() {
    calGrid.classList.remove("grid", "grid-cols-7", "gap-2");
    calGrid.classList.add("overflow-x-auto");
    weekdaysRow?.classList.add("hidden");
    
    // Hide the sidebar and let the calendar stretch to full width
    rightSidebar?.classList.add("hidden");
    scheduleGridWrap?.classList.remove("xl:grid-cols-[1fr_360px]");
  }

  function setActiveToggle() {
    document.querySelectorAll(".view-toggle").forEach(b => b.classList.remove("is-active"));
    if (currentView === "month") monthBtn?.classList.add("is-active");
    else dayBtn?.classList.add("is-active");
  }

  // ==========================
  // State + caches (less lag)
  // ==========================
  let currentView = "month"; // "month" or "day"
  let viewDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
  let selectedDate = null; // Date|null

  let monthCounts = {};
  let doctorOverlay = {};
  let dayAppointments = [];
  let selectedAppointment = null;

  const monthCache = new Map(); // key: `${ym}|docId`
  const dayCache = new Map();  // key: `${ymd}|docId`

  async function apiMonthCounts(ym) {
    const docId = getDoctorId();
    const key = `${ym}|${docId}`;
    if (monthCache.has(key)) {
      const cached = monthCache.get(key);
      monthCounts = cached.monthCounts || {};
      doctorOverlay = cached.doctorOverlay || {};
      return;
    }

    const url = `${BASE_URL}/api/admin_appointments.php?month=${encodeURIComponent(ym)}${docId ? `&doctor_id=${docId}` : ""}`;
    const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Failed to load month counts");

    monthCounts = data.month_counts || {};
    doctorOverlay = data.doctor_overlay || {};
    monthCache.set(key, { monthCounts, doctorOverlay });
  }

  async function apiDayList(ymd) {
    const docId = getDoctorId();
    const key = `${ymd}|${docId}`;
    if (dayCache.has(key)) return dayCache.get(key);

    const url = `${BASE_URL}/api/admin_appointments.php?date=${encodeURIComponent(ymd)}${docId ? `&doctor_id=${docId}` : ""}`;
    const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Failed to load appointments");

    const appts = Array.isArray(data.appointments) ? data.appointments : [];
    dayCache.set(key, appts);
    return appts;
  }

  async function safeJson(res) {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      throw new Error("Server returned HTML (not JSON): " + text.slice(0, 120));
    }
  }

  async function apiAction(appointmentId, action) {
    const res = await fetch(`${BASE_URL}/api/admin_appointment_action.php`, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ appointment_id: appointmentId, action })
    });

    const data = await safeJson(res);
    if (!data.ok) throw new Error(data.message || "Action failed");
    return data;
  }

  // ==========================
  // Modal
  // ==========================
  function openModal(appt) {
    selectedAppointment = appt;
    if (!modal) return;

    mMsg.textContent = "";
    mSub.textContent = `${appt.date} • ${to12(appt.time)}`;

    mPatient.textContent = appt.patient_name || "—";
    mContact.textContent = [appt.patient_email, appt.patient_phone].filter(Boolean).join(" • ") || "—";
    mDoctor.textContent = appt.doctor_name || "—";
    mStatus.textContent = (appt.status || "—").toUpperCase();
    mNotes.textContent = appt.notes ? appt.notes : "—";

    const closed = ["DONE","CANCELLED"].includes(String(appt.status || "").toUpperCase());
    mCancel.disabled = closed;
    mDone.disabled = closed;
    mCancel.classList.toggle("opacity-50", closed);
    mDone.classList.toggle("opacity-50", closed);

    modal.classList.remove("hidden");
    modal.classList.add("flex");
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    selectedAppointment = null;
  }

  // ==========================
  // Rendering
  // ==========================
  function renderList(appts) {
    listWrap.innerHTML = ""; 

    if (!appts.length) {
      listWrap.innerHTML = `<div class="text-sm text-slate-500">No appointments for this day.</div>`;
      return;
    }

    const rows = appts.map((a) => {
      const pill = statusPillClass(a.status);
      return `
        <button type="button"
                class="text-left rounded-3xl border border-slate-200 p-5 bg-white hover:bg-slate-50 hover:shadow-sm transition"
                data-appt='${h(JSON.stringify(a))}'>
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-extrabold text-slate-900">
                ${h(to12(a.time))} — ${h(a.patient_name)}
              </div>
              <div class="text-sm text-slate-600 mt-1">
                Doctor: <span class="font-semibold">${h(a.doctor_name)}</span>
              </div>
              ${a.notes ? `<div class="text-sm text-slate-700 mt-2"><span class="font-semibold">Notes:</span> ${h(a.notes)}</div>` : ""}
            </div>
            <span class="shrink-0 inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold border ${pill}">
              ${h(String(a.status || "").toUpperCase())}
            </span>
          </div>
        </button>
      `;
    }).join("");

    listWrap.innerHTML = `<div class="grid gap-3">${rows}</div>`;

    listWrap.querySelectorAll("button[data-appt]").forEach((btn) => {
      btn.addEventListener("click", () => {
        try {
          const appt = JSON.parse(btn.getAttribute("data-appt") || "{}");
          openModal(appt);
        } catch {}
      });
    });
  }

  // -----------------------------
  // ABLY REALTIME (signal -> refresh slots)
  // -----------------------------
  function initRealtime() {
    if (!CLINIC_ID) return;
    if (typeof Ably === "undefined" || !Ably?.Realtime) {
      console.warn("Ably JS not loaded. Realtime disabled.");
      return;
    }

    const ably = new Ably.Realtime({
      authUrl: `${BASE_URL}/api/ably_token.php`,
      authMethod: "POST",
    });

    const channel = ably.channels.get(`clinic-${CLINIC_ID}`);
    
    // Use "slots-updated" to match the PHP trigger
    channel.subscribe("slots-updated", (message) => {
      console.log("Real-time update received!");
      
      const updatedDate = message?.data?.date;
      
      if (selectedDate && updatedDate === toYMD(selectedDate)) {
          console.log("Refreshing current day slots...");
          renderSlots(); 
      } else {
          renderCalendar(); 
      }
    });
  }

  initRealtime();

  function renderMonth() {
    applyMonthLayout();

    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();

    monthLabel.textContent = `${monthNames[month]} ${year}`;
    calGrid.innerHTML = "";

    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    const startDow = first.getDay();
    const daysInMonth = last.getDate();

    for (let i = 0; i < startDow; i++) {
      const div = document.createElement("div");
      div.className = "h-20 rounded-2xl border border-slate-200 bg-slate-50";
      calGrid.appendChild(div);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(year, month, day);
      const ymd = toYMD(d);
      const counts = monthCounts[ymd] || null;

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "cal-cell h-20 p-3 flex flex-col justify-between text-left";

      if (selectedDate && toYMD(selectedDate) === ymd) btn.classList.add("is-selected");

      const top = document.createElement("div");
      top.className = "flex items-start justify-between gap-2";

      const dayNum = document.createElement("div");
      dayNum.className = "text-sm font-extrabold text-slate-900";
      dayNum.textContent = String(day);

      top.appendChild(dayNum);

      const docId = getDoctorId();
      if (docId > 0) {
        const k = dowKey(d);
        const isOn = !!doctorOverlay?.[k];
        const tag = document.createElement("span");
        tag.className =
          "inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-extrabold border " +
          (isOn ? "border-emerald-200 bg-emerald-50 text-emerald-700" : "border-slate-200 bg-slate-50 text-slate-500");
        tag.textContent = isOn ? "Available" : "Off";
        top.appendChild(tag);
      }

      const bottom = document.createElement("div");
      bottom.className = "flex items-center justify-between gap-2";

      if (counts && counts.total > 0) {
        const badge = document.createElement("span");
        badge.className =
          "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-extrabold border " +
          badgeClassForDay(counts);
        badge.textContent = `${counts.total} appt`;
        bottom.appendChild(badge);

        const hint = document.createElement("span");
        hint.className = "text-[11px] text-slate-500";
        if (counts.pending > 0) hint.textContent = `${counts.pending} pending`;
        else if (counts.approved > 0) hint.textContent = `${counts.approved} approved`;
        else if (counts.done > 0) hint.textContent = `${counts.done} done`;
        else if (counts.cancelled > 0) hint.textContent = `${counts.cancelled} cancelled`;
        bottom.appendChild(hint);
      } else {
        const empty = document.createElement("span");
        empty.className = "text-[11px] text-slate-400";
        empty.textContent = "—";
        bottom.appendChild(empty);
      }

      btn.appendChild(top);
      btn.appendChild(bottom);

      btn.addEventListener("click", async () => {
        selectedDate = d;
        selectedText.textContent = ymd;
        if (datePicker) datePicker.value = ymd;

        // Month view: just load list on the right
        if (currentView === "month") {
          renderMonth();
          listWrap.innerHTML = `<div class="text-sm text-slate-500">Loading appointments…</div>`;
          try {
            const appts = await apiDayList(ymd);
            renderList(appts);
          } catch (e) {
            listWrap.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
          }
          return;
        }

        // Day view: load and render the day table
        calGrid.innerHTML = `<div class="text-sm text-slate-500">Loading day schedule…</div>`;
        try {
          dayAppointments = await apiDayList(ymd);
          renderDay();
        } catch (e) {
          calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
        }
      });

      calGrid.appendChild(btn);
    }
  }

  function renderDay() {
    applyDayLayout();

    if (!selectedDate) {
      monthLabel.textContent = "Select a date";
      calGrid.innerHTML = `<div class="text-sm text-slate-600 py-6">Select a date to view day schedule.</div>`;
      return;
    }

    const ymd = toYMD(selectedDate);
    const dayName = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"][selectedDate.getDay()];
    monthLabel.textContent =
      `${dayName}, ${selectedDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;

    // Generate time slots (8:00 AM to 6:00 PM)
    const startHour = 8;
    const endHour = 18;
    const slots = [];
    for (let h = startHour; h < endHour; h++) {
      slots.push(`${pad2(h)}:00`);
      slots.push(`${pad2(h)}:30`);
    }
    slots.push(`${pad2(endHour)}:00`);

    // 1. Get filtered list of doctors
    let docs = DOCTORS.map(d => ({ ...d, appts: [] }));
    const filterId = getDoctorId();
    if (filterId) docs = docs.filter(d => Number(d.id) === filterId);

    // 2. Prepare the lookup map
    const byDoctor = new Map();
    docs.forEach(d => byDoctor.set(String(d.id).trim(), new Map()));

    // 3. Map appointments
    dayAppointments.forEach(appt => {
      let did = String(appt.doctor_id || "").trim();
      let m = byDoctor.get(did);
      
      if (!m) {
        const docByName = docs.find(d => String(d.name).trim().toLowerCase() === String(appt.doctor_name || "").trim().toLowerCase());
        if (docByName) m = byDoctor.get(String(docByName.id).trim());
      }
      if (!m) return; 

      let t = String(appt.time || "").trim().toUpperCase();
      if (!t) return;
      
      let isPM = t.includes("PM");
      let isAM = t.includes("AM");
      t = t.replace(/[^\d:]/g, ""); 
      let parts = t.split(':');
      let hh = parseInt(parts[0] || "0", 10);
      let mm = parseInt(parts[1] || "0", 10);
      
      if (isPM && hh < 12) hh += 12;
      if (isAM && hh === 12) hh = 0;
      
      const slotMm = mm >= 30 ? "30" : "00";
      const normalizedTime = `${String(hh).padStart(2, '0')}:${slotMm}`;

      if (!m.has(normalizedTime)) m.set(normalizedTime, []);
      m.get(normalizedTime).push(appt);
    });

    // 4. Build the Redesigned Vertical HTML Grid
    let html = '<div class="max-h-[600px] overflow-auto rounded-2xl border border-slate-200 bg-white shadow-sm custom-scrollbar">';
    html += '<table class="w-full text-left border-collapse min-w-[600px]">'; 
    
    // Table Header (Doctors as Columns)
    html += '<thead class="bg-slate-50 sticky top-0 z-30 shadow-sm ring-1 ring-slate-200">';
    html += '<tr>';
    // Top-left sticky corner
    html += '<th class="w-28 border-r border-slate-200 p-4 font-extrabold text-slate-500 text-xs uppercase sticky left-0 top-0 bg-slate-100 z-40">Time</th>';
    
    docs.forEach(doc => {
      html += `<th class="min-w-[220px] p-4 font-extrabold text-slate-800 text-sm bg-slate-50 border-r border-slate-200">${h(doc.name)}</th>`;
    });
    html += '</tr></thead>';

    // Table Body (Time slots as Rows)
    html += '<tbody class="divide-y divide-slate-100">';
    slots.forEach(slot => {
      // Added h-12 to the TR to force every single row to be exactly the same height
      html += '<tr class="group hover:bg-slate-50/50 transition-colors h-12">';
      
      // Sticky Time Column
      html += `<td class="border-r border-slate-100 p-2 align-top sticky left-0 bg-white group-hover:bg-slate-50 z-20 shadow-[2px_0_5px_rgba(0,0,0,0.02)] w-24">
                  <span class="text-xs font-bold text-slate-500 block mt-0.5">${h(to12(slot))}</span>
               </td>`;
      
      // Appointment Cells
      docs.forEach(doc => {
        const docIdStr = String(doc.id).trim();
        const m = byDoctor.get(docIdStr);
        const apptsInSlot = m ? (m.get(slot) || []) : [];
        
        // Changed padding to p-1, removed height constraints from the cell
        html += `<td class="p-1 align-top border-r border-slate-100 relative">`;
        
        if (apptsInSlot.length > 0) {
          html += `<div class="flex flex-col gap-1">`;
          apptsInSlot.forEach(appt => {
            const pill = statusPillClass(appt.status);
            // Ultra-compact button
            html += `
              <button type="button" data-appt-id="${h(appt.id)}"
                class="w-full text-left rounded border ${pill} px-2 py-0.5 hover:shadow-md transition-all overflow-hidden">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-[11px] truncate pr-1">${h(appt.patient_name || "—")}</span>
                  <span class="text-[9px] font-bold opacity-60 shrink-0">#${appt.id}</span>
                </div>
                <div class="text-[8px] uppercase tracking-wider font-extrabold opacity-80 leading-tight mt-0.5">${h(String(appt.status || ""))}</div>
              </button>`;
          });
          html += `</div>`;
        }
        
        html += `</td>`;
      });
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    calGrid.innerHTML = html;

    // Attach click listeners
    calGrid.querySelectorAll('button[data-appt-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-appt-id');
        const appt = dayAppointments.find(a => String(a.id) === String(id));
        if (appt) openModal(appt);
      });
    });
  }

  async function bootMonth() {
    const ym = toYM(viewDate);
    calGrid.innerHTML = `<div class="text-sm text-slate-500">Loading calendar…</div>`;
    try {
      await apiMonthCounts(ym);
      renderMonth();
    } catch (e) {
      calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
    }
  }

  async function bootDay() {
    if (!selectedDate) selectedDate = new Date();
    const ymd = toYMD(selectedDate);

    selectedText.textContent = ymd;
    if (datePicker) datePicker.value = ymd;

    calGrid.innerHTML = `<div class="text-sm text-slate-500">Loading day schedule…</div>`;
    try {
      dayAppointments = await apiDayList(ymd);
      renderDay();
    } catch (e) {
      calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
    }
  }

  function invalidateCaches() {
    monthCache.clear();
    dayCache.clear();
  }

  // ==========================
  // Events
  // ==========================
  prevBtn.addEventListener("click", async () => {
    if (currentView === "month") {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      await bootMonth();
      return;
    }

    // Day: previous day
    if (!selectedDate) selectedDate = new Date();
    selectedDate = new Date(selectedDate.getTime() - 24 * 60 * 60 * 1000);
    await bootDay();
  });

  nextBtn.addEventListener("click", async () => {
    if (currentView === "month") {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      await bootMonth();
      return;
    }

    // Day: next day
    if (!selectedDate) selectedDate = new Date();
    selectedDate = new Date(selectedDate.getTime() + 24 * 60 * 60 * 1000);
    await bootDay();
  });

  monthBtn?.addEventListener("click", async (e) => {
    e.preventDefault();
    currentView = "month";
    setActiveToggle();
    await bootMonth();
  });

  dayBtn?.addEventListener("click", async (e) => {
    e.preventDefault();
    currentView = "day";
    setActiveToggle();
    await bootDay();
  });

  todayBtn?.addEventListener("click", async (e) => {
    e.preventDefault();
    selectedDate = new Date();
    viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);

    if (currentView === "month") {
      await bootMonth();
      // also load list for today (nice)
      const ymd = toYMD(selectedDate);
      selectedText.textContent = ymd;
      if (datePicker) datePicker.value = ymd;
      listWrap.innerHTML = `<div class="text-sm text-slate-500">Loading appointments…</div>`;
      apiDayList(ymd).then(renderList).catch(err => listWrap.innerHTML = `<div class="text-sm text-rose-600">${h(err.message || err)}</div>`);
      return;
    }

    await bootDay();
  });

  datePicker?.addEventListener("change", async () => {
    const v = String(datePicker.value || "").trim();
    if (!v) return;

    // v is YYYY-MM-DD
    const parts = v.split("-");
    if (parts.length !== 3) return;
    const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    if (Number.isNaN(d.getTime())) return;

    selectedDate = d;
    viewDate = new Date(d.getFullYear(), d.getMonth(), 1);
    selectedText.textContent = toYMD(d);

    if (currentView === "month") {
      await bootMonth();
      listWrap.innerHTML = `<div class="text-sm text-slate-500">Loading appointments…</div>`;
      apiDayList(toYMD(d)).then(renderList).catch(err => listWrap.innerHTML = `<div class="text-sm text-rose-600">${h(err.message || err)}</div>`);
      return;
    }

    await bootDay();
  });

  doctorFilter?.addEventListener("change", async () => {
    invalidateCaches();
    selectedDate = null;
    selectedText.textContent = "None";
    listWrap.innerHTML = `<div class="text-sm text-slate-500">Select a day to view appointments.</div>`;

    if (datePicker) datePicker.value = "";

    if (currentView === "month") await bootMonth();
    else await bootDay();
  });

  // Modal events
  mClose?.addEventListener("click", closeModal);
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  async function refreshAfterAction() {
    invalidateCaches();

    // refresh month grid
    await apiMonthCounts(toYM(viewDate));
    if (currentView === "month") renderMonth();

    // refresh selected day list/table
    if (selectedDate) {
      const ymd = toYMD(selectedDate);
      dayAppointments = await apiDayList(ymd);
      if (currentView === "day") renderDay();
      else renderList(dayAppointments);
    }
  }

  mCancel?.addEventListener("click", async () => {
    if (!selectedAppointment) return;
    mMsg.textContent = "Processing…";
    try {
      await apiAction(parseInt(selectedAppointment.id, 10), "CANCELLED");
      mMsg.textContent = "Appointment cancelled.";
      await refreshAfterAction();
      closeModal();
    } catch (e) {
      mMsg.textContent = String(e.message || e);
      mMsg.className = "mt-3 text-xs text-rose-600";
    }
  });

  mDone?.addEventListener("click", async () => {
    if (!selectedAppointment) return;
    mMsg.textContent = "Processing…";
    try {
      await apiAction(parseInt(selectedAppointment.id, 10), "DONE");
      mMsg.textContent = "Marked as done.";
      await refreshAfterAction();
      closeModal();
    } catch (e) {
      mMsg.textContent = String(e.message || e);
      mMsg.className = "mt-3 text-xs text-rose-600";
    }
  });

  // ==============================
  // Create appointment (follow-up / reschedule)
  // ==============================
  const createBtn = document.getElementById("adminCreateBtn");
  const cModal = document.getElementById("adminCreateModal");
  const cClose = document.getElementById("admCreateClose");
  const cSub = document.getElementById("admCreateSub");
  const cEmail = document.getElementById("admCreateEmail");
  const cDoctor = document.getElementById("admCreateDoctor");
  const cTime = document.getElementById("admCreateTime");
  const cNotes = document.getElementById("admCreateNotes");
  const cSave = document.getElementById("admCreateSave");
  const cMsg = document.getElementById("admCreateMsg");

  function openCreate() {
    if (!cModal) return;
    if (!selectedDate) {
      alert("Select a date first.");
      return;
    }
    cSub.textContent = `Selected date: ${toYMD(selectedDate)}`;
    cMsg.textContent = "";
    cModal.classList.remove("hidden");
    cModal.classList.add("flex");
    loadCreateSlots();
  }

  function closeCreate() {
    if (!cModal) return;
    cModal.classList.add("hidden");
    cModal.classList.remove("flex");
  }

  async function loadCreateSlots() {
    if (!cDoctor || !cTime) return;
    cTime.innerHTML = '<option value="">Select time</option>';
    const did = parseInt(cDoctor.value || "0", 10);
    if (!did || !selectedDate) return;

    try {
      const res = await fetch(`${BASE_URL}/api/get_slots.php?clinic_id=${encodeURIComponent(CLINIC_ID)}&doctor_id=${encodeURIComponent(did)}&date=${encodeURIComponent(toYMD(selectedDate))}`);
      const data = await safeJson(res);
      if (!res.ok) throw new Error(data.error || "Failed to load slots");

      const available = (data.slots || []).filter(s => s.status === 'AVAILABLE');
      if (available.length === 0) {
        cTime.innerHTML = '<option value="">No available slots</option>';
        return;
      }
      for (const s of available) {
        const opt = document.createElement('option');
        opt.value = s.time;
        opt.textContent = to12(s.time);
        cTime.appendChild(opt);
      }
    } catch {
      cTime.innerHTML = '<option value="">Failed to load</option>';
    }
  }

  createBtn?.addEventListener("click", openCreate);
  cClose?.addEventListener("click", closeCreate);
  cModal?.addEventListener("click", (e) => { if (e.target === cModal) closeCreate(); });
  cDoctor?.addEventListener("change", loadCreateSlots);

  cSave?.addEventListener("click", async () => {
    const email = String(cEmail?.value || "").trim();
    const did = parseInt(cDoctor?.value || "0", 10);
    const time = String(cTime?.value || "").trim();
    const notes = String(cNotes?.value || "").trim();
    if (!selectedDate) return;

    if (!email) return alert("Patient email is required");
    if (!did) return alert("Select a doctor");
    if (!time) return alert("Select a time");

    cMsg.textContent = "Saving…";
    cMsg.className = "mt-3 text-xs text-slate-500";
    cSave.disabled = true;

    try {
      const res = await fetch(`${BASE_URL}/api/admin_create_appointment.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          patient_email: email,
          doctor_id: did,
          date: toYMD(selectedDate),
          time,
          notes
        })
      });
      const data = await safeJson(res);
      if (!res.ok) throw new Error(data.error || 'Failed');

      cMsg.textContent = "Appointment created.";
      invalidateCaches();
      await bootMonth();
      closeCreate();
    } catch (e) {
      cMsg.textContent = String(e.message || e);
      cMsg.className = "mt-3 text-xs text-rose-600";
    } finally {
      cSave.disabled = false;
    }
  });

  // ==========================
  // Start
  // ==========================
  setActiveToggle();
  bootMonth();
})();