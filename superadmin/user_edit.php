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
$user = $stmt->fetch();

if (!$user) {
  header('Location: ' . $baseUrl . '/superadmin/users.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim((string)$_POST['name']);
  $email = trim((string)$_POST['email']);
  $phone = trim((string)$_POST['phone']);

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
?>

<?php require_once __DIR__ . '/partials/top.php'; ?>

<h2 class="text-xl font-bold mb-4">Edit User</h2>

<form method="POST" class="bg-white p-6 rounded-2xl shadow-sm max-w-lg">
  <label class="block text-sm mb-1">Name</label>
  <input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="w-full border rounded-xl px-3 py-2 mb-3">

  <label class="block text-sm mb-1">Email</label>
  <input name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full border rounded-xl px-3 py-2 mb-3">

  <label class="block text-sm mb-1">Phone</label>
  <input name="phone" value="<?= htmlspecialchars((string)$user['phone']) ?>" class="w-full border rounded-xl px-3 py-2 mb-4">

  <button class="px-6 py-2 rounded-full text-white bg-blue-500">Save</button>
  <a href="<?= $baseUrl ?>/superadmin/users.php" class="ml-3 text-slate-500">Cancel</a>
</form>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>
