// assets/js/signup-admin-doctors.js
(() => {
  const onReady = (fn) => {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  };

  onReady(() => {
    const modal = document.getElementById("doctorModal");
    const openBtn = document.getElementById("openDoctorModal");
    const closeBtn = document.getElementById("closeDoctorModal");
    const cancelBtn = document.getElementById("cancelDoctor");
    const saveBtn = document.getElementById("saveDoctor");

    // If these are missing, modal can’t open.
    if (!modal || !openBtn) {
      console.warn("[signup-admin-doctors] Missing modal or open button:", {
        modal: !!modal,
        openBtn: !!openBtn,
      });
      return;
    }

    // Existing IDs in your modal (from signup-admin.php)
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

    // List + hidden json (already used by your page)
    const listEl = document.getElementById("doctorsList");
    const jsonEl = document.getElementById("doctorsJson");

    let doctors = [];
    let editingIndex = -1;

    /* ================= UI helpers (NO browser tooltip) ================= */
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

    // Same as signup Full Name rule
    const lettersSpacing50 = (raw) => {
      const v = normalizeSpaces(raw);
      if (!v) return { ok: false, msg: "Please fill out this field." };
      if (v.length > 50) return { ok: false, msg: "You can only use letters and spacing (Maximum of 50 characters)." };
      if (!/^[A-Za-z]+(?:\s[A-Za-z]+)*$/.test(v)) {
        return { ok: false, msg: "You can only use letters and spacing (Maximum of 50 characters)." };
      }
      return { ok: true, msg: "" };
    };

    /* ================= live field validation (like user sign up) ================= */
    const liveValidateNameLike = (key, el) => {
      if (!el) return;
      const v = String(el.value || "");
      // on input: just clear (prevents noisy typing), on blur: validate
      el.addEventListener("input", () => {
        // keep numbers from being typed in phone only (elsewhere)
        clearErr(key, el);
        el.setCustomValidity("");
      });
      el.addEventListener("blur", () => {
        const vv = String(el.value || "").trim();
        if (vv === "") {
          if (el.required) setErr(key, el, requiredMsg(el));
          return;
        }
        const r = lettersSpacing50(vv);
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
        // numeric only + max 10 digits
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

  // ✅ Clear old error when user starts interacting again
  const clearWhilePicking = () => {
    const p = errP(key);
    if (p) p.textContent = "";
    el.setCustomValidity("");
    clearRing(el);
  };

  // Works better for datepicker interactions
  el.addEventListener("pointerdown", clearWhilePicking);
  el.addEventListener("focus", clearWhilePicking);

  // ✅ Main validation when the value changes
  el.addEventListener("change", validate);

  // ✅ Handles "same date clicked again" cases:
  // some browsers won't fire change, but blur will happen when you click outside
  el.addEventListener("blur", validate);

  // ✅ If user types the date manually, validate as they type
  el.addEventListener("input", () => {
    // don't show "Please pick..." while typing
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
    // ✅ live validations (attach AFTER helpers are defined)
    liveValidateNameLike("docFullName", iName);
    liveValidateBirthdate("docBirthdate", iBirth);
    liveValidateNameLike("docSpecialization", iSpec);
    liveValidateEmail("docEmail", iEmail);
    liveValidatePhone("docPhone", iPhone);


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

    // Input filters
    iPhone?.addEventListener("input", () => {
      iPhone.value = (iPhone.value || "").replace(/\D/g, "").slice(0, 10);
    });

    iPrc?.addEventListener("input", () => {
      iPrc.value = (iPrc.value || "").replace(/[^0-9-]/g, "").slice(0, 20);
    });

    const validateAll = () => {
      clearAllErrors();

      let ok = true;
      let firstBad = null;

      const mark = (key, el, msg) => {
        ok = false;
        setErr(key, el, msg);
        if (!firstBad) firstBad = el;
      };

      // REQUIRED (mark ALL)
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
        if (String(el.value ?? "").trim() === "") {
          mark(key, el, requiredMsg(el));
        }
      });

      // Full name rule
      if (iName && String(iName.value || "").trim() !== "") {
        const r = lettersSpacing50(iName.value);
        if (!r.ok) mark("docFullName", iName, r.msg);
      }

      // Specialization rule
      if (iSpec && String(iSpec.value || "").trim() !== "") {
        const r = lettersSpacing50(iSpec.value);
        if (!r.ok) mark("docSpecialization", iSpec, r.msg);
      }

      // Birthdate (not future + at least 18)
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

      // Email format
      if (iEmail && String(iEmail.value || "").trim() !== "") {
        const v = String(iEmail.value || "").trim();
        const emailOk = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(v);
        if (!emailOk) mark("docEmail", iEmail, "Enter a valid email (ex: name@gmail.com).");
      }

      // Phone format (PH)
      if (iPhone && String(iPhone.value || "").trim() !== "") {
        const v = String(iPhone.value || "").trim();
        if (!/^9\d{9}$/.test(v)) mark("docPhone", iPhone, "Enter a valid PH mobile number (ex: 9123456789).");
      }

      // PRC numbers/hyphen only
      if (iPrc && String(iPrc.value || "").trim() !== "") {
        const v = String(iPrc.value || "").trim();
        if (!/^[0-9-]+$/.test(v)) mark("docPrc", iPrc, "PRC must be numbers only.");
      }

      // Start/End order
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

    /* ================= Modal show/hide (WORKS ALWAYS) ================= */
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

    openBtn.addEventListener("click", showModal);
    closeBtn?.addEventListener("click", hideModal);
    cancelBtn?.addEventListener("click", hideModal);

    // ESC to close
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) hideModal();
    });

    saveBtn?.addEventListener("click", () => {
      const r = validateAll();
      if (!r.ok) {
        focusFirst(r.firstBad);
        return;
      }

      // keep your saving logic below (if you already have it elsewhere)
    });

    /* ================= init doctors from hidden ================= */
    try {
      doctors = JSON.parse(jsonEl?.value || "[]") || [];
    } catch {
      doctors = [];
    }
  });
})();