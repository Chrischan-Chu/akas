<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$appTitle = 'AKAS | Add Admin Account';
$baseUrl  = '';

require_once __DIR__ . '/../includes/auth.php';
auth_require_role('clinic_admin', $baseUrl);

$ok    = flash_get('ok');
$error = flash_get('error');

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-slate-50">

<main class="max-w-4xl mx-auto px-4 py-10">
  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold" style="color: var(--secondary);">Add Admin Account</h1>
      <p class="text-slate-600 mt-1">Create another admin account for the same clinic.</p>
    </div>

    <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
       class="px-5 py-2 rounded-xl font-semibold border border-slate-200 bg-white hover:bg-slate-50">
      Back
    </a>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-5 py-4">
      <p class="font-semibold"><?php echo htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 text-red-900 px-5 py-4">
      <p class="font-semibold"><?php echo htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  <?php endif; ?>

  <section class="mt-6 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">

    <form action="<?php echo $baseUrl; ?>/admin/add-admin-process.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-4">

      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Admin Full Name</label>
        <input
          type="text"
          name="admin_name"
          maxlength="50"
          pattern="^[A-Za-z ]{1,50}$"
          title="You can only use letters and spacing (Maximum of 50 characters)."
          data-validate="full-name"
          required
          placeholder="Full Name"
          class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
        />
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Work ID (Optional)</label>
        <input
          type="file"
          name="admin_work_id"
          accept="image/png,image/jpeg,image/webp"
          class="mt-2 block w-full text-sm text-slate-700
                 file:mr-4 file:py-2 file:px-4
                 file:rounded-xl file:border-0
                 file:text-sm file:font-semibold
                 file:bg-slate-100 file:text-slate-800
                 hover:file:bg-slate-200"
        />
        <p class="text-xs text-slate-500 mt-1">PNG/JPG/WEBP only (max 2MB).</p>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Admin Email</label>
        <input
          type="text"
          name="email"
          required
          data-validate="email"
          data-unique="accounts_email"
          placeholder="name@gmail.com"
          class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
        />
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-700">Password</label>
        <div class="relative mt-2">
          <input
            type="password"
            id="password"
            name="password"
            required
            data-validate="password"
            placeholder="Password"
            class="w-full h-12 rounded-2xl border border-slate-200 bg-white pl-4 pr-12 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          />
          <button type="button" class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700" data-target="password"></button>
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-700">Confirm Password</label>
        <div class="relative mt-2">
          <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            required
            data-validate="password-confirm"
            data-match="password"
            placeholder="Confirm Password"
            class="w-full h-12 rounded-2xl border border-slate-200 bg-white pl-4 pr-12 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          />
          <button type="button" class="toggle-pass absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700" data-target="confirm_password"></button>
        </div>
      </div>

      <div class="sm:col-span-2 flex flex-col sm:flex-row gap-3 mt-2">
        <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
           class="flex-1 text-center py-3 rounded-2xl font-semibold border border-slate-200 bg-white hover:bg-slate-50">
          Cancel
        </a>
        <button
          type="submit"
          class="flex-1 py-3 rounded-2xl font-extrabold text-black shadow-md hover:shadow-lg transition-all"
          style="background: var(--secondary);"
        >
          Create Admin
        </button>
      </div>

    </form>
  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js"></script>
</body>
</html>
