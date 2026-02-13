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
  // CALENDAR (Month grid -> click day -> show slots)
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

  const patientName = document.getElementById("patientName")?.value || "";
  const patientEmail = document.getElementById("patientEmail")?.value || "";
  const patientContact = document.getElementById("patientContact")?.value || "";

  form.append("patient_name", patientName);
  form.append("patient_email", patientEmail);
  form.append("patient_contact", patientContact);


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

    async function fetchSlotsFromApi(ymd) {
      if (!CLINIC_ID) return [];

      const url = `${BASE_URL}/api/get_slots.php?clinic_id=${CLINIC_ID}&date=${encodeURIComponent(ymd)}`;
      const res = await fetch(url, { credentials: "same-origin", cache: "no-store" });
      const data = await res.json();

      if (!data.ok) throw new Error(data.message || "Failed to load slots.");

      return Array.isArray(data.slots) ? data.slots : [];
    }

    async function renderSlots() {
      clearSlots();
      if (!selectedDate) return;

      const ymd = toYMD(selectedDate);

      let slots;
      try {
        slots = await fetchSlotsFromApi(ymd);
      } catch (e) {
        console.error(e);
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">Failed to load slots.</div>`;
        return;
      }

      if (!slots.length) {
        slotGrid.innerHTML = `<div class="text-sm text-slate-600">No slots for this date.</div>`;
        return;
      }

      slots.forEach((s) => {
        // expecting: {time:"09:00", status:"AVAILABLE"|"NOT_AVAILABLE"}
        const time = typeof s === "string" ? s : (s.time || "");
        const status = typeof s === "string" ? "AVAILABLE" : (s.status || "AVAILABLE");
        if (!time) return;

        const available = status === "AVAILABLE";

        const b = document.createElement("button");
        b.type = "button";
        b.textContent = time;
        b.disabled = !available;

        b.className = "rounded-lg border px-2 py-1 text-sm font-semibold transition";

        if (available) {
          b.classList.add("bg-green-100", "border-green-300", "hover:bg-green-200");
          b.style.color = "#0f172a";
        } else {
          b.classList.add("bg-red-100", "border-red-300", "opacity-70", "cursor-not-allowed");
          b.style.color = "#7f1d1d";
        }

        b.addEventListener("click", () => {
          if (!available) return;

          slotGrid.querySelectorAll("button").forEach((x) => {
            x.classList.remove("text-white");
            x.style.background = "";
          });

          b.classList.add("text-white");
          b.style.background = getComputedStyle(document.documentElement).getPropertyValue("--primary");

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

  // ✅ doctor dropdown (change the id if yours is different)
  const doctorSelect =
    document.getElementById("doctorSelect") ||
    document.querySelector('select[name="doctor_id"]');

  const doctorId = parseInt(doctorSelect?.value || "0", 10);

  if (!doctorId) {
    alert("Please select a doctor first.");
    return;
  }

  // optional notes
  const notesEl =
    document.getElementById("notes") ||
    document.querySelector('textarea[name="notes"]');

  const notes = (notesEl?.value || "").trim();

  const dateYMD = toYMD(selectedDate);

  const form = new FormData();
  form.append("clinic_id", String(CLINIC_ID));
  form.append("doctor_id", String(doctorId));
  form.append("date", dateYMD);
  form.append("time", selectedSlot); // "HH:MM"
  form.append("notes", notes);

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

    // ✅ refresh slots immediately (Ably will also notify other users)
    await renderSlots();

    // optional: close modal after booking
    if (bookingModal) {
      bookingModal.classList.add("hidden");
      document.body.style.overflow = "";
    }
    } catch (e) {
      console.error(e);
      alert("Network error. Please try again.");
    } finally {
      // restore button UI
      bookBtn.textContent = oldText;
      // enable only if still a slot selected
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
