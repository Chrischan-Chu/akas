(() => {
  const root = document.body;
  const BASE_URL = root?.dataset?.baseUrl || "";
  const IS_USER = root?.dataset?.isUser === "1";
  const CLINIC_ID = Number.parseInt(root?.dataset?.clinicId || "0", 10) || 0;

  const $ = (id) => document.getElementById(id);

  const pad2 = (n) => String(n).padStart(2, "0");
  const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;

  function setBodyScrollLock(locked) {
    document.body.style.overflow = locked ? "hidden" : "";
  }

  async function safeJson(res) {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      throw new Error(`Server returned HTML (not JSON): ${text.slice(0, 120)}`);
    }
  }

  function to12FromHHMM(t) {
    // "13:30" -> "1:30 PM"
    if (!t) return "";
    const m = String(t).trim().match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return t; // fallback if not HH:MM
    let hh = Number(m[1]);
    const mm = m[2];
    const ampm = hh >= 12 ? "PM" : "AM";
    hh = hh % 12;
    if (hh === 0) hh = 12;
    return `${hh}:${mm} ${ampm}`;
  }

    function fmtPHTime(ts) {
    // accepts "YYYY-MM-DD HH:MM:SS" OR "HH:MM"
    if (!ts) return "";

    // if it's just HH:MM, convert directly
    if (/^\d{1,2}:\d{2}$/.test(ts)) return to12FromHHMM(ts);

    // try date-time
    const d = new Date(String(ts).replace(" ", "T") + "+08:00");
    if (Number.isNaN(d.getTime())) return ts;

    return d.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
  }



  

  // -----------------------------
  // BOOKING MODAL OPEN/CLOSE
  // -----------------------------
  const bookingModal = $("bookingModal");
  const openBooking = $("openBooking");
  const closeBooking = $("closeBooking");
  const closeBookingAlt = $("closeBookingAlt");

  function openBookingModal() {
    if (!bookingModal) return;
    bookingModal.classList.remove("hidden");
    setBodyScrollLock(true);
    const mobileMenu = document.getElementById("mobileMenu");
    mobileMenu?.classList.add("hidden");
  }
  function closeBookingModal() {
    if (!bookingModal) return;
    bookingModal.classList.add("hidden");
    setBodyScrollLock(false);
  }

  openBooking?.addEventListener("click", openBookingModal);
  closeBooking?.addEventListener("click", closeBookingModal);
  closeBookingAlt?.addEventListener("click", closeBookingModal);


  // -----------------------------
  // CALENDAR + SLOTS
  // -----------------------------
  const calendarGrid = $("calendarGrid");
  const monthLabel = $("monthLabel");
  const selectedDateText = $("selectedDateText");
  const slotGrid = $("slotGrid");
  const bookBtn = $("bookBtn");
  const prevMonthBtn = $("prevMonth");
  const nextMonthBtn = $("nextMonth");

  // global refresh hook for Ably
  window.__refreshSlotsIfSelected = null;

  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];

  function statusLabel(status) {
    switch (status) {
      case "AVAILABLE": return "Available";
      case "PAST": return "Past";
      case "NOT_YET_OPEN": return "Not yet open";
      case "BLOCKED": return "Blocked";
      case "BOOKED_APPROVED": return "Booked";
      case "BOOKED_PENDING": return "Pending";
      default: return status || "Unavailable";
    }
  }

  function applySlotStyles(btn, status, available) {
    btn.className = "rounded-xl border px-3 py-2 text-sm font-extrabold transition";

    if (available) {
      btn.classList.add("bg-emerald-50", "border-emerald-200", "hover:bg-emerald-100");
      btn.style.color = "#0f172a";
      return;
    }

    // unavailable states
    const disabledCommon = ["opacity-70", "cursor-not-allowed"];
    if (status === "NOT_YET_OPEN") {
      btn.classList.add("bg-slate-50", "border-slate-200", ...disabledCommon);
      btn.style.color = "#334155";
    } else if (status === "PAST") {
      btn.classList.add("bg-slate-50", "border-slate-200", ...disabledCommon);
      btn.style.color = "#64748b";
    } else {
      // booked/blocked/etc
      btn.classList.add("bg-rose-50", "border-rose-200", ...disabledCommon);
      btn.style.color = "#7f1d1d";
    }
  }

  function setSelectedSlotButton(btn) {
    slotGrid?.querySelectorAll("button[data-slot='1']").forEach((x) => {
      x.classList.remove("text-white");
      x.style.background = "";
      x.style.borderColor = "";
    });

    btn.classList.add("text-white");
    btn.style.background = getComputedStyle(document.documentElement).getPropertyValue("--primary");
    btn.style.borderColor = "transparent";
  }

  // calendar state
  let viewDate = new Date();
  let selectedDate = null;
  let selectedSlot = null;
  let lastMeta = null;

  function clearSlots() {
    if (!slotGrid) return;
    slotGrid.innerHTML = "";
    selectedSlot = null;
    if (IS_USER && bookBtn) bookBtn.disabled = true;
  }

  function getDoctorId() {
    const doctorSelect = $("doctorSelect");
    return Number.parseInt(doctorSelect?.value || "0", 10) || 0;
  }

  async function fetchSlotsFromApi(ymd) {
    if (!CLINIC_ID) return { slots: [], meta: null };

    const doctorId = getDoctorId();
    const url =
      `${BASE_URL}/api/get_slots.php?clinic_id=${CLINIC_ID}` +
      `&date=${encodeURIComponent(ymd)}` +
      (doctorId ? `&doctor_id=${doctorId}` : "");

    const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    const data = await safeJson(res);

    if (!data.ok) throw new Error(data.message || "Failed to load slots.");
    return {
      slots: Array.isArray(data.slots) ? data.slots : [],
      meta: data.meta || null,
    };
  }

  function renderClinicStatusMessage(meta) {
    const clinicStatus = meta?.clinic_status || "";

    if (clinicStatus === "CLOSED_FULL") {
      return `
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="font-extrabold text-slate-900">Closed</p>
          <p class="text-sm text-slate-600 mt-1">All slots for this day are already taken.</p>
        </div>
      `;
    }

    if (clinicStatus === "CLOSED_NOT_YET_OPEN") {
      const gate = fmtPHTime(meta?.booking_gate_open);
      return `
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="font-extrabold text-slate-900">Booking not available yet</p>
          <p class="text-sm text-slate-600 mt-1">
            Booking opens at <b>${gate || "the allowed time"}</b> (PH time).
          </p>
        </div>
      `;
    }

    return "";
  }

  async function renderSlots() {
    if (!slotGrid) return;

    clearSlots();
    lastMeta = null;

    if (!selectedDate) return;

    const ymd = toYMD(selectedDate);

    let slots = [];
    let meta = null;

    try {
      const out = await fetchSlotsFromApi(ymd);
      slots = out.slots;
      meta = out.meta;
      lastMeta = meta;
    } catch (e) {
      console.error(e);
      slotGrid.innerHTML = `<div class="text-sm text-slate-600">Failed to load slots.</div>`;
      return;
    }

    // day-level message (may still show slots if they exist)
    const statusMsg = renderClinicStatusMessage(meta);
    if (statusMsg && (meta?.clinic_status === "CLOSED_FULL")) {
      slotGrid.innerHTML = statusMsg;
      return;
    } else if (statusMsg) {
      slotGrid.innerHTML = statusMsg;
    }

    if (!slots.length) {
      slotGrid.innerHTML += `<div class="text-sm text-slate-600 mt-2">No slots for this date.</div>`;
      return;
    }

        // container for buttons
    const wrap = document.createElement("div");
    wrap.className = "grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mt-3";

    slots.forEach((s) => {
      const time24 = typeof s === "string" ? s : (s.time || "");
      const status = typeof s === "string" ? "AVAILABLE" : (s.status || "AVAILABLE");
      const canBook = typeof s === "string" ? true : !!s.can_book;

      if (!time24) return;

      const available = status === "AVAILABLE" && canBook;

      const b = document.createElement("button");
      b.type = "button";
      b.dataset.slot = "1";

      // ✅ show 12hr, store 24hr
      b.dataset.time24 = time24;
      b.textContent = to12FromHHMM(time24);

      b.disabled = !available;
      b.title = statusLabel(status);

      applySlotStyles(b, status, available);

      b.addEventListener("click", () => {
        if (!available) return;
        setSelectedSlotButton(b);

        // ✅ keep booking value in 24-hour format
        selectedSlot = time24;

        if (IS_USER && bookBtn) bookBtn.disabled = false;
      });

      wrap.appendChild(b);
    });

    slotGrid.appendChild(wrap);

  }

  function renderCalendar() {
    if (!calendarGrid || !monthLabel || !selectedDateText) return;

    calendarGrid.innerHTML = "";
    clearSlots();

    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    monthLabel.textContent = `${monthNames[month]} ${year}`;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startWeekday = firstDay.getDay(); // 0=Sun
    const daysInMonth = lastDay.getDate();

    // leading blanks
    for (let i = 0; i < startWeekday; i++) {
      const empty = document.createElement("div");
      empty.className = "h-10";
      calendarGrid.appendChild(empty);
    }

    const todayYMD = toYMD(new Date());

    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(year, month, day);
      const ymd = toYMD(d);

      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = String(day);

      // consistent card-like day style
      btn.className =
        "h-10 rounded-xl font-extrabold transition border bg-white/90 hover:bg-white " +
        "text-slate-800";

      btn.style.borderColor = "rgba(255,255,255,.35)";

      if (ymd === todayYMD) {
        btn.style.outline = "2px solid rgba(255,190,138,.9)";
        btn.style.outlineOffset = "2px";
      }

      if (selectedDate && ymd === toYMD(selectedDate)) {
        btn.style.background = "white";
        btn.style.borderColor = "rgba(255,190,138,.95)";
      }

      btn.addEventListener("click", async () => {
        selectedDate = d;
        selectedDateText.textContent = ymd;
        renderCalendar();
        await renderSlots();
      });

      calendarGrid.appendChild(btn);
    }
  }

  function wireCalendarEvents() {
    prevMonthBtn?.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      renderCalendar();
    });

    nextMonthBtn?.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      renderCalendar();
    });

    // When doctor changes, refresh slots for selected date
    $("doctorSelect")?.addEventListener("change", async () => {
      if (!selectedDate) return;
      await renderSlots();
    });
  }

  async function handleBooking() {
    if (!IS_USER) {
      window.location.href = `${BASE_URL}/pages/login.php`;
      return;
    }

    if (!selectedDate || !selectedSlot) {
      alert("Please select a date and time slot first.");
      return;
    }

    const clinicStatus = lastMeta?.clinic_status || "";
    if (clinicStatus === "CLOSED_FULL") {
      alert("All slots are already taken for this day.");
      return;
    }
    if (clinicStatus === "CLOSED_NOT_YET_OPEN") {
      const gate = fmtPHTime(lastMeta?.booking_gate_open);
      alert(`Booking is not available yet. It opens at ${gate || "the allowed time"} (PH time).`);
      return;
    }

    const doctorId = getDoctorId();
    if (!doctorId) {
      alert("Please select a doctor first.");
      return;
    }

    const notes = ($("notes")?.value || "").trim();
    const dateYMD = toYMD(selectedDate);

    const patientName = $("patientName")?.value || "";
    const patientEmail = $("patientEmail")?.value || "";
    const patientContact = $("patientContact")?.value || "";

    const form = new FormData();
    form.append("clinic_id", String(CLINIC_ID));
    form.append("doctor_id", String(doctorId));
    form.append("date", dateYMD);
    form.append("time", selectedSlot);
    form.append("notes", notes);

    // safe even if backend ignores these
    form.append("patient_name", patientName);
    form.append("patient_email", patientEmail);
    form.append("patient_contact", patientContact);

    // Disable button while booking
    const oldText = bookBtn?.textContent || "Book";
    if (bookBtn) {
      bookBtn.disabled = true;
      bookBtn.textContent = "Booking…";
    }

    try {
      const res = await fetch(`${BASE_URL}/api/book_appointment.php`, {
        method: "POST",
        body: form,
        credentials: "same-origin",
      });

      const data = await safeJson(res);

      if (!res.ok) {
        alert(data.error || "Booking failed.");
        return;
      }

      alert(data.message || "Booked!");
      await renderSlots();

      closeBookingModal();
    } catch (e) {
      console.error(e);
      alert(e?.message || "Network error. Please try again.");
    } finally {
      if (bookBtn) {
        bookBtn.textContent = oldText;
        bookBtn.disabled = !(IS_USER && selectedDate && selectedSlot);
      }
    }
  }

  bookBtn?.addEventListener("click", handleBooking);

  // expose refresh for Ably
  window.__refreshSlotsIfSelected = () => {
    if (!selectedDate) return;
    renderSlots();
  };

  // boot calendar if elements exist
  if (calendarGrid && monthLabel && selectedDateText && slotGrid && bookBtn && prevMonthBtn && nextMonthBtn) {
    wireCalendarEvents();
    renderCalendar();
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
    channel.subscribe("slots.updated", () => {
      if (typeof window.__refreshSlotsIfSelected === "function") {
        window.__refreshSlotsIfSelected();
      }
    });
  }

  initRealtime();

  // -----------------------------
  // DOCTOR MODAL
  // -----------------------------
  const doctorModal = $("doctorModal");
  const doctorBody = $("doctorModalBody");
  const closeDoctorBtn = $("closeDoctorModal");
  const doctorBackdrop = $("doctorBackdrop");

  function openDoctorModal() {
    if (!doctorModal) return;
    doctorModal.classList.remove("hidden");
    doctorModal.setAttribute("aria-hidden", "false");
    setBodyScrollLock(true);
  }

  function closeDoctorModal() {
    if (!doctorModal) return;
    doctorModal.classList.add("hidden");
    doctorModal.setAttribute("aria-hidden", "true");
    if (doctorBody) doctorBody.innerHTML = '<div class="text-slate-600 text-sm">Loading…</div>';
    setBodyScrollLock(false);
  }

  closeDoctorBtn?.addEventListener("click", closeDoctorModal);
  doctorBackdrop?.addEventListener("click", closeDoctorModal);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && doctorModal && !doctorModal.classList.contains("hidden")) {
      closeDoctorModal();
    }
  });

  document.addEventListener("click", async (e) => {
    const card = e.target.closest(".doctorCard");
    if (!card) return;

    e.preventDefault();

    const doctorId = card.dataset.doctorId;
    const clinicId = card.dataset.clinicId || "";

    if (!doctorId) return;

    openDoctorModal();

    try {
      const url =
        `${BASE_URL}/pages/doctor-modal.php?id=${encodeURIComponent(doctorId)}` +
        `&clinic_id=${encodeURIComponent(clinicId)}`;

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

  // ==============================
  // INLINE SCHEDULE PREVIEW (card)
  // Requires these elements in clinic-profile.php:
  // #previewDoctorSelect, #previewCalendarGrid, #previewSlotGrid, #previewSelectedDate
  // Modal elements already exist: #openBooking, #doctorSelect
  // ==============================
  (() => {
    const baseUrl = document.body?.dataset?.baseUrl || "/AKAS";
    const clinicId = document.body?.dataset?.clinicId || "0";

    const doctorSel = document.getElementById("previewDoctorSelect");
    const calGrid   = document.getElementById("previewCalendarGrid");
    const slotGrid  = document.getElementById("previewSlotGrid");
    const dateText  = document.getElementById("previewSelectedDate");

    // month controls (optional)
    const prevBtn = document.getElementById("previewPrevMonth");
    const nextBtn = document.getElementById("previewNextMonth");
    const monthLbl = document.getElementById("previewMonthLabel");

    const modalOpenBtn = document.getElementById("openBooking"); // opens modal
    const modalDoctor  = document.getElementById("doctorSelect"); // inside modal

    // If preview UI not present on this page, do nothing.
    if (!doctorSel || !calGrid || !slotGrid || !dateText) return;

    // --- simple month calendar render ---
    let cur = new Date();
    cur.setDate(1);
    let selectedDate = null;

    const monthNames = [
      "January","February","March","April","May","June",
      "July","August","September","October","November","December"
    ];

    function setMonthLabel() {
      if (!monthLbl) return;
      monthLbl.textContent = `${monthNames[cur.getMonth()]} ${cur.getFullYear()}`;
    }

    function sameMonth(a, b) {
      return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
    }

    function fmt(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    }

    function buildCalendar() {
      calGrid.innerHTML = "";

      setMonthLabel();

      const firstDay = new Date(cur.getFullYear(), cur.getMonth(), 1);
      const startDow = firstDay.getDay(); // 0..6
      const daysInMonth = new Date(cur.getFullYear(), cur.getMonth() + 1, 0).getDate();

      const today = new Date();
      const todayYMD = fmt(today);
      const isCurrentMonth = sameMonth(cur, today);

      // empty cells
      for (let i = 0; i < startDow; i++) {
        const div = document.createElement("div");
        calGrid.appendChild(div);
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const d = new Date(cur.getFullYear(), cur.getMonth(), day);
        const ymd = fmt(d);

        // In current month preview: do not show past days (per requirement)
        if (isCurrentMonth && ymd < todayYMD) {
          const div = document.createElement("div");
          calGrid.appendChild(div);
          continue;
        }

        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = String(day);
        btn.className =
          "h-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-sm font-bold";

        btn.dataset.date = ymd;

        btn.addEventListener("click", async () => {
          selectedDate = btn.dataset.date;
          dateText.textContent = selectedDate;

          // highlight
          [...calGrid.querySelectorAll("button[data-date]")].forEach((b) => {
            b.classList.remove("ring-2", "ring-slate-400");
          });
          btn.classList.add("ring-2", "ring-slate-400");

          await loadSlots();
        });

        calGrid.appendChild(btn);
      }
    }

    async function loadSlots() {
      slotGrid.innerHTML = "";
      const doctorId = doctorSel.value;

      const todayYMD = fmt(new Date());

      // Past dates can be viewed (for month navigation), but cannot be booked / loaded
      if (selectedDate && selectedDate < todayYMD) {
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">Past dates can’t be booked.</div>`;
        return;
      }

      if (!doctorId) {
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">Select a doctor first.</div>`;
        return;
      }
      if (!selectedDate) {
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">Pick a date to see slots.</div>`;
        return;
      }

      slotGrid.innerHTML = `<div class="text-sm text-slate-600">Loading…</div>`;

      const url = `${baseUrl}/api/get_slots.php?clinic_id=${encodeURIComponent(
        clinicId
      )}&doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(selectedDate)}`;

      try {
        const res = await fetch(url, { headers: { Accept: "application/json" } });
        const data = await res.json();

        if (!data || data.ok !== true) {
          slotGrid.innerHTML = `<div class="text-sm text-rose-600 font-semibold">Failed to load slots.</div>`;
          return;
        }

        const slotsRaw = Array.isArray(data.slots) ? data.slots : [];
        const slots = slotsRaw.map((s) => (typeof s === "string" ? { time: s, status: "AVAILABLE", can_book: true } : s));

        if (slots.length === 0) {
          slotGrid.innerHTML = `<div class="text-sm text-slate-600">No slots for this date.</div>`;
          return;
        }

        // render buttons (green = available, red = unavailable)
        const wrap = document.createElement("div");
        wrap.className = "grid grid-cols-2 sm:grid-cols-3 gap-2";

        slots.forEach((s) => {
          const time24 = (s.time || "").trim();
          if (!time24) return;

          const status = (s.status || "AVAILABLE").trim();
          const canBook = !!s.can_book;
          const available = status === "AVAILABLE" && canBook;

          const b = document.createElement("button");
          b.type = "button";

          // ✅ show 12hr, store 24hr
          b.dataset.time24 = time24;
          b.textContent = to12FromHHMM(time24);

          // base
          b.className = "h-10 rounded-xl border px-3 text-sm font-extrabold transition";
          if (available) {
            b.classList.add("bg-emerald-50", "border-emerald-200", "hover:bg-emerald-100");
            b.style.color = "#0f172a";
          } else {
            b.disabled = true;
            b.classList.add("bg-rose-50", "border-rose-200", "opacity-70", "cursor-not-allowed");
            b.style.color = "#7f1d1d";
          }

          // Click slot => open modal + set doctor/date/time
          b.addEventListener("click", () => {
            if (!available) return;
            // open modal
            modalOpenBtn?.click();

            // set doctor inside modal
            if (modalDoctor) {
              modalDoctor.value = doctorId;
              modalDoctor.dispatchEvent(new Event("change", { bubbles: true }));
            }

            // ✅ store 24-hour time so modal can select it safely
            window.__AKAS_PRESELECT = { doctorId, date: selectedDate, time: time24 };
          });

          wrap.appendChild(b);
        });


        slotGrid.innerHTML = "";
        slotGrid.appendChild(wrap);
      } catch (e) {
        slotGrid.innerHTML = `<div class="text-sm text-rose-600 font-semibold">Failed to load slots.</div>`;
      }

      // ==============================
      // PRESELECT FROM PREVIEW CARD
      // ==============================
      if (window.__AKAS_PRESELECT) {
        const p = window.__AKAS_PRESELECT;

        // Check if same date
        if (p.date === selectedDate) {
          const btns = document.querySelectorAll("#slotGrid button[data-time24]");

          btns.forEach((b) => {
            if ((b.dataset.time24 || "").trim() === (p.time || "").trim()) {
              b.click(); // simulate selecting the slot
            }
          });
        }


        // clear after use so it doesn’t repeat
        window.__AKAS_PRESELECT = null;
      }
    }

    // reset slots when doctor changes
    doctorSel.addEventListener("change", () => {
      slotGrid.innerHTML = `<div class="text-sm text-slate-600">Pick a date to see slots.</div>`;
    });

    // month navigation (preview)
    prevBtn?.addEventListener("click", () => {
      cur = new Date(cur.getFullYear(), cur.getMonth() - 1, 1);
      selectedDate = null;
      dateText.textContent = "No date selected";
      slotGrid.innerHTML = "";
      buildCalendar();
    });

    nextBtn?.addEventListener("click", () => {
      cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1);
      selectedDate = null;
      dateText.textContent = "No date selected";
      slotGrid.innerHTML = "";
      buildCalendar();
    });

    buildCalendar();
  })();


})();
