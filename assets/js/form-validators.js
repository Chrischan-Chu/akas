// assets/js/form-validators.js
(() => {
  /* ================= helpers ================= */

  const showError = (el) => {
    el.classList.add("ring-2", "ring-red-400");
  };

  const clearError = (el) => {
    el.classList.remove("ring-2", "ring-red-400");
  };

  const debounce = (fn, ms = 400) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  // Resolve app base URL from the script tag that loaded this file.
  // This avoids hardcoding "/AKAS" or assuming the site is at web root.
  const getAppBaseUrl = () => {
    const script = document.querySelector('script[src*="/assets/js/form-validators.js"]');
    if (!script?.src) return "";

    try {
      const u = new URL(script.src, window.location.href);
      // Strip "/assets/js/form-validators.js" from pathname
      u.pathname = u.pathname.replace(/\/assets\/js\/form-validators\.js(?:\?.*)?$/, "");
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

  const checkUnique = async ({ type, value }) => {
    const params = new URLSearchParams({ type, value });
    const url = `${UNIQUE_ENDPOINT}?${params.toString()}`;

    try {
      const res = await fetch(url, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      const data = await res.json();
      return data?.ok ? !!data.available : true; // fail-open
    } catch {
      return true; // fail-open
    }
  };

  const wireUnique = ({ input, type, message }) => {
    if (!input) return;
    if (input.dataset.uniqueBound === "1") return;
    input.dataset.uniqueBound = "1";

    const run = debounce(async () => {
      const v = (input.value || "").trim();
      if (!v) return;

      // Only hit server if the field is already valid format-wise.
      // (Your existing validators + native constraints handle format.)
      if (!input.checkValidity()) return;

      const available = await checkUnique({ type, value: v });
      if (!available) {
        showError(input);
        input.setCustomValidity(message);
      } else {
        if (input.validationMessage === message) input.setCustomValidity("");
        clearError(input);
      }
    }, 450);

    // Blur is a good UX balance (no spam while typing)
    input.addEventListener("blur", run);

    // If they start typing again, clear our message
    input.addEventListener("input", () => {
      if (input.validationMessage === message) input.setCustomValidity("");
    });
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
      // required fields will be handled by Next/Submit
      if (v === "") {
        clearError(input);
        input.setCustomValidity("");
        return;
      }

      // strict on blur ONLY if they typed something
      const { ok } = validate(v, input, "blur");
      if (!ok) showError(input);
      else clearError(input);

      applyValidity(v, "blur");
    });

    input.closest("form")?.addEventListener("submit", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      applyValidity(v, "submit"); // strict on submit
    });
  };

  /**
   * Required selects: customize the built-in message
   * - No red ring when empty on blur (until submit)
   * - On invalid/submit, show data-required-msg if provided
   */
  const wireRequiredSelect = (select) => {
    if (!select) return;

    const requiredMsg = (select.dataset.requiredMsg || "Please select an option.").trim();

    const syncUI = () => {
      const v = (select.value || "").trim();
      if (v === "") clearError(select);
      else clearError(select);
    };

    // When the browser triggers invalid, replace message
    select.addEventListener("invalid", () => {
      const v = (select.value || "").trim();
      if (v === "") {
        select.setCustomValidity(requiredMsg);
      } else {
        select.setCustomValidity("");
      }
    });

    select.addEventListener("change", () => {
      select.setCustomValidity("");
      syncUI();
    });

    select.addEventListener("blur", () => {
      // no red ring for empty; message handled by Next/Submit
      syncUI();
      if ((select.value || "").trim() === "") {
        select.setCustomValidity("");
      }
    });

    select.closest("form")?.addEventListener("submit", () => {
      const v = (select.value || "").trim();
      select.setCustomValidity(v === "" ? requiredMsg : "");
    });
  };

  /* ================= validators ================= */

  const validateFullName = (val) => {
  const v = (val || "").trim();

  if (!v) return { ok: true, message: "" };

  if (!/^[A-Za-z]+(?:\s[A-Za-z]+)*$/.test(v)) {
    return { ok: false, message: "Full Name must contain letters and spaces only." };
  }

  return { ok: true, message: "" };
};

  const validateEmail = (val) => {
    const v = (val || "").trim();

    // ✅ allow empty (optional fields)
    if (v === "") return { ok: true, message: "" };

    const ok = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(v);
    return {
      ok,
      message: ok ? "" : "Enter a valid email (ex: name@gmail.com).",
    };
  };

  const validatePHMobile = (val, input, phase = "input") => {
    const v = (val || "").trim();

    // while typing: don't show red until they reach 10 digits
    if (phase === "input" && v.length < 10) {
      return { ok: true, message: "" };
    }

    // blur/submit: must be exactly 10 digits and start with 9
    const ok = /^9\d{9}$/.test(v);
    return {
      ok,
      message: ok ? "" : "Enter a valid PH mobile number (ex: 9123456789).",
    };
  };

  const validateBusinessId10 = (val, input, phase = "input") => {
    const v = (val || "").trim();

    // while typing: don't show red until they reach 10 digits
    if (phase === "input" && v.length < 10) {
      return { ok: true, message: "" };
    }

    // blur/submit: must be exactly 10 digits
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

  // Optional new password: empty is OK, otherwise reuse validatePassword
  const validatePasswordOptional = (val) => {
    const v = (val || "").trim();
    if (v === "") return { ok: true, message: "" };
    return validatePassword(v);
  };

  // Confirm password must match target (optional-friendly)
  const validatePasswordConfirm = (val, input) => {
    const v = (val || "").trim();
    const targetName = input.dataset.match; // e.g. "password" or "new_password"
    const other = document.querySelector(`[name="${targetName}"]`);
    const otherVal = (other?.value || "").trim();

    // both empty -> ok (optional)
    if (v === "" && otherVal === "") return { ok: true, message: "" };

    const ok = v !== "" && v === otherVal;
    return { ok, message: ok ? "" : "Passwords do not match." };
  };

  const validateAge18 = (val) => {
  const v = (val || "").trim();
  if (!v) return { ok: true, message: "" };

  const birth = new Date(v);
  const today = new Date();

  // remove time
  birth.setHours(0, 0, 0, 0);
  today.setHours(0, 0, 0, 0);

  // ✅ check future first
  if (birth > today) {
    return { ok: false, message: "Birth date cannot be in the future." };
  }

  // calculate age
  let age = today.getFullYear() - birth.getFullYear();
  const m = today.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
    age--;
  }

  if (age < 18) {
    return { ok: false, message: "You must be at least 18 years old." };
  }

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
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M9.88 5.08A10.94 10.94 0 0112 4c7 0 11 8 11 8a18.46 18.46 0 01-3.12 4.47"/>
      <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        d="M6.1 6.1C3.9 7.8 1 12 1 12s4 8 11 8c1.2 0 2.33-.2 3.4-.57"/>
    </svg>
  `;

  const initPasswordToggles = () => {
    document.querySelectorAll(".toggle-pass").forEach((btn) => {
      // prevent double-binding if partial reloads
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";

      // set default icon if empty
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
      wireInput({
        input,
        validate: validateFullName
      });
    });

    // Email
    document.querySelectorAll('[data-validate="email"]').forEach((input) => {
      wireInput({ input, validate: validateEmail });
    });

    // Email uniqueness (only when explicitly marked)
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

    // Phone uniqueness (accounts.phone)
    document.querySelectorAll('[data-unique="accounts_phone"]').forEach((input) => {
      wireUnique({
        input,
        type: "phone",
        message: "Phone number is already in use.",
      });
    });

    // Clinic contact uniqueness (clinics.contact)
    document.querySelectorAll('[data-unique="clinic_contact"]').forEach((input) => {
      wireUnique({
        input,
        type: "clinic_contact",
        message: "Contact number is already in use.",
      });
    });

    // Business ID (10 digits)
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

    // Password (optional - settings)
    document.querySelectorAll('[data-validate="password-optional"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordOptional });
    });

    // Confirm password match
    document.querySelectorAll('[data-validate="password-confirm"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordConfirm });
      syncConfirmPassword(input); 
      
    });

    // Age 18+
    document.querySelectorAll('[data-validate="age-18"]').forEach((input) => {
      wireInput({ input, validate: validateAge18 });
    });

    // Required select custom messages
    document.querySelectorAll('select[required]').forEach((sel) => {
      wireRequiredSelect(sel);
    });

    // Password toggles
    initPasswordToggles();
  });
})();
