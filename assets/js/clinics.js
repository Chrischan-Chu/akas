// assets/js/clinics.js
// AJAX pagination for the Clinics section (keeps the same UI/behavior, no SPA routing)

(function () {
  const clinicContent = document.getElementById("clinic-content");
  const pagination = document.getElementById("clinicPagination");
  if (!clinicContent || !pagination) return;

  async function swapClinics(pageNum) {
    // Fade out
    clinicContent.classList.add("opacity-0", "translate-y-2", "pointer-events-none");
    await new Promise((r) => setTimeout(r, 160));

    // Fetch the clinics partial (returns HTML with #clinic-content)
    const url = `pages/clinics.php?page_num=${encodeURIComponent(pageNum)}`;
    const res = await fetch(url, { cache: "no-store" });
    if (!res.ok) throw new Error("Failed to load clinics page " + pageNum);

    const html = await res.text();
    const temp = document.createElement("div");
    temp.innerHTML = html;

    const nextContent = temp.querySelector("#clinic-content");
    if (!nextContent) throw new Error("Could not find #clinic-content in response");

    clinicContent.innerHTML = nextContent.innerHTML;

    // Update active styles
    pagination.querySelectorAll("[data-clinic-page]").forEach((a) => {
      a.classList.remove("bg-white", "text-gray-900");
      a.classList.add("bg-transparent", "text-white/90");
    });

    const active = pagination.querySelector(`[data-clinic-page="${pageNum}"]`);
    if (active) {
      active.classList.remove("bg-transparent", "text-white/90");
      active.classList.add("bg-white", "text-gray-900");
    }

    // Fade in
    clinicContent.classList.remove("opacity-0", "translate-y-2", "pointer-events-none");
  }

  pagination.addEventListener("click", async (e) => {
    const a = e.target.closest("[data-clinic-page]");
    if (!a) return;

    e.preventDefault();
    const pageNum = a.dataset.clinicPage;

    try {
      // Update URL (no SPA) – keep clinics anchor
      const url = `?page_num=${encodeURIComponent(pageNum)}#clinics`;
      history.pushState({}, "", url);

      await swapClinics(pageNum);
    } catch (err) {
      console.error(err);
      clinicContent.classList.remove("opacity-0", "translate-y-2", "pointer-events-none");
    }
  });

  // Support back/forward navigation
  window.addEventListener("popstate", () => {
    const params = new URLSearchParams(window.location.search);
    const pageNum = params.get("page_num") || "1";
    swapClinics(pageNum).catch(() => {});
  });
})();
