<?php
// includes/partials/navbar.php
// Make sure $baseUrl exists even if the page didn't define it
if (!isset($baseUrl)) $baseUrl = "/AKAS";
?>

<a id="top"></a>

<!-- Back to Top -->
<button
  id="backToTop"
  class="flex items-center justify-center cursor-pointer hover:shadow-2xl transition-all text-white text-xl font-bold rounded-full"
  style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; width: 48px; height: 48px; background-color: var(--primary); border: 2px solid rgba(255,255,255,0.5); display: none; box-shadow: 0 4px 12px rgba(64, 183, 255, 0.4);">
  ↑
</button>

<nav class="bg-white shadow-sm py-3 sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-4">
    <div class="flex justify-between items-center">

      <!-- Brand -->
      <a href="<?php echo $baseUrl; ?>/index.php#top" class="flex items-center font-bold text-lg gap-2">AKAS</a>

      <!-- Desktop links -->
      <div class="hidden md:flex items-center gap-8">
        <ul class="flex gap-6">
          <li><a href="<?php echo $baseUrl; ?>/index.php#home" class="nav-link transition-colors">Home</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#about" class="nav-link transition-colors">About</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#clinics" class="nav-link transition-colors">Clinics</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#contact" class="nav-link transition-colors">Contact</a></li>
        </ul>

        <div class="flex gap-2">
          <a href="<?php echo $baseUrl; ?>/pages/login.php"
             class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
             style="background-color: var(--primary);">Login</a>

          <a href="<?php echo $baseUrl; ?>/pages/signup.php"
             class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
             style="background-color: var(--primary);">Sign Up</a>
        </div>
      </div>

      <!-- Mobile burger menu -->
      <div class="md:hidden flex items-center">
        <button id="burgerBtn" class="p-2 rounded-xl bg-white border border-gray-200 shadow-sm focus:outline-none" aria-label="Open menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
    <!-- Mobile menu panel -->
    <div id="mobileMenu" class="md:hidden fixed inset-0 z-50 bg-white/95 backdrop-blur-sm flex flex-col items-center justify-center gap-8 text-xl font-semibold text-[var(--primary)] transition-all duration-300 opacity-0 pointer-events-none">
      <button id="closeMenu" class="absolute top-6 right-6 p-2 rounded-full bg-gray-100 hover:bg-gray-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
      <a href="<?php echo $baseUrl; ?>/index.php#home" class="nav-link">Home</a>
      <a href="<?php echo $baseUrl; ?>/index.php#about" class="nav-link">About</a>
      <a href="<?php echo $baseUrl; ?>/index.php#clinics" class="nav-link">Clinics</a>
      <a href="<?php echo $baseUrl; ?>/index.php#contact" class="nav-link">Contact</a>
      <a href="<?php echo $baseUrl; ?>/pages/login.php" class="nav-link">Login</a>
      <a href="<?php echo $baseUrl; ?>/pages/signup.php" class="nav-link">Sign Up</a>
    </div>
    <script>
      const burgerBtn = document.getElementById('burgerBtn');
      const mobileMenu = document.getElementById('mobileMenu');
      const closeMenu = document.getElementById('closeMenu');
      burgerBtn.addEventListener('click', () => {
        mobileMenu.classList.remove('opacity-0', 'pointer-events-none');
        mobileMenu.classList.add('opacity-100');
      });
      closeMenu.addEventListener('click', () => {
        mobileMenu.classList.add('opacity-0', 'pointer-events-none');
        mobileMenu.classList.remove('opacity-100');
      });
      // Close menu on link click
      mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          mobileMenu.classList.add('opacity-0', 'pointer-events-none');
          mobileMenu.classList.remove('opacity-100');
        });
      });
    </script>

    </div>
  </div>
</nav>
