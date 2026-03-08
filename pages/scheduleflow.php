<?php
/**
 * AKAS 3-Step Procedure (Full Page) — PHP + Tailwind + Vanilla JS (NO Alpine)
 * - Right side: 3 steps list (active highlight) + thin track + thumb indicator
 * - Left side: changes per step with SMOOTH fade/slide transition
 * - Thumb snaps smoothly to step 1/2/3 positions
 *
 * Usage:
 * - Save as: akas-steps.php
 * - Update $bgImage to your real image path
 */

$bgImage = "assets/img/schedule_flow.jpg"; // <-- change to your background image

$steps = [
  [
    "id" => 1,
    "label" => "Patient books appointment",
    "title" => "Patient books appointment",
    "subtitle" => "Chooses clinic, date, and concern",
    "desc" =>
      "Patients start by selecting the clinic or department, choosing a preferred date/time, and describing their concern. This generates a complete booking record for the clinic to review promptly.",
    "bullets" => [
      "Pick clinic / department",
      "Choose preferred date & time",
      "Add reason for visit / concern",
    ],
  ],
  [
    "id" => 2,
    "label" => "Clinic reviews schedule",
    "title" => "Clinic reviews schedule",
    "subtitle" => "Monitors bookings in real time",
    "desc" =>
        "When an appointment appears in the admin dashboard, staff can view new bookings instantly. Available time slots are automatically confirmed by the system, while administrators monitor incoming appointments.",
    "bullets" => [
        "Booking appears in dashboard",
        "Real-time appointment visibility",
        "System auto-confirms available slots",
    ],
  ],
  [
    "id" => 3,
    "label" => "Appointment confirmed",
    "title" => "Appointment confirmed",
    "subtitle" => "Patient gets updates & reminders",
    "desc" =>
      "Once confirmed, the patient receives instant notifications and reminders. If a scheduling conflict arises, administrators can reschedule the appointment and send updated details reducing no-shows and confusion.",
    "bullets" => [
      "Confirmation message sent",
      "Reminders before visit",
      "Reschedule notifications if needed",
    ],
  ],
];

$activeDefault = isset($_GET["step"]) ? max(1, min(3, (int)$_GET["step"])) : 1;

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>AKAS - 3 Step Flow</title>
  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css">

  <style>
    .no-scrollbar::-webkit-scrollbar { width: 0px; height: 0px; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Smooth left-content transitions */
    .content-wrapper {
      transition: opacity 0.35s ease, transform 0.35s ease;
      will-change: opacity, transform;
    }
    .content-hidden {
      opacity: 0;
      transform: translateY(14px);
    }

    /* Smooth thumb movement */
    #thumb {
      transition: top 0.35s cubic-bezier(.4,0,.2,1);
      will-change: top;
    }
  </style>
</head>

<body class="bg-slate-100 p-6">

  <!-- HERO / SECTION -->
  <section class="relative w-full overflow-hidden ">
     <div class="absolute inset-0">
    <!-- Background Image -->
    <img
        src="<?= esc($bgImage) ?>"
        class="h-full w-full object-cover"
        alt="Background"
    />

    <!-- Gradient Overlay (LIGHT -> DARK) rgba(144,213,255,0.85) = 90D5FF, rgba(64,183,255,0.85) = 40B7FF -->
    <div
        class="absolute inset-0"
        style="background: linear-gradient(
        to right,
        rgba(64,183,255,0.80),
        rgba(144,213,255,0.75),
        rgba(64,183,255,0.80)
        );"
    ></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-10 py-16">
      <div class="grid items-center gap-10 lg:grid-cols-2">

        <!-- LEFT column (static title + animated content) -->
        <div class="w-full">

            <!-- ✅ Static page/section title (never fades) -->
            <h2 class="text-3xl lg:text-6xl font-extrabold leading-tight tracking-tight text-white mb-8">
            Scheduling Flow
            </h2>

            <!-- ✅ Only this part fades (cascading steps content) -->
            <div id="contentWrapper" class="content-wrapper">
            <h1 id="leftTitle" class="text-2xl font-extrabold leading-tight text-white"></h1>

            <p id="leftDesc" class="mt-6 max-w-xl text-base leading-7 text-white"></p>

            <ul id="leftBullets" class="mt-8 space-y-3 max-w-xl"></ul>

            <!-- ✅ Step counter pill -->
            <div class="mt-14 flex justify-center lg:justify-start">
                <div class="px-4 py-2 rounded-full border border-white/40 bg-white/10 backdrop-blur-sm">
                <span id="stepIndicator" class="text-xs font-semibold tracking-wide text-white">
                    Step 1 of 3
                </span>
                </div>
            </div>
        </div>

        </div>
        <!-- RIGHT (3 steps list + track + thumb) -->
        <div class="relative justify-self-end w-full max-w-md">
          <div id="stepsList" class="relative pr-10">
            <ul class="space-y-8 text-right">
              <?php foreach($steps as $s): ?>
                <li>
                  <button
                    type="button"
                    class="step-btn w-full text-right"
                    data-step="<?= (int)$s["id"] ?>"
                  >
                    <span class="step-text text-xl tracking-wide transition">
                      <?= esc($s["label"]) ?>
                    </span>
                  </button>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Track -->
          <div class="absolute right-2 top-0 h-full w-[2px] bg-white/60 rounded-full"></div>

          <!-- Thumb -->
          <div
            id="thumb"
            class="absolute right-[3px] w-[8px] h-[56px] rounded-full bg-[#FF9239]"
            style="top: 10px;"
          ></div>
        </div>

      </div>
    </div>
  </section>

