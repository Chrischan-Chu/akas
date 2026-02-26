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
  const viewMonthBtn = document.getElementById("adminViewMonth");
  const viewDayBtn = document.getElementById("adminViewDay");

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

  // doctor overlay: {sun:true,mon:false,...}
  let doctorOverlay = {};

  let currentView = "month"; // "month" or "day"
  let viewDate = new Date();
  let selectedDate = null;
  let monthCounts = {}; // ymd -> {total,pending,approved,cancelled,done}
  let selectedAppointment = null;
  let dayAppointments = []; // appointments for day view

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
    // JS getDay() 0=Sun
    const keys = ["sun","mon","tue","wed","thu","fri","sat"];
    return keys[dateObj.getDay()];
  }

  function badgeClassForDay(counts) {
    // Consistent soft badges
    // Priority: pending -> approved -> done/cancelled
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

  async function apiMonthCounts(ym) {
    const docId = getDoctorId();
    const url = `${BASE_URL}/api/admin_appointments.php?month=${encodeURIComponent(ym)}${docId ? `&doctor_id=${docId}` : ""}`;
    const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Failed to load month counts");
    monthCounts = data.month_counts || {};
    doctorOverlay = data.doctor_overlay || {};
  }

  async function apiDayList(ymd) {
    const docId = getDoctorId();
    const url = `${BASE_URL}/api/admin_appointments.php?date=${encodeURIComponent(ymd)}${docId ? `&doctor_id=${docId}` : ""}`;
    const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Failed to load appointments");
    return Array.isArray(data.appointments) ? data.appointments : [];
  }

  async function safeJson(res) {
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch {
        // show the first part of HTML so you can see what's returned
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


  function openModal(appt) {
    selectedAppointment = appt;
    if (!modal) return;

    mMsg.textContent = "";
    const sub = `${appt.date} • ${to12(appt.time)}`;
    mSub.textContent = sub;

    mPatient.textContent = appt.patient_name || "—";
    const contact = [appt.patient_email, appt.patient_phone].filter(Boolean).join(" • ");
    mContact.textContent = contact || "—";

    mDoctor.textContent = appt.doctor_name || "—";
    mStatus.textContent = (appt.status || "—").toUpperCase();
    mNotes.textContent = appt.notes ? appt.notes : "—";

    // Disable actions if already closed
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

  function renderMonth() {
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();

    monthLabel.textContent = `${monthNames[month]} ${year}`;
    calGrid.innerHTML = "";

    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    const startDow = first.getDay(); // 0 Sun
    const daysInMonth = last.getDate();

    // blanks
    for (let i = 0; i < startDow; i++) {
      const div = document.createElement("div");
      div.className = "h-16 rounded-2xl border border-slate-200 bg-slate-50";
      calGrid.appendChild(div);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(year, month, day);
      const ymd = toYMD(d);
      const counts = monthCounts[ymd] || null;

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className =
        "h-16 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 transition text-left p-2 flex flex-col justify-between";

      // Selected ring
      if (selectedDate && toYMD(selectedDate) === ymd) {
        btn.classList.add("ring-2", "ring-slate-300");
      }

      const top = document.createElement("div");
      top.className = "flex items-start justify-between gap-2";

      const dayNum = document.createElement("div");
      dayNum.className = "text-sm font-extrabold text-slate-800";
      dayNum.textContent = String(day);

      // Availability overlay tag (only when filtering a doctor)
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

      top.appendChild(dayNum);

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
        
        if (currentView === "month") {
          renderMonth();
          listWrap.innerHTML = `<div class="text-sm text-slate-500">Loading appointments…</div>`;
          try {
            const appts = await apiDayList(ymd);
            renderList(appts);
          } catch (e) {
            listWrap.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
          }
        } else {
          // Day view: load appointments and render
          try {
            dayAppointments = await apiDayList(ymd);
            renderDay();
          } catch (e) {
            calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
          }
        }
      });

      calGrid.appendChild(btn);
    }
  }

  function renderList(appts) {
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

    // click handler (event delegation)
    listWrap.querySelectorAll("button[data-appt]").forEach((btn) => {
      btn.addEventListener("click", () => {
        try {
          const appt = JSON.parse(btn.getAttribute("data-appt") || "{}");
          openModal(appt);
        } catch {
          // ignore
        }
      });
    });
  }

  function renderDay() {
    if (!selectedDate) {
      monthLabel.textContent = "Select a date";
      calGrid.innerHTML = `<div class="col-span-7 text-center py-8 text-slate-600">Select a date to view day details</div>`;
      listWrap.innerHTML = "";
      return;
    }

    const ymd = toYMD(selectedDate);
    const dayName = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][selectedDate.getDay()];
    monthLabel.textContent = `${dayName}, ${selectedDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;

    // Render hourly slots (9 AM to 5 PM)
    calGrid.innerHTML = "";
    const startHour = 9;
    const endHour = 17;

    // Create a map of appointments by hour
    const apptsByHour = {};
    for (let h = startHour; h < endHour; h++) {
      apptsByHour[h] = [];
    }
    
    dayAppointments.forEach(appt => {
      const time = appt.time?.substring(0, 5) || "00:00";
      const parts = time.split(":");
      const apptHour = parseInt(parts[0] || "0", 10);
      
      if (apptHour >= startHour && apptHour < endHour) {
        apptsByHour[apptHour].push(appt);
      }
    });

    // Show all hours
    for (let hour = startHour; hour < endHour; hour++) {
      const hourDisplay = to12(`${pad2(hour)}:00`);
      const appts = apptsByHour[hour] || [];

      const slot = document.createElement("div");
      slot.className = "col-span-7 rounded-2xl border border-slate-200 p-4 bg-white hover:shadow-md transition";

      const header = document.createElement("div");
      header.className = "font-extrabold text-slate-900 mb-3 text-sm";
      header.textContent = hourDisplay;
      slot.appendChild(header);

      if (appts.length === 0) {
        const empty = document.createElement("div");
        empty.className = "text-sm text-slate-400";
        empty.textContent = "No appointments";
        slot.appendChild(empty);
      } else {
        const grid = document.createElement("div");
        grid.className = "grid gap-2";

        appts.forEach(appt => {
          const apptDiv = document.createElement("button");
          apptDiv.type = "button";
          apptDiv.className = "text-left rounded-xl border border-slate-200 p-3 bg-slate-50 hover:bg-slate-100 transition text-sm";

          const pill = statusPillClass(appt.status);
          const apptTime = to12(appt.time);

          apptDiv.innerHTML = `
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">${h(apptTime)} - ${h(appt.patient_name || "—")}</div>
                <div class="text-xs text-slate-600 mt-0.5">Dr. ${h(appt.doctor_name || "—")}</div>
              </div>
              <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-extrabold border ${pill}">
                ${h(String(appt.status || "").toUpperCase())}
              </span>
            </div>
          `;

          apptDiv.addEventListener("click", () => {
            openModal(appt);
          });

          grid.appendChild(apptDiv);
        });

        slot.appendChild(grid);
      }

      calGrid.appendChild(slot);
    }

    listWrap.innerHTML = "";
  }

  function updateViewToggleButtons() {
    if (currentView === "month") {
      viewMonthBtn?.classList.add("bg-slate-900", "text-white");
      viewMonthBtn?.classList.remove("bg-white", "text-slate-900");
      viewDayBtn?.classList.remove("bg-slate-900", "text-white");
      viewDayBtn?.classList.add("bg-white", "text-slate-900");
    } else {
      viewDayBtn?.classList.add("bg-slate-900", "text-white");
      viewDayBtn?.classList.remove("bg-white", "text-slate-900");
      viewMonthBtn?.classList.remove("bg-slate-900", "text-white");
      viewMonthBtn?.classList.add("bg-white", "text-slate-900");
    }
  }

  function render() {
    if (currentView === "month") {
      renderMonth();
    } else {
      renderDay();
    }
    updateViewToggleButtons();
  }

  async function refreshAfterAction() {
    // Re-fetch month counts and the selected day list
    await apiMonthCounts(toYM(viewDate));
    
    if (selectedDate) {
      const ymd = toYMD(selectedDate);
      dayAppointments = await apiDayList(ymd);
      
      if (currentView === "month") {
        renderMonth();
      } else {
        renderDay();
      }
    }
  }

  async function boot() {
    selectedText.textContent = selectedDate ? toYMD(selectedDate) : "None";
    
    try {
      await apiMonthCounts(toYM(viewDate));
      
      if (selectedDate) {
        const ymd = toYMD(selectedDate);
        dayAppointments = await apiDayList(ymd);
      } else {
        dayAppointments = [];
      }
      
      render();
    } catch (e) {
      calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
    }
  }

  prevBtn.addEventListener("click", async () => {
    if (currentView === "month") {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      selectedDate = null;
      selectedText.textContent = "None";
      listWrap.innerHTML = `<div class="text-sm text-slate-500">Select a day to view appointments.</div>`;
    } else {
      // Day view: go to previous day
      if (selectedDate) {
        selectedDate = new Date(selectedDate.getTime() - 24 * 60 * 60 * 1000);
        selectedText.textContent = toYMD(selectedDate);
      }
    }
    await boot();
  });

  nextBtn.addEventListener("click", async () => {
    if (currentView === "month") {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      selectedDate = null;
      selectedText.textContent = "None";
      listWrap.innerHTML = `<div class="text-sm text-slate-500">Select a day to view appointments.</div>`;
    } else {
      // Day view: go to next day
      if (selectedDate) {
        selectedDate = new Date(selectedDate.getTime() + 24 * 60 * 60 * 1000);
        selectedText.textContent = toYMD(selectedDate);
      }
    }
    await boot();
  });

  doctorFilter?.addEventListener("change", async () => {
    selectedDate = null;
    selectedText.textContent = "None";
    listWrap.innerHTML = `<div class="text-sm text-slate-500">Select a day to view appointments.</div>`;
    await boot();
  });

  // View toggle events
  viewMonthBtn?.addEventListener("click", async () => {
    currentView = "month";
    selectedDate = null;
    selectedText.textContent = "None";
    listWrap.innerHTML = `<div class="text-sm text-slate-500">Select a day to view appointments.</div>`;
    await boot();
  });

  viewDayBtn?.addEventListener("click", async () => {
    currentView = "day";
    if (!selectedDate) {
      selectedDate = new Date();
    }
    selectedText.textContent = toYMD(selectedDate);
    
    try {
      const ymd = toYMD(selectedDate);
      dayAppointments = await apiDayList(ymd);
      render();
    } catch (e) {
      calGrid.innerHTML = `<div class="text-sm text-rose-600">${h(e.message || e)}</div>`;
    }
  });

  // Modal events
  mClose?.addEventListener("click", closeModal);
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

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
    cSub.textContent = `Selected date: ${selectedDate}`;
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
      const res = await fetch(`${BASE_URL}/api/get_slots.php?clinic_id=${encodeURIComponent(CLINIC_ID)}&doctor_id=${encodeURIComponent(did)}&date=${encodeURIComponent(selectedDate)}`);
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
    } catch (e) {
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
          date: selectedDate,
          time,
          notes
        })
      });
      const data = await safeJson(res);
      if (!res.ok) throw new Error(data.error || 'Failed');
      cMsg.textContent = "Appointment created.";
      await boot();
      closeCreate();
    } catch (e) {
      cMsg.textContent = String(e.message || e);
      cMsg.className = "mt-3 text-xs text-rose-600";
    } finally {
      cSave.disabled = false;
    }
  });

  boot();
})();
