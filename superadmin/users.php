<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/top.php';
$pdo = db();

/*
  Only NORMAL USERS
  (Super Admin manages users, not clinic_admins here)
*/
$stmt = $pdo->query("
  SELECT id, name, email, phone, created_at
  FROM accounts
  WHERE role = 'user'
  ORDER BY created_at DESC
");
$users = $stmt->fetchAll();

$success = flash_get('success');
$error   = flash_get('error');
?>

<div class="flex items-center justify-between mb-4">
  <h2 class="text-xl font-bold text-slate-900">Users</h2>
</div>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm p-4">
  <?php if (!$users): ?>
    <div class="text-slate-500">No users found.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Name</th>
            <th class="py-2">Email</th>
            <th class="py-2">Phone</th>
            <th class="py-2">Joined</th>
            <th class="py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr class="border-t">
              <td class="py-3 font-semibold text-slate-900">
                <?= htmlspecialchars((string)$u['name']) ?>
              </td>
              <td class="py-3"><?= htmlspecialchars((string)$u['email']) ?></td>
              <td class="py-3"><?= htmlspecialchars((string)($u['phone'] ?? '-')) ?></td>
              <td class="py-3 text-slate-500"><?= htmlspecialchars((string)$u['created_at']) ?></td>
              <td class="py-3 text-right">
                <a href="<?= $baseUrl ?>/superadmin/user_edit.php?id=<?= (int)$u['id'] ?>"
                   class="px-3 py-1 rounded-full text-sm bg-blue-500 text-white">
                  Edit
                </a>

                <form class="inline"
                      method="POST"
                      action="<?= $baseUrl ?>/superadmin/user_delete.php"
                      onsubmit="return confirm('Delete this user?');">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button class="ml-2 px-3 py-1 rounded-full text-sm bg-red-500 text-white">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>
