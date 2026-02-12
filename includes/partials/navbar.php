<?php
if (!isset($baseUrl)) $baseUrl = "/AKAS";

require_once dirname(__DIR__) . "/auth.php";

$isLoggedIn = auth_is_logged_in();
$role = $isLoggedIn ? auth_role() : null;

// Role flags (explicit)
$isGuest       = !$isLoggedIn;
$isUser        = $isLoggedIn && $role === 'user';
$isClinicAdmin = $isLoggedIn && $role === 'clinic_admin';
$isSuperAdmin  = $isLoggedIn && $role === 'super_admin';

// Optional debug (view page source to see role)
// echo "<!-- ROLE_DEBUG: " . htmlspecialchars((string)$role) . " -->";
?>

<a id="top"></a>

<nav class="bg-white shadow-sm sticky top-0 z-40">
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
        <ul class="flex gap-6">
          <li><a href="<?php echo $baseUrl; ?>/index.php#home" class="nav-link transition-colors">Home</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#about" class="nav-link transition-colors">About</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#clinics" class="nav-link transition-colors">Clinics</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#contact" class="nav-link transition-colors">Contact</a></li>
        </ul>

        <div class="relative">
          <?php if ($isGuest): ?>
            <!-- Guest: Login / Sign Up -->
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
            <!-- Clinic Admin -->
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
            <!-- Superadmin -->
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
            <!-- User: Settings dropdown -->
            <button
              id="profileBtn"
              class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 transition"
              aria-label="Open account menu"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="profileMenu"
              type="button"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21a8 8 0 10-16 0"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11a4 4 0 100-8 4 4 0 000 8z"/>
              </svg>
            </button>

            <div
              id="profileMenu"
              class="hidden absolute right-2 mt-5 w-48 bg-white border border-slate-200 rounded-2xl shadow-lg overflow-hidden z-50"
              role="menu"
              aria-labelledby="profileBtn"
            >
              <a href="<?php echo $baseUrl; ?>/pages/settings.php"
                 class="block px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                 role="menuitem">
                Settings
              </a>

              <div class="h-px bg-slate-200/70"></div>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="block px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                 role="menuitem">
                Logout
              </a>
            </div>

          <?php else: ?>
            <!-- Fallback for unexpected roles: Dashboard + Logout -->
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Dashboard
              </a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">
                Logout
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Mobile burger -->
      <div class="lg:hidden flex items-center">
        <button id="burgerBtn"
                class="p-2 rounded-xl bg-white border border-gray-200 shadow-sm focus:outline-none"
                aria-label="Open menu"
                aria-controls="mobileMenu"
                aria-expanded="false">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

    </div>
  </div>
</nav>

<!-- Mobile menu -->
<div id="mobileMenu" class="fixed inset-0 z-50 hidden" aria-hidden="true">
  <div id="mobileBackdrop" class="absolute inset-0 bg-black/40"></div>

  <div class="relative h-full w-full bg-white flex flex-col">
    <div class="py-3 flex items-center justify-center" style="background: var(--primary);">
      <a href="<?php echo $baseUrl; ?>/index.php#top" class="flex items-center">
        <img
          src="<?php echo $baseUrl; ?>/assets/img/akas-logo-test.png"
          alt="AKAS Logo"
          class="w-auto"
          style="height: 72px;" <!-- FIX: force height -->
        />
      </a>

      <button id="closeMenu"
              class="absolute right-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full flex items-center justify-center text-white/90 hover:text-white hover:bg-white/10 transition"
              aria-label="Close menu">
        ✕
      </button>
    </div>

    <div class="px-6 pt-6 flex-1 overflow-auto">
      <div class="text-slate-900 font-extrabold text-2xl mb-4">Menu</div>

      <div class="divide-y divide-slate-200/80 rounded-2xl border border-slate-200/80 overflow-hidden">
        <a href="<?php echo $baseUrl; ?>/index.php#home" class="mobileLink flex items-center justify-between px-5 py-5 text-lg font-semibold">
          Home
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#about" class="mobileLink flex items-center justify-between px-5 py-5 text-lg font-semibold">
          About
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#clinics" class="mobileLink flex items-center justify-between px-5 py-5 text-lg font-semibold">
          Clinics
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>

        <a href="<?php echo $baseUrl; ?>/index.php#contact" class="mobileLink flex items-center justify-between px-5 py-5 text-lg font-semibold">
          Contact
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>
      </div>
    </div>

    <!-- Mobile bottom buttons -->
    <div class="px-6 pb-8 pt-4 border-t border-slate-200">
      <div class="grid grid-cols-1 gap-3">

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

<script>
(function () {
  const burgerBtn = document.getElementById("burgerBtn");
  const menu = document.getElementById("mobileMenu");
  const closeBtn = document.getElementById("closeMenu");
  const backdrop = document.getElementById("mobileBackdrop");
  const links = document.querySelectorAll(".mobileLink");

  function openMenu() {
    menu.classList.remove("hidden");
    menu.setAttribute("aria-hidden", "false");
    burgerBtn?.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";
  }

  function closeMenu() {
    menu.classList.add("hidden");
    menu.setAttribute("aria-hidden", "true");
    burgerBtn?.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }

  burgerBtn?.addEventListener("click", openMenu);
  closeBtn?.addEventListener("click", closeMenu);
  backdrop?.addEventListener("click", closeMenu);
  links.forEach(a => a.addEventListener("click", closeMenu));

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !menu.classList.contains("hidden")) closeMenu();
  });

  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  function closeProfileMenu() {
    if (!profileMenu) return;
    profileMenu.classList.add("hidden");
    profileBtn?.setAttribute("aria-expanded", "false");
  }

  profileBtn?.addEventListener("click", (e) => {
    e.stopPropagation();
    if (!profileMenu) return;
    const isHidden = profileMenu.classList.contains("hidden");
    profileMenu.classList.toggle("hidden");
    profileBtn?.setAttribute("aria-expanded", isHidden ? "true" : "false");
  });

  document.addEventListener("click", closeProfileMenu);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeProfileMenu();
  });
})();
</script>
