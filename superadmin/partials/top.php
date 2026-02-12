<?php
declare(strict_types=1);

require_once __DIR__ . '/../_guard.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AKAS Super Admin</title>

  <!-- Tailwind CDN (works immediately, no build needed) -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root{
      --akas-blue: #4aa3ff; /* keep your website blue */
      --akas-light: #eaf4ff;
      --akas-dark: #0b1220;
    }
  </style>
</head>

<body class="bg-[#f6f8fb]">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-[260px] bg-[var(--akas-dark)] text-white fixed top-0 left-0 h-screen p-5">
      <div class="flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-full bg-[var(--akas-blue)]"></div>
        <div>
          <div class="font-bold leading-4">AKAS</div>
          <div class="text-xs text-slate-300">Super Admin</div>
        </div>
      </div>

      <nav class="flex flex-col gap-2 text-slate-200">
        <a class="px-3 py-2 rounded-xl hover:bg-white/10 <?= str_contains($_SERVER['PHP_SELF'],'dashboard.php') ? 'bg-white/10 text-white' : '' ?>"
           href="<?= $baseUrl ?>/superadmin/dashboard.php">Dashboard</a>

        <a class="px-3 py-2 rounded-xl hover:bg-white/10 <?= str_contains($_SERVER['PHP_SELF'],'clinics.php') ? 'bg-white/10 text-white' : '' ?>"
           href="<?= $baseUrl ?>/superadmin/clinics.php">Clinic Approvals</a>
           <a class="px-3 py-2 rounded-xl hover:bg-white/10 <?= str_contains($_SERVER['PHP_SELF'],'doctors.php') ? 'bg-white/10 text-white' : '' ?>"
          href="<?= $baseUrl ?>/superadmin/doctors.php?status=PENDING">CMS Doctor Approvals</a>

        <a href="<?= $baseUrl ?>/superadmin/users.php"
          class="px-3 py-2 rounded-xl transition-all duration-150 hover:bg-white/10 active:scale-95
          <?= str_contains($_SERVER['PHP_SELF'],'users.php') ? 'bg-white/10 text-white shadow-inner' : '' ?>">
          Users
        </a>

        <a class="mt-6 px-3 py-2 rounded-xl hover:bg-white/10"
           href="<?= $baseUrl ?>/superadmin/logout.php">Logout</a>
      </nav>
    </aside>

    <!-- Main -->
    <main class="ml-[260px] w-full p-6">
      <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-3xl font-bold text-slate-900">Overview</h1>

        <div class="flex items-center gap-2">
          <a href="<?= $baseUrl ?>/index.php" target="_blank" rel="noopener"
             class="px-5 py-2 rounded-full bg-white border border-slate-200 text-slate-800 font-semibold shadow-sm hover:bg-slate-50">
            View Website
          </a>

          <a href="<?= $baseUrl ?>/superadmin/clinics.php"
             class="px-5 py-2 rounded-full text-white font-semibold shadow-sm"
             style="background:var(--akas-blue);">
            Manage Clinics
          </a>
        </div>
      </div>
