<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$pdo = db();

$status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
$allowed = ['PENDING','APPROVED','DECLINED'];
if (!in_array($status, $allowed, true)) $status = 'PENDING';

$stmt = $pdo->prepare("
  SELECT
    d.id,
    d.clinic_id,
    d.name,
    d.specialization,
    d.prc_no,
    d.schedule,
    d.email,
    d.contact_number,
    d.approval_status,
    d.created_at,
    c.clinic_name
  FROM clinic_doctors d
  JOIN clinics c ON c.id = d.clinic_id
  WHERE d.approval_status = :status
    AND d.created_via IN ('CMS','REGISTRATION')
  ORDER BY d.created_at DESC, d.id DESC
");
$stmt->execute([':status' => $status]);
$doctors = $stmt->fetchAll();

include __DIR__ . '/partials/top.php';

$success = flash_get('success');
$error   = flash_get('error');

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<div class="flex items-center justify-between mb-4">
  <h2 class="text-xl font-bold text-slate-900">Doctor Approvals</h2>

  <div class="flex gap-2">
    <a class="px-4 py-2 rounded-full text-sm <?= $status==='PENDING'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='PENDING'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/doctors.php?status=PENDING">Pending</a>

    <a class="px-4 py-2 rounded-full text-sm <?= $status==='APPROVED'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='APPROVED'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/doctors.php?status=APPROVED">Approved</a>

    <a class="px-4 py-2 rounded-full text-sm <?= $status==='DECLINED'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='DECLINED'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/doctors.php?status=DECLINED">Declined</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4"><?= h($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm p-4">
  <?php if (!$doctors): ?>
    <div class="text-slate-500">No doctors found.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Doctor</th>
            <th class="py-2">Clinic</th>
            <th class="py-2">Contact</th>
            <th class="py-2">Schedule</th>
            <th class="py-2">Status</th>
            <th class="py-2 text-right">Actions</th>
          </tr>
        </thead>

        <tbody>
        <?php foreach ($doctors as $d): ?>
          <tr class="border-t align-top">
            <!-- Doctor -->
            <td class="py-3">
              <div class="font-semibold text-slate-900"><?= h($d['name'] ?? '') ?></div>
              <div class="text-slate-500 text-xs">
                <?= h($d['specialization'] ?? '-') ?> • PRC: <?= h($d['prc_no'] ?? '-') ?>
              </div>
              <div class="text-slate-500 text-xs">
                Added: <?= h($d['created_at'] ?? '-') ?>
              </div>
            </td>

            <!-- Clinic -->
            <td class="py-3">
              <div class="font-medium text-slate-800"><?= h($d['clinic_name'] ?? '-') ?></div>
            </td>

            <!-- Contact -->
            <td class="py-3">
              <div><?= h($d['contact_number'] ?? '-') ?></div>
              <div class="text-slate-500 text-xs"><?= h($d['email'] ?? '-') ?></div>
            </td>

            <!-- Schedule -->
            <td class="py-3">
              <div class="text-slate-700 whitespace-pre-line"><?= h($d['schedule'] ?? '-') ?></div>
            </td>

            <!-- Status -->
            <td class="py-3">
              <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-700">
                <?= h($d['approval_status'] ?? '-') ?>
              </span>

              <?php // Note: clinic_doctors table currently has no declined_reason column. ?>
            </td>

            <!-- Actions -->
            <td class="py-3 text-right">
              <?php if (($d['approval_status'] ?? '') === 'PENDING'): ?>
                <form class="inline" method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                  <input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">
                  <button name="action" value="approve"
                          class="px-4 py-2 rounded-full text-white text-sm"
                          style="background:var(--akas-blue);">
                    Approve
                  </button>
                </form>

                <form class="inline ml-2" method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                  <input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">
                  <button name="action" value="decline"
                          class="px-4 py-2 rounded-full text-white text-sm bg-red-500">
                    Decline
                  </button>
                </form>
              <?php else: ?>
                <span class="text-slate-400">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/bottom.php'; ?>
