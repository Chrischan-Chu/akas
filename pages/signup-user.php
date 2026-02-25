<?php
$appTitle = "AKAS | User Sign Up";
$baseUrl  = "";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_config.php';

// ✅ flash messages
$errMsg = flash_get('error');
$okMsg  = flash_get('success');
include "../includes/partials/head.php";
?>

<body class="min-h-screen">
 
<main class="min-h-screen w-full">
  <!-- ✅ Flash Messages -->
  <?php if ($errMsg): ?>
    <div class="w-full px-4 pt-4">
      <div class="mx-auto max-w-md rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($okMsg): ?>
    <div class="w-full px-4 pt-4">
      <div class="mx-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        <?= htmlspecialchars($okMsg, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
<style>
  .akas-logo {
    width: 260px;
    max-width: 100%;
    height: auto;
    display: block;
    margin-left: auto;
    margin-right: auto;
  }
  @media (min-width: 640px) {
    .akas-logo { width: 320px; }
  }
  @media (min-width: 768px) {
    .akas-logo { width: 360px; }
  }
  @media (min-width: 1024px) {
    .akas-logo { width: 500px; }
  }

  /* ✅ MANUAL DESKTOP POSITION CONTROL (ONLY DESKTOP) */
  @media (min-width: 1024px) {
    .logo-wrap {
      margin-top: 120px;    
      padding-bottom: 80px;  
    }
  }
</style>

<!-- LEFT: WHITE BRANDING -->
<section class="bg-white px-6 py-10 sm:px-10 lg:px-12 lg:py-14 lg:min-h-screen">
  <div class="w-full max-w-md mx-auto flex flex-col lg:min-h-screen">

    <!-- TOP TEXT -->
    <div class="text-left">
      <h1 class="text-slate-900 font-bold text-4xl sm:text-5xl leading-tight">
        Create your user account
      </h1>

      <p class="mt-4 text-slate-600 text-base sm:text-lg leading-relaxed">
        Discover available clinics and book your preferred appointments with ease.
      </p>
    </div>

    <!-- LOGO -->
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

      <!-- BACK BUTTON -->
      <a
        href="<?= $baseUrl; ?>/pages/signup.php"
        class="absolute text-white font-semibold hover:underline z-50"
        style="top:8px; left:13px;"
      >
        ← Back to selection
      </a>

      <div class="min-h-screen px-6 sm:px-10 py-12 flex items-center justify-center">
        <div class="w-full max-w-sm">

          <h2 class="mt-10 text-white text-2xl sm:text-3xl font-semibold">
            Sign up for User
          </h2>

          <!-- Google Signup -->
          <div class="mt-6">
            <form id="googleUserSignupForm" action="<?= $baseUrl; ?>/pages/google-auth.php" method="POST">
              <input type="hidden" name="mode" value="signup">
              <input type="hidden" name="role" value="user">
              <input type="hidden" name="credential" id="googleCredentialUserSignup">
            </form>

            <div
              id="g_id_onload"
              data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID); ?>"
              data-callback="onGoogleUserSignup"
              data-auto_prompt="false">
            </div>

            <!-- Force Google button to match input width (max-w-sm ≈ 384px) -->
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

            <script>
              function onGoogleUserSignup(response) {
                document.getElementById('googleCredentialUserSignup').value = response.credential;
                document.getElementById('googleUserSignupForm').submit();
              }
            </script>

            <!-- OR -->
            <div class="flex items-center gap-3 mt-6">
              <div class="h-px flex-1 bg-white/40"></div>
              <div class="text-xs text-white/90 font-semibold">OR</div>
              <div class="h-px flex-1 bg-white/40"></div>
            </div>
          </div>

          <!-- USER FORM -->
          <form
            action="signup-process.php"
            method="POST"
            class="mt-2 space-y-3"
            novalidate
            data-inline-errors="1"
          >
            <input type="hidden" name="role" value="user" />

            <!-- Full Name -->
            <div>
              <label class="block text-md text-white mb-1 ml-1">
                Full Name <span class="text-red-600 font-semibold ml-1">*</span>
              </label>
              <input
                type="text"
                name="name"
                placeholder="Full Name"
                maxlength="50"
                data-validate="full-name"
                required
                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                       text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
              <p data-err-for="name" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
            </div>

           <div>
  <label class="block text-md text-white mb-1 ml-1">
    Gender <span class="text-red-600 font-semibold ml-1">*</span>
  </label>

  <div class="relative">
    <select
      name="gender"
      required
      data-required-msg="Please select a Gender."
      class="w-full appearance-none rounded-xl bg-white pl-4 pr-10 py-2.5
             border border-white/80 text-slate-700
             focus:outline-none focus:ring-2 focus:ring-white/60"
    >
      <option value="" disabled selected hidden>Select a Gender</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
      <option value="Prefer not to say">Prefer not to say</option>
    </select>

    <!-- custom arrow -->
    <svg
      class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-600"
      viewBox="0 0 20 20"
      fill="currentColor"
      aria-hidden="true"
    >
      <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/>
    </svg>
  </div>

  <p data-err-for="gender" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
