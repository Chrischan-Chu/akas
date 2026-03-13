<?php
// pages/faq.php

$faqsLeft = [
  [
    "q" => "What is AKAS?",
    "a" => "AKAS (Angeles Klinik Appointment System) is a web-based appointment scheduling system developed specifically for clinics in Angeles City. It streamlines appointment booking for patients while enabling clinics to efficiently manage schedules and prevent overbooking."
  ],
  [
    "q" => "How do patients book an appointment?",
    "a" => "Patients can select a clinic, choose an available date and time slot, enter their information, and receive instant confirmation through the system."
  ],
  [
    "q" => "How does AKAS help clinics?",
    "a" => "AKAS helps clinics manage doctor availability, set working hours, block unavailable dates, and monitor appointments in real time to keep daily operations organized."
  ],
  [
    "q" => "Does AKAS prevent overbooking?",
    "a" => "Yes. The system tracks real-time availability and automatically blocks time slots once they are booked, preventing scheduling conflicts and overbooking."
  ],
  [
    "q" => "Are appointment reminders included?",
    "a" => "Yes. AKAS can send automated email or SMS reminders, confirmations, and cancellation notifications to help reduce missed appointments."
  ],
];

$faqsRight = [
  [
    "q" => "Is patient information secure?",
    "a" => "Yes. AKAS securely stores patient information and uses role-based access so only authorized clinic staff can view and manage records."
  ],
  [
    "q" => "Can AKAS support multiple doctors or departments?",
    "a" => "Yes. AKAS supports multiple doctors and department-based booking, making it suitable for clinics in Angeles City with different services and specialists."
  ],
  [
    "q" => "Can patients reschedule or cancel appointments?",
    "a" => "Patients may cancel their existing appointment at any time. As the system allows only one active booking per user, a new appointment can be made after the current one is canceled."
  ],
  [
    "q" => "Do patients need to install any software?",
    "a" => "No. AKAS is fully web-based and can be accessed using any modern browser on mobile or desktop no installation required."
  ],
  [
    "q" => "Who can use AKAS?",
    "a" => "AKAS is intended for clinics in Angeles City that want a faster and more organized way to handle appointment booking and schedule management."
  ],
];

// Open first item in each column like the reference image
$openLeftIndex  = 0;
$openRightIndex = 0;
?>

