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
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = flash_get('success');
$error   = flash_get('error');

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function format_display_datetime(?string $raw): string {
  $raw = trim((string)$raw);
  if ($raw === '') return '-';

  $ts = strtotime($raw);
  if ($ts === false) return $raw;

  return date('M d, Y', $ts) . ' at ' . date('g:i A', $ts);
}
?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-2xl font-bold text-slate-900">Users</h2>
</div>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4">
    <?= h($success) ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4">
    <?= h($error) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4 sm:p-6">
  <?php if (!$users): ?>
    <div class="text-slate-500">No users found.</div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full table-fixed text-sm">
        <thead class="text-slate-500">
          <tr class="text-left border-b border-slate-200">
            <th class="py-3 pr-4 w-[24%]">Name</th>
            <th class="py-3 pr-4 w-[31%]">Email</th>
            <th class="py-3 pr-4 w-[18%]">Phone</th>
            <th class="py-3 pr-4 w-[17%]">Joined</th>
            <th class="py-3 text-right w-[10%]">Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($users as $u): ?>
            <tr class="border-t border-slate-200 align-middle">
              <td class="py-4 pr-4">
                <div class="font-semibold text-slate-900 break-words">
                  <?= h($u['name'] ?? '-') ?>
                </div>
              </td>

              <td class="py-4 pr-4">
                <div class="text-slate-700 break-all">
                  <?= h($u['email'] ?? '-') ?>
                </div>
              </td>

              <td class="py-4 pr-4">
                <div class="text-slate-700">
                  <?= h(($u['phone'] ?? '') !== '' ? $u['phone'] : '-') ?>
                </div>
              </td>

              <td class="py-4 pr-4">
                <div class="text-slate-500 text-xs leading-5">
                  <?= h(format_display_datetime($u['created_at'] ?? '')) ?>
                </div>
              </td>

              <td class="py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <a
                    href="<?= $baseUrl ?>/superadmin/user_edit.php?id=<?= (int)$u['id'] ?>"
                    class="inline-flex items-center justify-center h-9 min-w-[72px] px-4 rounded-lg text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
                    style="background:var(--akas-blue);"
                  >
                    Edit
                  </a>

                  <form
                    class="inline"
                    method="POST"
                    action="<?= $baseUrl ?>/superadmin/user_delete.php"
                    onsubmit="return confirm('Delete this user?');"
                  >
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button
                      type="submit"
                      class="inline-flex items-center justify-center h-9 min-w-[72px] px-4 rounded-lg text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
                      style="background:#ef4444;"
                    >
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>