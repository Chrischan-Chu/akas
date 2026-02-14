<?php
declare(strict_types=1);

$appTitle = "AKAS | Resend Verification";
$baseUrl  = "/AKAS";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_verification.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}

$emailPrefill = strtolower(trim((string)($_GET['email'] ?? '')));
$emailPrefill = preg_replace('/\s+/', '', $emailPrefill);

$errorMsg = flash_get('error');
$successMsg = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $email = preg_replace('/\s+/', '', $email);

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email.');
    redirect_to($baseUrl . '/pages/resend-verification.php');
  }

  $pdo = db();
  $stmt = $pdo->prepare('SELECT id, name, role, auth_provider, email_verified_at FROM accounts WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $acc = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$acc) {
    // Don’t reveal whether email exists (prevents account enumeration)
    flash_set('success', 'If an account exists for that email, we sent a verification link.');
    redirect_to($baseUrl . '/pages/resend-verification.php?email=' . urlencode($email));
  }

  if ((string)$acc['auth_provider'] !== 'local') {
    flash_set('success', 'This account was created using Google sign-in and does not require email verification.');
    redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
  }

  if (!empty($acc['email_verified_at'])) {
    flash_set('success', 'This email is already verified. You can log in.');
    redirect_to($baseUrl . '/pages/login.php?email=' . urlencode($email));
  }

  $token = akas_create_email_verify_token($pdo, (int)$acc['id'], 30);
  $sent  = akas_send_verification_email($baseUrl, $email, (string)($acc['name'] ?? ''), $token);

  flash_set('success', $sent
    ? 'Verification email sent! Please check your inbox/spam.'
    : 'Could not send email. Please configure SMTP in includes/smtp_config.php.'
  );

  redirect_to($baseUrl . '/pages/resend-verification.php?email=' . urlencode($email));
}

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-white">
<main class="min-h-screen flex items-center justify-center px-6 py-10">
  <section class="w-full max-w-xl rounded-[32px] border border-slate-100 shadow-xl overflow-hidden">

    <div class="p-10" style="background: var(--primary);">
      <h1 class="text-3xl sm:text-4xl font-extrabold text-black text-center">Resend Verification Email</h1>
      <p class="text-black/80 text-center mt-3 text-sm sm:text-base">
        Enter your email and we\'ll send a new verification link (manual accounts only).
      </p>
    </div>

    <div class="p-8 sm:p-10 bg-white">
      <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>

      <?php if (!empty($successMsg)): ?>
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2" for="email">Email address</label>
          <input id="email" name="email" type="email" required
                 value="<?php echo htmlspecialchars($emailPrefill); ?>"
                 class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-black/10"
                 placeholder="name@gmail.com" />
        </div>

        <button type="submit"
                class="w-full py-3 rounded-xl font-bold text-white"
                style="background: var(--secondary);">
          Send Verification Link
        </button>

        <div class="text-center text-sm text-slate-600">
          <a class="underline" href="<?php echo $baseUrl; ?>/pages/login.php<?php echo $emailPrefill ? ('?email=' . urlencode($emailPrefill)) : ''; ?>">Back to Login</a>
        </div>
      </form>
    </div>

  </section>
</main>
</body>
</html>