<section id="faq" class="scroll-mt-24 py-16 px-4" style="background:#FAF9EE;">
  <div class="max-w-6xl mx-auto">

    <!-- Top header row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center mb-10">

      <!-- Left title -->
      <div>
        <a href="#contact"
           class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/70 px-4 py-2 text-xs font-bold tracking-widest uppercase text-slate-700 shadow-sm">
          <i class="bi bi-chat-dots-fill text-[var(--primary)]"></i>
          Contact Now
        </a>

        <h2 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.05] text-slate-900">
          Our Frequently<br />
          <span style="color:#40B7FF;">Asked Questions</span>
        </h2>
      </div>

      <!-- Right description -->
      <div class="text-slate-600 leading-relaxed">
        <p class="text-lg sm:text-base pt-14">
          Here are quick answers about AKAS appointment booking and clinic scheduling.
          This section is designed to help patients and clinic admins understand the process faster.
        </p>
      </div>

    </div>

    <!-- 2-column FAQ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

      <!-- LEFT COLUMN -->
      <div class="space-y-4">
        <?php foreach ($faqsLeft as $i => $item):
          $isOpen = ($i === $openLeftIndex);
          $panelId = "faqL-$i";
        ?>
          <div class="faq-item">
            <!-- pill -->
            <button
              type="button"
              class="faq-btn w-full flex items-center justify-between gap-4 rounded-full px-6 py-4 text-left
                     border border-white/70 shadow-sm transition
                     focus:outline-none"
              data-target="<?= $panelId ?>"
              aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
              style="<?= $isOpen ? 'background:#40B7FF;color:#fff;border-color:transparent;' : 'background:#fff;color:#0f172a;' ?>"
            >
              <span class="font-semibold text-sm sm:text-base leading-relaxed pl-2">
                <?= htmlspecialchars($item["q"]) ?>
              </span>

              <span class="faq-bubble shrink-0 h-10 w-10 rounded-full flex items-center justify-center"
                    style="<?= $isOpen ? 'background:rgba(255,255,255,.18);' : 'background:#f1f5f9;' ?>">
                <i class="bi <?= $isOpen ? 'bi-chevron-up text-white' : 'bi-chevron-down text-slate-600' ?>"></i>
              </span>
            </button>

            <!-- answer (no extra card; sits under pill like image) -->
            <div id="<?= $panelId ?>" class="faq-panel <?= $isOpen ? '' : 'hidden' ?> px-6 pt-4 pb-2">
              <p class="text-sm text-slate-600 leading-7">
                <?= htmlspecialchars($item["a"]) ?>
              </p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- RIGHT COLUMN -->
      <div class="space-y-4">
        <?php foreach ($faqsRight as $i => $item):
          $isOpen = ($i === $openRightIndex);
          $panelId = "faqR-$i";
        ?>
          <div class="faq-item">
            <button
              type="button"
              class="faq-btn w-full flex items-center justify-between gap-4 rounded-full px-6 py-4 text-left
                     border border-white/70 shadow-sm transition
                     focus:outline-none"
              data-target="<?= $panelId ?>"
              aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
              style="<?= $isOpen ? 'background:#FF9239;color:#fff;border-color:transparent;' : 'background:#fff;color:#0f172a;' ?>"
            >
              <span class="font-semibold text-sm sm:text-base leading-relaxed pl-2">
                <?= htmlspecialchars($item["q"]) ?>
              </span>

              <span class="faq-bubble shrink-0 h-10 w-10 rounded-full flex items-center justify-center"
                    style="<?= $isOpen ? 'background:rgba(255,255,255,.18);' : 'background:#f1f5f9;' ?>">
                <i class="bi <?= $isOpen ? 'bi-chevron-up text-white' : 'bi-chevron-down text-slate-600' ?>"></i>
              </span>
            </button>

            <div id="<?= $panelId ?>" class="faq-panel <?= $isOpen ? '' : 'hidden' ?> px-6 pt-4 pb-2">
              <p class="text-sm text-slate-600 leading-7">
                <?= htmlspecialchars($item["a"]) ?>
              </p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</section>

<script>
(() => {
  const root = document.getElementById("faq");
  if (!root) return;

  const buttons = root.querySelectorAll(".faq-btn");

  const closeAll = () => {
    buttons.forEach((btn) => {
      const id = btn.dataset.target;
      const panel = document.getElementById(id);
      const bubble = btn.querySelector(".faq-bubble");
      const icon = bubble?.querySelector("i");

      btn.setAttribute("aria-expanded", "false");
      panel?.classList.add("hidden");

      // default pill
      btn.style.background = "#ffffff";
      btn.style.color = "#0f172a";
      btn.style.borderColor = "rgba(255,255,255,.70)";

      // default bubble + icon
      if (bubble) bubble.style.background = "#f1f5f9";
      if (icon) {
        icon.classList.remove("bi-chevron-up", "text-white");
        icon.classList.add("bi-chevron-down", "text-slate-600");
      }
    });
  };

  const openOne = (btn) => {
    const id = btn.dataset.target;
    const panel = document.getElementById(id);
    const bubble = btn.querySelector(".faq-bubble");
    const icon = bubble?.querySelector("i");

    // choose color by column (left uses lighter blue, right uses deeper blue)
    const isRight = id.startsWith("faqR-");
    const bg = isRight ? " #FF9239" : "#40B7FF";

    btn.setAttribute("aria-expanded", "true");
    panel?.classList.remove("hidden");

    btn.style.background = bg;
    btn.style.color = "#ffffff";
    btn.style.borderColor = "transparent";

    if (bubble) bubble.style.background = "rgba(255,255,255,.18)";
    if (icon) {
      icon.classList.remove("bi-chevron-down", "text-slate-600");
      icon.classList.add("bi-chevron-up", "text-white");
    }
  };

  buttons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const isOpen = btn.getAttribute("aria-expanded") === "true";
      closeAll();
      if (!isOpen) openOne(btn);
    });
  });
})();
</script>