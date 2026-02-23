<?php
$appTitle = "AKAS | Clinic Sign Up";
$baseUrl  = "";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_config.php';

// ✅ flash messages (so you can see why "Create Clinic" bounces back)
$errMsg = flash_get('error');
$okMsg  = flash_get('success');

$declinedDoctorCount = 0;
try {
  if (auth_is_logged_in() && auth_role() === 'clinic_admin') {
    $cid = (int)(auth_clinic_id() ?? 0);
    if ($cid > 0) {
      $pdo = db();
      $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM clinic_doctors
        WHERE clinic_id = ?
          AND approval_status = 'DECLINED'
      ");
      $stmt->execute([$cid]);
      $declinedDoctorCount = (int)$stmt->fetchColumn();
    }
  }
} catch (Throwable $e) {
  $declinedDoctorCount = 0; // fail silently
}

$locked = (($_GET['locked'] ?? '') === '1');

include "../includes/partials/head.php";
?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Disable Enter key on all forms
    document.querySelectorAll("form").forEach(function(form) {
        form.addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                return false;
            }
        });
    });

});
</script>

<body class="bg-white">

<style>
  .auth-title {
    font-family: ui-monospace, "Courier New", monospace;
    letter-spacing: .14em;
  }

  .step { display: none; }
  .step.active { display: block; }

  /* Modal */
  .modal-backdrop{ display:none; }
  .modal-backdrop.show{ display:flex; }
</style>

