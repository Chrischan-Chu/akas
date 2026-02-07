<?php
$appTitle = "AKAS | Clinic Sign Up";
$baseUrl  = "/AKAS";
include "../includes/partials/head.php";
?>

<body class="bg-white">

<style>
  .auth-title {
    font-family: ui-monospace, "Courier New", monospace;
    letter-spacing: .14em;
  }
  /* Step transition */
  .step { display: none; }
  .step.active { display: block; }
</style>

<main class="min-h-screen flex items-center justify-center px-4">

  <section
    class="w-full max-w-6xl
           mx-4 sm:mx-8 lg:mx-10 xl:mx-auto
           rounded-2xl sm:rounded-3xl lg:rounded-[40px]
           overflow-hidden shadow-xl border border-slate-100">

    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[520px]">

      <!-- LEFT -->
      <div class="bg-[#FFFDF6] relative flex items-center justify-center p-6">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="w-64 max-w-full"
        />
      </div>

      <!-- RIGHT -->
      <div class="relative flex items-center justify-center p-4 sm:p-6 lg:p-8"
           style="background: var(--primary);">

        <div class="w-full max-w-sm px-2 sm:px-4 lg:px-0">

          <!-- Title (changes per step, same design) -->
          <div id="titleStep1Wrap">
            <h1 class="auth-title text-3xl sm:text-4xl font-semibold text-white text-center">
              ADMIN SIGN UP
            </h1>
            <p class="text-center text-white text-sm mb-6">
              Create your admin account for managing the clinic.
            </p>
          </div>

          <div id="titleStep2Wrap" class="hidden">
            <h1 class="auth-title text-3xl sm:text-4xl font-semibold text-white text-center">
              CLINIC SIGN UP
            </h1>
            <p class="text-center text-white text-sm mb-6">
              Add your clinic details to start accepting appointments.
            </p>
          </div>

          <!-- Progress -->
          <div class="mb-6">
            <div class="flex items-center justify-between text-xs font-semibold text-white/90">
              <span id="labelStep1" class="opacity-100">Admin Account</span>
              <span id="labelStep2" class="opacity-60">Clinic Details</span>
            </div>
            <div class="mt-2 h-2 w-full rounded-full bg-white/20 overflow-hidden">
              <div id="progressBar" class="h-full w-1/2 rounded-full" style="background: var(--secondary);"></div>
            </div>
          </div>

          <!-- FORM (single form, 2 steps) -->
          <form id="signupWizard" action="signup-process.php" method="POST" enctype="multipart/form-data" class="space-y-4">

            <input type="hidden" name="role" value="clinic_admin" />

            <!-- ================= STEP 1 ================= -->
            <div id="step1" class="step active space-y-3">

              <!-- Admin Name -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <!-- user icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5.121 17.804A9 9 0 1118.879 17.8M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                </span>

                <input
                  type="text"
                  name="admin_name"
                  placeholder="Admin Full Name"
                  required
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Work ID (Optional) -->
              <div class="bg-white/90 rounded-xl px-4 py-3 border border-white/40">
                <label class="block text-xs font-semibold text-slate-700 mb-2">
                  Work ID (Optional)
                </label>

                <input
                  type="file"
                  name="admin_work_id"
                  accept="image/png,image/jpeg,image/webp"
                  class="block w-full text-sm text-slate-700
                        file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                        file:text-sm file:font-semibold file:bg-white file:text-slate-800 hover:file:opacity-90"
                />
              </div>

              <!-- Email -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l9 6 9-6m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8" />
                  </svg>
                </span>
                <input
                  type="text"
                  name="email"
                  placeholder="Admin Email"
                  required
                  data-validate="email"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Password -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
                  </svg>
                </span>

                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="Admin Password"
                  data-validate="password"
                  required
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-12 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />

                <button type="button"
                        class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        data-target="password"></button>
              </div>

              <!-- Confirm Password -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
                  </svg>
                </span>

                <input
                  type="password"
                  id="confirm_password"
                  name="confirm_password"
                  placeholder="Confirm Admin Password"
                  data-validate="password-confirm"
                  data-match="password"
                  required
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-12 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />

                <button type="button"
                        class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                        data-target="confirm_password"></button>
              </div>

              <!-- Buttons -->
              <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a
                  href="<?php echo $baseUrl; ?>/pages/signup.php"
                  class="w-full text-center py-2.5 rounded-xl font-semibold
                         border border-white/50 text-white hover:bg-white/10 transition">
                  ← Back to Selection
                </a>

                <button
                  type="button"
                  id="nextBtn"
                  class="w-full py-2.5 rounded-xl font-bold text-black shadow-md hover:shadow-lg transition-all"
                  style="background: var(--secondary);">
                  Next →
                </button>
              </div>
            </div>

            <!-- ================= STEP 2 ================= -->
            <div id="step2" class="step space-y-3">

              <!-- Clinic Name -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 21h18M6 21V7a2 2 0 012-2h8a2 2 0 012 2v14M9 21V9m6 12V9" />
                  </svg>
                </span>
                <input
                  type="text"
                  name="clinic_name"
                  placeholder="Clinic Name"
                  required
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Clinic Type -->
              <div class="bg-white/90 rounded-xl px-4 py-3 border border-white/40">
                <label class="block text-xs font-semibold text-slate-700 mb-2">
                  Clinic Type / Category
                </label>

                <div class="relative">
                  <select
                    id="specialtySelect"
                    name="specialty"
                    required
                    data-required-msg="Please select a Clinic Type."
                    class="appearance-none w-full rounded-xl bg-white px-4 pr-12 py-2.5
                           text-slate-700 outline-none border border-slate-200
                           focus:ring-2 focus:ring-white/60"
                  >
                    <option value="" disabled selected hidden>Select Clinic Type</option>
                    <option value="Optometry Clinic">Optometry Clinic</option>
                    <option value="Family Clinic">Family Clinic</option>
                    <option value="Dental Clinic">Dental Clinic</option>
                    <option value="Veterinary Clinic">Veterinary Clinic</option>
                    <option value="Pediatric Clinic">Pediatric Clinic</option>
                    <option value="Dermatology Clinic">Dermatology Clinic</option>
                    <option value="Other">Other</option>
                  </select>

                  <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M6 9l6 6 6-6"/>
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Other (shows only if Other is selected) -->
              <div id="otherSpecialtyWrap" class="hidden">
                <input
                  type="text"
                  name="specialty_other"
                  placeholder="Please specify (required if Other)"
                  class="w-full h-11 rounded-xl bg-white px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Contact Number -->
              <div class="space-y-1">
                <div class="flex gap-2">
                  <div class="w-20 h-11 flex items-center justify-center rounded-xl bg-white text-slate-700 font-semibold">
                    +63
                  </div>
                  <input
                    type="text"
                    name="contact_number"
                    placeholder="9123456789"
                    maxlength="10"
                    inputmode="numeric"
                    required
                    data-validate="phone-ph"
                    class="flex-1 h-11 rounded-xl bg-white px-4
                          text-slate-700 placeholder:text-slate-400
                          focus:outline-none focus:ring-2 focus:ring-white/60"
                  />
                </div>
              </div>

              <!-- Clinic Email (Optional) -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l9 6 9-6m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8" />
                  </svg>
                </span>
                <input
                  type="text"
                  name="clinic_email"
                  placeholder="Clinic Email (Optional)"
                  data-validate="email"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Clinic Logo (Optional) -->
              <div class="bg-white/90 rounded-xl px-4 py-3 border border-white/40">
                <label class="block text-xs font-semibold text-slate-700 mb-2">
                  Clinic Logo (Optional)
                </label>
                <input
                  type="file"
                  name="logo"
                  accept="image/png,image/jpeg,image/webp"
                  class="block w-full text-sm text-slate-700
                         file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                         file:text-sm file:font-semibold file:bg-white file:text-slate-800 hover:file:opacity-90"
                />
              </div>

              <!-- Business ID -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 7h10M7 11h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                  </svg>
                </span>

                <input
                  type="text"
                  name="business_id"
                  inputmode="numeric"
                  maxlength="10"
                  placeholder="10-Digit Business ID"
                  required
                  data-validate="business-id-10"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- Buttons -->
              <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                  type="button"
                  id="backBtn"
                  class="w-full py-2.5 rounded-xl font-semibold border border-white/50 text-white hover:bg-white/10 transition">
                  ← Back
                </button>

                <button
                  type="submit"
                  class="w-full py-2.5 rounded-xl font-bold text-black shadow-md hover:shadow-lg transition-all"
                  style="background: var(--secondary);">
                  Create Clinic
                </button>
              </div>
            </div>

          </form>

        </div>
      </div>

    </div>
  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js"></script>

