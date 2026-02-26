// assets/js/signup-admin-doctors.js
// Doctor modal logic + JSON payload for signup-process.php
(() => {
  const onReady = (fn) => {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  };

  onReady(() => {
    const modal = document.getElementById("doctorModal");
    const openBtn = document.getElementById("openDoctorModal");
    const closeBtn = document.getElementById("closeDoctorModal");
    const cancelBtn = document.getElementById("cancelDoctor");
    const saveBtn = document.getElementById("saveDoctor");

    if (!modal || !openBtn) {
      console.warn("[signup-admin-doctors] Missing modal or open button:", {
        modal: !!modal,
        openBtn: !!openBtn,
      });
      return;
    }

    // Inputs
    const iName = document.getElementById("docFullName");
    const iBirth = document.getElementById("docBirthdate");
    const iSpec = document.getElementById("docSpecialization");
    const iPrc = document.getElementById("docPrc");
    const iEmail = document.getElementById("docEmail");
    const iPhone = document.getElementById("docPhone");
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
    const daysWrap = document.getElementById("docDaysWrap");

    // List + hidden json
    const listEl = document.getElementById("doctorsList");
    const jsonEl = document.getElementById("doctorsJson");

    let doctors = [];
    let editingIndex = -1;

    /* ================= UI helpers ================= */

    const showRing = (el) => {
      if (!el) return;
      el.classList.add("ring-2", "ring-red-400", "ring-offset-0");
      el.classList.add("focus:ring-red-400");
    };

    const clearRing = (el) => {
      if (!el) return;
      el.classList.remove("ring-2", "ring-red-400", "ring-offset-0");
      el.classList.remove("focus:ring-red-400");
    };

    const errP = (key) => modal.querySelector(`[data-err-for="${CSS.escape(key)}"]`);

    const setErr = (key, inputEl, msg) => {
      if (inputEl) inputEl.setCustomValidity(msg || "Invalid value.");
      const p = errP(key);
      if (p) p.textContent = msg || "";
      showRing(inputEl);
    };

    const clearErr = (key, inputEl) => {
      if (inputEl) inputEl.setCustomValidity("");
      const p = errP(key);
      if (p) p.textContent = "";
      clearRing(inputEl);
    };

    const requiredMsg = (el) => (el?.dataset?.requiredMsg || "Please fill out this field.").trim();
    const normalizeSpaces = (v) => (v || "").trim().replace(/\s+/g, " ");

    /* ================= field rules ================= */

    // User/Admin full-name rule (letters + spaces only)
    const lettersSpacing50 = (raw) => {
      const v = normalizeSpaces(raw);
      if (!v) return { ok: false, msg: "Please fill out this field." };
      if (v.length > 50)
        return { ok: false, msg: "You can only use letters and spacing (Maximum of 50 characters)." };
      if (!/^[A-Za-z]+(?:\s[A-Za-z]+)*$/.test(v))
        return { ok: false, msg: "You can only use letters and spacing (Maximum of 50 characters)." };
      return { ok: true, msg: "" };
    };

    // Doctor name rule (allows dots)
    // Examples allowed: "Dr. Juan Dela Cruz", "O'Neil", "Anne-Marie"
    const doctorNameRule = (raw) => {
      const v = normalizeSpaces(raw);
      const msg =
        "Only letters, spaces, dots (.), hyphens (-), and apostrophes (') are allowed. Maximum of 50 characters.";
      if (!v) return { ok: false, msg: "Please fill out this field." };
      if (v.length > 50) return { ok: false, msg };
      if (!/^[A-Za-z]+(?:[A-Za-z.\-\'\s]*[A-Za-z])?$/.test(v)) return { ok: false, msg };
      return { ok: true, msg: "" };
    };

    const prcRule = (raw) => {
      const v = String(raw || "").trim();
      if (!v) return { ok: false, msg: "Please fill out this field." };
      if (!/^\d{5,8}$/.test(v)) return { ok: false, msg: "PRC should be 5–8 digits." };
      return { ok: true, msg: "" };
    };

    /* ================= live validation ================= */

    const liveValidateName = (key, el) => {
      if (!el) return;

      el.addEventListener("input", () => {
        clearErr(key, el);
        el.setCustomValidity("");
      });

      el.addEventListener("blur", () => {
        const vv = String(el.value || "").trim();
        if (vv === "") {
          if (el.required) setErr(key, el, requiredMsg(el));
          return;
        }
        const r = key === "docFullName" ? doctorNameRule(vv) : lettersSpacing50(vv);
        if (!r.ok) setErr(key, el, r.msg);
        else clearErr(key, el);
      });
    };

    const liveValidateEmail = (key, el) => {
      if (!el) return;

      el.addEventListener("input", () => {
        clearErr(key, el);
        el.setCustomValidity("");
      });

      el.addEventListener("blur", () => {
        const vv = String(el.value || "").trim();
        if (vv === "") {
          if (el.required) setErr(key, el, requiredMsg(el));
          return;
        }
        const emailOk = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(vv);
        if (!emailOk) setErr(key, el, "Enter a valid email (ex: name@gmail.com).");
        else clearErr(key, el);
      });
    };

    const liveValidatePhone = (key, el) => {
      if (!el) return;

      el.addEventListener("input", () => {
        el.value = String(el.value || "").replace(/\D/g, "").slice(0, 10);
        clearErr(key, el);
        el.setCustomValidity("");
      });

      el.addEventListener("blur", () => {
        const vv = String(el.value || "").trim();
        if (vv === "") {
          if (el.required) setErr(key, el, requiredMsg(el));
          return;
        }
        if (!/^9\d{9}$/.test(vv)) setErr(key, el, "Enter a valid PH mobile number (ex: 9123456789).");
        else clearErr(key, el);
      });
    };

    const liveValidateBirthdate = (key, el) => {
      if (!el) return;

      const validate = () => {
        const vv = String(el.value || "").trim();

        if (vv === "") {
          if (el.required) setErr(key, el, "Please pick a birthdate.");
          else clearErr(key, el);
          return false;
        }

        const birth = new Date(vv);
        const today = new Date();
        birth.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);

        if (birth > today) {
          setErr(key, el, "Birthdate cannot be in the future.");
          return false;
        }

        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;

        if (age < 18) {
          setErr(key, el, "You must be at least 18 years old.");
          return false;
        }

        clearErr(key, el);
        return true;
      };

      const clearWhilePicking = () => {
        const p = errP(key);
        if (p) p.textContent = "";
        el.setCustomValidity("");
        clearRing(el);
      };

      el.addEventListener("pointerdown", clearWhilePicking);
      el.addEventListener("focus", clearWhilePicking);
      el.addEventListener("change", validate);
      el.addEventListener("blur", validate);
      el.addEventListener("input", () => {
        const vv = String(el.value || "").trim();
        if (!vv) {
          const p = errP(key);
          if (p) p.textContent = "";
          el.setCustomValidity("");
          clearRing(el);
          return;
        }
        validate();
      });
    };

    const liveValidatePRC = (key, el) => {
      if (!el) return;

      el.addEventListener("input", () => {
        // digits only + max 8
        el.value = String(el.value || "").replace(/\D/g, "").slice(0, 8);
        clearErr(key, el);
        el.setCustomValidity("");
      });

      el.addEventListener("blur", () => {
        const vv = String(el.value || "").trim();
        if (vv === "") {
          if (el.required) setErr(key, el, requiredMsg(el));
          return;
        }
        const r = prcRule(vv);
        if (!r.ok) setErr(key, el, r.msg);
        else clearErr(key, el);
      });
    };

    // Attach live validation
    liveValidateName("docFullName", iName);
    liveValidateBirthdate("docBirthdate", iBirth);
    liveValidateName("docSpecialization", iSpec);
    liveValidatePRC("docPrc", iPrc);
    liveValidateEmail("docEmail", iEmail);
    liveValidatePhone("docPhone", iPhone);

    /* ================= days helpers ================= */

    const getSelectedDays = () => {
      const days = [];
      if (chkMon?.checked) days.push(1);
      if (chkTue?.checked) days.push(2);
      if (chkWed?.checked) days.push(3);
      if (chkThu?.checked) days.push(4);
      if (chkFri?.checked) days.push(5);
      if (chkSat?.checked) days.push(6);
      if (chkSun?.checked) days.push(0);
      return days;
    };

    // prevent browser tooltip
    modal.querySelectorAll("input, select, textarea").forEach((el) => {
      el.addEventListener("invalid", (e) => e.preventDefault());
    });

    const focusFirst = (el) => {
      try {
        el?.focus?.();
        el?.scrollIntoView?.({ behavior: "smooth", block: "center" });
      } catch {}
    };

    const clearAllErrors = () => {
      clearErr("docFullName", iName);
      clearErr("docBirthdate", iBirth);
      clearErr("docSpecialization", iSpec);
      clearErr("docPrc", iPrc);
      clearErr("docEmail", iEmail);
      clearErr("docPhone", iPhone);
      clearErr("docSlotMins", iSlotMins);
      clearErr("docStartTime", iStart);
      clearErr("docEndTime", iEnd);

      if (daysWrap) clearRing(daysWrap);
      const p = errP("docDays");
      if (p) p.textContent = "";
    };

    const bindClear = (key, el) => {
      if (!el) return;
      const clear = () => clearErr(key, el);
      el.addEventListener("input", clear);
      el.addEventListener("change", clear);
    };

    bindClear("docFullName", iName);
    bindClear("docSpecialization", iSpec);
    bindClear("docPrc", iPrc);
    bindClear("docEmail", iEmail);
    bindClear("docPhone", iPhone);
    bindClear("docSlotMins", iSlotMins);
    bindClear("docStartTime", iStart);
    bindClear("docEndTime", iEnd);

    [chkMon, chkTue, chkWed, chkThu, chkFri, chkSat, chkSun].forEach((c) => {
      c?.addEventListener("change", () => {
        if (daysWrap) clearRing(daysWrap);
        const p = errP("docDays");
        if (p) p.textContent = "";
      });
    });

    /* ================= render list ================= */

    const daysLabel = (arr) => {
      const map = { 0: "Sun", 1: "Mon", 2: "Tue", 3: "Wed", 4: "Thu", 5: "Fri", 6: "Sat" };
      return (Array.isArray(arr) ? arr : []).map((d) => map[d]).join(", ");
    };

    const escapeHtml = (s) =>
      String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

    const renderList = () => {
      if (!listEl) return;

      if (!Array.isArray(doctors) || doctors.length === 0) {
        listEl.innerHTML = "";
        return;
      }

      listEl.innerHTML = doctors
        .map(
          (d, idx) => `
          <div class="rounded-xl bg-white/90 border border-white/50 px-3 py-2">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-semibold text-slate-800 truncate">${escapeHtml(d.full_name || "")}</div>
                <div class="text-sm text-slate-600">${escapeHtml(d.specialization || "")}</div>
                <div class="text-sm text-slate-600">${escapeHtml(d.prc || "")}</div>
                <div class="text-xs text-slate-500 mt-1">${escapeHtml(d.start_time || "")}–${escapeHtml(
            d.end_time || ""
          )}</div>
                <div class="text-xs text-slate-500">${escapeHtml(daysLabel(d.days))}</div>
              </div>

              <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                <button type="button"
                        data-edit-doc="${idx}"
                        style="background:#40b7ff; color:#fff; border:none; padding:10px 14px; border-radius:10px; font-weight:700; cursor:pointer; min-width:90px;">
                  Edit
                </button>
                <button type="button"
                        data-del-doc="${idx}"
                        style="background:#ef4444; color:#fff; border:none; padding:10px 14px; border-radius:10px; font-weight:700; cursor:pointer; min-width:90px;">
                  Remove
                </button>
              </div>
            </div>
          </div>
        `
        )
        .join("");

      listEl.querySelectorAll("[data-del-doc]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const i = parseInt(btn.getAttribute("data-del-doc"), 10);
          if (Number.isNaN(i)) return;
          doctors.splice(i, 1);
          syncJson();
          renderList();
        });
      });

      listEl.querySelectorAll("[data-edit-doc]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const i = parseInt(btn.getAttribute("data-edit-doc"), 10);
          if (Number.isNaN(i)) return;
          startEdit(i);
        });
      });
    };

    const syncJson = () => {
      if (jsonEl) jsonEl.value = JSON.stringify(doctors || []);
      // trigger validators watching doctorsJson
      jsonEl?.dispatchEvent?.(new Event("change", { bubbles: true }));
    };

    /* ================= modal show/hide ================= */

    const lockScroll = (locked) => {
      document.documentElement.style.overflow = locked ? "hidden" : "";
      document.body.style.overflow = locked ? "hidden" : "";
    };

    const showModal = () => {
      modal.classList.remove("hidden");
      modal.classList.add("flex");
      lockScroll(true);

      clearAllErrors();
      iName?.focus?.();

      if (saveBtn) saveBtn.textContent = editingIndex >= 0 ? "Save Changes" : "Add Doctor";
    };

    const hideModal = () => {
      modal.classList.add("hidden");
      modal.classList.remove("flex");
      lockScroll(false);
    };

    openBtn.addEventListener("click", () => {
      editingIndex = -1;
      resetModalInputs();
      showModal();
    });
    closeBtn?.addEventListener("click", hideModal);
    cancelBtn?.addEventListener("click", hideModal);

    // ESC to close
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) hideModal();
    });

    /* ================= edit / reset ================= */

    const resetModalInputs = () => {
      if (iName) iName.value = "";
      if (iBirth) iBirth.value = "";
      if (iSpec) iSpec.value = "";
      if (iPrc) iPrc.value = "";
      if (iEmail) iEmail.value = "";
      if (iPhone) iPhone.value = "";
      if (iSlotMins) iSlotMins.value = "30";
      if (iStart) iStart.value = "09:00";
      if (iEnd) iEnd.value = "17:00";

      if (chkMon) chkMon.checked = true;
      if (chkTue) chkTue.checked = true;
      if (chkWed) chkWed.checked = true;
      if (chkThu) chkThu.checked = true;
      if (chkFri) chkFri.checked = true;
      if (chkSat) chkSat.checked = false;
      if (chkSun) chkSun.checked = false;

      clearAllErrors();
    };

    const startEdit = (idx) => {
      const d = doctors[idx];
      if (!d) return;

      editingIndex = idx;

      if (iName) iName.value = d.full_name || "";
      if (iBirth) iBirth.value = d.birthdate || "";
      if (iSpec) iSpec.value = d.specialization || "";
      if (iPrc) iPrc.value = d.prc || "";
      if (iEmail) iEmail.value = d.email || "";
      if (iPhone) iPhone.value = d.contact_number || "";

      // Prefer availability JSON (if present)
      let a = null;
      try {
        if (typeof d.availability === "string" && d.availability.trim() !== "") {
          a = JSON.parse(d.availability);
        }
      } catch {}

      const slot = a?.slot_mins ?? d.slot_mins ?? 30;
      const start = a?.start ?? d.start_time ?? "09:00";
      const end = a?.end ?? d.end_time ?? "17:00";
      const days = Array.isArray(a?.days) ? a.days : Array.isArray(d.days) ? d.days : [1, 2, 3, 4, 5];

      if (iSlotMins) iSlotMins.value = String(slot);
      if (iStart) iStart.value = String(start);
      if (iEnd) iEnd.value = String(end);

      const setDays = new Set(days.map((x) => parseInt(String(x), 10)).filter((n) => !Number.isNaN(n)));
      if (chkMon) chkMon.checked = setDays.has(1);
      if (chkTue) chkTue.checked = setDays.has(2);
      if (chkWed) chkWed.checked = setDays.has(3);
      if (chkThu) chkThu.checked = setDays.has(4);
      if (chkFri) chkFri.checked = setDays.has(5);
      if (chkSat) chkSat.checked = setDays.has(6);
      if (chkSun) chkSun.checked = setDays.has(0);

      showModal();
    };

    /* ================= validate all ================= */

    const validateAll = () => {
      clearAllErrors();
      let ok = true;
      let firstBad = null;

      const mark = (key, el, msg) => {
        ok = false;
        setErr(key, el, msg);
        if (!firstBad) firstBad = el;
      };

      const requiredFields = [
        ["docFullName", iName],
        ["docBirthdate", iBirth],
        ["docSpecialization", iSpec],
        ["docPrc", iPrc],
        ["docEmail", iEmail],
        ["docPhone", iPhone],
        ["docSlotMins", iSlotMins],
        ["docStartTime", iStart],
        ["docEndTime", iEnd],
      ];

      requiredFields.forEach(([key, el]) => {
        if (!el) return;
        if (String(el.value ?? "").trim() === "") mark(key, el, requiredMsg(el));
      });

      // Doctor name rule
      if (iName && String(iName.value || "").trim() !== "") {
        const r = doctorNameRule(iName.value);
        if (!r.ok) mark("docFullName", iName, r.msg);
      }

      // Specialization rule (keep strict)
      if (iSpec && String(iSpec.value || "").trim() !== "") {
        const r = lettersSpacing50(iSpec.value);
        if (!r.ok) mark("docSpecialization", iSpec, r.msg);
      }

      // PRC rule 5–8 digits
      if (iPrc && String(iPrc.value || "").trim() !== "") {
        const r = prcRule(iPrc.value);
        if (!r.ok) mark("docPrc", iPrc, r.msg);
      }

      // Birthdate 18+
      if (iBirth && String(iBirth.value || "").trim() !== "") {
        const v = String(iBirth.value || "").trim();
        const birth = new Date(v);
        const today = new Date();

        birth.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);

        if (birth > today) {
          mark("docBirthdate", iBirth, "Birthdate cannot be in the future.");
        } else {
          let age = today.getFullYear() - birth.getFullYear();
          const m = today.getMonth() - birth.getMonth();
          if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
          if (age < 18) mark("docBirthdate", iBirth, "You must be at least 18 years old.");
        }
      }

      // Email
      if (iEmail && String(iEmail.value || "").trim() !== "") {
        const v = String(iEmail.value || "").trim();
        const emailOk = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(v);
        if (!emailOk) mark("docEmail", iEmail, "Enter a valid email (ex: name@gmail.com).");
      }

      // Phone
      if (iPhone && String(iPhone.value || "").trim() !== "") {
        const v = String(iPhone.value || "").trim();
        if (!/^9\d{9}$/.test(v)) mark("docPhone", iPhone, "Enter a valid PH mobile number (ex: 9123456789).");
      }

      // Start < End
      if (iStart && iEnd && iStart.value && iEnd.value) {
        if (iStart.value >= iEnd.value) mark("docEndTime", iEnd, "End time must be later than start time.");
      }

      // Days required
      const days = getSelectedDays();
      if (days.length === 0) {
        ok = false;
        if (daysWrap) showRing(daysWrap);
        const p = errP("docDays");
        if (p) p.textContent = "Please choose at least one day.";
        if (!firstBad) firstBad = daysWrap;
      }

      return { ok, firstBad };
    };

    /* ================= save (Add Doctor button) ================= */

    saveBtn?.addEventListener("click", () => {
      const r = validateAll();
      if (!r.ok) {
        focusFirst(r.firstBad);
        return;
      }

      const days = getSelectedDays();
      const slotMins = parseInt(String(iSlotMins?.value || "30"), 10) || 30;
      const startTime = String(iStart?.value || "").trim();
      const endTime = String(iEnd?.value || "").trim();

      // IMPORTANT: signup-process.php expects prc under key "prc" (not prc_number)
      // and it generates DB schedule from availability JSON.
      const availabilityObj = {
        days,
        start: startTime,
        end: endTime,
        slot_mins: slotMins,
      };

      const doc = {
        full_name: normalizeSpaces(iName?.value || ""),
        birthdate: String(iBirth?.value || "").trim(),
        specialization: normalizeSpaces(iSpec?.value || ""),
        prc: String(iPrc?.value || "").trim(),
        email: String(iEmail?.value || "").trim(),
        contact_number: String(iPhone?.value || "").trim(),

        // for UI + server fallback builder
        slot_mins: slotMins,
        start_time: startTime,
        end_time: endTime,
        days,

        // used by signup-process.php to build schedule in DB
        availability: JSON.stringify(availabilityObj),
      };

      if (editingIndex >= 0) doctors[editingIndex] = doc;
      else doctors.push(doc);

      editingIndex = -1;
      syncJson();
      renderList();
      hideModal();
    });

    /* ================= init doctors ================= */

    try {
      doctors = JSON.parse(jsonEl?.value || "[]") || [];
    } catch {
      doctors = [];
    }
    if (!Array.isArray(doctors)) doctors = [];

    renderList();
    syncJson(); // keeps hidden value normalized
  });
})();
