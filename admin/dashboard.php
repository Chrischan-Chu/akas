<?php
declare(strict_types=1);

$appTitle = 'AKAS | Admin Dashboard';
$baseUrl  = '/AKAS';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('clinic_admin', $baseUrl);

$pdo = db();
$stmt = $pdo->prepare(
  'SELECT
      a.email AS admin_email,
      c.id AS clinic_id,
      c.clinic_name, c.specialty, c.specialty_other, c.logo_path,
      c.business_id,
      c.email AS clinic_email,
      c.contact AS clinic_contact
   FROM accounts a
   LEFT JOIN clinics c ON c.id = a.clinic_id
   WHERE a.id = ? AND a.role = "clinic_admin"
   LIMIT 1'
);
$stmt->execute([auth_user_id()]);
$me = $stmt->fetch() ?: [];

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-slate-50">

<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold" style="color: var(--secondary);">Admin Dashboard</h1>
      <p class="text-slate-600 mt-1">Welcome, <?php echo htmlspecialchars(auth_name() ?? 'Admin'); ?>.</p>
    </div>

    <div class="flex gap-2">
      <a href="<?php echo $baseUrl; ?>/logout.php"
         class="px-5 py-2 rounded-xl font-semibold text-white"
         style="background: var(--primary);">Logout</a>
    </div>
  </div>

  <section class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
      <h2 class="text-xl font-bold" style="color: var(--secondary);">Clinic Profile</h2>

      <div class="mt-5 flex items-start gap-4">
        <div class="h-16 w-16 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center">
          <?php if (!empty($me['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars((string)$me['logo_path']); ?>" alt="Logo" class="h-full w-full object-cover" />
          <?php else: ?>
            <span class="text-slate-400 text-sm">No Logo</span>
          <?php endif; ?>
        </div>

        <div class="flex-1">
          <p class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars((string)($me['clinic_name'] ?? auth_name() ?? '')); ?></p>
          <p class="text-sm text-slate-600">
            <?php
              $type = (string)($me['specialty'] ?? '-');
              if ($type === 'Other' && !empty($me['specialty_other'])) {
                $type = (string)$me['specialty_other'];
              }
            ?>
            Type: <?php echo htmlspecialchars($type); ?>
          </p>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="rounded-2xl border border-slate-200 p-4">
          <div class="text-slate-500">Email</div>
          <div class="font-semibold text-slate-900"><?php echo htmlspecialchars((string)($me['clinic_email'] ?? $me['admin_email'] ?? '')); ?></div>
        </div>
        <div class="rounded-2xl border border-slate-200 p-4">
  <div class="text-slate-500">Contact</div>

  <div class="font-semibold text-slate-900">
    <?php
      $phone = preg_replace('/\D/', '', (string)($me['clinic_contact'] ?? ''));

      echo ($phone && strlen($phone) === 10)
        ? '+63 ' . htmlspecialchars($phone)
        : '—';
    ?>
  </div>
</div>

        <div class="rounded-2xl border border-slate-200 p-4 sm:col-span-2">
          <div class="text-slate-500">Business ID</div>
          <div class="font-semibold text-slate-900"><?php echo htmlspecialchars((string)($me['business_id'] ?? '—')); ?></div>
        </div>
      </div>
    </div>

    <div class="rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
      <h2 class="text-xl font-bold" style="color: var(--secondary);">Quick Actions</h2>
      <p class="text-sm text-slate-600 mt-2">Manage what patients see on your clinic profile.</p>

      <div class="mt-5 grid gap-3">
        <a href="<?php echo $baseUrl; ?>/admin/clinic-details.php"
           class="w-full py-3 rounded-2xl font-bold text-white text-center"
           style="background: var(--secondary);">
          Clinic Details
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/doctors.php"
           class="w-full py-3 rounded-2xl font-bold text-white text-center"
           style="background: var(--primary);">
          Doctors
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/add-admin.php"
           class="w-full py-3 rounded-2xl font-bold text-white text-center"
           style="background: var(--primary);">
          Add Admin Account
        </a>
      </div>
    </div>

  </section>

</main>

</body>
</html>
