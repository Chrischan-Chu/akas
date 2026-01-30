// assets/js/contact.js
// Contact page tab switcher (Clinic Feedback vs Developer Message)

(function () {
  const tabClinic = document.getElementById("tabClinic");
  const tabDev = document.getElementById("tabDev");
  const panelClinic = document.getElementById("panelClinic");
  const panelDev = document.getElementById("panelDev");

  if (!tabClinic || !tabDev || !panelClinic || !panelDev) return;

  function activate(tab) {
    const activeStyle = "background:#ffffff;";
    const inactiveStyle = "background: rgba(75,182,245,.55);";

    if (tab === "clinic") {
      tabClinic.style = activeStyle;
      tabDev.style = inactiveStyle;
      panelClinic.classList.remove("hidden");
      panelDev.classList.add("hidden");
    } else {
      tabDev.style = activeStyle;
      tabClinic.style = inactiveStyle;
      panelDev.classList.remove("hidden");
      panelClinic.classList.add("hidden");
    }
  }

  tabClinic.addEventListener("click", () => activate("clinic"));
  tabDev.addEventListener("click", () => activate("dev"));

  activate("clinic");
})();
