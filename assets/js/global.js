
// assets/js/global.js
// Handles back-to-top button and smooth scroll reset
(function () {
  const backToTopBtn = document.getElementById("backToTop");
  if (backToTopBtn) {
    window.addEventListener("scroll", () => {
      backToTopBtn.style.display = window.scrollY > 50 ? "flex" : "none";
    });
    backToTopBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
  window.addEventListener("load", () => {
    document.documentElement.classList.remove("nohash-snap");
  });
})();
