<?php
declare(strict_types=1);

$appTitle = "AKAS | Forgot Password";
$baseUrl  = "";

require_once __DIR__ . '/../includes/auth.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$emailPrefill = strtolower(trim((string)($_GET['email'] ?? '')));
$emailPrefill = preg_replace('/\s+/', '', $emailPrefill);

$errorMsg = flash_get('error');
$successMsg = flash_get('success');

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-white">
<main class="min-h-screen flex items-center justify-center px-6 py-10">
  <section class="w-full max-w-xl rounded-[32px] border border-slate-100 shadow-xl overflow-hidden">

    <div class="p-10" style="background: var(--primary);">
      <h1 class="text-3xl sm:text-4xl font-extrabold text-black text-center">Reset your password</h1>
      <p class="text-black/80 text-center mt-3 text-sm sm:text-base">
        Enter your email address and we’ll send you a password reset link.
      </p>
    </div>

    <div class="p-8 sm:p-10 bg-white">
      <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>

      <?php if (!empty($successMsg)): ?>
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <form action="<?php echo $baseUrl; ?>/pages/forgot-password-process.php" method="post" class="space-y-4" data-inline-errors="1">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2" for="email">Email <span class="text-red-600 font-semibold">*</span></label>
          <input id="email" name="email" type="email" required
                 data-validate="email"
                 data-required-msg="Email is required."
                 value="<?php echo htmlspecialchars($emailPrefill); ?>"
                 class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-black/10"
                 placeholder="name@gmail.com" />
          <p data-err-for="email" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
        </div>

        <button type="submit"
                class="w-full py-3 rounded-xl font-bold text-white"
                style="background: var(--secondary);">
          Send Reset Link
        </button>

        <div class="text-center mt-4">
    <a href="<?php echo $baseUrl; ?>/pages/login.php"
       class="text-sm text-blue-600 hover:underline">
        Back to Login
    </a>
</div>
      </form>

      <div class="mt-6 text-center text-xs text-slate-500">
        <p>Reset is available for <strong>verified</strong> email accounts only.</p>
        <p class="mt-1">If you haven’t verified yet, use <a class="underline" href="<?php echo $baseUrl; ?>/pages/resend-verification.php<?php echo $emailPrefill ? ('?email=' . urlencode($emailPrefill)) : ''; ?>">Resend Verification</a>.</p>
      </div>
    </div>

  </section>
</main>

<?php $v = @filemtime(__DIR__ . '/../assets/js/form-validators.js') ?: time(); ?>
<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js?v=<?php echo (int)$v; ?>"></script>
</body>
</html>
