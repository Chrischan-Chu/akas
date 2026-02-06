<?php
$appTitle = "AKAS | User Sign Up";
$baseUrl  = "/AKAS";
include "../includes/partials/head.php";
?>

<body class="bg-white">

<style>
  .auth-title {
    font-family: ui-monospace, "Courier New", monospace;
    letter-spacing: .14em;
  }
</style>

<main class="min-h-screen flex items-center justify-center">

  <section
    class="w-full max-w-6xl
           mx-4 sm:mx-8 lg:mx-10 xl:mx-auto
           rounded-2xl sm:rounded-3xl lg:rounded-[40px]
           overflow-hidden shadow-xl border border-slate-100">

    <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[480px]">

      <!-- LEFT (off-white) -->
      <div class="bg-[#FFFDF6] relative flex items-center justify-center p-6 sm:p-10">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="w-56 sm:w-64 max-w-full"
        />
      </div>

      <!-- RIGHT (blue panel) -->
      <div class="relative flex items-center justify-center p-4 sm:p-6 lg:p-10" style="background: var(--primary);">

        <div class="w-full max-w-sm px-3 sm:px-4 lg:px-0">

          <!-- Title -->
          <h1 class="auth-title text-3xl sm:text-4xl font-semibold text-white text-center">
            USER SIGN UP
          </h1>
          <p class="text-center text-white text-sm mb-5">
            Create your user account to book appointments.
          </p>

          <form action="signup-process.php" method="POST" class="space-y-3">
            <input type="hidden" name="role" value="user" />

            <!-- Name -->
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/80">
                <!-- user icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5.121 17.804A9 9 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </span>
              <input
                type="text"
                name="name"
                placeholder="Full Name"
                required
                class="w-full rounded-xl bg-white px-12 py-2.5 text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
            </div>
            <!-- Gender -->
            <!-- Gender -->
<div>
  <div class="relative">
    <select
      id="gender"
      name="gender"
      required
      class="appearance-none w-full rounded-xl bg-white px-4 pr-12 py-2.5
             text-slate-700 outline-none border border-slate-200
             focus:ring-2 focus:ring-white/60"
    >
      <option value="" disabled selected hidden>Select a Gender</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
      <option value="Prefer not to say">Prefer not to say</option>
    </select>

    <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 flex items-center text-slate-500">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </div>
  </div>
</div>



            <!-- Email -->
            <div class="relative">
              <span class="absolute inset-y-0 left-4 flex items-center text-black/80">
                <!-- mail icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l9 6 9-6m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8" />
                </svg>
              </span>
              <input
                type="text"
                name="email"
                placeholder="Email"
                data-validate="email"
                data-error-id="emailError"
                required
                class="w-full rounded-xl bg-white px-12 py-2.5 text-slate-700 placeholder:text-slate-400
                       focus:outline-none focus:ring-2 focus:ring-white/60"
              />
            </div>

           <!-- PASSWORD + CONFIRM (side by side on sm+) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

              <!-- PASSWORD -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/80">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
                  </svg>
                </span>

                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="Password"
                  data-validate="password"
                  required
                  class="w-full rounded-xl border border-slate-200 pl-12 pr-12 py-3 outline-none focus:ring-2 focus:ring-slate-200"
                />

                <button
                  type="button"
                  class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                  data-target="password">
                </button>
              </div>

              <!-- CONFIRM PASSWORD -->
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-black/80">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 11V7a4 4 0 00-8 0v4m8 0h6a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2h6z" />
                  </svg>
                </span>

                <input
                  type="password"
                  id="confirm_password"
                  name="confirm_password"
                  placeholder="Confirm Password"
                  data-validate="password-confirm"
                  data-match="password"
                  required
                  class="w-full rounded-xl border border-slate-200 pl-12 pr-12 py-3 outline-none focus:ring-2 focus:ring-slate-200"
                />

                <button
                  type="button"
                  class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                  data-target="confirm_password">
                </button>
              </div>

            </div>



            <!-- Contact Number -->
            <div class="space-y-1">
              <div class="flex items-center gap-2">

                <!-- +63 prefix (compact on mobile) -->
                <div class="bg-white rounded-xl px-2 sm:px-2 py-2 sm:py-2.5 text-slate-700 font-semibold border border-white/40">
                  +63
                </div>

                <!-- input -->
                <input
                type="tel"
                id="contactNumber"
                name="number"
                placeholder="9XXXXXXXXX"
                inputmode="numeric"
                minlength="10"
                required
                data-validate="phone-ph"
                data-error-id="phoneError"
                class="flex-1 rounded-xl bg-white px-2 sm:px-2 py-2 sm:py-2.5
                      text-slate-300 placeholder:text-slate-300
                      focus:outline-none focus:ring-2 focus:ring-white/60
                      text-sm sm:text-base"
              />
              </div>

            </div>

            <!-- Birthdate -->
            <div class="bg-white/90 rounded-xl px-4 py-2.5 border border-white/40">
              <label class="block text-xs font-semibold text-slate-700 mb-2">
                Birthdate
              </label>
              <input
                type="date"
                name="birthdate"
                data-error-id="birthdateError"
                data-validate="age-18"
                class="w-full rounded-xl bg-white px-4 py-2.5 text-slate-700 outline-none border border-slate-200
                       focus:ring-2 focus:ring-white/60"
                required
              />
            </div>

            <!-- Buttons -->
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">

              <!-- Back -->
              <a
                href="<?php echo $baseUrl; ?>/pages/signup.php"
                class="w-full text-center py-2.5 text-sm sm:text-base rounded-lg font-semibold
                      border border-slate-300 text-slate-600 bg-white
                      hover:bg-slate-100 transition">
                ← Back to Selection
              </a>

              <!-- Create Account -->
              <button
                type="submit"
                class="w-full py-2.5 text-sm sm:text-base rounded-lg font-semibold
                      text-black  shadow-md hover:shadow-lg transition-all"
                style="background-color: var(--secondary);">
                Create Account
              </button>

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
