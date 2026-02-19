<?php
$appTitle = "AKAS | Sign Up Success";
$baseUrl  = "";
require_once __DIR__ . '/../includes/auth.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$role = $_GET['role'] ?? 'user';
$isAdmin = ($role === 'clinic_admin');

$successMsg = flash_get('success');
$errorMsg = flash_get('error');
include "../includes/partials/head.php";
?>

<body class="min-h-screen bg-white">

<main class="min-h-screen flex items-center justify-center px-6 py-10">
  <section class="w-full max-w-2xl rounded-[32px] border border-slate-100 shadow-xl overflow-hidden">

    <div class="p-10" style="background: var(--primary);">
      <h1 class="text-4xl sm:text-5xl font-extrabold text-black text-center">Congrats! 🎉</h1>
      <p class="text-black/80 text-center mt-3 text-sm sm:text-base">
        Your <?php echo $isAdmin ? 'clinic admin' : 'user'; ?> account was created successfully.
      </p>
    </div>

    <div class="p-8 sm:p-10 bg-white">
      <?php if (!empty($errorMsg)): ?>
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>
      <?php if (!empty($successMsg)): ?>
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><?php echo htmlspecialchars($successMsg); ?></div>
      <?php endif; ?>

      <div class="rounded-2xl border border-slate-200 p-5 text-slate-700">
        <p class="font-semibold mb-2">Next step:</p>
        <ul class="list-disc pl-5 space-y-1 text-sm">
          <li>Check your email and click the verification link.</li>
          <li>After verifying, log in using the email + password you used in sign up.</li>
          <?php if ($isAdmin): ?>
            <li>If your clinic is still pending approval, you will be sent to the Admin Pending page after login.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="mt-8 flex flex-col sm:flex-row gap-3">
        <a href="<?php echo $baseUrl; ?>/pages/login.php"
           class="flex-1 text-center py-3 rounded-xl font-bold text-white"
           style="background: var(--secondary);">Go to Login</a>
        <a href="<?php echo $baseUrl; ?>/pages/resend-verification.php"
           class="flex-1 text-center py-3 rounded-xl font-bold text-slate-900"
           style="background: var(--accent);">Resend Verification</a>
        <a href="<?php echo $baseUrl; ?>/index.php#top"
           class="flex-1 text-center py-3 rounded-xl font-bold text-slate-900"
           style="background: var(--accent);">Back to Website</a>
      </div>
    </div>

  </section>
</main>

</body>
</html>
