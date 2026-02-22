// assets/js/signup-admin.js
(() => {
  "use strict";

  /* ---------------- helpers ---------------- */

  const showError = (el) => {
    if (!el) return;
    el.classList.add("ring-2", "ring-red-400");
  };

  const clearError = (el) => {
    if (!el) return;
    el.classList.remove("ring-2", "ring-red-400");
  };

  const debounce = (fn, ms = 200) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  // Resolve app base URL from THIS script tag so it works at / or /akas etc.
  const getAppBaseUrl = () => {
    const script = document.querySelector('script[src*="/assets/js/signup-admin.js"]');
    if (!script?.src) return "";

    try {
      const u = new URL(script.src, window.location.href);
      u.pathname = u.pathname.replace(/\/assets\/js\/signup-admin\.js(?:\?.*)?$/, "");
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
      return data?.ok ? !!data.available : true; // fail-open
    } catch {
      return true; // fail-open
    }
  };

  /* ---------------- main ---------------- */

  document.addEventListener("DOMContentLoaded", () => {
    const wizard = document.getElementById("signupWizard");
    const step1 = document.getElementById("step1");
    const step2 = document.getElementById("step2");

    const nextBtn = document.getElementById("nextBtn");
    const backBtn = document.getElementById("backBtn");

    const specialtySelect = document.getElementById("specialtySelect");
    const otherWrap = document.getElementById("otherSpecialtyWrap");
    const otherInput = otherWrap?.querySelector('input[name="specialty_other"]');

    if (!wizard || !step1 || !step2 || !nextBtn) return;

    // Remember which were originally required, so toggling steps doesn't permanently break it
    wizard.querySelectorAll("[required]").forEach((el) => {
      el.dataset.wasRequired = "1";
    });

    const toggleStepRequired = (container, enabled) => {
      container.querySelectorAll('[data-was-required="1"]').forEach((el) => {
        el.required = !!enabled;
      });
    };

    const showStep = (n) => {
      if (n === 1) {
        step1.classList.add("active");
        step2.classList.remove("active");
        toggleStepRequired(step1, true);
        toggleStepRequired(step2, false);
        return;
      }

      step1.classList.remove("active");
      step2.classList.add("active");
      toggleStepRequired(step1, false);
      toggleStepRequired(step2, true);
    };

    const toggleOtherSpecialty = () => {
      const isOther = specialtySelect?.value === "Other";
      if (!otherWrap || !otherInput) return;

      if (isOther) {
        otherWrap.classList.remove("hidden");
        otherInput.required = true;
      } else {
        otherWrap.classList.add("hidden");
        otherInput.required = false;
        otherInput.value = "";
        otherInput.setCustomValidity("");
        clearError(otherInput);
      }
    };

    specialtySelect?.addEventListener("change", toggleOtherSpecialty);
    toggleOtherSpecialty();

    /* ---------- Step 1: soft-gating on Next click ---------- */

    const emailInput = step1.querySelector('input[name="email"][data-unique="accounts_email"]');

    // uniqueness check state (prevents race conditions)
    let controller = null;
    const UNIQUE_MSG = "Email is already in use.";

    const runEmailUnique = async () => {
      if (!emailInput) return true;

      const v = (emailInput.value || "").trim();

      // empty/invalid format: let normal HTML + form-validators handle it
      if (!v) return false;
      if (!emailInput.checkValidity()) return false;

      try {
        controller?.abort();
      } catch {}
      controller = new AbortController();

      const available = await checkUnique({
        type: "email",
        value: v,
        signal: controller.signal,
      });

      if (!available) {
        emailInput.setCustomValidity(UNIQUE_MSG);
        showError(emailInput);
        return false;
      }

      // clear ONLY our uniqueness message
      if (emailInput.validationMessage === UNIQUE_MSG) emailInput.setCustomValidity("");
      clearError(emailInput);
      return true;
    };

    // Faster email uniqueness: check on blur + after user pauses typing
    const debouncedUnique = debounce(async () => {
      await runEmailUnique();
    }, 250);

    emailInput?.addEventListener("input", () => {
      // as they type, remove uniqueness error and re-check after pause
      if (emailInput.validationMessage === UNIQUE_MSG) emailInput.setCustomValidity("");
      clearError(emailInput);
      debouncedUnique();
    });

    emailInput?.addEventListener("blur", () => {
      // check immediately on blur (no ring on empty; handled by validators)
      debouncedUnique.cancel?.();
      runEmailUnique();
    });

    // Paint invalid fields red ONLY when Next is clicked
    const paintStep1Invalids = () => {
      const controls = step1.querySelectorAll("input, select, textarea");
      let firstInvalid = null;

      for (const el of controls) {
        if (el.closest(".hidden")) continue;
        if (el.disabled) continue;
        if (el.type === "file") continue; // Work ID optional

        if (!el.checkValidity()) {
          showError(el);
          if (!firstInvalid) firstInvalid = el;
        } else {
          // Don't clear if email has uniqueness error
          if (!(el === emailInput && el.validationMessage === UNIQUE_MSG)) {
            clearError(el);
          }
        }
      }

      return firstInvalid;
    };

    nextBtn.addEventListener("click", async () => {
      // 1) show required/format errors
      const firstInvalid = paintStep1Invalids();
      if (firstInvalid) {
        firstInvalid.focus();
        firstInvalid.reportValidity();
        return;
      }

      // 2) ensure email uniqueness before proceeding
      const uniqueOk = await runEmailUnique();
      if (!uniqueOk) {
        emailInput?.focus();
        emailInput?.reportValidity();
        return;
      }

      // ✅ proceed to step 2
      showStep(2);

      document.getElementById("titleStep1Wrap")?.classList.add("hidden");
      document.getElementById("titleStep2Wrap")?.classList.remove("hidden");

      const bar = document.getElementById("progressBar");
      if (bar) bar.style.width = "100%";

      document.getElementById("labelStep1")?.classList.add("opacity-60");
      document.getElementById("labelStep2")?.classList.remove("opacity-60");

      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    backBtn?.addEventListener("click", () => {
      showStep(1);

      document.getElementById("titleStep2Wrap")?.classList.add("hidden");
      document.getElementById("titleStep1Wrap")?.classList.remove("hidden");

      const bar = document.getElementById("progressBar");
      if (bar) bar.style.width = "50%";

      document.getElementById("labelStep2")?.classList.add("opacity-60");
      document.getElementById("labelStep1")?.classList.remove("opacity-60");

      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    /* ---------- Handle ?step=2 and Google-locked Step 2 ---------- */

    const qs = new URLSearchParams(window.location.search);
    const urlStep = qs.get("step");
    const locked = qs.get("locked") === "1";

    if (locked) {
      showStep(2);

      // hide step 1 and disable its inputs
      step1.classList.remove("active");
      step1.style.display = "none";
      step1.querySelectorAll("input, select, textarea").forEach((el) => (el.disabled = true));

      if (backBtn) backBtn.style.display = "none";

      document.getElementById("titleStep1Wrap")?.classList.add("hidden");
      document.getElementById("titleStep2Wrap")?.classList.remove("hidden");

      const bar = document.getElementById("progressBar");
      if (bar) bar.style.width = "100%";

      document.getElementById("labelStep1")?.classList.add("opacity-60");
      document.getElementById("labelStep2")?.classList.remove("opacity-60");
    } else if (urlStep === "2") {
      showStep(2);

      document.getElementById("titleStep1Wrap")?.classList.add("hidden");
      document.getElementById("titleStep2Wrap")?.classList.remove("hidden");

      const bar = document.getElementById("progressBar");
      if (bar) bar.style.width = "100%";

      document.getElementById("labelStep1")?.classList.add("opacity-60");
      document.getElementById("labelStep2")?.classList.remove("opacity-60");
    } else {
      showStep(1);
    }
  });
})();