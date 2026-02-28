// assets/js/contact.js
// Contact page logic: clinic details updater + available clinics pagination (4 per page) + blur-only validation

(function () {
  const onReady = (fn) => {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  };

  onReady(() => {
    /* ================= UI helpers ================= */
    
    // Remove ?contact_ok=1 after showing success
try {
  const url = new URL(window.location.href);
  if (url.searchParams.get("contact_ok") === "1") {
    url.searchParams.delete("contact_ok");
    history.replaceState({}, "", url.pathname + (url.hash || ""));
  }
} catch (e) {}

    const showError = (el) => {
      if (!el) return;
      el.classList.add("ring-2", "ring-red-400", "ring-offset-0");
      el.classList.add("focus:ring-red-400");
    };

    const clearError = (el) => {
      if (!el) return;
      el.classList.remove("ring-2", "ring-red-400", "ring-offset-0");
      el.classList.remove("focus:ring-red-400");
    };

    const setErrText = (p, msg) => {
      if (!p) return;
      p.textContent = msg || "";
      p.style.color = msg ? "rgb(220 38 38)" : ""; // always red for errors
    };

    const setClinicErr = (p, msg) => {
      if (!p) return;
      if (!msg) {
        p.style.display = "none";
        p.textContent = "";
        p.style.color = "";
        return;
      }
      p.style.display = "block";
      p.textContent = msg;
      p.style.color = "rgb(220 38 38)";
    };

    /* ================= validators (blur-only UI) ================= */
    const validateFullName = (val) => {
      const v = (val || "").trim().replace(/\s+/g, " ");
      if (!v) return { ok: false, message: "Full Name is required." };

      const msg = "You can only use letters and spacing (Maximum of 50 characters).";
      if (v.length > 50) return { ok: false, message: msg };
      if (!/^[A-Za-z]+(?:\s[A-Za-z]+)*$/.test(v)) return { ok: false, message: msg };
      return { ok: true, message: "" };
    };

    const validateEmail = (val) => {
      const v = (val || "").trim();
      if (!v) return { ok: false, message: "Email is required." };
      const ok = /^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test(v);
      return { ok, message: ok ? "" : "Enter a valid email (ex: name@gmail.com)." };
    };

    const validateMessage = (val) => {
      const v = (val || "").trim();
      if (!v) return { ok: false, message: "Message is required." };
      return { ok: true, message: "" };
    };

    /* ================= clinic details wiring ================= */
    const select = document.getElementById("clinicSelect");
    const hidden = document.getElementById("clinicHidden");

    const cdName = document.getElementById("cdName");
    const cdAddress = document.getElementById("cdAddress");
    const cdEmail = document.getElementById("cdEmail");
    const cdPhone = document.getElementById("cdPhone");

    const clinicErr = document.getElementById("clinicErr");

    const clinicSelected = () => !!(hidden && String(hidden.value || "").trim());

    const setClinicDetailsFromOption = (opt) => {
      if (!opt) return;

      const id = opt.value || "";
      if (hidden) hidden.value = id;

      const name = opt.getAttribute("data-name") || "—";
      const addressRaw = opt.getAttribute("data-address") || "—";
      const emailRaw = opt.getAttribute("data-email") || "—";
      const phoneRaw = opt.getAttribute("data-phone") || "—";

      const address = (addressRaw.trim() === "" || addressRaw.trim() === "-") ? "—" : addressRaw;
      const email = (emailRaw.trim() === "" || emailRaw.trim() === "-") ? "—" : emailRaw;
      const phone = (phoneRaw.trim() === "" || phoneRaw.trim() === "-") ? "—" : phoneRaw;

      if (cdName) cdName.textContent = name;
      if (cdAddress) { cdAddress.textContent = address; cdAddress.title = address; }
      if (cdEmail) cdEmail.textContent = email;
      if (cdPhone) cdPhone.textContent = phone;
    };

    if (select) {
      select.addEventListener("change", () => {
        const opt = select.options[select.selectedIndex];
        setClinicDetailsFromOption(opt);
        setClinicErr(clinicErr, ""); // clear clinic required msg once selected
        syncSend();
      });

      // on load, if a value is already selected (rare), sync details
      if (select.value) {
        const opt = select.options[select.selectedIndex];
        setClinicDetailsFromOption(opt);
      }
    }

    /* ================= available clinics pagination ================= */
    const wrap = document.getElementById("clinicCards");
    const cards = wrap ? Array.from(wrap.querySelectorAll(".clinic-card")) : [];
    const prev = document.getElementById("namesPrev");
    const next = document.getElementById("namesNext");

    const pageSize = 4;
    let page = 0;

    const renderCards = () => {
      if (!cards.length) return;

      const total = cards.length;
      const maxPage = Math.max(0, Math.ceil(total / pageSize) - 1);
      if (page < 0) page = 0;
      if (page > maxPage) page = maxPage;

      const start = page * pageSize;
      const end = start + pageSize;

      cards.forEach((card, idx) => {
        card.style.display = (idx >= start && idx < end) ? "" : "none";
      });

      if (prev) prev.disabled = page === 0;
      if (next) next.disabled = page === maxPage;
    };

    if (prev) prev.addEventListener("click", () => { page--; renderCards(); });
    if (next) next.addEventListener("click", () => { page++; renderCards(); });

    renderCards(); // ✅ ensures only 4 show at start

    /* ================= form validation ================= */
    const form = document.getElementById("contactForm");
    const btnSend = document.getElementById("btnSend");
    const btnClear = document.getElementById("btnClear");

    const iName = document.getElementById("fullName");
    const iEmail = document.getElementById("email");
    const iMsg = document.getElementById("message");

    const pName = document.getElementById("errFullName");
    const pEmail = document.getElementById("errEmail");
    const pMsg = document.getElementById("errMessage");

    // ✅ Sending-state lock (prevents double submit)
    let isSubmitting = false;
    const originalSendText = btnSend ? (btnSend.textContent || "Send") : "Send";

    const setSendEnabled = (enabled) => {
      if (!btnSend) return;

      // If we're already submitting, keep it disabled no matter what.
      if (isSubmitting) {
        btnSend.disabled = true;
        btnSend.style.opacity = ".65";
        btnSend.style.cursor = "not-allowed";
        return;
      }

      btnSend.disabled = !enabled;
      btnSend.style.opacity = enabled ? "1" : ".65";
      btnSend.style.cursor = enabled ? "pointer" : "not-allowed";
    };

    function syncSend() {
      const enabled =
        clinicSelected() &&
        validateFullName(iName?.value).ok &&
        validateEmail(iEmail?.value).ok &&
        validateMessage(iMsg?.value).ok;

      setSendEnabled(enabled);
    }

    // ✅ Some browsers autofill/persist form values AFTER DOMContentLoaded
    // without firing input events. Run a few delayed sync checks so the Send
    // button state matches the actual field values.
    [0, 80, 200, 450, 900].forEach((ms) => setTimeout(syncSend, ms));

    // ✅ Also re-check whenever the user focuses anywhere in the form.
    // This covers cases where values are restored by the browser session.
    if (form) {
      form.addEventListener("focusin", () => setTimeout(syncSend, 0));
      form.addEventListener("change", () => setTimeout(syncSend, 0));
    }

    // input: DO NOT validate, just clear UI while typing (less distracting)
    if (iName) iName.addEventListener("input", () => { clearError(iName); setErrText(pName, ""); syncSend(); });
    if (iEmail) iEmail.addEventListener("input", () => { clearError(iEmail); setErrText(pEmail, ""); syncSend(); });
    if (iMsg) iMsg.addEventListener("input", () => { clearError(iMsg); setErrText(pMsg, ""); syncSend(); });

    // blur: validate
    if (iName) iName.addEventListener("blur", () => {
      const r = validateFullName(iName.value);
      if (!r.ok) { showError(iName); setErrText(pName, r.message); }
      else { clearError(iName); setErrText(pName, ""); }
      syncSend();
    });

    if (iEmail) iEmail.addEventListener("blur", () => {
      const r = validateEmail(iEmail.value);
      if (!r.ok) { showError(iEmail); setErrText(pEmail, r.message); }
      else { clearError(iEmail); setErrText(pEmail, ""); }
      syncSend();
    });

    if (iMsg) iMsg.addEventListener("blur", () => {
      const r = validateMessage(iMsg.value);
      if (!r.ok) { showError(iMsg); setErrText(pMsg, r.message); }
      else { clearError(iMsg); setErrText(pMsg, ""); }
      syncSend();
    });

    // submit: enforce clinic selected + enforce validation + ✅ Sending state
    if (form) {
      form.addEventListener("submit", (ev) => {
        // ✅ already submitting? block double click
        if (isSubmitting) {
          ev.preventDefault();
          return;
        }

        let ok = true;

        if (!clinicSelected()) {
          setClinicErr(clinicErr, "Please select a clinic before sending your message.");
          ok = false;
        } else {
          setClinicErr(clinicErr, "");
        }

        const rN = validateFullName(iName?.value);
        const rE = validateEmail(iEmail?.value);
        const rM = validateMessage(iMsg?.value);

        if (!rN.ok) { showError(iName); setErrText(pName, rN.message); ok = false; }
        if (!rE.ok) { showError(iEmail); setErrText(pEmail, rE.message); ok = false; }
        if (!rM.ok) { showError(iMsg); setErrText(pMsg, rM.message); ok = false; }

        if (!ok) {
          ev.preventDefault();
          syncSend();
          return;
        }

        // ✅ Valid submit -> lock UI
        isSubmitting = true;
        if (btnSend) {
          btnSend.textContent = "Sending...";
          btnSend.disabled = true;
          btnSend.style.opacity = ".65";
          btnSend.style.cursor = "not-allowed";
        }
        if (btnClear) {
          btnClear.disabled = true; // optional but helps avoid confusion
          btnClear.style.opacity = ".65";
          btnClear.style.cursor = "not-allowed";
        }
      });
    }

    // ✅ Optional: remove the PHP-rendered status alerts when user clears the form
    const clearInlinePhpAlerts = () => {
      if (!form) return;
      // targets the moved success/error blocks (red/green borders)
      const alerts = form.querySelectorAll(".border-red-200, .border-green-200");
      alerts.forEach((el) => el.remove());
    };

    if (btnClear) {
      btnClear.addEventListener("click", () => {
        // reset "sending" state if user clears before submitting
        isSubmitting = false;
        if (btnSend) btnSend.textContent = originalSendText;
        btnClear.disabled = false;
        btnClear.style.opacity = "";
        btnClear.style.cursor = "";

        clearInlinePhpAlerts();
        setClinicErr(clinicErr, "");
        clearError(iName); clearError(iEmail); clearError(iMsg);
        setErrText(pName, ""); setErrText(pEmail, ""); setErrText(pMsg, "");
        setTimeout(syncSend, 0);
      });
    }

    syncSend();
  });
})();