<main class="min-h-screen flex items-center justify-center px-4">

  <section
    class="w-full max-w-6xl
           mx-4 sm:mx-8 lg:mx-10 xl:mx-auto
           rounded-2xl sm:rounded-3xl lg:rounded-[40px]
           overflow-hidden shadow-xl border border-slate-100">

    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[520px]">

      <div class="bg-[#FFFDF6] relative flex items-center justify-center p-6">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="w-44 sm:w-56 md:w-64 lg:w-72 xl:w-80 max-w-full"
        />
      </div>

      <div class="relative flex items-center justify-center p-4 sm:p-6 lg:p-8"
           style="background: var(--primary);">

        <div class="w-full max-w-sm px-2 sm:px-4 lg:px-0">

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

          <div class="mb-6">
            <div class="flex items-center justify-between text-xs font-semibold text-white/90">
              <span id="labelStep1" class="opacity-100">Admin Account</span>
              <span id="labelStep2" class="opacity-60">Clinic Details</span>
            </div>
            <div class="mt-2 h-2 w-full rounded-full bg-white/20 overflow-hidden">
              <div id="progressBar" class="h-full w-1/2 rounded-full" style="background: var(--secondary);"></div>
            </div>
          </div>

          <!-- ✅ SHOW FLASH MESSAGES HERE -->
          <?php if (!empty($errMsg)): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
              <?php echo htmlspecialchars((string)$errMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($okMsg)): ?>
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm">
              <?php echo htmlspecialchars((string)$okMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <form id="signupWizard"
                action="<?php echo $baseUrl; ?>/pages/signup-process.php"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-4">

            <input type="hidden" name="role" value="clinic_admin" />

            <input type="hidden" name="google_locked" value="<?php echo $locked ? '1' : '0'; ?>" />
            <?php if ($locked && auth_is_logged_in() && auth_role() === 'clinic_admin'): ?>
              <input type="hidden" name="admin_name" value="<?php echo htmlspecialchars(auth_name() ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="email" value="<?php echo htmlspecialchars(auth_email() ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>

            <!-- STEP 1 -->
            <div id="step1" class="step active space-y-3">
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/70">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5.121 17.804A9 9 0 1118.879 17.8M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                </span>
<input
  type="text"
  name="admin_name"
  placeholder="Admin Full Name"
  maxlength="50"
  required
  data-validate="full-name"
 class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400
       border border-slate-200
       focus:outline-none focus:ring-2 focus:ring-white/60
       ring-offset-0"
/>
              </div>

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
                  data-unique="accounts_email"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

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

            <!-- STEP 2 -->
            <div id="step2" class="step space-y-3">
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
                  maxlength="50"
                  data-validate="full-name"
                  required
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

              <!-- DOCTORS (Optional) -->
              <div class="bg-white/90 rounded-xl px-4 py-4 border border-white/40">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 flex items-center gap-2">
                      Doctors
                      <?php if ($declinedDoctorCount > 0): ?>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold
                                     bg-rose-50 text-rose-700 border border-rose-200">
                          ⚠ <?= (int)$declinedDoctorCount ?> Declined
                        </span>
                      <?php endif; ?>
                    </p>

                    <?php if ($declinedDoctorCount > 0): ?>
                      <p class="text-[11px] text-rose-700 mt-1">
                        You have declined doctor(s). You can reapply later in <b>Admin Dashboard → Doctors</b>.
                      </p>
                    <?php else: ?>
                      <p class="text-xs text-slate-600">
                        Add doctors now, or you can add them later in the admin dashboard.
                      </p>
                    <?php endif; ?>
                  </div>

                  <button
                    type="button"
                    id="openDoctorModal"
                    class="shrink-0 px-3 py-2 rounded-lg font-semibold text-black shadow-sm hover:shadow transition"
                    style="background: var(--secondary);">
                    + Add Doctor
                  </button>
                </div>

                <input type="hidden" name="doctors_json" id="doctorsJson" value="[]" />
                <div id="doctorsList" class="mt-3 space-y-2"></div>
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

              <div id="otherSpecialtyWrap" class="hidden">
                <input
                  type="text"
                  name="specialty_other"
                  placeholder="Please specify (required if Other)"
                  class="w-full h-11 rounded-xl bg-white px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

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
                    data-unique="clinic_contact"
                    class="flex-1 h-11 rounded-xl bg-white px-4
                          text-slate-700 placeholder:text-slate-400
                          focus:outline-none focus:ring-2 focus:ring-white/60"
                  />
                </div>
              </div>

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
                  data-unique="clinic_email"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

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
                  data-unique="clinic_business_id"
                  class="w-full h-11 rounded-xl bg-white pl-12 pr-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>

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

<!-- ADD DOCTOR MODAL -->
<div id="doctorModal" class="modal-backdrop fixed inset-0 z-50 items-center justify-center p-4" style="background: rgba(0,0,0,.55);">
  <div class="w-full max-w-xl rounded-2xl bg-white shadow-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 flex items-center justify-between" style="background: var(--primary);">
      <div>
        <p class="text-white font-bold">Add Doctor</p>
        <p class="text-white/80 text-xs">Fill in the doctor details. All fields are required.</p>
      </div>
      <button type="button" id="closeDoctorModal" class="text-white/90 hover:text-white text-xl leading-none">&times;</button>
    </div>

    <div class="p-5">
      <!-- modal body unchanged -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
          <input id="docFullName" type="text" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700" placeholder="e.g., Juan Dela Cruz" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Birthdate</label>
          <input id="docBirthdate" type="date" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Specialization</label>
          <input id="docSpecialization" type="text" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700" placeholder="e.g., Pediatrics" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">PRC (License No.)</label>
          <input id="docPrc" type="text" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700" placeholder="e.g., 1234567" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
          <input id="docEmail" type="text" data-validate="email" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700" placeholder="doctor@email.com" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Number</label>
          <div class="flex gap-2">
            <div class="w-20 h-11 flex items-center justify-center rounded-xl bg-slate-50 text-slate-700 font-semibold border border-slate-200">
              +63
            </div>
            <input id="docPhone" type="text" maxlength="10" inputmode="numeric" data-validate="phone-ph" class="flex-1 h-11 rounded-xl border border-slate-200 px-4 text-slate-700" placeholder="9123456789" />
          </div>
        </div>

        <label class="block text-xs font-semibold text-slate-700 mb-1">Availability</label>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="sm:col-span-1">
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Slot length</label>
            <select id="docSlotMins" class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700">
              <option value="30" selected>30 minutes</option>
              <option value="15">15 minutes</option>
              <option value="60">60 minutes</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Start time</label>
            <input id="docStartTime" type="time" step="1800"
                  class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                  value="09:00" />
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">End time</label>
            <input id="docEndTime" type="time" step="1800"
                  class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                  value="17:00" />
          </div>
        </div>

        <div class="mt-3">
          <label class="block text-[11px] font-semibold text-slate-600 mb-2">Days available</label>
          <div class="flex flex-wrap gap-2">
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dMon" checked>Mon
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dTue" checked>Tue
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dWed" checked>Wed
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dThu" checked>Thu
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dFri" checked>Fri
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dSat">Sat
            </label>
            <label class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
              <input type="checkbox" class="mr-2" id="dSun">Sun
            </label>
          </div>
        </div>

        <p class="mt-2 text-[11px] text-slate-500">
          Start/End must be within clinic hours. Booking uses 30-min slots.
        </p>

      </div>

      <div class="mt-5 flex items-center justify-end gap-2">
        <button type="button" id="cancelDoctor" class="px-4 py-2 rounded-xl font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">Cancel</button>
        <button type="button" id="saveDoctor" class="px-4 py-2 rounded-xl font-bold text-black shadow-sm hover:shadow" style="background: var(--secondary);">Save Doctor</button>
      </div>
    </div>
  </div>
</div>

<?php $v1 = @filemtime(__DIR__ . '/../assets/js/form-validators.js') ?: time(); ?>
<script src="/assets/js/form-validators.js?v=<?= (int)$v1 ?>"></script>

<?php $v2 = @filemtime(__DIR__ . '/../assets/js/signup-admin.js') ?: time(); ?>
<script src="/assets/js/signup-admin.js?v=<?= (int)$v2 ?>"></script>

<script src="<?php echo $baseUrl; ?>/assets/js/signup-admin-doctors.js"></script>
</body>
</html>