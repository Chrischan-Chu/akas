<?php
$appTitle = "AKAS | Login";
$baseUrl  = "/AKAS";
require_once __DIR__ . '/../includes/auth.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$errorMsg = flash_get('error');
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

      <div class="bg-[#FFFDF6] relative flex items-start justify-center pt-16 p-10">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="w-80 max-w-full"
        />

        <img
          src="<?php echo $baseUrl; ?>/assets/img/doctor.png"
          alt="Doctor"
          class="hidden lg:block absolute bottom-0 z-30 pointer-events-none
                 -right-10 xl:-right-24
                 w-[260px] lg:w-[300px] xl:w-[360px]"
        />
      </div>

      <div
        class="relative flex items-center justify-center p-6 sm:p-8 lg:p-10 rounded-tl-[40px] rounded-bl-[40px]"
        style="background: var(--primary);">

        <div class="w-full max-w-sm px-4 sm:px-6 lg:px-0">

          <h1 class="login-title text-5xl font-semibold text-white mb-6 text-center">
            LOGIN
          </h1>

          <?php if ($errorMsg): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
              <?php echo htmlspecialchars($errorMsg); ?>
            </div>
          <?php endif; ?>

          <form action="<?php echo $baseUrl; ?>/pages/login-process.php" method="POST">

            <div class="relative mb-5">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/80">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l9 6 9-6m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8" />
                </svg>
              </span>

              <input
                type="text"
                name="email"
                placeholder="Email"
                class="w-full rounded-xl bg-white px-12 py-3 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                required
              />
            </div>

            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/80">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
                </svg>
              </span>

              <input
                type="password"
                name="password"
                placeholder="Password"
                class="w-full rounded-xl bg-white px-12 py-3 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/60"
                required
              />
            </div>

            <div class="mt-3 text-xs text-black/80">
              <a href="#" class="hover:underline">Forgot password?</a>
            </div>

            <div class="mt-8 flex flex-wrap items-center gap-3">
              <button
                type="submit"
                class="px-6 sm:px-8 py-2.5 text-sm sm:text-base rounded-lg font-semibold text-white shadow-md hover:shadow-lg transition-all"
                style="background-color: var(--secondary);">
                Login
              </button>

              <a
                href="<?php echo $baseUrl; ?>/pages/signup.php"
                class="px-4 sm:px-6 lg:px-8 py-2.5 text-sm sm:text-base rounded-lg font-semibold shadow-md hover:shadow-lg transition-all border border-white whitespace-nowrap"
                style="background-color: rgba(255, 255, 255, 0.9); color: var(--primary);">
                Sign Up
              </a>
            </div>

          </form>

        </div>
      </div>

    </div>
  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js"></script>
</body>
</html>
