<?php
declare(strict_types=1);

$appTitle = "AKAS | Reset Password";
$baseUrl  = "";

require_once __DIR__ . '/../includes/auth.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$email = strtolower(trim((string)($_GET['email'] ?? '')));
$email = preg_replace('/\s+/', '', $email);
$token = (string)($_GET['token'] ?? '');

$errorMsg = flash_get('error');
$successMsg = flash_get('success');

// Basic token presence check (full validation happens in reset-password-process.php)
$tokenOk = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $token !== '');
if (!$tokenOk && $errorMsg === null) {
  $errorMsg = 'Invalid or missing reset link.';
}

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-white">
<main class="min-h-screen flex items-center justify-center px-6 py-10">
  <section class="w-full max-w-xl rounded-[32px] border border-slate-100 shadow-xl overflow-hidden">

    <div class="p-10" style="background: var(--primary);">
      <h1 class="text-3xl sm:text-4xl font-extrabold text-black text-center">Create new password</h1>
      <p class="text-black/80 text-center mt-3 text-sm sm:text-base">Enter your new password below.</p>
    </div>

    <div class="p-8 sm:p-10 bg-white">
      <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>

      <?php if (!empty($successMsg)): ?>
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <?php if ($tokenOk): ?>
      <form action="<?php echo $baseUrl; ?>/pages/reset-password-process.php" method="post" class="space-y-4" data-inline-errors="1">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <!-- Password (copied behavior from signup-user.php) -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2" for="password">Password</label>
          <div class="relative">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="New password"
              data-validate="password"
              data-required-msg="Password is required."
              required
              class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-12 outline-none focus:ring-2 focus:ring-black/10"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center text-slate-500 hover:text-slate-700 focus:outline-none"
              data-toggle-password="#password"
              aria-label="Show password"
              aria-pressed="false"
            >
              <svg class="pw-eye h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <svg class="pw-eye-off hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.474-4.118m3.197-2.146A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.043 5.307M15 12a3 3 0 00-3-3m0 0a2.99 2.99 0 00-2.225.99M12 9v.01M3 3l18 18"/>
              </svg>
            </button>
          </div>
          <p data-err-for="password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
        </div>

        <!-- Confirm Password (copied behavior from signup-user.php) -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2" for="confirm_password">Confirm Password</label>
          <div class="relative">
            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              placeholder="Confirm password"
              data-validate="password-confirm"
              data-match="password"
              data-required-msg="Please confirm your password."
              required
              class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-12 outline-none focus:ring-2 focus:ring-black/10"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center text-slate-500 hover:text-slate-700 focus:outline-none"
              data-toggle-password="#confirm_password"
              aria-label="Show password"
              aria-pressed="false"
            >
              <svg class="pw-eye h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <svg class="pw-eye-off hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.474-4.118m3.197-2.146A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.043 5.307M15 12a3 3 0 00-3-3m0 0a2.99 2.99 0 00-2.225.99M12 9v.01M3 3l18 18"/>
              </svg>
            </button>
          </div>
          <p data-err-for="confirm_password" class="min-h-[16px] mt-1 text-sm text-red-600"></p>
        </div>

        <button type="submit"
                class="w-full py-3 rounded-xl font-bold text-white"
                style="background: var(--secondary);">
          Update Password
        </button>

        <div class="text-center mt-4">
    <a href="<?php echo $baseUrl; ?>/pages/login.php"
       class="text-sm text-blue-600 hover:underline">
        Back to Login
    </a>
</div>
      </form>
      <?php endif; ?>

    </div>

  </section>
</main>

<?php $v = @filemtime(__DIR__ . '/../assets/js/form-validators.js') ?: time(); ?>
<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js?v=<?php echo (int)$v; ?>"></script>
</body>
</html>
