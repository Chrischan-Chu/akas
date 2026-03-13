<?php declare(strict_types=1); require_once __DIR__ . '/../_guard.php'; ?> 
<!doctype html> 
<html lang="en"> 
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<title>AKAS Super Admin</title>

<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css">

<style>
:root{
  --akas-blue: #4aa3ff;
  --akas-light: #eaf4ff;
  --akas-dark: #0b1220;
}
</style>
</head>

<body class="min-h-screen bg-gradient-to-b from-[#8fd3ff] to-white">
  <!-- Mobile overlay -->
  <div id="mobileOverlay" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-black/40"></div>
  </div>

  <div class="min-h-screen">
    <!-- TOP BAR (like image 2) -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-slate-200">
      <div class="px-4 sm:px-6 lg:px-8">
        <div class="h-16 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <!-- Mobile menu button -->
            <button
              id="openSidebarBtn"
              type="button"
              class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 active:scale-95 transition"
              aria-label="Open menu"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
              </svg>
            </button>

            <!-- Brand -->
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-sm"
                   style="background:var(--akas-blue);">
                <span class="text-white font-extrabold tracking-wide">A</span>
              </div>
              <div class="leading-tight">
                <div class="font-extrabold text-slate-900">AKAS</div>
                <div class="text-xs text-slate-500 -mt-0.5">Super Admin</div>
              </div>
            </div>
          </div>

          <!-- Right actions (same links + same text) -->
          <div class="flex items-center gap-2">
            <a href="<?= $baseUrl ?>/index.php" target="_blank" rel="noopener"
              class="hidden sm:inline-flex px-5 py-2 rounded-full bg-white border border-slate-200 text-slate-800 font-semibold shadow-sm hover:bg-slate-50 active:scale-95 transition">
              View Website
            </a>

            <a href="<?= $baseUrl ?>/superadmin/clinics.php"
              class="px-5 py-2 rounded-full text-white font-semibold shadow-sm hover:brightness-95 active:scale-95 transition"
              style="background:var(--akas-blue);">
              Manage Clinics
            </a>
          </div>
        </div>
      </div>
    </header>

    <div class="flex min-h-[calc(100vh-64px)]">
      <!-- SIDEBAR (design only) -->
      <aside
        id="sidebar"
        class="fixed lg:sticky top-16 lg:top-0 left-0 z-[70] h-[calc(100vh-64px)] lg:h-screen w-[280px]
               bg-white/90 backdrop-blur border-r border-slate-200
               -translate-x-full lg:translate-x-0 transition-transform duration-200"
      >
        <div class="p-5">
          <!-- Mobile close -->
          <div class="flex items-center justify-between lg:hidden mb-4">
            <div class="font-extrabold text-slate-900">Menu</div>
            <button id="closeSidebarBtn" type="button"
              class="w-10 h-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 active:scale-95 transition"
              aria-label="Close menu">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-700 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Links: SAME hrefs + SAME active PHP -->
          <nav class="flex flex-col gap-2 text-slate-700">
            <a
              class="px-4 py-3 rounded-2xl font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition
              <?= str_contains($_SERVER['PHP_SELF'],'dashboard.php') ? 'ring-2 ring-[var(--akas-blue)] text-slate-900' : '' ?>"
              href="<?= $baseUrl ?>/superadmin/dashboard.php"
            >
              Dashboard
            </a>

            <a
              class="px-4 py-3 rounded-2xl font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition
              <?= str_contains($_SERVER['PHP_SELF'],'clinics.php') ? 'ring-2 ring-[var(--akas-blue)] text-slate-900' : '' ?>"
              href="<?= $baseUrl ?>/superadmin/clinics.php"
            >
              Clinic Approvals
            </a>

            <a
              class="px-4 py-3 rounded-2xl font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition
              <?= str_contains($_SERVER['PHP_SELF'],'doctors.php') ? 'ring-2 ring-[var(--akas-blue)] text-slate-900' : '' ?>"
              href="<?= $baseUrl ?>/superadmin/doctors.php?status=PENDING"
            >
              CMS Doctor Approvals
            </a>

            <a
              href="<?= $baseUrl ?>/superadmin/users.php"
              class="px-4 py-3 rounded-2xl font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition
              <?= str_contains($_SERVER['PHP_SELF'],'users.php') ? 'ring-2 ring-[var(--akas-blue)] text-slate-900' : '' ?>"
            >
              Users
            </a>

            <div class="h-px bg-slate-200 my-2"></div>

            <a
              class="px-4 py-3 rounded-2xl font-semibold text-white shadow-sm hover:brightness-95 active:scale-95 transition"
              style="background:var(--akas-blue);"
              href="<?= $baseUrl ?>/superadmin/logout.php"
            >
              Logout
            </a>
          </nav>
        </div>
      </aside>

      <!-- MAIN (your content continues exactly the same after this header row) -->
      <main class="w-full lg:ml-0">
        <div class="px-4 sm:px-6 lg:px-8 py-8">
          <!-- Your original header row, same content -->
          <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-3xl font-extrabold text-white drop-shadow-sm">Overview</h1>

            
          </div>

          <!-- IMPORTANT:
               Do NOT remove this wrapper.
               Your existing dashboard page content continues below as-is.
          -->