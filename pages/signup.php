<?php
$appTitle = "AKAS | Choose Account Type";
$baseUrl  = "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $appTitle ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

<div class="w-full max-w-6xl bg-white rounded-3xl shadow-2xl overflow-hidden">
  <div class="flex flex-col lg:flex-row">

    <!-- LEFT PANEL -->
    <div class="lg:w-1/2 w-full text-white flex flex-col"
         style="background-color:#40b7ff;">

      <!-- Back to home INSIDE LEFT ONLY -->
      <div class="px-8 sm:px-12 pt-6">
        <a href="../index.php"
           class="text-white font-medium hover:underline transition">
          ← Back to home
        </a>
      </div>

      <div class="p-8 sm:p-12 lg:p-16 flex flex-col flex-grow">
        <div>
          <h1 class="text-4xl sm:text-5xl font-bold leading-tight">
            Create your AKAS account
          </h1>

          <p class="mt-4 text-white/95 text-base sm:text-lg">
            Choose what type of account you want to create.
          </p>
        </div>

        <!-- Bottom Sign In -->
        <div class="mt-10 lg:mt-auto pt-10">
          <div class="flex items-center gap-4 opacity-90 mb-4">
            <div class="h-px bg-white/60 flex-1"></div>
            <span class="text-sm sm:text-base font-medium whitespace-nowrap">
              Already have an account?
            </span>
            <div class="h-px bg-white/60 flex-1"></div>
          </div>

          <a href="login.php"
             class="flex items-center justify-center gap-3 font-semibold text-lg sm:text-xl hover:underline transition">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M10 17l5-5-5-5" />
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15 12H3" />
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 3v18a2 2 0 01-2 2h-6" />
            </svg>
            <span>Sign in to your account</span>
          </a>
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL (FULL WHITE, INCLUDING TOP) -->
    <div class="lg:w-1/2 w-full bg-white p-8 sm:p-12 lg:p-16 flex flex-col justify-center gap-10 sm:gap-14">

      <!-- USER -->
      <div>
        <div class="flex items-center gap-3 mb-3">
          <svg class="w-7 h-7" style="color:#40b7ff;"
               fill="none" stroke="currentColor" stroke-width="2"
               viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M5.121 17.804A9 9 0 1118.879 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <h2 class="text-2xl sm:text-3xl font-bold text-slate-800">
            User Account
          </h2>
        </div>

        <p class="text-slate-600 text-base sm:text-lg mb-6">
          Book appointments, browse clinics, and manage your schedule.
        </p>

        <a href="signup-user.php"
           class="block w-full text-center bg-orange-500 hover:bg-orange-600
                  text-white font-semibold text-xl py-4 sm:py-5
                  rounded-full shadow-lg transition duration-200">
          Continue as User
        </a>
      </div>

      <!-- CLINIC ADMIN -->
      <div>
        <div class="flex items-center gap-3 mb-3">
          <svg class="w-7 h-7" style="color:#40b7ff;"
               fill="none" stroke="currentColor" stroke-width="2"
               viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M9 8h6M9 12h6M9 16h6" />
          </svg>
          <h2 class="text-2xl sm:text-3xl font-bold text-slate-800">
            Clinic Admin Account
          </h2>
        </div>

        <p class="text-slate-600 text-base sm:text-lg mb-6">
          Register a clinic, manage doctors, and handle appointments.
        </p>

        <a href="signup-admin.php"
           class="block w-full text-center text-white font-semibold text-xl
                  py-4 sm:py-5 rounded-full shadow-lg transition duration-200"
           style="background-color:#40b7ff;"
           onmouseover="this.style.backgroundColor='#2fa5e6'"
           onmouseout="this.style.backgroundColor='#40b7ff'">
          Continue as Admin
        </a>

        <p class="text-sm text-slate-500 mt-4">
          Admin sign up has multiple steps. Make sure the clinic details are accurate.
        </p>
      </div>

    </div>
  </div>
</div>

</body>
</html>