<script>
(function(){
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');

  const nextBtn = document.getElementById('nextBtn');
  const backBtn = document.getElementById('backBtn');

  const labelStep1 = document.getElementById('labelStep1');
  const labelStep2 = document.getElementById('labelStep2');
  const progressBar = document.getElementById('progressBar');

  const titleStep1Wrap = document.getElementById('titleStep1Wrap');
  const titleStep2Wrap = document.getElementById('titleStep2Wrap');

  const specialtySelect = document.getElementById("specialtySelect");
  const otherWrap = document.getElementById("otherSpecialtyWrap");
  const otherInput = otherWrap?.querySelector('input[name="specialty_other"]');

  function showStep(n){
    if(n === 1){
      step1.classList.add('active');
      step2.classList.remove('active');
      labelStep1.classList.remove('opacity-60');
      labelStep1.classList.add('opacity-100');
      labelStep2.classList.add('opacity-60');
      progressBar.style.width = '50%';

      titleStep1Wrap?.classList.remove('hidden');
      titleStep2Wrap?.classList.add('hidden');
      return;
    }
    step1.classList.remove('active');
    step2.classList.add('active');
    labelStep1.classList.add('opacity-60');
    labelStep2.classList.remove('opacity-60');
    labelStep2.classList.add('opacity-100');
    progressBar.style.width = '100%';

    titleStep1Wrap?.classList.add('hidden');
    titleStep2Wrap?.classList.remove('hidden');
  }

  function toggleOtherSpecialty() {
    const isOther = specialtySelect?.value === "Other";
    if (!otherWrap || !otherInput) return;

    if (isOther) {
      otherWrap.classList.remove("hidden");
      otherInput.required = true;
    } else {
      otherWrap.classList.add("hidden");
      otherInput.required = false;
      otherInput.value = "";
    }
  }

  specialtySelect?.addEventListener("change", toggleOtherSpecialty);
  toggleOtherSpecialty();

  nextBtn?.addEventListener('click', () => {
  // ✅ trigger validators to set customValidity (password-confirm, etc.)
  const form = document.getElementById('signupWizard');
  form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

  // Validate only step 1 fields
  const step1Inputs = step1.querySelectorAll('input, select, textarea');
  for (const el of step1Inputs) {
    if (!el.checkValidity()) {
      el.reportValidity();
      return;
    }
  }

  showStep(2);
  window.scrollTo({ top: 0, behavior: 'smooth' });
});


  backBtn?.addEventListener('click', () => {
    showStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  showStep(1);
})();
</script>

</body>
</html>
