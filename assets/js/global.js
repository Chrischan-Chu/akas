// assets/js/global.js
document.addEventListener("DOMContentLoaded", function () {
  initBurgerMenu();

  /* =====================================================
     HELPERS
  ===================================================== */
  const $ = (id) => document.getElementById(id);


  /* =====================================================
     MOBILE BURGER MENU
  ===================================================== */
  function initBurgerMenu() {
  const burgerBtn = document.getElementById("burgerBtn");
  const mobileMenu = document.getElementById("mobileMenu");

  if (!burgerBtn || !mobileMenu) return;

  // prevent double binding (important for back button)
  if (burgerBtn.dataset.bound === "1") return;
  burgerBtn.dataset.bound = "1";

  function closeMenu() {
    mobileMenu.classList.add("hidden");
    burgerBtn.setAttribute("aria-expanded", "false");
  }

  burgerBtn.addEventListener("click", function (e) {
    e.preventDefault();

    mobileMenu.classList.toggle("hidden");
    const isOpen = !mobileMenu.classList.contains("hidden");
    burgerBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });

  mobileMenu.addEventListener("click", function (e) {
    if (e.target.closest("a")) closeMenu();
  });
}



  /* =====================================================
     PROFILE DROPDOWN
  ===================================================== */
  const profileBtn = $("profileBtn");
  const profileMenu = $("profileMenu");

  if (profileBtn && profileMenu) {

    profileBtn.addEventListener("click", function (e) {
      e.stopPropagation();

      const isHidden = profileMenu.classList.contains("hidden");
      profileMenu.classList.toggle("hidden");
      profileBtn.setAttribute("aria-expanded", isHidden ? "true" : "false");
    });

    document.addEventListener("click", function () {
      profileMenu.classList.add("hidden");
      profileBtn.setAttribute("aria-expanded", "false");
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        profileMenu.classList.add("hidden");
        profileBtn.setAttribute("aria-expanded", "false");
      }
    });
  }


  /* =====================================================
     BACK TO TOP BUTTON
  ===================================================== */
  const backToTopBtn = $("backToTop");

  if (backToTopBtn) {

    function updateBackToTop() {

      const isMenuOpen = mobileMenu && !mobileMenu.classList.contains("hidden");

      if (isMenuOpen) {
        backToTopBtn.classList.remove("show");
        return;
      }

      if (window.scrollY > 80)
        backToTopBtn.classList.add("show");
      else
        backToTopBtn.classList.remove("show");
    }

    window.addEventListener("scroll", updateBackToTop, { passive: true });
    window.addEventListener("load", updateBackToTop);

    backToTopBtn.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }


  /* =====================================================
     SMOOTH SCROLL + UPDATE URL HASH
  ===================================================== */
  document.addEventListener("click", function (e) {

    const a = e.target.closest("a");
    if (!a) return;

    const href = a.getAttribute("href");
    if (!href) return;

    let url;
    try {
      url = new URL(href, window.location.href);
    } catch {
      return;
    }

    if (!url.hash || url.hash === "#") return;

    // Only intercept same-page links
    if (url.pathname !== window.location.pathname) return;

    const target = document.querySelector(url.hash);
    if (!target) return;

    e.preventDefault();

    target.scrollIntoView({
      behavior: "smooth",
      block: "start"
    });

    try {
      history.pushState(null, "", url.hash);
    } catch {
      window.location.hash = url.hash;
    }

    // Close mobile menu after clicking section
    if (burgerBtn && mobileMenu) {
      mobileMenu.classList.add("hidden");
      burgerBtn.setAttribute("aria-expanded", "false");
    }
  });

});
// Active nav highlight + smooth underline (no flicker on click)
document.addEventListener("DOMContentLoaded", function () {
  const navLinks = Array.from(document.querySelectorAll(".nav-link"));
  const sections = Array.from(document.querySelectorAll("main section[id]"));
  if (!navLinks.length || !sections.length) return;

  const underline = document.getElementById("navUnderline");
  const underlineHost = document.getElementById("mainNavbar");


  let lockScrollSpy = false;
  let lockTimer = null;

  function getHashIdFromHref(href) {
    if (!href) return "";
    const i = href.indexOf("#");
    return i >= 0 ? href.slice(i + 1) : "";
  }

  function moveUnderline(link) {
    if (!underline || !underlineHost || !link) return;

    const hostRect = underlineHost.getBoundingClientRect();
    const linkRect = link.getBoundingClientRect();

    const left = linkRect.left - hostRect.left;
    const width = linkRect.width;

    underline.style.opacity = "1";
    underline.style.width = width + "px";
    underline.style.transform = `translate3d(${left}px,0,0)`;
  }

  function activateById(id) {
    let activeLink = null;

    navLinks.forEach((a) => {
      const linkId = getHashIdFromHref(a.getAttribute("href"));
      const isActive = linkId === id;
      a.classList.toggle("is-active", isActive);
      if (isActive) activeLink = a;
      if (isActive) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });

    // move underline after layout settles (prevents jitter)
    if (activeLink) {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => moveUnderline(activeLink));
      });
    }
  }

  function lock(ms = 700) {
    lockScrollSpy = true;
    if (lockTimer) clearTimeout(lockTimer);
    lockTimer = setTimeout(() => (lockScrollSpy = false), ms);
  }

  // ---------- INITIAL (always Home on refresh) ----------
  // If you want refresh to ALWAYS start at Home, keep this:
  window.scrollTo({ top: 0, behavior: "auto" });
  activateById("home");

  // ---------- CLICK (smooth scroll + lock scrollspy) ----------
  navLinks.forEach((a) => {
    a.addEventListener("click", (e) => {
      const id = getHashIdFromHref(a.getAttribute("href"));
      if (!id) return;

      e.preventDefault();

      const target = document.getElementById(id);
      if (!target) return;

      // highlight instantly
      activateById(id);

      // lock scrollspy while smooth-scrolling
      lock(900);

      // smooth scroll
      target.scrollIntoView({ behavior: "smooth", block: "start" });

      // update URL hash without jump
      history.pushState(null, "", `#${id}`);
    });
  });

  // ---------- SCROLLSPY (viewport-based, stable) ----------
  function getCurrentSectionId() {
    // marker line 1/3 down the viewport
    const marker = window.innerHeight * 0.33;

    // if near top, treat as home
    if (window.scrollY < 50) return "home";

    for (const sec of sections) {
      const r = sec.getBoundingClientRect();
      if (r.top <= marker && r.bottom > marker) {
        return sec.id;
      }
    }

    // fallback: keep current active
    const active = document.querySelector(".nav-link.is-active");
    return active ? getHashIdFromHref(active.getAttribute("href")) : "home";
  }

  window.addEventListener(
    "scroll",
    () => {
      if (lockScrollSpy) return;

      const current = getCurrentSectionId();
      if (current) activateById(current);
    },
    { passive: true }
  );

  // Keep underline aligned on resize
  window.addEventListener("resize", () => {
    const active = document.querySelector(".nav-link.is-active");
    if (active) moveUnderline(active);
  });
});


