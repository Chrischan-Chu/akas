// assets/js/form-validators.js
(() => {
  /* ================= helpers ================= */

  const showError = (el) => {
    el.classList.add("ring-2", "ring-red-400", "ring-offset-0");
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

  const inlineEnabled = (inputOrForm) => {
    const form =
      inputOrForm?.tagName === "FORM" ? inputOrForm : inputOrForm?.closest?.("form");
    return form?.dataset?.inlineErrors === "1";
  };

  const getRequiredMsg = (el) => {
    return (el?.dataset?.requiredMsg || "").trim() || "Please fill out this field.";
  };

  const getFieldContainer = (input) => {
    if (!input) return null;

    const field = input.closest(".field");
    if (field) return field;

    const rel = input.closest(".relative");
    if (rel) return rel;

    // ✅ your signup-user.php uses simple <div> wrappers
    return input.parentElement;
  };

  const getErrorTextEl = (input) => {
    const container = getFieldContainer(input);
    if (!container) return null;

    const key = input.name || input.id || "field";

    // ✅ if your HTML already has <p data-err-for="..."> use it
    let p = container.querySelector(`p[data-err-for="${CSS.escape(key)}"]`);
    if (p) return p;

    // ✅ else create one (but mark it)
    p = document.createElement("p");
    p.dataset.errFor = key;
    p.dataset.inlineGenerated = "1";
    p.className = "mt-1 text-sm font-semibold text-red-600 leading-snug";
    p.style.color = "rgb(220 38 38)"; // fallback red
    container.appendChild(p);
    return p;
  };

  const showInlineError = (input, message) => {
    if (!inlineEnabled(input)) return;
    const p = getErrorTextEl(input);
    if (!p) return;

    // ✅ force red style even if something overrides it
    p.classList.remove("text-black", "text-slate-700", "text-white");
    p.classList.add("text-red-600", "font-semibold");
    p.style.color = "rgb(220 38 38)";

    p.textContent = message || "";
  };

  const clearInlineError = (input) => {
    if (!inlineEnabled(input)) return;

    const container = getFieldContainer(input);
    if (!container) return;

    const key = input.name || input.id || "field";
    const p = container.querySelector(`p[data-err-for="${CSS.escape(key)}"]`);
    if (!p) return;

    // ✅ IMPORTANT:
    // If the <p> exists in your HTML template, DO NOT remove it (keeps layout stable).
    // Only remove if we created it dynamically.
    if (p.dataset.inlineGenerated === "1") {
      p.remove();
    } else {
      p.textContent = "";
    }
  };

  const getAppBaseUrl = () => {
    const script = document.querySelector('script[src*="/assets/js/form-validators.js"]');
    if (!script?.src) return "";

    try {
      const u = new URL(script.src, window.location.href);
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
      return data?.ok ? !!data.available : true;
    } catch {
      return true;
    }
  };

  /* ================= wiring ================= */

  const wireUnique = ({ input, type, message }) => {
    if (!input) return;
    if (input.dataset.uniqueBound === "1") return;
    input.dataset.uniqueBound = "1";

    input.dataset.uniqueMsg = message;

    let controller = null;

    const runCheckNow = async () => {
      const v = (input.value || "").trim();

      if (!v) return true;
      if (!input.checkValidity()) return true;

      try { controller?.abort(); } catch {}
      controller = new AbortController();

      const available = await checkUnique({
        type,
        value: v,
        signal: controller.signal,
      });

      if (!available) {
        showError(input);
        input.setCustomValidity(message);
        showInlineError(input, message);
        return false;
      }

      if (input.validationMessage === message) input.setCustomValidity("");
      clearError(input);
      clearInlineError(input);
      return true;
    };

    input.addEventListener("blur", debounce(runCheckNow, 120));

    input.addEventListener("input", () => {
      if (input.validationMessage === message) input.setCustomValidity("");
      clearError(input);
      clearInlineError(input);
    });

    const form = input.closest("form");
    if (!form) return;

    if (!form.__uniqueChecks) form.__uniqueChecks = [];
    form.__uniqueChecks.push({ input, runCheckNow });

    if (form.dataset.uniqueSubmitBound === "1") return;
    form.dataset.uniqueSubmitBound = "1";

    form.addEventListener(
      "submit",
      async (e) => {
        if (form.dataset.uniqueSubmitLock === "1") return;

        e.preventDefault();

        const checks = Array.isArray(form.__uniqueChecks) ? form.__uniqueChecks : [];
        for (const c of checks) {
          const ok = await c.runCheckNow();
          if (!ok) {
            c.input.focus();
            return;
          }
        }

        if (!form.checkValidity()) {
          const invalids = Array.from(form.querySelectorAll(":invalid"));
          invalids.forEach((el) => {
            showError(el);
            showInlineError(el, el.validationMessage || "Invalid value.");
          });
          invalids[0]?.focus();
          return;
        }

        form.dataset.uniqueSubmitLock = "1";
        try {
          form.requestSubmit();
        } finally {
          setTimeout(() => delete form.dataset.uniqueSubmitLock, 0);
        }
      },
      true
    );
  };

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
      const uniqueMsg = input.dataset.uniqueMsg;
      if (uniqueMsg && input.validationMessage === uniqueMsg) return;

      if (v === "") {
        input.setCustomValidity("");
        clearInlineError(input);
        return;
      }

      const { ok, message } = validate(v, input, phase);
      input.setCustomValidity(ok ? "" : message);

      if (!ok) showInlineError(input, message);
      else clearInlineError(input);
    };

    input.addEventListener("input", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      applyUI(v);
      input.setCustomValidity("");
      clearInlineError(input);
    });

    input.addEventListener("blur", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      if (v === "") {
        clearError(input);

        if (input.required && inlineEnabled(input)) {
          const msg = getRequiredMsg(input);
          input.setCustomValidity(msg);
          showInlineError(input, msg);
          showError(input);
        } else {
          clearInlineError(input);
          input.setCustomValidity("");
        }
        return;
      }

      const { ok } = validate(v, input, "blur");
      if (!ok) showError(input);
      else clearError(input);

      applyValidity(v, "blur");
    });

    input.addEventListener("invalid", (e) => {
      showError(input);
      if (inlineEnabled(input)) showInlineError(input, input.validationMessage || "Invalid value.");
      e.preventDefault?.();
    });

    input.closest("form")?.addEventListener("submit", () => {
      if (typeof filter === "function") filter(input);
      const v = (input.value || "").trim();

      if (v === "" && input.required && inlineEnabled(input)) {
        showError(input);
        const msg = getRequiredMsg(input);
        input.setCustomValidity(msg);
        showInlineError(input, msg);
        return;
      }

      applyValidity(v, "submit");
    });
  };

  const wireRequiredSelect = (select) => {
    if (!select) return;

    const requiredMsg = (select.dataset.requiredMsg || "Please select an option.").trim();
    const isEmpty = () => (select.value == null || String(select.value).trim() === "");

    const setState = (bad) => {
      if (!inlineEnabled(select)) {
        if (bad) showError(select);
        else clearError(select);
        return;
      }

      if (bad) {
        select.setCustomValidity(requiredMsg);
        showError(select);
        showInlineError(select, requiredMsg);
      } else {
        select.setCustomValidity("");
        clearError(select);
        clearInlineError(select);
      }
    };

    select.addEventListener("change", () => setState(isEmpty()));
    select.addEventListener("blur", () => setState(isEmpty()));
    select.closest("form")?.addEventListener("submit", () => setState(isEmpty()));
  };

  /* ================= validators ================= */

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

    if (birth > today) return { ok: false, message: "Birthdate cannot be in the future." };

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

  // mark confirm as "touched" only when user actually interacts with it
  const markTouched = () => (confirmInput.dataset.touched = "1");
  confirmInput.addEventListener("input", markTouched);
  confirmInput.addEventListener("blur", markTouched);

  passInput.addEventListener("input", () => {
    const confirmVal = (confirmInput.value || "").trim();

    // ✅ If confirm is still empty and user hasn't touched it, do NOTHING (no red)
    if (!confirmVal && confirmInput.dataset.touched !== "1") {
      confirmInput.setCustomValidity("");
      clearError(confirmInput);
      clearInlineError(confirmInput);
      return;
    }

    // ✅ If confirm has value OR was touched already, re-validate it
    confirmInput.dispatchEvent(new Event("blur"));
  });
};

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('[data-validate="full-name"]').forEach((input) => {
      wireInput({ input, validate: validateFullName });
    });

    document.querySelectorAll('[data-validate="email"]').forEach((input) => {
      wireInput({ input, validate: validateEmail });
    });

    document.querySelectorAll('[data-unique="accounts_email"]').forEach((input) => {
      wireUnique({ input, type: "email", message: "Email is already in use." });
    });

    document.querySelectorAll('[data-validate="phone-ph"]').forEach((input) => {
      wireInput({
        input,
        validate: validatePHMobile,
        filter: (el) => (el.value = el.value.replace(/\D/g, "").slice(0, 10)),
      });
    });

    document.querySelectorAll('[data-unique="accounts_phone"]').forEach((input) => {
      wireUnique({ input, type: "phone", message: "Phone number is already in use." });
    });

    document.querySelectorAll('[data-unique="clinic_contact"]').forEach((input) => {
      wireUnique({ input, type: "clinic_contact", message: "Phone number is already in use." });
    });

    document.querySelectorAll('[data-unique="clinic_email"]').forEach((input) => {
      wireUnique({ input, type: "clinic_email", message: "Email is already in use." });
    });

    document.querySelectorAll('[data-unique="clinic_business_id"]').forEach((input) => {
      wireUnique({ input, type: "clinic_business_id", message: "Business ID is already registered." });
    });

    document.querySelectorAll('[data-validate="business-id-10"]').forEach((input) => {
      wireInput({
        input,
        validate: validateBusinessId10,
        filter: (el) => (el.value = el.value.replace(/\D/g, "").slice(0, 10)),
      });
    });

    document.querySelectorAll('[data-validate="password"]').forEach((input) => {
      wireInput({ input, validate: validatePassword });
    });

    document.querySelectorAll('[data-validate="password-optional"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordOptional });
    });

    document.querySelectorAll('[data-validate="password-confirm"]').forEach((input) => {
      wireInput({ input, validate: validatePasswordConfirm });
      syncConfirmPassword(input);
    });

    document.querySelectorAll('[data-validate="age-18"]').forEach((input) => {
      wireInput({ input, validate: validateAge18 });
    });

    document.querySelectorAll("select[required]").forEach((sel) => {
      wireRequiredSelect(sel);
    });
    // Disable Enter-to-submit (prevents accidental submits)
    document.querySelectorAll('form[data-inline-errors="1"]').forEach((form) => {
    form.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
    }
  });
});
  });
})();