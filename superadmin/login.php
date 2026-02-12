<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$baseUrl = '/AKAS';
$pdo = db();

$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  // Filter out entries, using preg_match or other filtering function
  // Check email validity ISO standard emal format
  

  $stmt = $pdo->prepare("SELECT * FROM accounts WHERE email = :email LIMIT 1");
  $stmt->execute([':email' => $email]);
  $acc = $stmt->fetch();

  if ($acc && $acc['role'] === 'super_admin' && password_verify($pass, $acc['password_hash'])) {
    auth_set((int)$acc['id'], (string)$acc['role'], (string)$acc['name'], (string)$acc['email'], null);
    header('Location: ' . $baseUrl . '/superadmin/dashboard.php');
    exit;
  }

  flash_set('error', 'Invalid Super Admin credentials.');
  header('Location: ' . $baseUrl . '/superadmin/login.php');
  exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Super Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f6f8fb] min-h-screen flex items-center justify-center p-6">
  <div class="w-full max-w-md bg-white rounded-3xl shadow-lg p-6">
    <div class="mb-4">
      <h2 class="text-2xl font-bold text-slate-900">AKAS Super Admin</h2>
      <p class="text-slate-500 text-sm">Login to approve/decline clinics.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-3">
      <div>
        <label class="text-sm text-slate-600">Email</label>
        <input name="email" required class="w-full border rounded-xl px-3 py-2 outline-none" />
      </div>

      <div>
        <label class="text-sm text-slate-600">Password</label>
        <input name="password" type="password" required class="w-full border rounded-xl px-3 py-2 outline-none" />
      </div>

      <button class="w-full text-white font-semibold py-2 rounded-full"
              style="background:#4aa3ff;">
        Login
      </button>
    </form>

    <div class="text-center mt-4">
      <a class="text-sm text-slate-500 hover:underline" href="<?= $baseUrl ?>/index.php#top">Back to website</a>
    </div>
  </div>
</body>
</html>
