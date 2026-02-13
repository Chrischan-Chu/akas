(function () {
  const modal = document.getElementById("doctorModal");
  const openBtn = document.getElementById("openDoctorModal");
  const closeBtn = document.getElementById("closeDoctorModal");
  const cancelBtn = document.getElementById("cancelDoctor");
  const saveBtn = document.getElementById("saveDoctor");

  const listEl = document.getElementById("doctorsList");
  const jsonEl = document.getElementById("doctorsJson");

  // --- Doctor basic fields ---
  const iName = document.getElementById("docFullName");
  const iBirth = document.getElementById("docBirthdate");
  const iSpec = document.getElementById("docSpecialization");
  const iPrc = document.getElementById("docPrc");
  const iEmail = document.getElementById("docEmail");
  const iPhone = document.getElementById("docPhone");

  // --- Availability fields (NEW) ---
  const iSlotMins = document.getElementById("docSlotMins");
  const iStart = document.getElementById("docStartTime");
  const iEnd = document.getElementById("docEndTime");

  const chkMon = document.getElementById("dMon");
  const chkTue = document.getElementById("dTue");
  const chkWed = document.getElementById("dWed");
  const chkThu = document.getElementById("dThu");
  const chkFri = document.getElementById("dFri");
  const chkSat = document.getElementById("dSat");
  const chkSun = document.getElementById("dSun");

  let doctors = [];
  let editingIndex = -1; // -1 = add, >=0 = edit

  function lockScroll(locked) {
    document.documentElement.style.overflow = locked ? "hidden" : "";
    document.body.style.overflow = locked ? "hidden" : "";
  }

  function clearValidity() {
    [iName, iBirth, iSpec, iPrc, iEmail, iPhone, iStart, iEnd, iSlotMins].forEach((el) => {
      if (el) el.setCustomValidity("");
    });
  }

  function showModal() {
    modal?.classList.add("show");
    lockScroll(true);

    clearValidity();

    if (saveBtn) saveBtn.textContent = editingIndex >= 0 ? "Save Changes" : "Add Doctor";
    iName?.focus();
  }

  function hideModal() {
    modal?.classList.remove("show");
    lockScroll(false);
  }

  function resetModalInputs() {
    if (iName) iName.value = "";
    if (iBirth) iBirth.value = "";
    if (iSpec) iSpec.value = "";
    if (iPrc) iPrc.value = "";
    if (iEmail) iEmail.value = "";
    if (iPhone) iPhone.value = "";

    // defaults
    if (iSlotMins) iSlotMins.value = "30";
    if (iStart) iStart.value = "09:00";
    if (iEnd) iEnd.value = "17:00";

    // default weekdays checked
    if (chkMon) chkMon.checked = true;
    if (chkTue) chkTue.checked = true;
    if (chkWed) chkWed.checked = true;
    if (chkThu) chkThu.checked = true;
    if (chkFri) chkFri.checked = true;
    if (chkSat) chkSat.checked = false;
    if (chkSun) chkSun.checked = false;

    clearValidity();
  }

  function fillModalInputs(d) {
    if (!d) return;

    if (iName) iName.value = d.full_name || "";
    if (iBirth) iBirth.value = d.birthdate || "";
    if (iSpec) iSpec.value = d.specialization || "";
    if (iPrc) iPrc.value = d.prc || "";
    if (iEmail) iEmail.value = d.email || "";
    if (iPhone) iPhone.value = d.contact_number || "";

    // availability stored as JSON string
    let av = null;
    try {
      av = typeof d.availability === "string" ? JSON.parse(d.availability) : d.availability;
    } catch {
      av = null;
    }

    if (av && typeof av === "object") {
      if (iSlotMins) iSlotMins.value = String(av.slot_mins || 30);
      if (iStart) iStart.value = av.start || "09:00";
      if (iEnd) iEnd.value = av.end || "17:00";

      const days = Array.isArray(av.days) ? av.days : [];
      if (chkMon) chkMon.checked = days.includes(1);
      if (chkTue) chkTue.checked = days.includes(2);
      if (chkWed) chkWed.checked = days.includes(3);
      if (chkThu) chkThu.checked = days.includes(4);
      if (chkFri) chkFri.checked = days.includes(5);
      if (chkSat) chkSat.checked = days.includes(6);
      if (chkSun) chkSun.checked = days.includes(0);
    }
  }

  function setError(el, msg) {
    if (!el) return;
    el.setCustomValidity(msg);
    el.reportValidity();
  }

  function getSelectedDays() {
    const days = [];
    if (chkMon?.checked) days.push(1);
    if (chkTue?.checked) days.push(2);
    if (chkWed?.checked) days.push(3);
    if (chkThu?.checked) days.push(4);
    if (chkFri?.checked) days.push(5);
    if (chkSat?.checked) days.push(6);
    if (chkSun?.checked) days.push(0);
    return days;
  }

  function formatDays(days) {
    const map = { 0: "Sun", 1: "Mon", 2: "Tue", 3: "Wed", 4: "Thu", 5: "Fri", 6: "Sat" };
    const labels = (days || []).map((d) => map[d]).filter(Boolean);

    // common shortcuts
    const isWeekdays = JSON.stringify(days) === JSON.stringify([1,2,3,4,5]);
    const isAllDays = JSON.stringify(days) === JSON.stringify([0,1,2,3,4,5,6]);

    if (isWeekdays) return "Mon–Fri";
    if (isAllDays) return "Daily";
    return labels.join(", ");
  }

  function validateDoctor() {
    const name = (iName?.value || "").trim();
    const birth = (iBirth?.value || "").trim();
    const spec = (iSpec?.value || "").trim();
    const prc = (iPrc?.value || "").trim();
    const email = (iEmail?.value || "").trim().toLowerCase();
    const phone = (iPhone?.value || "").trim();

    const start = (iStart?.value || "").trim();
    const end = (iEnd?.value || "").trim();
    const slotMins = parseInt(iSlotMins?.value || "30", 10);
    const days = getSelectedDays();

    clearValidity();

    // --- basic fields ---
    if (name === "") return setError(iName, "Full name is required."), null;
    if (name.length > 190) return setError(iName, "Full name is too long."), null;
    if (!/^[A-Za-z\s.'-]+$/.test(name)) return setError(iName, "Full name contains invalid characters."), null;

    if (birth === "") return setError(iBirth, "Birthdate is required."), null;
    const d = new Date(birth + "T00:00:00");
    if (Number.isNaN(d.getTime())) return setError(iBirth, "Enter a valid birthdate."), null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (d > today) return setError(iBirth, "Birthdate cannot be in the future."), null;
    const age =
      today.getFullYear() -
      d.getFullYear() -
      (today.getMonth() < d.getMonth() || (today.getMonth() === d.getMonth() && today.getDate() < d.getDate()) ? 1 : 0);
    if (age < 18) return setError(iBirth, "Doctor must be at least 18 years old."), null;

    if (spec === "") return setError(iSpec, "Specialization is required."), null;
    if (spec.length > 120) return setError(iSpec, "Specialization is too long."), null;
    if (!/^[A-Za-z0-9\s.,'()-]+$/.test(spec)) return setError(iSpec, "Specialization contains invalid characters."), null;

    if (prc === "") return setError(iPrc, "PRC is required."), null;
    if (prc.length > 50) return setError(iPrc, "PRC is too long."), null;
    if (!/^[A-Za-z0-9\-]+$/.test(prc)) return setError(iPrc, "PRC contains invalid characters."), null;

    if (email === "") return setError(iEmail, "Email is required."), null;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return setError(iEmail, "Enter a valid email."), null;
    if (email.length > 190) return setError(iEmail, "Email is too long."), null;

    const digits = phone.replace(/\D+/g, "");
    if (!/^9\d{9}$/.test(digits)) return setError(iPhone, "Enter a valid PH mobile number (ex: 9123456789)."), null;

    // --- availability ---
    if (!start) return setError(iStart, "Start time is required."), null;
    if (!end) return setError(iEnd, "End time is required."), null;
    if (start >= end) return setError(iEnd, "End time must be later than start time."), null;

    if (!Number.isFinite(slotMins) || slotMins <= 0) return setError(iSlotMins, "Slot length is invalid."), null;
    if (!days.length) return alert("Please choose at least 1 day."), null;

    const availability = { days, start, end, slot_mins: slotMins };

    return {
      full_name: name,
      birthdate: birth,
      specialization: spec,
      prc,
      email,
      contact_number: digits,
      availability: JSON.stringify(availability),
    };
  }

  function syncHidden() {
    if (jsonEl) jsonEl.value = JSON.stringify(doctors);
  }

  function renderList() {
    if (!listEl) return;

    if (!doctors.length) {
      listEl.innerHTML = '<p class="text-sm text-slate-600">No doctors added yet.</p>';
      return;
    }

    const safe = (s) =>
      String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));

    listEl.innerHTML = doctors
      .map((d, idx) => {
        let av = null;
        try {
          av = typeof d.availability === "string" ? JSON.parse(d.availability) : d.availability;
        } catch {
          av = null;
        }

        const dayLabel = av?.days ? formatDays(av.days) : "—";
        const start = av?.start || "—";
        const end = av?.end || "—";
        const mins = av?.slot_mins || 30;

        const availText = `${dayLabel} • ${start}–${end} (${mins}m)`;

        return `
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex items-start justify-between gap-3">
          <div>
            <p class="font-bold text-slate-800">${safe(d.full_name)}</p>
            <p class="text-xs text-slate-600">${safe(d.specialization)} • PRC: ${safe(d.prc)}</p>
            <p class="text-xs text-slate-600">Availability: ${safe(availText)}</p>
          </div>

          <div class="shrink-0 flex items-center gap-2">
            <button type="button"
                    data-edit="${idx}"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">
              Edit
            </button>

            <button type="button"
                    data-remove="${idx}"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">
              Remove
            </button>
          </div>
        </div>
      `;
      })
      .join("");
  }

  openBtn?.addEventListener("click", () => {
    editingIndex = -1;
    resetModalInputs();
    showModal();
  });

  closeBtn?.addEventListener("click", hideModal);
  cancelBtn?.addEventListener("click", hideModal);

  // prevent closing by clicking backdrop (close only via buttons)
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) e.stopPropagation();
  });

  saveBtn?.addEventListener("click", () => {
    const doc = validateDoctor();
    if (!doc) return;

    if (editingIndex >= 0 && editingIndex < doctors.length) {
      doctors[editingIndex] = doc;
    } else {
      doctors.push(doc);
    }

    editingIndex = -1;
    syncHidden();
    renderList();
    hideModal();
  });

  listEl?.addEventListener("click", (e) => {
    const removeBtn = e.target?.closest("button[data-remove]");
    const editBtn = e.target?.closest("button[data-edit]");

    if (removeBtn) {
      const idx = parseInt(removeBtn.getAttribute("data-remove") || "-1", 10);
      if (!Number.isFinite(idx) || idx < 0 || idx >= doctors.length) return;

      doctors.splice(idx, 1);

      if (editingIndex === idx) editingIndex = -1;
      if (editingIndex > idx) editingIndex -= 1;

      syncHidden();
      renderList();
      return;
    }

    if (editBtn) {
      const idx = parseInt(editBtn.getAttribute("data-edit") || "-1", 10);
      if (!Number.isFinite(idx) || idx < 0 || idx >= doctors.length) return;

      editingIndex = idx;
      resetModalInputs();
      fillModalInputs(doctors[idx]);
      showModal();
      return;
    }
  });

  // init from hidden json
  try {
    doctors = JSON.parse(jsonEl?.value || "[]") || [];
  } catch {
    doctors = [];
  }

  syncHidden();
  renderList();
})();
