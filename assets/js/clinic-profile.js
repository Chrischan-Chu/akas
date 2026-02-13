(function () {
  const root = document.body;
  const BASE_URL = root?.dataset?.baseUrl || "";
  const IS_USER = root?.dataset?.isUser === "1";
  const CLINIC_ID = parseInt(root?.dataset?.clinicId || "0", 10);

  // -----------------------------
  // BOOKING MODAL OPEN/CLOSE
  // -----------------------------
  const bookingModal = document.getElementById("bookingModal");
  const openBooking = document.getElementById("openBooking");
  const closeBooking = document.getElementById("closeBooking");

  if (bookingModal && openBooking && closeBooking) {
    openBooking.addEventListener("click", () => {
      bookingModal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
    });

    closeBooking.addEventListener("click", () => {
      bookingModal.classList.add("hidden");
      document.body.style.overflow = "";
    });
  }

  // -----------------------------
  // CALENDAR + SLOTS
  // -----------------------------
  const calendarGrid = document.getElementById("calendarGrid");
  const monthLabel = document.getElementById("monthLabel");
  const selectedDateText = document.getElementById("selectedDateText");
  const slotGrid = document.getElementById("slotGrid");
  const bookBtn = document.getElementById("bookBtn");
  const prevMonthBtn = document.getElementById("prevMonth");
  const nextMonthBtn = document.getElementById("nextMonth");

  // global refresh hook for Ably
  window.__refreshSlotsIfSelected = null;

  if (
    calendarGrid &&
    monthLabel &&
    selectedDateText &&
    slotGrid &&
    bookBtn &&
    prevMonthBtn &&
    nextMonthBtn
  ) {
    let viewDate = new Date();
    let selectedDate = null;
    let selectedSlot = null;

    let lastMeta = null; // ✅ store latest meta for UI/booking guards

    const monthNames = [
      "January","February","March","April","May","June",
      "July","August","September","October","November","December"
    ];

    const pad2 = (n) => String(n).padStart(2, "0");
    const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;

    function clearSlots() {
      slotGrid.innerHTML = "";
      selectedSlot = null;
      if (IS_USER) bookBtn.disabled = true;
    }

    function fmtPHTime(ts) {
      // ts: "YYYY-MM-DD HH:MM:SS"
      if (!ts) return "";
      const d = new Date(ts.replace(" ", "T") + "+08:00"); // force PH offset
      if (Number.isNaN(d.getTime())) return ts;
      return d.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
    }

    async function fetchSlotsFromApi(ymd) {
      if (!CLINIC_ID) return { slots: [], meta: null };

      const url = `${BASE_URL}/api/get_slots.php?clinic_id=${CLINIC_ID}&date=${encodeURIComponent(ymd)}`;
      const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
      const data = await res.json();

      if (!data.ok) throw new Error(data.message || "Failed to load slots.");

      return {
        slots: Array.isArray(data.slots) ? data.slots : [],
        meta: data.meta || null,
      };
    }

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
      btn.className = "rounded-lg border px-2 py-1 text-sm font-semibold transition";

      if (available) {
        btn.classList.add("bg-green-100", "border-green-300", "hover:bg-green-200");
        btn.style.color = "#0f172a";
        return;
      }

      // different unavailable flavors
      if (status === "NOT_YET_OPEN") {
        btn.classList.add("bg-slate-100", "border-slate-300", "opacity-80", "cursor-not-allowed");
        btn.style.color = "#334155";
      } else if (status === "PAST") {
        btn.classList.add("bg-slate-100", "border-slate-300", "opacity-70", "cursor-not-allowed");
        btn.style.color = "#64748b";
      } else {
        // booked/blocked/etc
        btn.classList.add("bg-red-100", "border-red-300", "opacity-70", "cursor-not-allowed");
        btn.style.color = "#7f1d1d";
      }
    }

    async function renderSlots() {
      clearSlots();
      lastMeta = null;

      if (!selectedDate) return;

      const ymd = toYMD(selectedDate);

      let slots, meta;
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

      // ✅ day-level clinic status messages
      const clinicStatus = meta?.clinic_status || "";
      if (clinicStatus === "CLOSED_FULL") {
        slotGrid.innerHTML = `
          <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="font-bold text-slate-900">Closed</p>
            <p class="text-sm text-slate-600 mt-1">All slots for this day are already taken.</p>
          </div>
        `;
        return;
      }

      if (clinicStatus === "CLOSED_NOT_YET_OPEN") {
        const gate = fmtPHTime(meta?.booking_gate_open);
        slotGrid.innerHTML = `
          <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="font-bold text-slate-900">Booking not available yet</p>
            <p class="text-sm text-slate-600 mt-1">
              Booking opens at <b>${gate || "the allowed time"}</b> (PH time).
            </p>
          </div>
        `;
        // still show slots if API returned them
        // (they will be NOT_YET_OPEN and disabled)
      }

      if (!slots.length) {
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">No slots for this date.</div>`;
        return;
      }

      // show slots
      slots.forEach((s) => {
        // full mode expects: {time:"09:00", status:"AVAILABLE"|..., can_book:true|false}
        const time = typeof s === "string" ? s : (s.time || "");
        const status = typeof s === "string" ? "AVAILABLE" : (s.status || "AVAILABLE");
        const canBook = typeof s === "string" ? true : !!s.can_book;

        if (!time) return;

        const available = (status === "AVAILABLE" && canBook);

        const b = document.createElement("button");
        b.type = "button";
        b.textContent = time;
        b.disabled = !available;
        b.title = statusLabel(status);

        applySlotStyles(b, status, available);

        b.addEventListener("click", () => {
          if (!available) return;

          slotGrid.querySelectorAll("button").forEach((x) => {
            x.classList.remove("text-white");
            x.style.background = "";
          });

          b.classList.add("text-white");
          b.style.background = getComputedStyle(document.documentElement)
            .getPropertyValue("--primary");

          selectedSlot = time;
          if (IS_USER) bookBtn.disabled = false;
        });

        slotGrid.appendChild(b);
      });
    }

    function renderCalendar() {
      calendarGrid.innerHTML = "";
      clearSlots();

      const year = viewDate.getFullYear();
      const month = viewDate.getMonth();
      monthLabel.textContent = `${monthNames[month]} ${year}`;

      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startWeekday = firstDay.getDay();
      const daysInMonth = lastDay.getDate();

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
        btn.textContent = day;
        btn.className = "h-10 rounded-lg font-semibold transition border bg-white/90 hover:bg-white";
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

    prevMonthBtn.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener("click", () => {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      renderCalendar();
    });

    bookBtn.addEventListener("click", async () => {
      if (!IS_USER) {
        window.location.href = `${BASE_URL}/pages/login.php`;
        return;
      }

      if (!selectedDate || !selectedSlot) {
        alert("Please select a date and time slot first.");
        return;
      }

      // extra guard: if API says CLOSED_FULL or gate not open yet
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

      // doctor dropdown
      const doctorSelect =
        document.getElementById("doctorSelect") ||
        document.querySelector('select[name="doctor_id"]');

      const doctorId = parseInt(doctorSelect?.value || "0", 10);
      if (!doctorId) {
        alert("Please select a doctor first.");
        return;
      }

      const notesEl =
        document.getElementById("notes") ||
        document.querySelector('textarea[name="notes"]');

      const notes = (notesEl?.value || "").trim();
      const dateYMD = toYMD(selectedDate);

      // ✅ patient fields: pulled from readonly inputs (logged-in prefill)
      const patientName = document.getElementById("patientName")?.value || "";
      const patientEmail = document.getElementById("patientEmail")?.value || "";
      const patientContact = document.getElementById("patientContact")?.value || "";

      const form = new FormData();
      form.append("clinic_id", String(CLINIC_ID));
      form.append("doctor_id", String(doctorId));
      form.append("date", dateYMD);
      form.append("time", selectedSlot); // HH:MM
      form.append("notes", notes);

      // Only include these if your PHP accepts them (safe even if ignored)
      form.append("patient_name", patientName);
      form.append("patient_email", patientEmail);
      form.append("patient_contact", patientContact);

      // disable button while booking
      bookBtn.disabled = true;
      const oldText = bookBtn.textContent;
      bookBtn.textContent = "Booking...";

      try {
        const res = await fetch(`${BASE_URL}/api/book_appointment.php`, {
          method: "POST",
          body: form,
          credentials: "same-origin",
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
          alert(data.error || "Booking failed.");
          throw new Error("Booking failed");
        }

        alert(data.message || "Booked!");
        await renderSlots();

        // close modal after booking
        if (bookingModal) {
          bookingModal.classList.add("hidden");
          document.body.style.overflow = "";
        }
      } catch (e) {
        console.error(e);
        alert("Network error. Please try again.");
      } finally {
        bookBtn.textContent = oldText;
        bookBtn.disabled = !(IS_USER && selectedDate && selectedSlot);
      }
    });

    // expose refresh for Ably
    window.__refreshSlotsIfSelected = () => {
      if (!selectedDate) return;
      renderSlots();
    };

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
      authMethod: "POST"
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

  document.addEventListener("click", async (e) => {
    const card = e.target.closest(".doctorCard");
    if (!card) return;

    e.preventDefault();

    const doctorId = card.dataset.doctorId;
    const clinicId = card.dataset.clinicId || "";

    if (!doctorId) return;

    openDoctorModal();

    try {
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