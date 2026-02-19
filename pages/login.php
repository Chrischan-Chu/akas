<?php
$appTitle = "AKAS | Login";
$baseUrl  = "";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_config.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$errorMsg = flash_get('error');
$successMsg = flash_get('success');
$emailPrefill = strtolower(trim((string)($_GET['email'] ?? '')));
$emailPrefill = preg_replace('/\s+/', '', $emailPrefill);
$needVerify = ((string)($_GET['need_verify'] ?? '') === '1');
include "../includes/partials/head.php";
?>

<body class="min-h-screen bg-white">

<style>
  .login-title {
    font-family: ui-monospace, "Courier New", monospace;
    letter-spacing: .14em;
  }
</style>

<main class="min-h-screen flex items-center justify-center py-10">

  <section
    class="w-full max-w-6xl
           mx-4 sm:mx-8 lg:mx-10 xl:mx-auto
           rounded-[40px] overflow-visible shadow-xl border border-slate-100">

    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[560px]">

      <div class="bg-[#FFFDF6] flex items-center justify-center
            py-10 sm:py-12 md:py-16
            lg:h-full">

  <img
    src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
    alt="AKAS Logo"
    class="w-48 sm:w-64 md:w-72 lg:w-96 xl:w-72
           object-contain"
  />
</div>


      <div
        class="relative flex items-center justify-center p-6 sm:p-8 lg:p-10 rounded-tl-[40px] rounded-bl-[40px]"
        style="background: var(--primary);">

        <div class="w-full max-w-sm px-4 sm:px-6 lg:px-0">

          <h1 class="login-title text-5xl font-semibold text-white mb-6 text-center">
            SIGN IN
          </h1>

          <?php if ($successMsg): ?>
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm">
              <?php echo htmlspecialchars($successMsg); ?>
            </div>
          <?php endif; ?>

          <?php if ($errorMsg): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
              <?php echo htmlspecialchars($errorMsg); ?>
            </div>
          <?php endif; ?>
          <?php if ($needVerify && $emailPrefill): ?>
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900">
              <div class="text-sm font-semibold">Email verification required</div>
              <div class="mt-1 text-sm">
                We found your account, but your email isn’t verified yet. Please check your inbox (and Spam/Junk) for the verification link.
              </div>
              <form class="mt-3" action="<?php echo $baseUrl; ?>/pages/resend-verification.php" method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($emailPrefill); ?>">
                <button type="submit"
                  class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold bg-black text-white hover:opacity-90">
                  Resend verification email
                </button>
              </form>
              <div class="mt-2 text-xs text-black/60">
                Tip: If you signed in with Google for the same Gmail address, your account will be verified automatically.
              </div>
            </div>
          <?php endif; ?>


          <form action="<?php echo $baseUrl; ?>/pages/login-process.php" method="POST">

            <!-- Email -->
            <div class="mb-5">
            <label for="email" class="block text-sm font-semibold text-white/90 mb-2">
              Email Address
            </label>

            <div class="flex items-center bg-white rounded-xl overflow-hidden">
    
    <!-- Icon -->
              <div class="px-4 text-black/80">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 8l9 6 9-6m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8" />
                </svg>
              </div>
              <div class="h-8 w-px" style="background: var(--primary);"></div>
              <input
                id="email"
                type="text"
                name="email"
                placeholder="Enter your Email Address"
                required
                data-validate="email"
                class="flex-1 px-4 py-3 text-slate-700 focus:outline-none"
              / value="<?php echo htmlspecialchars($emailPrefill); ?>">
            </div>
</div>


           <!-- Password -->
<div class="mb-2">
  <label for="password"
         class="block text-sm font-semibold text-white/90 mb-2">
    Password
  </label>

  <div class="flex items-center bg-white rounded-xl overflow-hidden">

    <!-- Icon -->
    <div class="px-4 text-black/80">
      <svg xmlns="http://www.w3.org/2000/svg"
           class="h-5 w-5"
           fill="none"
           viewBox="0 0 24 24"
           stroke="currentColor">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
      </svg>
    </div>

    <!-- Vertical Line -->
    <div class="h-8 w-px" style="background: var(--primary);"></div>

    <!-- Input -->
    <input
      id="password"
      type="password"
      name="password"
      placeholder="Enter your Password"
      required
      class="flex-1 px-4 py-3 text-slate-700 focus:outline-none"
    />
  </div>
</div>


            <div class="mt-3 text-xs text-black/80">
              <a href="#" class="hover:underline">Forgot password?</a>
            </div>

            <!-- Login button ONLY -->
            <div class="mt-5">
              <button
                type="submit"
                class="w-full py-3 text-sm sm:text-base rounded-xl font-semibold text-white shadow-md hover:shadow-lg transition-all"
                style="background-color: var(--secondary);">
                Sign In
              </button>
            </div>

          </form>

          <!-- OR + Google -->
          <div>
            <div class="flex items-center gap-5 my-3">
              <div class="h-px flex-1 bg-white/40"></div>
              <div class="text-xs text-white/90 font-semibold">OR</div>
              <div class="h-px flex-1 bg-white/40"></div>
            </div>

            <form id="googleLoginForm" action="<?php echo $baseUrl; ?>/pages/google-auth.php" method="POST">
              <input type="hidden" name="mode" value="login">
              <input type="hidden" name="role" value="user">
              <input type="hidden" name="credential" id="googleCredentialLogin">
            </form>

            <div id="g_id_onload"
                 data-client_id="<?php echo htmlspecialchars(GOOGLE_CLIENT_ID); ?>"
                 data-callback="onGoogleLogin"
                 data-auto_prompt="false">
            </div>

            <!-- Make Google button wider -->
            <div class="flex justify-center">
              <div class="g_id_signin"
                   data-type="standard"
                   data-size="large"
                   data-theme="outline"
                   data-text="signin_with"
                   data-shape="pill"
                   data-logo_alignment="left"
                   data-width="315">
              </div>
            </div>

            <!-- Don't have an account -->
           <p class="mt-6 text-center text-sm text-white">
  Don’t have an account?
  <a href="<?php echo $baseUrl; ?>/pages/signup.php"
     class="font-semibold text-white hover:text-yellow-300 transition-all duration-200">
    Sign Up
  </a>
</p>



          </div>

          <script>
            function onGoogleLogin(response) {
              document.getElementById('googleCredentialLogin').value = response.credential;
              document.getElementById('googleLoginForm').submit();
            }
          </script>

        </div>
      </div>

    </div>
  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>
