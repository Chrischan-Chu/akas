<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$pdo = db();

$status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
$allowed = ['PENDING', 'APPROVED', 'DECLINED'];
if (!in_array($status, $allowed, true)) {
  $status = 'PENDING';
}

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
    d.approved_at,
    d.declined_at,
    d.declined_reason,
    c.clinic_name
  FROM clinic_doctors d
  JOIN clinics c ON c.id = d.clinic_id
  WHERE d.approval_status = :status
    AND d.created_via IN ('CMS','REGISTRATION')
  ORDER BY d.created_at DESC, d.id DESC
");
$stmt->execute([':status' => $status]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/top.php';

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

function format_doctor_schedule(?string $raw): string {
  if (!$raw) return 'No schedule';

  $data = json_decode($raw, true);
  if (!is_array($data)) return 'No schedule';

  $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  $lines = [];

  foreach ($days as $day) {
    $row = $data[$day] ?? [];
    $enabled = !empty($row['enabled']);
    $start = trim((string)($row['start'] ?? ''));
    $end   = trim((string)($row['end'] ?? ''));
    $slot  = (int)($row['slot_mins'] ?? 0);

    if ($enabled && $start !== '' && $end !== '') {
      $startFormatted = date('g:i A', strtotime($start));
      $endFormatted   = date('g:i A', strtotime($end));

      $line = '<div><span class="font-medium">' . h($day) . ':</span> '
            . h($startFormatted . ' - ' . $endFormatted);

      if ($slot > 0) {
        $line .= ' <span class="text-slate-500">(' . h((string)$slot) . ' mins)</span>';
      }

      $line .= '</div>';
      $lines[] = $line;
    } else {
      $lines[] = '<div><span class="font-medium">' . h($day) . ':</span> <span class="text-slate-400">Off</span></div>';
    }
  }

  return implode('', $lines);
}
?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-2xl font-bold text-slate-900">Doctor Approvals</h2>

  <div class="flex gap-2">
    <a
      class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'PENDING' ? 'text-white' : 'bg-white text-slate-700' ?>"
      style="<?= $status === 'PENDING' ? 'background:var(--akas-blue);' : '' ?>"
      href="<?= $baseUrl ?>/superadmin/doctors.php?status=PENDING"
    >Pending</a>

    <a
      class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'APPROVED' ? 'text-white' : 'bg-white text-slate-700' ?>"
      style="<?= $status === 'APPROVED' ? 'background:var(--akas-blue);' : '' ?>"
      href="<?= $baseUrl ?>/superadmin/doctors.php?status=APPROVED"
    >Approved</a>

    <a
      class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'DECLINED' ? 'text-white' : 'bg-white text-slate-700' ?>"
      style="<?= $status === 'DECLINED' ? 'background:var(--akas-blue);' : '' ?>"
      href="<?= $baseUrl ?>/superadmin/doctors.php?status=DECLINED"
    >Declined</a>
  </div>
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
  <?php if (!$doctors): ?>
    <div class="text-slate-500">No doctors found.</div>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full table-fixed text-sm">
        <thead class="text-slate-500">
          <tr class="text-left border-b border-slate-200">
            <th class="py-3 pr-4 w-[18%]">Doctor</th>
            <th class="py-3 pr-4 w-[20%]">Clinic</th>
            <th class="py-3 pr-4 w-[15%]">Contact</th>
            <th class="py-3 pr-4 w-[22%]">Schedule</th>
            <th class="py-3 pr-4 w-[10%]">Status</th>
            <?php if ($status === 'PENDING'): ?>
              <th class="py-3 text-right w-[15%]">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>

        <tbody>
        <?php foreach ($doctors as $d): ?>
          <?php
            $rowStatus = strtoupper((string)($d['approval_status'] ?? 'PENDING'));

            if ($rowStatus === 'APPROVED') {
              $statusLine = 'Approved on ' . format_display_datetime($d['approved_at'] ?? $d['created_at'] ?? '');
            } elseif ($rowStatus === 'DECLINED') {
              $statusLine = 'Declined on ' . format_display_datetime($d['declined_at'] ?? $d['created_at'] ?? '');
            } else {
              $statusLine = 'Submitted on ' . format_display_datetime($d['created_at'] ?? '');
            }

            if ($rowStatus === 'APPROVED') {
              $badgeStyle = 'background:var(--akas-blue);color:#ffffff;';
            } elseif ($rowStatus === 'DECLINED') {
              $badgeStyle = 'background:#ef4444;color:#ffffff;';
            } else {
              $badgeStyle = 'background:#f59e0b;color:#ffffff;';
            }
          ?>
          <tr class="border-t border-slate-200 align-top">
            <td class="py-5 pr-4">
              <div class="font-semibold text-slate-900 break-words">
                <?= h($d['name'] ?? '') ?>
              </div>

              <div class="text-slate-500 text-xs leading-5 mt-2 space-y-1">
                <div>Specialization: <?= h($d['specialization'] ?? '-') ?></div>
                <div>PRC: <?= h($d['prc_no'] ?? '-') ?></div>
                <div><?= h($statusLine) ?></div>
                <?php if ($rowStatus === 'DECLINED' && trim((string)($d['declined_reason'] ?? '')) !== ''): ?>
                  <div>Comment: <?= h($d['declined_reason']) ?></div>
                <?php endif; ?>
              </div>
            </td>

            <td class="py-5 pr-4">
              <div class="font-medium text-slate-800 break-words leading-6">
                <?= h($d['clinic_name'] ?? '-') ?>
              </div>
            </td>

            <td class="py-5 pr-4">
              <div class="text-slate-900 break-words leading-6">
                <?= h($d['contact_number'] ?? '-') ?>
              </div>
              <div class="text-slate-500 text-xs break-all mt-1">
                <?= h($d['email'] ?? '-') ?>
              </div>
            </td>

            <td class="py-5 pr-4">
              <div class="text-slate-700 text-xs leading-6 break-words">
                <?= format_doctor_schedule($d['schedule'] ?? '') ?>
              </div>
            </td>

            <td class="py-5 pr-4">
              <span
                class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap"
                style="<?= $badgeStyle ?>"
              >
                <?= h($rowStatus) ?>
              </span>
            </td>

            <?php if ($status === 'PENDING'): ?>
              <td class="py-5 text-right">
                <?php if ($rowStatus === 'PENDING'): ?>
                  <div class="flex flex-col items-end gap-3">
                    <form method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                      <input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">
                      <button
                        type="submit"
                        name="action"
                        value="approve"
                        class="h-9 min-w-[90px] px-4 rounded-lg text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
                        style="background:var(--akas-blue);"
                      >
                        Approve
                      </button>
                    </form>

                    <form method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php" class="flex flex-col items-end gap-2">
                      <input type="hidden" name="doctor_id" value="<?= (int)$d['id'] ?>">

                      <button
                        type="submit"
                        name="action"
                        value="decline"
                        class="h-9 min-w-[90px] px-4 rounded-lg text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
                        style="background:#ef4444;"
                      >
                        Decline
                      </button>

                      <div class="text-[11px] text-slate-400">
                        Comment (for decline only)
                      </div>

                      <input
                        type="text"
                        name="reason"
                        maxlength="255"
                        placeholder="Optional comment"
                        class="h-9 w-[180px] rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-red-200"
                      >
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/bottom.php'; ?>