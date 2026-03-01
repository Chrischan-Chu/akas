<?php
if (!isset($baseUrl)) $baseUrl = "";

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
        <ul class="flex gap-6">
          <li><a href="<?php echo $baseUrl; ?>/index.php#home" class="nav-link transition-colors">Home</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#about" class="nav-link transition-colors">About</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#clinics" class="nav-link transition-colors">Clinics</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#contact" class="nav-link transition-colors">Contact</a></li>
          <li><a href="<?php echo $baseUrl; ?>/index.php#clinic-map" class="nav-link transition-colors">Map</a></li>
        </ul>

        <!-- Right side (keep relative so dropdowns anchor correctly) -->
        <div class="relative">
          <?php if ($isGuest): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/pages/login.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Login</a>

              <a href="<?php echo $baseUrl; ?>/pages/signup.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Sign Up</a>
            </div>

          <?php elseif ($isClinicAdmin): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Clinic Dashboard</a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Logout</a>
            </div>

          <?php elseif ($isSuperAdmin): ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/superadmin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Admin Dashboard</a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Logout</a>
            </div>

          <?php elseif ($isUser): ?>
            <div class="flex items-center gap-2">
              <!-- Notifications -->
              <div class="relative">
                <button
                  id="notifBtn"
                  class="relative inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 transition"
                  aria-label="Open notifications"
                  aria-haspopup="true"
                  aria-expanded="false"
                  aria-controls="notifMenu"
                  type="button"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-5-5.9V4a1 1 0 1 0-2 0v1.1A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a3 3 0 0 0 6 0"/>
                  </svg>
                  <span id="notifBadge" class="hidden absolute -top-1 -right-1 h-5 min-w-[20px] px-1 rounded-full text-[11px] font-extrabold text-white flex items-center justify-center"
                        style="background: var(--primary);"></span>
                </button>

                <div
                  id="notifMenu"
                  class="hidden absolute right-0 top-full mt-2 w-80 bg-white border border-slate-200 rounded-2xl shadow-lg overflow-hidden z-50"
                  role="menu"
                  aria-labelledby="notifBtn"
                >
                  <div class="px-4 py-3 text-sm font-extrabold text-slate-900">Notifications</div>
                  <div class="h-px bg-slate-200/70"></div>
                  <div id="notifList" class="max-h-80 overflow-auto">
                    <div class="px-4 py-4 text-sm text-slate-600">Loading…</div>
                  </div>
                </div>
              </div>

              <!-- Profile -->
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
            </div>

            <div
              id="profileMenu"
              class="hidden absolute right-0 top-full mt-2 w-48 bg-white border border-slate-200 rounded-2xl shadow-lg overflow-hidden z-50"
              role="menu"
              aria-labelledby="profileBtn"
            >
              <a href="<?php echo $baseUrl; ?>/pages/settings.php"
                 class="block px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                 role="menuitem">Settings</a>

              <div class="h-px bg-slate-200/70"></div>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="block px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                 role="menuitem">Logout</a>
            </div>

          <?php else: ?>
            <div class="flex gap-2">
              <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Dashboard</a>

              <a href="<?php echo $baseUrl; ?>/logout.php"
                 class="px-6 py-2 rounded-full font-semibold text-white transition-all duration-300"
                 style="background-color: var(--primary);">Logout</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Mobile actions -->
      <div class="lg:hidden flex items-center gap-2">
        <?php if ($isUser): ?>
          <div class="relative">
            <button
              id="notifBtnM"
              class="relative inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 transition"
              aria-label="Open notifications"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="notifMenuM"
              type="button"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-5-5.9V4a1 1 0 1 0-2 0v1.1A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a3 3 0 0 0 6 0"/>
              </svg>
              <span id="notifBadgeM" class="hidden absolute -top-1 -right-1 h-5 min-w-[20px] px-1 rounded-full text-[11px] font-extrabold text-white flex items-center justify-center"
                    style="background: var(--primary);"></span>
            </button>

            <div
              id="notifMenuM"
              class="hidden absolute right-0 top-full mt-2 w-80 bg-white border border-slate-200 rounded-2xl shadow-lg overflow-hidden z-[20000]"
              role="menu"
              aria-labelledby="notifBtnM"
            >
              <div class="px-4 py-3 text-sm font-extrabold text-slate-900">Notifications</div>
              <div class="h-px bg-slate-200/70"></div>
              <div id="notifListM" class="max-h-80 overflow-auto">
                <div class="px-4 py-4 text-sm text-slate-600">Loading…</div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <button id="burgerBtn"
                type="button"
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

  <!-- underline at bottom of navbar -->
  <span id="navUnderline" class="nav-underline" aria-hidden="true"></span>
</nav>

<!-- Mobile menu -->
<div id="mobileMenu"
     class="hidden lg:hidden fixed left-0 right-0 top-[72px] z-[9999] bg-white border-t border-slate-200 shadow-md max-h-[calc(100vh-72px)] overflow-auto">

  <div class="max-w-6xl mx-auto px-4">
    <div class="px-4 py-4">

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
        
        <a href="<?php echo $baseUrl; ?>/index.php#clinic-map" class="mobileLink flex items-center justify-between px-5 py-4 text-lg font-semibold">
          Map
          <span class="text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6" />
            </svg>
          </span>
        </a>
      </div>

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

