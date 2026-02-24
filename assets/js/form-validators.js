// assets/js/form-validators.js
(() => {
  /* ================= helpers ================= */

  // ✅ Error ring that still shows even while focused (overrides focus ring)
  const showError = (el) => {
    el.classList.add("ring-2", "ring-red-400", "ring-offset-0");
    // if Tailwind is compiled with JIT/purge, ensure this class exists in your build
    el.classList.add("focus:ring-red-400");
  };

  const clearError = (el) => {
    el.classList.remove("ring-2", "ring-red-400", "ring-offset-0");
    el.classList.remove("focus:ring-red-400");
  };

  const debounce = (fn, ms = 100) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  // Resolve app base URL from the script tag that loaded this file.
  // This avoids hardcoding "/AKAS" or assuming the site is at web root.
  const getAppBaseUrl = () => {
    const script = document.querySelector(
      'script[src*="/assets/js/form-validators.js"]'
    );
    if (!script?.src) return "";

    try {
      const u = new URL(script.src, window.location.href);
      // Strip "/assets/js/form-validators.js" from pathname
      u.pathname = u.pathname.replace(
        /\/assets\/js\/form-validators\.js(?:\?.*)?$/,
        ""
      );
      return u.pathname.endsWith("/") ? u.pathname.slice(0, -1) : u.pathname;
    } catch {
      return "";
    }
  };

  const UNIQUE_ENDPOINT = (() => {
    const base = getAppBaseUrl();
    // Keep it as a same-origin absolute path
    return `${base}/includes/check-unique.php`;
  })();

  const checkUnique = async ({ type, value, signal }) => {
    const params = new URLSearchParams({ type, value });
    const url = `${UNIQUE_ENDPOINT}?${params.toString()}`;

    try {
      const res = await fetch(url, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
        signal,
      });
      const data = await res.json();
      return data?.ok ? !!data.available : true; // fail-open
    } catch {
      return true; // fail-open
    }
  };

  /**
   * ✅ FIXED: unique checks run on blur AND block form submit
   * - prevents jumping to password if email is taken
   * - prevents submitting when email is already in use
   */
  const wireUnique = ({ input, type, message }) => {
    if (!input) return;
    if (input.dataset.uniqueBound === "1") return;
    input.dataset.uniqueBound = "1";

    // ✅ store the uniqueness message so wireInput won't overwrite it
    input.dataset.uniqueMsg = message;

    let controller = null;

    const runCheckNow = async () => {
      const v = (input.value || "").trim();

      // empty: let required handle it; don't do unique check
      if (!v) return true;

      // only check server if format already valid
      if (!input.checkValidity()) return true;

      // cancel old request to avoid race conditions
      try {
        controller?.abort();
      } catch {}
      controller = new AbortController();

      const available = await checkUnique({
        type,
        value: v,
        signal: controller.signal,
      });

      if (!available) {
        showError(input);
        input.setCustomValidity(message);
        return false;
      }

      if (input.validationMessage === message) input.setCustomValidity("");
      clearError(input);
      return true;
    };

    // blur (nice UX)
    input.addEventListener("blur", debounce(runCheckNow, 100));

    // typing: clear OUR uniqueness message
    input.addEventListener("input", () => {
      if (input.validationMessage === message) input.setCustomValidity("");
      clearError(input);
    });

    const form = input.closest("form");
    if (!form) return;

    // Register this field's uniqueness check on the form so submit can validate ALL unique fields.
    if (!form.__uniqueChecks) form.__uniqueChecks = [];
    form.__uniqueChecks.push({ input, runCheckNow });

    // ✅ Block ALL submit paths (click, Enter, etc.) - once per form
    if (form.dataset.uniqueSubmitBound === "1") return;
    form.dataset.uniqueSubmitBound = "1";

    form.addEventListener(
      "submit",
      async (e) => {
        // prevent re-submit loop
        if (form.dataset.uniqueSubmitLock === "1") return;

        // pause submit until uniqueness is confirmed
        e.preventDefault();

        const checks = Array.isArray(form.__uniqueChecks) ? form.__uniqueChecks : [];
        // run all registered uniqueness checks
        for (const c of checks) {
          const ok = await c.runCheckNow();
          if (!ok) {
            c.input.focus();
            c.input.reportValidity();
            return;
          }
        }

        // if other fields invalid, let browser show them
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }

        // submit for real
        form.dataset.uniqueSubmitLock = "1";
        try {
          form.requestSubmit();
        } finally {
          setTimeout(() => delete form.dataset.uniqueSubmitLock, 0);
        }
      },
      true // capture phase helps run early
    );
  };

  /**
   * Prevent popup spam while typing:
   * - input: UI only (red ring), no customValidity message
   * - blur + submit: set customValidity so submit is blocked
   */
  const wireInput = ({ input, validate, filter }) => {
    if (!input) return;

    const applyUI = (v) => {
      if (v === "") {
        clearError(input);
        return;
      }
      const { ok } = validate(v, input, "input");
      if (!ok) showError(input);
      else clearError(input);
    };

    const applyValidity = (v, phase) => {
      // ✅ DO NOT override uniqueness error message
      const uniqueMsg = input.dataset.uniqueMsg;
      if (uniqueMsg && input.validationMessage === uniqueMsg) return;

      if (v === "") {
        input.setCustomValidity("");
        return;
      }
      const { ok, message } = validate(v, input, phase);
      input.setCustomValidity(ok ? "" : message);
    };

    input.addEventListener("input", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      applyUI(v);
      input.setCustomValidity(""); // quiet while typing
    });

    input.addEventListener("blur", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      // ✅ empty should NOT show red ring
      if (v === "") {
        clearError(input);
        input.setCustomValidity("");
        return;
      }

      const { ok } = validate(v, input, "blur");
      if (!ok) showError(input);
      else clearError(input);

      applyValidity(v, "blur");
    });

    // ✅ If browser triggers invalid directly, enforce red ring
    input.addEventListener("invalid", () => {
      showError(input);
    });

    input.closest("form")?.addEventListener("submit", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();
      applyValidity(v, "submit");
    });
  };

  /**
   * Required selects: customize the built-in message
   */
  const wireRequiredSelect = (select) => {
  if (!select) return;

  const requiredMsg = (select.dataset.requiredMsg || "Please select an option.").trim();

  const isEmpty = () => (select.value == null || String(select.value).trim() === "");

 
  // set your custom message BEFORE the tooltip shows.
  select.addEventListener("invalid", () => {
    if (isEmpty()) {
      select.setCustomValidity(requiredMsg); // replaces "Please select an item in the list."
      showError(select);                     // make it red
    } else {
      select.setCustomValidity("");
      clearError(select);
    }
  });

  // ✅ When user changes, clear error/message if valid
  select.addEventListener("change", () => {
    if (isEmpty()) {
      select.setCustomValidity(requiredMsg);
      showError(select);
    } else {
      select.setCustomValidity("");
      clearError(select);
    }
  });

  // ✅ On blur, keep it red if still empty (don’t clear!)
  select.addEventListener("blur", () => {
    if (isEmpty()) {
      select.setCustomValidity(requiredMsg);
      showError(select);
    } else {
      select.setCustomValidity("");
      clearError(select);
    }
  });

  // ✅ On submit, force the correct message + red ring
  select.closest("form")?.addEventListener("submit", () => {
    if (isEmpty()) {
      select.setCustomValidity(requiredMsg);
      showError(select);
    } else {
      select.setCustomValidity("");
      clearError(select);
    }
  });
};

  /* ================= validators ================= */

  // Letters + spaces only, max 50 chars
  // Used for: User Full Name, Admin Full Name, Clinic Name
  const validateFullName = (val) => {
    const v = (val || "").trim().replace(/\s+/g, " ");
    if (!v) return { ok: true, message: "" };

    if (v.length > 50) {
      return { ok: false, message: "You can only use letters and spacing (Maximum of 50 characters)." };
    }

    if (!/^[A-Za-z]+(?:\s[A-Za-z]+)*$/.test(v)) {
      return { ok: false, message: "You can only use letters and spacing (Maximum of 50 characters)." };
    }

    return { ok: true, message: "" };
  };

  const validateEmail = (val) => {
    const v = (val || "").trim();

    // allow empty if not required by HTML
    if (v === "") return { ok: true, message: "" };

    const ok = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(v);
    return { ok, message: ok ? "" : "Enter a valid email (ex: name@gmail.com)." };
  };

  const validatePHMobile = (val, input, phase = "input") => {
    const v = (val || "").trim();

    if (phase === "input" && v.length < 10) return { ok: true, message: "" };

    const ok = /^9\d{9}$/.test(v);
    return { ok, message: ok ? "" : "Enter a valid PH mobile number (ex: 9123456789)." };
  };

  const validateBusinessId10 = (val, input, phase = "input") => {
    const v = (val || "").trim();

    if (phase === "input" && v.length < 10) return { ok: true, message: "" };

    const ok = /^\d{10}$/.test(v);
    return { ok, message: ok ? "" : "Business ID must be exactly 10 digits." };
  };

  const validatePassword = (val) => {
    const v = val || "";
    const ok = v.length >= 8 && /[A-Z]/.test(v) && /[^A-Za-z0-9]/.test(v);
    return {
      ok,
      message: ok ? "" : "Password must be 8+ chars, with 1 uppercase and 1 special character.",
    };
  };

  const validatePasswordOptional = (val) => {
    const v = (val || "").trim();
    if (v === "") return { ok: true, message: "" };
    return validatePassword(v);
  };

  const validatePasswordConfirm = (val, input) => {
    const v = (val || "").trim();
    const targetName = input.dataset.match;
    const other = document.querySelector(`[name="${targetName}"]`);
    const otherVal = (other?.value || "").trim();

    if (v === "" && otherVal === "") return { ok: true, message: "" };

    const ok = v !== "" && v === otherVal;
    return { ok, message: ok ? "" : "Passwords do not match." };
  };

  const validateAge18 = (val) => {
    const v = (val || "").trim();
    if (!v) return { ok: true, message: "" };

    const birth = new Date(v);
    const today = new Date();

    birth.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    if (birth > today) return { ok: false, message: "Birth date cannot be in the future." };

    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;

    if (age < 18) return { ok: false, message: "You must be at least 18 years old." };

    return { ok: true, message: "" };
  };

  const syncConfirmPassword = (confirmInput) => {
    const targetName = (confirmInput.dataset.match || "").trim();
    const passInput = document.querySelector(`[name="${targetName}"]`);
    if (!passInput) return;

    passInput.addEventListener("input", () => {
      confirmInput.dispatchEvent(new Event("blur"));
    });
  };

  /* ================= password toggle (SVG icons) ================= */

  const ICON_EYE = `
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3" stroke-width="2"/>
    </svg>
  `;

  const ICON_EYE_OFF = `
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M3 3l18 18"/>
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M10.58 10.58A3 3 0 0012 15a3 3 0 002.42-4.42"/>
      <path stroke-width="2" stroke-linecap="round" stroke-linecap="round" stroke-linejoin="round"
        d="M9.88 5.08A10.94 10.94 0 0112 4c7 0 11 8 11 8a18.46 18.46 0 01-3.12 4.47"/>
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M6.1 6.1C3.9 7.8 1 12 1 12s4 8 11 8c1.2 0 2.33-.2 3.4-.57"/>
    </svg>
  `;

  const initPasswordToggles = () => {
    document.querySelectorAll(".toggle-pass").forEach((btn) => {
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";

      if (!btn.innerHTML.trim()) btn.innerHTML = ICON_EYE;

      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);
        if (!input) return;

        const nowVisible = input.type === "password";
        input.type = nowVisible ? "text" : "password";

        btn.innerHTML = nowVisible ? ICON_EYE_OFF : ICON_EYE;
      });
    });
  };

  /* ================= auto-wire ================= */

  document.addEventListener("DOMContentLoaded", () => {
    // Full Name
    document.querySelectorAll('[data-validate="full-name"]').forEach((input) => {
      wireInput({ input, validate: validateFullName });
    });

    // Email
    document.querySelectorAll('[data-validate="email"]').forEach((input) => {
      wireInput({ input, validate: validateEmail });
    });

    // Email uniqueness
    document.querySelectorAll('[data-unique="accounts_email"]').forEach((input) => {
      wireUnique({
        input,
        type: "email",
        message: "Email is already in use.",
      });
    });

    // Phone (PH)
    document.querySelectorAll('[data-validate="phone-ph"]').forEach((input) => {
      wireInput({
        input,
        validate: validatePHMobile,
        filter: (el) => (el.value = el.value.replace(/\D/g, "").slice(0, 10)),
      });
    });

    // Phone uniqueness
    document.querySelectorAll('[data-unique="accounts_phone"]').forEach((input) => {
      wireUnique({
        input,
        type: "phone",
        message: "Phone number is already in use.",
      });
    });

    // Clinic contact uniqueness
    document.querySelectorAll('[data-unique="clinic_contact"]').forEach((input) => {
      wireUnique({
        input,
        type: "clinic_contact",
        message: "Contact number is already in use.",
      });
    });

    
    // Clinic email uniqueness (optional)
    document.querySelectorAll('[data-unique="clinic_email"]').forEach((input) => {
      wireUnique({
        input,
        type: "clinic_email",
        message: "Clinic email is already in use.",
      });
    });

    // Business ID uniqueness
    document.querySelectorAll('[data-unique="clinic_business_id"]').forEach((input) => {
      wireUnique({
        input,
        type: "clinic_business_id",
        message: "Business ID is already registered.",
      });
    });

// Business ID
    document.querySelectorAll('[data-validate="business-id-10"]').forEach((input) => {
      wireInput({
        input,
        validate: validateBusinessId10,
        filter: (el) => (el.value = el.value.replace(/\D/g, "").slice(0, 10)),
      });
    });

    // Password (required)
    document.querySelectorAll('[data-validate="password"]').forEach((input) => {
      wireInput({ input, validate: validatePassword });
    });

    // Password optional
    document.querySelectorAll('[data-validate="password-optional"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordOptional });
    });

    // Confirm password
    document.querySelectorAll('[data-validate="password-confirm"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordConfirm });
      syncConfirmPassword(input);
    });

    // Age 18+
    document.querySelectorAll('[data-validate="age-18"]').forEach((input) => {
      wireInput({ input, validate: validateAge18 });
    });

    // Required selects
    document.querySelectorAll("select[required]").forEach((sel) => {
      wireRequiredSelect(sel);
    });

    // Password toggles
    initPasswordToggles();
  });
})();