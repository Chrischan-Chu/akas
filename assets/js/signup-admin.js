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
    const wrapped = (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
    wrapped.cancel = () => clearTimeout(t);
    return wrapped;
  };

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
    const backBtn = document.getElementById("backBtn"); // hidden in step2 but kept for JS
    const googleStep1Block = document.getElementById("googleStep1Block");
    const topBackLink = document.getElementById("topBackLink");

    const specialtySelect = document.getElementById("specialtySelect");
    const otherWrap = document.getElementById("otherSpecialtyWrap");
    const otherInput = otherWrap?.querySelector('input[name="specialty_other"]');

    // Step indicator elements
    const stepPill1 = document.getElementById("stepPill1");
    const stepPill2 = document.getElementById("stepPill2");
    const stepDot1 = document.getElementById("stepDot1");
    const stepDot2 = document.getElementById("stepDot2");
    const stepLine = document.getElementById("stepLine");

    const ORANGE = "#ffa154";

const setStepIndicator = (n) => {
  if (!stepPill1 || !stepPill2 || !stepDot1 || !stepDot2) return;

  const setActive = (pill, dot) => {
    pill.classList.remove("opacity-70", "bg-white/5", "border-white/15");
    pill.classList.add("bg-white/15", "border-white/25", "opacity-100");

    dot.classList.remove("bg-white/30");
    dot.style.background = ORANGE;
  };

  const setInactive = (pill, dot) => {
    pill.classList.add("opacity-70");
    pill.classList.remove("bg-white/15", "border-white/25", "opacity-100");
    pill.classList.add("bg-white/5", "border-white/15");

    dot.style.background = "";
    dot.classList.add("bg-white/30");
  };

  const setCompletedDotOnly = (pill, dot) => {
    // ✅ pill stays like inactive (NOT highlighted)
    pill.classList.add("opacity-70");
    pill.classList.remove("bg-white/15", "border-white/25", "opacity-100");
    pill.classList.add("bg-white/5", "border-white/15");

    // ✅ but dot becomes orange
    dot.classList.remove("bg-white/30");
    dot.style.background = ORANGE;
  };

  if (n === 1) {
    setActive(stepPill1, stepDot1);
    setInactive(stepPill2, stepDot2);
    if (stepLine) stepLine.className = "w-10 h-[3px] rounded-full bg-white/30";
  } else {
    // ✅ Step 1 completed (dot only), Step 2 active (highlighted)
    setCompletedDotOnly(stepPill1, stepDot1);
    setActive(stepPill2, stepDot2);
    if (stepLine) stepLine.className = "w-10 h-[3px] rounded-full bg-white/60";
  }
};

    if (!wizard || !step1 || !step2 || !nextBtn) return;

    // remember originally required
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

        if (googleStep1Block) googleStep1Block.style.display = "";
        setStepIndicator(1);

        document.getElementById("titleStep2Wrap")?.classList.add("hidden");
        document.getElementById("titleStep1Wrap")?.classList.remove("hidden");

        if (topBackLink) topBackLink.textContent = "← Back to selection";
        return;
      }

      step1.classList.remove("active");
      step2.classList.add("active");
      toggleStepRequired(step1, false);
      toggleStepRequired(step2, true);

      if (googleStep1Block) googleStep1Block.style.display = "none";
      setStepIndicator(2);

      document.getElementById("titleStep1Wrap")?.classList.add("hidden");
      document.getElementById("titleStep2Wrap")?.classList.remove("hidden");

      if (topBackLink) topBackLink.textContent = "← Back";
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
    let controller = null;
    const UNIQUE_MSG = "Email is already in use.";

    const runEmailUnique = async () => {
      if (!emailInput) return true;

      const v = (emailInput.value || "").trim();
      if (!v) return false;
      if (!emailInput.checkValidity()) return false;

      try { controller?.abort(); } catch {}
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

      if (emailInput.validationMessage === UNIQUE_MSG) emailInput.setCustomValidity("");
      clearError(emailInput);
      return true;
    };

    const debouncedUnique = debounce(async () => {
      await runEmailUnique();
    }, 250);

    emailInput?.addEventListener("input", () => {
      if (emailInput.validationMessage === UNIQUE_MSG) emailInput.setCustomValidity("");
      clearError(emailInput);
      debouncedUnique();
    });

    emailInput?.addEventListener("blur", () => {
      debouncedUnique.cancel?.();
      runEmailUnique();
    });

    const paintStep1Invalids = () => {
      const controls = step1.querySelectorAll("input, select, textarea");
      let firstInvalid = null;

      for (const el of controls) {
        if (el.closest(".hidden")) continue;
        if (el.disabled) continue;
        if (el.type === "file") continue;

        if (!el.checkValidity()) {
          showError(el);
          if (!firstInvalid) firstInvalid = el;
        } else {
          if (!(el === emailInput && el.validationMessage === UNIQUE_MSG)) {
            clearError(el);
          }
        }
      }

      return firstInvalid;
    };

    nextBtn.addEventListener("click", async () => {
      const firstInvalid = paintStep1Invalids();
      if (firstInvalid) {
        firstInvalid.focus();
        firstInvalid.reportValidity();
        return;
      }

      const uniqueOk = await runEmailUnique();
      if (!uniqueOk) {
        emailInput?.focus();
        emailInput?.reportValidity();
        return;
      }

      showStep(2);
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    // top back behavior (step2 -> step1)
    topBackLink?.addEventListener("click", (e) => {
      const qs = new URLSearchParams(window.location.search);
      const locked = qs.get("locked") === "1";

      if (locked) return; // google-locked stays as normal link

      if (step2.classList.contains("active")) {
        e.preventDefault();
        showStep(1);
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });

    // hidden back button if you ever call it
    backBtn?.addEventListener("click", () => {
      showStep(1);
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    /* ---------- Handle Google locked Step 2 ---------- */

    const qs = new URLSearchParams(window.location.search);
    const locked = qs.get("locked") === "1";

    if (locked) {
      showStep(2);

      // hide step 1 and disable its inputs
      step1.classList.remove("active");
      step1.style.display = "none";
      step1.querySelectorAll("input, select, textarea").forEach((el) => (el.disabled = true));

      if (googleStep1Block) googleStep1Block.style.display = "none";

      // keep top link as selection for locked mode
      if (topBackLink) topBackLink.textContent = "← Back to selection";
    } else {
      showStep(1);
    }
  });
})();