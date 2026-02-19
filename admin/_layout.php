<?php
declare(strict_types=1);

if (!isset($baseUrl)) $baseUrl = "";
require_once __DIR__ . "/../includes/auth.php";

/**
 * Inputs expected from page:
 * - $adminTitle   (string) page title (e.g., "Dashboard")
 * - $activeNav    (string) one of: dashboard, clinic, doctors, appointments, admins, verify
 * - $adminContent (string) HTML content captured by output buffer
 */

$role = auth_is_logged_in() ? auth_role() : null;
$isClinicAdmin = $role === 'clinic_admin';
$isSuperAdmin  = $role === 'superadmin';

if (!isset($adminTitle)) $adminTitle = "Admin";
if (!isset($activeNav))  $activeNav  = "dashboard";
if (!isset($adminContent)) $adminContent = "";

function navItem(string $href, string $label, string $key, string $activeKey): string {
  $active = $key === $activeKey;
  $cls = $active
    ? "bg-white/10 text-white"
    : "text-white/80 hover:text-white hover:bg-white/10";

  return '<a href="'.htmlspecialchars($href).'"
            class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold transition '.$cls.'">'.
            $label.
         '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars("AKAS | " . $adminTitle); ?></title>

  <!-- Use your compiled Tailwind -->
  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css">
</head>

<body class="min-h-screen bg-slate-50">

<!-- MOBILE SIDEBAR OVERLAY -->
<div id="adminMobileWrap" class="fixed inset-0 z-50 hidden">
  <div id="adminMobileBackdrop" class="absolute inset-0 bg-black/40"></div>

  <aside class="relative h-full w-[280px] max-w-[85vw] p-4"
         style="background: var(--primary);">
    <div class="flex items-center justify-between px-2 py-2">
      <div class="text-white font-extrabold tracking-wide">
        AKAS Admin
      </div>
      <button id="adminCloseMobile"
              class="h-10 w-10 rounded-xl bg-white/10 hover:bg-white/15 text-white font-bold">
        ✕
      </button>
    </div>

    <div class="mt-4 space-y-2">
      <?php echo navItem($baseUrl.'/admin/dashboard.php', '🏠 Dashboard', 'dashboard', $activeNav); ?>
      <?php if ($isClinicAdmin): ?>
        <?php echo navItem($baseUrl.'/admin/clinic-details.php', '🏥 Clinic Details', 'clinic', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/doctors.php', '🩺 Doctors', 'doctors', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/appointments.php', '📅 Appointments', 'appointments', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/add-admin.php', '👤 Add Admin', 'admins', $activeNav); ?>
      <?php elseif ($isSuperAdmin): ?>
        <?php echo navItem($baseUrl.'/admin/verify-admins.php', '✅ Verify Admins', 'verify', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/clinics-all.php', '🏥 All Clinics', 'clinic', $activeNav); ?>
      <?php endif; ?>
    </div>

    <div class="mt-6 border-t border-white/15 pt-4 space-y-2">
      <a href="<?php echo $baseUrl; ?>/index.php"
         target="_blank" rel="noopener"
         class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold
                bg-white text-slate-900 hover:bg-slate-50 transition">
        🌐 View Website
      </a>

      <a href="<?php echo $baseUrl; ?>/logout.php"
         class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold
                bg-white/10 hover:bg-white/15 text-white transition">
        Logout
      </a>
    </div>
  </aside>
</div>

<div class="min-h-screen flex">

  <!-- DESKTOP SIDEBAR -->
  <aside class="hidden lg:flex lg:flex-col lg:w-72 p-5"
         style="background: var(--primary);">
    <div class="flex items-center gap-3 px-2">
      <div class="text-white font-extrabold tracking-wide text-lg">
        AKAS Admin
      </div>
    </div>

    <div class="mt-6 space-y-2">
      <?php echo navItem($baseUrl.'/admin/dashboard.php', '🏠 Dashboard', 'dashboard', $activeNav); ?>

      <?php if ($isClinicAdmin): ?>
        <?php echo navItem($baseUrl.'/admin/clinic-details.php', '🏥 Clinic Details', 'clinic', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/doctors.php', '🩺 Doctors', 'doctors', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/appointments.php', '📅 Appointments', 'appointments', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/add-admin.php', '👤 Add Admin', 'admins', $activeNav); ?>
      <?php elseif ($isSuperAdmin): ?>
        <?php echo navItem($baseUrl.'/admin/verify-admins.php', '✅ Verify Admins', 'verify', $activeNav); ?>
        <?php echo navItem($baseUrl.'/admin/clinics-all.php', '🏥 All Clinics', 'clinic', $activeNav); ?>
      <?php endif; ?>
    </div>

    <div class="mt-auto pt-6 space-y-2 border-t border-white/15">
      <a href="<?php echo $baseUrl; ?>/index.php"
         target="_blank" rel="noopener"
         class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold
                bg-white text-slate-900 hover:bg-slate-50 transition">
        🌐 View Website
      </a>

      <a href="<?php echo $baseUrl; ?>/logout.php"
         class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold
                bg-white/10 hover:bg-white/15 text-white transition">
        Logout
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="flex-1">

    <!-- TOPBAR -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-slate-200">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <button id="adminOpenMobile"
                  class="lg:hidden h-11 w-11 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition"
                  aria-label="Open menu">
            ☰
          </button>

          <div>
            <div class="text-slate-900 font-extrabold text-xl leading-tight">
              <?php echo htmlspecialchars($adminTitle); ?>
            </div>
            <div class="text-slate-500 text-sm">
              Logged in as <span class="font-semibold"><?php echo htmlspecialchars((string)(auth_name() ?? 'Admin')); ?></span>
            </div>
          </div>
        </div>

        <!-- right actions -->
        <div class="hidden sm:flex items-center gap-2">
          <a href="<?php echo $baseUrl; ?>/index.php"
             target="_blank" rel="noopener"
             class="h-11 px-4 rounded-xl font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition flex items-center gap-2">
            🌐 View Website
          </a>
          <a href="<?php echo $baseUrl; ?>/logout.php"
             class="h-11 px-4 rounded-xl font-semibold text-white hover:opacity-90 transition flex items-center"
             style="background: var(--primary);">
            Logout
          </a>
        </div>
      </div>
    </header>

    <!-- PAGE CONTENT -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
      <?php echo $adminContent; ?>
    </main>

  </div>
</div>

<script>
(function () {
  const wrap = document.getElementById('adminMobileWrap');
  const openBtn = document.getElementById('adminOpenMobile');
  const closeBtn = document.getElementById('adminCloseMobile');
  const backdrop = document.getElementById('adminMobileBackdrop');

  function open() {
    wrap?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    wrap?.classList.add('hidden');
    document.body.style.overflow = '';
  }

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && wrap && !wrap.classList.contains('hidden')) close();
  });
})();
</script>

</body>
</html>
