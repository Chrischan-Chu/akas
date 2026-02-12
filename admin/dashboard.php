<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

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
      c.contact AS clinic_contact,
      c.approval_status,
      c.declined_reason
   FROM accounts a
   LEFT JOIN clinics c ON c.id = a.clinic_id
   WHERE a.id = ? AND a.role = "clinic_admin"
   LIMIT 1'
);

$stmt->execute([auth_user_id()]);
$me = $stmt->fetch() ?: [];

$clinicId = (int)($me['clinic_id'] ?? 0);

/**
 * =========================================================
 * ✅ DOCTOR STATUS INDICATORS (DECLINED + PENDING COUNTS)
 * - Used to show a banner/badge in dashboard
 * =========================================================
 */
$declinedDoctors = 0;
$pendingDoctors  = 0;

// Keep your original (REGISTRATION) counts too, if you still want them:
$declinedRegDoctors = 0;
$pendingRegDoctors  = 0;

if ($clinicId > 0) {

  // ✅ All doctors counts
  $qAll = $pdo->prepare("
    SELECT
      SUM(approval_status='DECLINED') AS declined_all,
      SUM(approval_status='PENDING')  AS pending_all
    FROM clinic_doctors
    WHERE clinic_id = :cid
  ");
  $qAll->execute([':cid' => $clinicId]);
  $countsAll = $qAll->fetch(PDO::FETCH_ASSOC) ?: [];

  $declinedDoctors = (int)($countsAll['declined_all'] ?? 0);
  $pendingDoctors  = (int)($countsAll['pending_all'] ?? 0);

  // ✅ Your original REGISTRATION-only counts (kept)
  $q = $pdo->prepare("
    SELECT
      SUM(approval_status='DECLINED' AND created_via='REGISTRATION') AS declined_reg,
      SUM(approval_status='PENDING'  AND created_via='REGISTRATION') AS pending_reg
    FROM clinic_doctors
    WHERE clinic_id = :cid
  ");
  $q->execute([':cid' => $clinicId]);
  $counts = $q->fetch(PDO::FETCH_ASSOC) ?: [];

  $declinedRegDoctors = (int)($counts['declined_reg'] ?? 0);
  $pendingRegDoctors  = (int)($counts['pending_reg'] ?? 0);
}

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-slate-50">

<main class="max-w-5xl mx-auto px-6 py-10">

  <!-- HEADER -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">

    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight"
          style="color: var(--secondary);">
        Admin Dashboard
      </h1>
      <p class="text-slate-600 mt-1">
        Welcome back, <?php echo htmlspecialchars(auth_name() ?? 'Admin'); ?> 👋
      </p>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
      <a href="<?php echo $baseUrl; ?>/index.php"
         target="_blank" rel="noopener"
         class="h-11 px-5 rounded-xl font-semibold border border-slate-200 bg-white
                hover:bg-slate-50 transition flex items-center justify-center gap-2">
        View Website
      </a>

      <a href="<?php echo $baseUrl; ?>/logout.php"
         class="h-11 px-5 rounded-xl font-semibold text-white
                shadow-sm hover:opacity-90 transition flex items-center justify-center"
         style="background: var(--primary);">
        Logout
      </a>
    </div>

  </div>

  <?php
    $clinicStatus = (string)($me['approval_status'] ?? '');
    $declineReason = (string)($me['declined_reason'] ?? '');
  ?>

  <!-- ✅ NEW: DOCTOR DECLINED INDICATOR (SHOW ALWAYS IF ANY DECLINED) -->
  <?php if ($declinedDoctors > 0): ?>
  <a href="<?php echo $baseUrl; ?>/admin/doctors.php?status=DECLINED"
     class="group mb-6 block rounded-3xl border border-rose-200 bg-rose-50 p-5 hover:bg-rose-100 hover:shadow-md transition cursor-pointer">

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">

      <div class="min-w-0">
        <div class="text-sm font-extrabold text-rose-700">
          Doctor Application Declined
        </div>

        <div class="text-sm text-slate-700 mt-1">
          You have <span class="font-bold"><?php echo (int)$declinedDoctors; ?></span>
          declined doctor<?php echo $declinedDoctors > 1 ? 's' : ''; ?>.
          Click the "Doctors" button to view the reason and reapply.
        </div>

        <!-- ✨ Underline on hover -->
        <div class="mt-2 text-xs text-rose-700 font-semibold underline-offset-4 group-hover:underline group-hover:translate-x-1 transition">
          Click here to review declined doctor/s →
        </div>
      </div>

      <span class="shrink-0 inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold
                   bg-white text-rose-700 border border-rose-200">
        <?php echo (int)$declinedDoctors; ?> Declined
      </span>

    </div>
  </a>
<?php endif; ?>


  <?php if ($clinicStatus === 'DECLINED'): ?>
    <div class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="text-sm font-bold text-red-700">Clinic Application Declined</div>
          <div class="text-slate-700 mt-1 text-sm">
            <?php if ($declineReason !== ''): ?>
              Reason: <span class="font-semibold"><?= htmlspecialchars($declineReason, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
              Your clinic application was declined. Please review your details and reapply.
            <?php endif; ?>
          </div>

          <div class="mt-2 text-sm text-slate-700">
            Registration doctors declined: <span class="font-bold"><?= (int)$declinedRegDoctors ?></span>
          </div>
        </div>

        <form method="POST" action="<?= $baseUrl ?>/superadmin/action.php" class="shrink-0">
          <input type="hidden" name="clinic_id" value="<?= (int)$clinicId ?>">
          <input type="hidden" name="action" value="reapply">
          <button
            class="h-11 px-5 rounded-2xl font-bold text-white shadow-sm hover:opacity-95 transition"
            style="background: var(--secondary);">
            Reapply Now
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($clinicStatus === 'PENDING'): ?>
    <div class="mb-6 rounded-3xl border border-yellow-200 bg-yellow-50 p-5">
      <div class="text-sm font-bold text-yellow-700">Clinic Application Pending</div>
      <div class="text-sm text-slate-700 mt-1">
        Your clinic is under review by the super admin.
        <?php if ($pendingRegDoctors > 0): ?>
          Pending registration doctors: <span class="font-bold"><?= (int)$pendingRegDoctors ?></span>
        <?php endif; ?>
        <?php if ($pendingDoctors > 0): ?>
          <span class="ml-2">• Total pending doctors: <span class="font-bold"><?= (int)$pendingDoctors ?></span></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>


  <!-- CONTENT -->
  <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- CLINIC PROFILE -->
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
          <p class="text-lg font-semibold text-slate-900">
            <?php echo htmlspecialchars((string)($me['clinic_name'] ?? auth_name() ?? '')); ?>
          </p>

          <p class="text-sm text-slate-600 mt-0.5">
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
          <div class="font-semibold text-slate-900 break-all">
            <?php echo htmlspecialchars((string)($me['clinic_email'] ?? $me['admin_email'] ?? '—')); ?>
          </div>
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
          <div class="font-semibold text-slate-900">
            <?php echo htmlspecialchars((string)($me['business_id'] ?? '—')); ?>
          </div>
        </div>

      </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
      <h2 class="text-xl font-bold" style="color: var(--secondary);">Quick Actions</h2>
      <p class="text-sm text-slate-600 mt-2">Manage what patients see on your clinic profile.</p>

      <div class="mt-5 grid gap-3">
        <a href="<?php echo $baseUrl; ?>/admin/clinic-details.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--secondary);">
          Clinic Details
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/doctors.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--primary);">
          Doctors
        </a>

        <a href="<?php echo $baseUrl; ?>/admin/add-admin.php"
           class="w-full h-12 rounded-2xl font-bold text-white text-center flex items-center justify-center
                  shadow-sm hover:opacity-95 transition"
           style="background: var(--primary);">
          Add Admin Account
        </a>
      </div>
    </div>

  </section>

</main>

</body>
</html>