<?php if ($isUser): ?>
<script>
(function () {
  const BASE_URL = document.body?.dataset?.baseUrl || "<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>";

  const btnD = document.getElementById('notifBtn');
  const menuD = document.getElementById('notifMenu');
  const listD = document.getElementById('notifList');
  const badgeD = document.getElementById('notifBadge');

  const btnM = document.getElementById('notifBtnM');
  const menuM = document.getElementById('notifMenuM');
  const listM = document.getElementById('notifListM');
  const badgeM = document.getElementById('notifBadgeM');

  const profileBtn = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  async function safeJson(res) {
    const txt = await res.text();
    try { return JSON.parse(txt); } catch { throw new Error(txt.slice(0, 120)); }
  }

  function render(items, listEl, badgeEl) {
    if (!listEl) return;

    if (!Array.isArray(items) || items.length === 0) {
      listEl.innerHTML = '<div class="px-4 py-4 text-sm text-slate-600">No notifications.</div>';
      if (badgeEl) { badgeEl.classList.add('hidden'); badgeEl.textContent = ''; }
      return;
    }

    if (badgeEl) {
      badgeEl.textContent = String(items.length);
      badgeEl.classList.remove('hidden');
    }

    listEl.innerHTML = items.map(it => {
      const title = `${it.clinic_name || 'Clinic'} • ${it.doctor_name || 'Doctor'}`;
      const when = `${it.date || ''} ${it.time_12 || ''}`.trim();
      return `
        <div class="px-4 py-3 border-b border-slate-200/70">
          <div class="text-sm font-extrabold text-slate-900">${escapeHtml(title)}</div>
          <div class="text-xs text-slate-600 mt-1">Approved appointment: <span class="font-semibold">${escapeHtml(when)}</span></div>
          <div class="mt-2">
            <button data-appt-id="${it.appointment_id}" class="notifCancelBtn text-xs font-extrabold px-3 py-1 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50">Cancel</button>
          </div>
        </div>
      `;
    }).join('');

    listEl.querySelectorAll('.notifCancelBtn').forEach(b => {
      b.addEventListener('click', async () => {
        const id = b.getAttribute('data-appt-id');
        if (!id) return;
        b.disabled = true;
        try {
          const res = await fetch(`${BASE_URL}/api/user_delete_appointment.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ appointment_id: id })
          });
          const data = await safeJson(res);
          if (!res.ok) throw new Error(data.error || 'Cancel failed');
          await loadAndRender();
        } catch (e) {
          alert(e?.message || 'Cancel failed');
          b.disabled = false;
        }
      });
    });
  }

  async function loadAndRender() {
    try {
      const res = await fetch(`${BASE_URL}/api/user_notifications.php`, { credentials: 'same-origin', cache: 'no-store' });
      const data = await safeJson(res);
      const items = (res.ok && data?.ok === true) ? (data.items || []) : [];
      render(items, listD, badgeD);
      render(items, listM, badgeM);
    } catch {
      if (listD) listD.innerHTML = '<div class="px-4 py-4 text-sm text-rose-600">Failed to load notifications.</div>';
      if (listM) listM.innerHTML = '<div class="px-4 py-4 text-sm text-rose-600">Failed to load notifications.</div>';
    }
  }

  function closeAll() {
    if (menuD) { menuD.classList.add('hidden'); btnD?.setAttribute('aria-expanded', 'false'); }
    if (menuM) { menuM.classList.add('hidden'); btnM?.setAttribute('aria-expanded', 'false'); }
    if (profileMenu) { profileMenu.classList.add('hidden'); profileBtn?.setAttribute('aria-expanded', 'false'); }
  }

  btnD?.addEventListener('click', async (e) => {
    e.preventDefault();
    const isOpen = !menuD.classList.contains('hidden');
    closeAll();
    menuD.classList.toggle('hidden', isOpen);
    btnD.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (!isOpen) await loadAndRender();
  });

  btnM?.addEventListener('click', async (e) => {
    e.preventDefault();
    const isOpen = !menuM.classList.contains('hidden');
    closeAll();
    menuM.classList.toggle('hidden', isOpen);
    btnM.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (!isOpen) await loadAndRender();
  });

  profileBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = !profileMenu.classList.contains('hidden');
    closeAll();
    profileMenu.classList.toggle('hidden', isOpen);
    profileBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  });

  document.addEventListener('click', (e) => {
    const t = e.target;
    const clickedInNotifD = menuD?.contains(t) || btnD?.contains(t);
    const clickedInNotifM = menuM?.contains(t) || btnM?.contains(t);
    const clickedInProfile = profileMenu?.contains(t) || profileBtn?.contains(t);
    if (!clickedInNotifD && !clickedInNotifM && !clickedInProfile) closeAll();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll();
  });

  // preload badges
  loadAndRender();
})();
</script>
<?php endif; ?>