<script>
(function(){
  const steps = <?= json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  // Left elements
  const wrapper = document.getElementById("contentWrapper");
  const leftTitle = document.getElementById("leftTitle");
  const leftSub = document.getElementById("leftSub");
  const leftDesc = document.getElementById("leftDesc");
  const leftBullets = document.getElementById("leftBullets");

  // Right elements
  const listBox = document.getElementById("stepsList");
  const thumb = document.getElementById("thumb");
  const buttons = Array.from(document.querySelectorAll(".step-btn"));

  let active = <?= (int)$activeDefault ?>;
  let isAnimating = false;

  function escapeHtml(str){
    return String(str)
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  function getThumbPositions(){
    // Snap thumb to 3 “fixed” points along the list height
    const h = listBox.clientHeight;
    const thumbH = 56;
    const pad = 10;

    const top = pad;
    const mid = Math.max(pad, (h/2) - (thumbH/2));
    const bot = Math.max(pad, h - thumbH - pad);

    return { 1: top, 2: mid, 3: bot };
  }

  function renderLeft(step){
    leftTitle.innerHTML =
        `${escapeHtml(step.title)}<br><span class="text-white">${escapeHtml(step.subtitle)}</span>`;

    const stepIndicator = document.getElementById("stepIndicator");
    if (stepIndicator) stepIndicator.textContent = `Step ${step.id} of 3`;

    leftDesc.textContent = step.desc;

    leftBullets.innerHTML = "";
    step.bullets.forEach((b) => {
        const li = document.createElement("li");
        li.className = "flex items-start gap-3 text-sm text-white";
        li.innerHTML = `
        <span class="mt-1 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15">
            <span class="h-2 w-2 rounded-full bg-white/70"></span>
        </span>
        <span>${escapeHtml(b)}</span>
        `;
        leftBullets.appendChild(li);
    });
    }

  function renderRight(){
  buttons.forEach((btn) => {
    const id = Number(btn.dataset.step);
    const text = btn.querySelector(".step-text");

    if(id === active){
      // ACTIVE STEP
      text.className =
        "step-text text-xl tracking-wide transition font-extrabold text-white";
    } else {
      // INACTIVE STEP
      text.className =
        "step-text text-xl tracking-wide transition font-medium text-white/60 hover:text-white";
    }
  });
}

  function moveThumb(){
    const pos = getThumbPositions();
    thumb.style.top = (pos[active] ?? 10) + "px";
  }

  // Smooth transition: fade/slide out -> update -> fade/slide in
  function setActive(stepId){
    if(stepId === active || isAnimating) return;

    const next = steps.find(s => s.id === stepId);
    if(!next) return;

    isAnimating = true;

    // start hide animation
    wrapper.classList.add("content-hidden");

    // after fade-out, swap content
    setTimeout(() => {
      active = stepId;

      renderRight();
      renderLeft(next);
      moveThumb();

      // fade back in
      wrapper.classList.remove("content-hidden");

      // unlock after fade-in
      setTimeout(() => {
        isAnimating = false;
      }, 360);

    }, 320);
  }

  // Click handlers
  buttons.forEach((btn) => {
    btn.addEventListener("click", () => setActive(Number(btn.dataset.step)));
  });

  // Keep thumb aligned on resize
  window.addEventListener("resize", () => {
    renderRight();
    moveThumb();
  });

  // Init
  const initStep = steps.find(s => s.id === active) || steps[0];
  renderLeft(initStep);
  renderRight();
  moveThumb();

})();
</script>

</body>
</html>