// assets/js/global.js
document.addEventListener("DOMContentLoaded", function () {

  /* =====================================================
     HELPERS
  ===================================================== */
  const $ = (id) => document.getElementById(id);


  /* =====================================================
     MOBILE BURGER MENU
  ===================================================== */
  const burgerBtn = $("burgerBtn");
  const mobileMenu = $("mobileMenu");

  if (burgerBtn && mobileMenu) {

    burgerBtn.addEventListener("click", function (e) {
      e.preventDefault();

      mobileMenu.classList.toggle("hidden");

      const isOpen = !mobileMenu.classList.contains("hidden");
      burgerBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    // Close after clicking mobile link
    document.querySelectorAll(".mobileLink").forEach(link => {
      link.addEventListener("click", function () {
        mobileMenu.classList.add("hidden");
        burgerBtn.setAttribute("aria-expanded", "false");
      });
    });

    // Close on ESC
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        mobileMenu.classList.add("hidden");
        burgerBtn.setAttribute("aria-expanded", "false");
      }
    });

    // Close on resize to desktop
    window.addEventListener("resize", function () {
      if (window.innerWidth >= 1024) {
        mobileMenu.classList.add("hidden");
        burgerBtn.setAttribute("aria-expanded", "false");
      }
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
