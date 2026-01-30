
// assets/js/doctor-profile.js
// Handles back-to-top button for doctor-profile page
(function () {
  const btn = document.getElementById("backToTopBtn");
  if (!btn) return;
  function onScroll() {
    if (window.scrollY > 300) btn.classList.remove("hidden");
    else btn.classList.add("hidden");
  }
  window.addEventListener("scroll", onScroll);
  btn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  onScroll();
})();
