<?php
$appTitle = "AKAS | Clinic Sign Up";
$baseUrl  = "";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_config.php';

// ✅ flash messages
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
  $declinedDoctorCount = 0;
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

<body class="min-h-screen">

<style>
  .step { display: none; }
  .step.active { display: block; }

  /* ✅ SAME LOGO STYLE AS signup-user */
  .akas-logo {
    width: 260px;
    max-width: 100%;
    height: auto;
    display: block;
    margin-left: auto;
    margin-right: auto;
  }
  @media (min-width: 640px) { .akas-logo { width: 320px; } }
  @media (min-width: 768px) { .akas-logo { width: 360px; } }
  @media (min-width: 1024px) { .akas-logo { width: 500px; } }

  /* ✅ SAME MANUAL DESKTOP POSITION CONTROL (ONLY DESKTOP) */
  @media (min-width: 1024px) {
    .logo-wrap {
      margin-top: 120px;
      padding-bottom: 80px;
    }
  }
</style>

<main class="min-h-screen w-full">
  <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">

    <!-- LEFT: WHITE BRANDING -->
    <section class="bg-white px-6 py-10 sm:px-10 lg:px-12 lg:py-14 lg:min-h-screen">
      <div class="w-full max-w-md mx-auto flex flex-col lg:min-h-screen">

        <div class="text-left">
          <h1 class="text-slate-900 font-bold text-4xl sm:text-5xl leading-tight">
            Create your admin account
          </h1>
          <p class="mt-4 text-slate-600 text-base sm:text-lg leading-relaxed">
            Manage your clinic, doctors, and appointments with ease.
          </p>
        </div>

        <div class="logo-wrap mt-10 sm:mt-12 md:mt-14 flex justify-center pb-2 sm:pb-12 md:pb-14">
          <img
            src="<?= $baseUrl ?>/assets/img/akas-logo.png"
            alt="AKAS Logo"
            class="akas-logo select-none"
          />
        </div>

      </div>
    </section>

    <!-- RIGHT: BLUE FORM -->
    <section class="relative min-h-screen -mt-6 lg:-mt-15 p-0" style="background:#38B6FF;">

      <!-- TOP LEFT BACK (changes to Back on step 2 via JS) -->
      <a
        id="topBackLink"
        href="<?= $baseUrl; ?>/pages/signup.php"
        class="absolute text-white font-semibold hover:underline z-50"
        style="top:8px; left:13px;"
      >
        ← Back to selection
      </a>

      <div class="min-h-screen px-6 sm:px-10 py-12 flex items-center justify-center">
        <div class="w-full max-w-sm">

          <!-- TITLES -->
          <div id="titleStep1Wrap">
            <h2 class="mt-10 text-white text-2xl sm:text-3xl font-semibold">
              Admin Sign Up
            </h2>
          </div>

          <div id="titleStep2Wrap" class="hidden">
            <h2 class="mt-10 text-white text-2xl sm:text-3xl font-semibold">
              Clinic Sign Up
            </h2>
            <p class="text-white/90 text-sm mt-2">
              Add your clinic details to start accepting appointments.
            </p>
          </div>

          <!-- ✅ GOOGLE + OR (STEP 1 ONLY) -->
          <div id="googleStep1Block" class="mt-6">
            <form id="googleAdminSignupForm" action="<?= $baseUrl; ?>/pages/google-auth.php" method="POST">
              <input type="hidden" name="mode" value="signup">
              <input type="hidden" name="role" value="clinic_admin">
              <input type="hidden" name="credential" id="googleCredentialAdminSignup">
            </form>

            <div
              id="g_id_onload"
              data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID); ?>"
              data-callback="onGoogleAdminSignup"
              data-auto_prompt="false">
            </div>

            <div class="w-full overflow-hidden">
              <div style="width:100%; max-width:100%;">
                <div
                  class="g_id_signin"
                  data-type="standard"
                  data-size="large"
                  data-theme="outline"
                  data-text="signup_with"
                  data-shape="rectangular"
                  data-logo_alignment="left"
                  data-width="384">
                </div>
              </div>
            </div>

            <p class="mt-1 text-sm text-white/80 text-center">
              (Skips Step 1 when using Google.)
            </p>

            <script>
              function onGoogleAdminSignup(response) {
                document.getElementById('googleCredentialAdminSignup').value = response.credential;
                document.getElementById('googleAdminSignupForm').submit();
              }
            </script>

            <div class="flex items-center gap-3 mt-6">
              <div class="h-px flex-1 bg-white/40"></div>
              <div class="text-sm text-white/90 font-semibold">OR</div>
              <div class="h-px flex-1 bg-white/40"></div>
            </div>
          </div>

          <!-- ✅ STEP INDICATOR (ALWAYS VISIBLE) -->
          <div class="mt-6 mb-4 flex items-center justify-center">
            <div class="w-full max-w-sm">
              <div class="flex items-center gap-3">

                <!-- Step 1 -->
                <div id="stepPill1" class="flex-1 rounded-xl px-3 py-2 border border-white/15 bg-white/5 opacity-70">
                  <div class="flex items-center gap-2">
                    <span id="stepDot1"
                          class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-extrabold text-white bg-white/30">
                      1
                    </span>
                    <div class="leading-tight">
                      <div class="text-white font-semibold text-[13px]">Admin</div>
                      <div class="text-white/70 text-[11px] -mt-0.5">Account</div>
                    </div>
                  </div>
                </div>

                <div id="stepLine" class="w-10 h-[3px] rounded-full bg-white/30"></div>

                <!-- Step 2 -->
                <div id="stepPill2" class="flex-1 rounded-xl px-3 py-2 border border-white/15 bg-white/5 opacity-70">
                  <div class="flex items-center gap-2">
                    <span id="stepDot2"
                          class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-extrabold text-white bg-white/30">
                      2
                    </span>
                    <div class="leading-tight">
                      <div class="text-white font-semibold text-[13px]">Clinic</div>
                      <div class="text-white/70 text-[11px] -mt-0.5">Details</div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- FLASH -->
          <?php if (!empty($errMsg)): ?>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
              <?= htmlspecialchars((string)$errMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($okMsg)): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-sm">
              <?= htmlspecialchars((string)$okMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <!-- FORM -->
          <form id="signupWizard"
                action="<?= $baseUrl; ?>/pages/signup-process.php"
                method="POST"
                enctype="multipart/form-data"
                class="mt-4 space-y-3"
                novalidate
                data-inline-errors="1">

            <input type="hidden" name="role" value="clinic_admin" />
            <input type="hidden" name="google_locked" value="<?= $locked ? '1' : '0'; ?>" />

            <?php if ($locked && auth_is_logged_in() && auth_role() === 'clinic_admin'): ?>
              <input type="hidden" name="admin_name" value="<?= htmlspecialchars(auth_name() ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="email" value="<?= htmlspecialchars(auth_email() ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>

            <!-- STEP 1 -->
            <div id="step1" class="step active space-y-3">

              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Admin Full Name <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                <input
                  type="text"
                  name="admin_name"
                  placeholder="Admin Full Name"
                  maxlength="50"
                  required
                  data-validate="full-name"
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="admin_name" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <div>
                <div class="bg-white/95 rounded-xl px-4 py-3 border border-white/80">
                  <label class="block text-md text-black mb-2 ml-1">
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
              </div>

              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Admin Email <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                <input
                  type="text"
                  name="email"
                  placeholder="Admin Email"
                  required
                  data-validate="email"
                  data-unique="accounts_email"
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="email" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Admin Password <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                              <div class="relative">
                              <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Admin Password"
                                data-validate="password"
                                required
                                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                                       text-slate-700 placeholder:text-slate-400
                                       focus:outline-none focus:ring-2 focus:ring-white/60 pr-12"
                              />
                              <button type="button"
                                      class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 hover:text-slate-700 focus:outline-none"
                                      data-toggle-password="#password"
                                      aria-label="Show password"
                                      aria-pressed="false">
                                      <svg class="pw-eye h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                  </svg>
                                      <svg class="pw-eye-off hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.474-4.118m3.197-2.146A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.043 5.307M15 12a3 3 0 00-3-3m0 0a2.99 2.99 0 00-2.225.99M12 9v.01M3 3l18 18"/>
                                  </svg>
                                    </button>
                            </div>
                <p data-err-for="password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Confirm Admin Password <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                              <div class="relative">
                              <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm Admin Password"
                                data-validate="password-confirm"
                                data-match="password"
                                required
                                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                                       text-slate-700 placeholder:text-slate-400
                                       focus:outline-none focus:ring-2 focus:ring-white/60 pr-12"
                              />
                              <button type="button"
                                      class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 hover:text-slate-700 focus:outline-none"
                                      data-toggle-password="#confirm_password"
                                      aria-label="Show password"
                                      aria-pressed="false">
                                      <svg class="pw-eye h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                  </svg>
                                      <svg class="pw-eye-off hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.474-4.118m3.197-2.146A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.043 5.307M15 12a3 3 0 00-3-3m0 0a2.99 2.99 0 00-2.225.99M12 9v.01M3 3l18 18"/>
                                  </svg>
                                    </button>
                            </div>
                <p data-err-for="confirm_password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <button
                type="button"
                id="nextBtn"
                class="w-full py-3 rounded-xl font-semibold text-white text-base transition-colors duration-300"
                style="background-color:#ffa154;"
                onmouseover="this.style.backgroundColor='#f97316'"
                onmouseout="this.style.backgroundColor='#ffa154'"
              >
                Next →
              </button>

              <!-- ✅ Already have account (STEP 1) -->
              <div class="pt-6">
                <div class="flex items-center gap-3">
                  <div class="h-px flex-1 bg-white/40"></div>
                  <div class="text-sm text-white/90 font-semibold">Already have an account?</div>
                  <div class="h-px flex-1 bg-white/40"></div>
                </div>

                <div class="mt-3 flex items-center justify-center gap-2">
                  <a
                    href="<?= $baseUrl; ?>/pages/login.php"
                    class="text-white font-semibold hover:underline inline-flex items-center gap-2"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5m0 0l-5-5m5 5H3" />
                    </svg>
                    Sign in to your account
                  </a>
                </div>
              </div>
            </div>

            <!-- STEP 2 -->
            <div id="step2" class="step space-y-3">

              <!-- hidden backBtn (used by JS), not visible -->
              <button type="button" id="backBtn" class="hidden"></button>

              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Clinic Name <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                <input
                  type="text"
                  name="clinic_name"
                  placeholder="Clinic Name"
                  maxlength="50"
                  data-validate="full-name"
                  required
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="clinic_name" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <!-- DOCTORS -->
              <div id="doctorsBlock" class="bg-white/90 rounded-xl px-4 py-4 border border-white/40">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 flex items-center gap-2">
                      Doctors <span class="text-red-600 font-extrabold">*</span>
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
                      <p class="text-sm text-slate-600">
                        Please add at least one doctor. You can add more later in the Admin Dashboard.
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
                <p data-err-for="doctors_json" class="min-h-[16px] mt-2 text-sm text-red-600 font-semibold"></p>
              </div>

              <!-- Clinic Type -->
              <div class="bg-white/90 rounded-xl px-4 py-3 border border-white/40">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                  Clinic Type / Category <span class="text-red-600 font-extrabold">*</span>
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
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="specialty_other" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <!-- Contact -->
              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Contact Number <span class="text-red-600 font-semibold ml-1">*</span>
                </label>

                <div class="flex items-center gap-2">
                  <div class="bg-white rounded-xl px-3 py-2.5 text-slate-700 font-semibold border border-white/80">
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
                    class="flex-1 rounded-xl bg-white px-4 py-2.5 border border-white/80
                           text-slate-700 placeholder:text-slate-400
                           focus:outline-none focus:ring-2 focus:ring-white/60"
                  />
                </div>
                <p data-err-for="contact_number" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <!-- Clinic Email -->
              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  Clinic Email (Optional)
                </label>
                <input
                  type="email"
                  name="clinic_email"
                  placeholder="Clinic Email (Optional)"
                  data-validate="email"
                  data-unique="clinic_email"
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="clinic_email" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <!-- Clinic Logo -->
              <div class="bg-white/90 rounded-xl px-4 py-3 border border-white/40">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
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
              <div>
                <label class="block text-md text-white mb-1 ml-1">
                  10-Digit Business ID <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                <input
                  type="text"
                  name="business_id"
                  inputmode="numeric"
                  maxlength="10"
                  placeholder="10-Digit Business ID"
                  required
                  data-validate="business-id-10"
                  data-unique="clinic_business_id"
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
                <p data-err-for="business_id" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
              </div>

              <!-- ✅ Create Clinic FULL WIDTH -->
              <button
                type="submit"
                class="w-full py-3 rounded-xl font-semibold text-white text-base transition-colors duration-300"
                style="background-color:#ffa154;"
                onmouseover="this.style.backgroundColor='#f97316'"
                onmouseout="this.style.backgroundColor='#ffa154'"
              >
                Create Clinic
              </button>

              <!-- ✅ Already have account (STEP 2) -->
              <div class="pt-6">
                <div class="flex items-center gap-3">
                  <div class="h-px flex-1 bg-white/40"></div>
                  <div class="text-sm text-white/90 font-semibold">Already have an account?</div>
                  <div class="h-px flex-1 bg-white/40"></div>
                </div>

                <div class="mt-3 flex items-center justify-center gap-2">
                  <a
                    href="<?= $baseUrl; ?>/pages/login.php"
                    class="text-white font-semibold hover:underline inline-flex items-center gap-2"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5m0 0l-5-5m5 5H3" />
                    </svg>
                    Sign in to your account
                  </a>
                </div>
              </div>

            </div>
          </form>

        </div>
      </div>
    </section>
  </div>
</main>

<!-- ✅ DOCTOR MODAL (Tailwind hidden; JS toggles hidden/flex) -->
<div id="doctorModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
 <div class="relative w-full max-w-md sm:max-w-lg
            max-h-[90vh]
            rounded-2xl
            bg-white
            outline outline-8 
            shadow-2xl
            flex flex-col" style="outline-color:#ffa154;">
    <div class="px-5 py-4 flex items-center justify-between bg-white border-b border-slate-200">
      <div>
        <p class="text-slate-900 font-bold">Add Doctor</p>
<p class="text-slate-500 text-xs">Fill in the doctor details. All fields are required.</p>
      </div>
      <button type="button" id="closeDoctorModal"
        class="text-slate-400 hover:text-slate-700 text-xl leading-none">
  &times;
</button>
    </div>

    <!-- ✅ scrollable body -->
   <div class="flex-1 overflow-y-auto p-5" style="background:#38B6FF;">
  <div class="bg-white rounded-2xl border border-white/70 shadow-sm p-4 sm:p-5">
    <!-- ✅ PUT ALL YOUR FORM FIELDS INSIDE THIS CARD -->
      <form id="doctorModalForm" data-inline-errors="1" novalidate>
<div class="grid grid-cols-1 gap-4">

        <div>
          <label class="block text-sm font-semibold text-black mb-1">Full Name <span class="text-red-600 font-semibold">*</span></label>
          <input id="docFullName" type="text" required
                 data-required-msg="Please fill out this field."
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                 placeholder="e.g., Juan Dela Cruz"
                 data-validate="doctor-name"
                 maxlength="50" />
          <p data-err-for="docFullName" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-black mb-1">Birthdate <span class="text-red-600 font-semibold">*</span></label>
          <input id="docBirthdate" type="date" required
                 data-required-msg="Please pick a birthdate."
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 focus:outline-none focus:ring-2 focus:ring-white/60"
                 data-validate="age-18" />
          <p data-err-for="docBirthdate" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-black mb-1">Specialization <span class="text-red-600 font-semibold">*</span></label>
          <input id="docSpecialization" type="text" required
                 data-required-msg="Please fill out this field."
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                 placeholder="e.g., Pediatrics"
                 data-validate="full-name"
                 maxlength="50" />
          <p data-err-for="docSpecialization" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-black mb-1">PRC (License No.) <span class="text-red-600 font-semibold">*</span></label>
          <input
  id="docPrc"
  type="text"
  required
  inputmode="numeric"
  maxlength="8"
  data-validate="prc-5-8"
  data-required-msg="Please fill out this field."
  class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
  placeholder="e.g., 1234567"
/>
<p data-err-for="docPrc" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-black mb-1">Email <span class="text-red-600 font-semibold">*</span></label>
          <input id="docEmail" type="text" required
                 data-required-msg="Please fill out this field."
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                 placeholder="doctor@email.com"
                 data-validate="email" />
          <p data-err-for="docEmail" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-black mb-1">Contact Number <span class="text-red-600 font-semibold">*</span></label>
          <div class="flex gap-2">
            <div class="w-20 h-11 flex items-center justify-center rounded-xl bg-slate-50 text-slate-700 font-semibold border border-slate-200">
              +63
            </div>
            <input id="docPhone" type="text" maxlength="10" inputmode="numeric" required
                   data-required-msg="Please fill out this field."
                   class="flex-1 h-11 rounded-xl border border-slate-200 px-4 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                   placeholder="9123456789"
                 data-validate="phone-ph" />
          </div>
          <p data-err-for="docPhone" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
        </div>

        <!-- ✅ Days Availability -->
        <div>
          <label class="block text-sm font-semibold text-black mb-2">Days Availability <span class="text-red-600 font-semibold">*</span></label>

          <div class="grid grid-cols-1 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Slot length <span class="text-red-600 font-semibold">*</span></label>
              <select id="docSlotMins" required
                      data-required-msg="Please select an option."
                      class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 focus:outline-none focus:ring-2 focus:ring-white/60">
                <option value="30" selected>30 minutes</option>
                <option value="15">15 minutes</option>
                <option value="60">60 minutes</option>
              </select>
              <p data-err-for="docSlotMins" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Start time <span class="text-red-600 font-semibold">*</span></label>
              <input id="docStartTime" type="time" required value="09:00"
                     data-required-msg="Please fill out this field."
                     class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 focus:outline-none focus:ring-2 focus:ring-white/60" />
              <p data-err-for="docStartTime" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">End time <span class="text-red-600 font-semibold">*</span></label>
              <input id="docEndTime" type="time" required value="17:00"
                     data-required-msg="Please fill out this field."
                     class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700 focus:outline-none focus:ring-2 focus:ring-white/60" />
              <p data-err-for="docEndTime" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>
            </div>

            <div id="docDaysWrap" class="rounded-xl border border-slate-200 p-3">
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

            <p data-err-for="docDays" class="mt-1 text-sm font-semibold text-red-600 leading-snug"></p>

            <p class="text-[11px] text-white/80">
              Choose the doctor schedule used for appointment slots.
            </p>
          </div>
        </div>

      </div>

      <div class="mt-5 flex items-center justify-end gap-2">
        <button type="button" id="cancelDoctor"
                class="px-4 py-2 rounded-xl font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50">
          Cancel
        </button>
        <button type="button" id="saveDoctor"
                class="px-4 py-2 rounded-xl font-bold text-black shadow-sm hover:shadow"
                style="background: var(--secondary);">
          Add Doctor
        </button>
      </div>
</form>
    </div>
  </div>
</div>
<script>
  if ("scrollRestoration" in history) {
    history.scrollRestoration = "manual";
  }

  window.addEventListener("load", function () {
    window.scrollTo(0, 0);
  });
</script>

<?php $v1 = @filemtime(__DIR__ . '/../assets/js/form-validators.js') ?: time(); ?>
<script defer src="<?= $baseUrl ?>/assets/js/form-validators.js?v=<?= (int)$v1 ?>"></script>

<?php $v2 = @filemtime(__DIR__ . '/../assets/js/signup-admin.js') ?: time(); ?>
<script defer src="<?= $baseUrl ?>/assets/js/signup-admin.js?v=<?= (int)$v2 ?>"></script>

<?php $v3 = @filemtime(__DIR__ . '/../assets/js/signup-admin-doctors.js') ?: time(); ?>
<script defer src="<?= $baseUrl ?>/assets/js/signup-admin-doctors.js?v=<?= (int)$v3 ?>"></script>

<script src="https://accounts.google.com/gsi/client" async defer></script>

</body>
</html>
