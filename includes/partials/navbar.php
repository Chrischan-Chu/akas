<?php
if (!isset($baseUrl)) $baseUrl = "/AKAS";

require_once dirname(__DIR__) . "/auth.php";

$isLoggedIn = auth_is_logged_in();
$role = $isLoggedIn ? auth_role() : null;

$isGuest       = !$isLoggedIn;
$isUser        = $isLoggedIn && $role === 'user';
$isClinicAdmin = $isLoggedIn && $role === 'clinic_admin';
$isSuperAdmin  = $isLoggedIn && $role === 'super_admin';

?>

<a id="top"></a>

<nav id="mainNavbar" class="relative bg-white shadow-sm sticky top-0 z-[10000]">

  <div class="max-w-6xl mx-auto px-4">
    <div class="px-4 flex justify-between items-center" style="min-height: 72px;">

      <a href="<?php echo $baseUrl; ?>/index.php#top" class="flex items-center gap-2">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo.png"
          alt="AKAS Logo"
          class="w-auto"
          style="max-height: 45px; width:auto;"
        />
      </a>

      <!-- Desktop -->
      <div class="hidden lg:flex items-center gap-8">

        <!-- 🔥 REMOVE relative from UL -->
        <ul class="flex gap-6">
          <li><a href="<?php echo $baseUrl; ?>/index.php#home" class="nav-link transition-colors">Home</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#about" class="nav-link transition-colors">About</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#clinics" class="nav-link transition-colors">Clinics</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#contact" class="nav-link transition-colors">Contact</a></li>
        </ul>

        <!-- Right Side Buttons (UNCHANGED) -->
        <div class="relative">
          <?php if ($isGuest): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/pages/login.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Login
              </a>

              <a href="<?php echo $baseUrl; ?>/pages/signup.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Sign Up
              </a>
            </div>

          <?php elseif ($isClinicAdmin): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Clinic Dashboard
              </a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Logout
              </a>
            </div>

          <?php elseif ($isSuperAdmin): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/superadmin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Admin Dashboard
              </a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Logout
              </a>
            </div>

          <?php elseif ($isUser): ?>
            <button
              id="profileBtn"
              class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 transition"
              type="button">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21a8 8 0 10-16 0"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11a4 4 0 100-8 4 4 0 000 8z"/>
              </svg>
            </button>
          <?php endif; ?>
        </div>

      </div>

      <!-- Mobile burger -->
      <div class="lg:hidden flex items-center">
        <button id="burgerBtn"
                type="button"
                class="p-2 rounded-xl bg-white border border-gray-200 shadow-sm focus:outline-none">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

    </div>
  </div>

  <!-- 🔥 UNDERLINE MOVED TO BOTTOM OF NAVBAR -->
  <span id="navUnderline" class="nav-underline" aria-hidden="true"></span>

</nav>



<!-- Mobile menu (dropdown under navbar) -->
<div id="mobileMenu"
     class="hidden lg:hidden fixed left-0 right-0 top-[72px] z-[9999] bg-white border-t border-slate-200 shadow-md max-h-[calc(100vh-72px)] overflow-auto">

  <div class="max-w-6xl mx-auto px-4">
    <div class="px-4 py-4">

      <!-- links -->
      <div class="divide-y divide-slate-200/80 rounded-2xl border border-slate-200/80 overflow-hidden">
        <a href="<?php echo $baseUrl; ?>/index.php#home" class="mobileLink flex items-center justify-between px-5 py-4 text-lg font-semibold">
          Home
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#about" class="mobileLink flex items-center justify-between px-5 py-4 text-lg font-semibold">
          About
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#clinics" class="mobileLink flex items-center justify-between px-5 py-4 text-lg font-semibold">
          Clinics
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#contact" class="mobileLink flex items-center justify-between px-5 py-4 text-lg font-semibold">
          Contact
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>
      </div>

      <!-- bottom buttons -->
      <div class="mt-4 grid grid-cols-1 gap-3">
        <?php if ($isGuest): ?>
          <a href="<?php echo $baseUrl; ?>/pages/login.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-white"
             style="background: var(--secondary);">Login</a>

          <a href="<?php echo $baseUrl; ?>/pages/signup.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-gray-900"
             style="background: var(--accent);">Sign Up</a>

        <?php elseif ($isClinicAdmin): ?>
          <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-white"
             style="background: var(--secondary);">Clinic Dashboard</a>

          <a href="<?php echo $baseUrl; ?>/logout.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-gray-900"
             style="background: var(--accent);">Logout</a>

        <?php elseif ($isSuperAdmin): ?>
          <a href="<?php echo $baseUrl; ?>/superadmin/dashboard.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-white"
             style="background: var(--secondary);">Admin Dashboard</a>

          <a href="<?php echo $baseUrl; ?>/logout.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-gray-900"
             style="background: var(--accent);">Logout</a>

        <?php elseif ($isUser): ?>
          <a href="<?php echo $baseUrl; ?>/pages/settings.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-white"
             style="background: var(--secondary);">Settings</a>

          <a href="<?php echo $baseUrl; ?>/logout.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-gray-900"
             style="background: var(--accent);">Logout</a>

        <?php else: ?>
          <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-white"
             style="background: var(--secondary);">Dashboard</a>

          <a href="<?php echo $baseUrl; ?>/logout.php"
             class="w-full text-center rounded-2xl py-3 font-extrabold text-gray-900"
             style="background: var(--accent);">Logout</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>