</div>

            <!-- Email -->
            <div>
              <label class="block text-md text-white mb-1 ml-1">
                Email <span class="text-red-600 font-semibold ml-1">*</span>
              </label>
              <input
                type="text"
                name="email"
                placeholder="Email"
                data-validate="email"
                data-unique="accounts_email"
                required
                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                       text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
              <p data-err-for="email" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-md text-white mb-1 ml-1">
                Password <span class="text-red-600 font-semibold ml-1">*</span>
              </label>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Password"
                data-validate="password"
                required
                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                       text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
              <p data-err-for="password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
            </div>

            <!-- Confirm Password -->
            <div>
              <label class="block text-md text-white mb-1 ml-1">
                Confirm Password <span class="text-red-600 font-semibold ml-1">*</span>
              </label>
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Confirm Password"
                data-validate="password-confirm"
                data-match="password"
                required
                class="w-full rounded-xl bg-white px-4 py-2.5 border border-white/80
                       text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
              <p data-err-for="confirm_password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
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
                  type="tel"
                  name="contact_number"
                  placeholder="9123456789"
                  inputmode="numeric"
                  required
                  data-validate="phone-ph"
                  data-unique="accounts_phone"
                  class="flex-1 rounded-xl bg-white px-4 py-2.5 border border-white/80
                         text-slate-700 placeholder:text-slate-400
                         focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>
              <p data-err-for="contact_number" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
            </div>

            <!-- Birthdate -->
            <div>
              <div class="bg-white/95 rounded-xl px-4 py-2.5 border border-white/80">
                <label class="block text-md text-black mb-1 ml-1">
                  Birthdate <span class="text-red-600 font-semibold ml-1">*</span>
                </label>
                <input
                  type="date"
                  name="birthdate"
                  required
                  data-validate="age-18"
                  data-required-msg="Please select your Birthdate."
                  class="w-full rounded-xl bg-white px-4 py-2.5 border border-slate-200
                         text-slate-700 focus:outline-none focus:ring-2 focus:ring-white/60"
                />
              </div>
              <p data-err-for="birthdate" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
            </div>

        <button
  type="submit"
  class="w-full py-3 rounded-xl font-semibold text-white text-base
         transition-colors duration-300"
  style="background-color:#ffa154;"
  onmouseover="this.style.backgroundColor='#f97316'"
  onmouseout="this.style.backgroundColor='#ffa154'"
>
  Create Account
</button>
            <!-- Bottom block -->
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

          </form>

        </div>
      </div>
    </section>

  </div>
</main>

<?php $v = filemtime(__DIR__ . '/../assets/js/form-validators.js'); ?>
<script src="<?= $baseUrl ?>/assets/js/form-validators.js?v=<?= $v ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>

</body>
</html>