<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
$pdo = db();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

$stmt = $pdo->prepare("
  SELECT id, name, email, phone
  FROM accounts
  WHERE id=:id AND role='user'
");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $name  = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));

  $upd = $pdo->prepare("
    UPDATE accounts
    SET name=:n, email=:e, phone=:p
    WHERE id=:id AND role='user'
  ");

  $upd->execute([
    ':n' => $name,
    ':e' => $email,
    ':p' => $phone,
    ':id'=> $userId
  ]);

  flash_set('success', 'User updated.');
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<?php require_once __DIR__ . '/partials/top.php'; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-2xl font-bold text-slate-900">Edit User</h2>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 max-w-xl">

<form method="POST" class="space-y-5">

  <div>
    <label class="block text-sm font-medium text-slate-700 mb-1">
      Name
    </label>

    <input
      name="name"
      value="<?= h($user['name']) ?>"
      required
      class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
    >
  </div>

  <div>
    <label class="block text-sm font-medium text-slate-700 mb-1">
      Email
    </label>

    <input
      name="email"
      value="<?= h($user['email']) ?>"
      required
      class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
    >
  </div>

  <div>
    <label class="block text-sm font-medium text-slate-700 mb-1">
      Phone
    </label>

    <input
      name="phone"
      value="<?= h((string)$user['phone']) ?>"
      class="w-full h-10 rounded-xl border border-slate-200 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200"
    >
  </div>

  <div class="flex items-center gap-3 pt-2">

    <button
      type="submit"
      class="h-10 px-5 rounded-lg text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
      style="background:var(--akas-blue);"
    >
      Save Changes
    </button>

    <a
      href="<?= $baseUrl ?>/superadmin/users.php"
      class="text-sm text-slate-500 hover:text-slate-700"
    >
      Cancel
    </a>

  </div>

</form>

</div>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>