<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/top.php';
$pdo = db();

/* ======================================================
   HANDLE REAPPLY FIRST (BEFORE SELECT)
   ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reapply') {
    $reapplyError = null;
    $clinicId = (int)($_POST['clinic_id'] ?? 0);

    if ($clinicId <= 0) {
        $reapplyError = 'Invalid clinic ID.';
    } else {
        $stmt = $pdo->prepare("
            SELECT approval_status
            FROM clinics
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $clinicId]);
        $curStatus = (string)($stmt->fetchColumn() ?: '');

        if ($curStatus !== 'DECLINED') {
            $reapplyError = 'Reapply is only available when your clinic is declined.';
        } else {
            $clinic_name     = trim((string)($_POST['clinic_name'] ?? ''));
            $clinic_name     = preg_replace('/\s+/', ' ', $clinic_name);
            $specialty       = trim((string)($_POST['specialty'] ?? ''));
            $specialty_other = trim((string)($_POST['specialty_other'] ?? ''));
            $contact_number  = preg_replace('/\D+/', '', (string)($_POST['contact_number'] ?? ''));
            $clinic_email    = trim((string)($_POST['clinic_email'] ?? ''));
            $business_id     = trim((string)($_POST['business_id'] ?? ''));

            if ($clinic_name === '' || $specialty === '' || $business_id === '' || $contact_number === '') {
                $reapplyError = 'Please fill in required fields.';
            } elseif (mb_strlen($clinic_name) > 50 || !preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $clinic_name)) {
                $reapplyError = 'You can only use letters and spacing (Maximum of 50 characters).';
            } elseif ($specialty === 'Other' && $specialty_other === '') {
                $reapplyError = 'Please specify your clinic type (Other).';
            } elseif (strlen($contact_number) !== 10) {
                $reapplyError = 'Contact number must be 10 digits (e.g., 9123456789).';
            } elseif ($clinic_email !== '' && !filter_var($clinic_email, FILTER_VALIDATE_EMAIL)) {
                $reapplyError = 'Invalid clinic email format.';
            } elseif (!preg_match('/^\d{10}$/', $business_id)) {
                $reapplyError = 'Business ID must be exactly 10 digits.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $upClinic = $pdo->prepare("
                        UPDATE clinics
                        SET
                            clinic_name = :clinic_name,
                            specialty = :specialty,
                            specialty_other = :specialty_other,
                            contact = :contact,
                            email = :email,
                            business_id = :business_id,
                            approval_status = 'PENDING',
                            declined_reason = NULL,
                            declined_at = NULL
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $upClinic->execute([
                        ':clinic_name'     => $clinic_name,
                        ':specialty'       => $specialty,
                        ':specialty_other' => ($specialty === 'Other') ? $specialty_other : null,
                        ':contact'         => $contact_number,
                        ':email'           => ($clinic_email !== '') ? $clinic_email : null,
                        ':business_id'     => $business_id,
                        ':id'              => $clinicId,
                    ]);

                    $resetDocs = $pdo->prepare("
                        UPDATE clinic_doctors
                        SET
                            approval_status = 'PENDING',
                            declined_reason = NULL,
                            declined_at = NULL,
                            approved_at = NULL
                        WHERE clinic_id = :cid
                          AND created_via = 'REGISTRATION'
                    ");
                    $resetDocs->execute([':cid' => $clinicId]);

                    $pdo->commit();

                    flash_set('success', 'Reapply submitted. Your clinic is back to PENDING.');
                    header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=PENDING');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $reapplyError = 'Failed to submit reapply: ' . $e->getMessage();
                }
            }
        }
    }
}

/* ======================================================
   NOW DO THE FILTER + SELECT
   ====================================================== */
$status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
$allowed = ['PENDING', 'APPROVED', 'DECLINED'];
if (!in_array($status, $allowed, true)) {
    $status = 'PENDING';
}

$stmt = $pdo->prepare("
    SELECT
        id,
        clinic_name,
        specialty,
        contact,
        email,
        address,
        business_id,
        approval_status,
        declined_reason,
        updated_at
    FROM clinics
    WHERE approval_status = :st
    ORDER BY updated_at DESC
");
$stmt->execute([':st' => $status]);
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---- Fetch REGISTRATION doctors for these clinics ---- */
$doctorsByClinic = [];

if (!empty($clinics)) {
    $clinicIds = array_column($clinics, 'id');
    $clinicIds = array_map('intval', $clinicIds);

    $placeholders = implode(',', array_fill(0, count($clinicIds), '?'));

    $docStmt = $pdo->prepare("
        SELECT
            id,
            clinic_id,
            name,
            specialization,
            prc_no,
            email,
            contact_number,
            birthdate,
            schedule,
            approval_status,
            declined_reason,
            created_at
        FROM clinic_doctors
        WHERE clinic_id IN ($placeholders)
          AND created_via = 'REGISTRATION'
        ORDER BY created_at DESC
    ");
    $docStmt->execute($clinicIds);

    foreach ($docStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $doctorsByClinic[(int)$row['clinic_id']][] = $row;
    }
}

$success = flash_get('success');
$error   = flash_get('error');

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_display_datetime(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '-';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }

    return date('M d, Y', $ts) . ' at ' . date('g:i A', $ts);
}

function format_schedule_display(?string $raw): string
{
    $raw = trim((string)$raw);

    if ($raw === '') {
        return '-';
    }

    $data = json_decode($raw, true);

    // JSON schedule format
    if (is_array($data)) {
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

    // Legacy plain-text format like:
    // Mon–Fri • 14:00–19:00 • 15m slots
    $text = str_replace(['—', '–'], '-', $raw);

    $daysOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $dayIndex = array_flip($daysOrder);

    $parts = preg_split('/\s*•\s*/u', $text);
    $dayPart  = trim((string)($parts[0] ?? ''));
    $timePart = trim((string)($parts[1] ?? ''));
    $slotPart = trim((string)($parts[2] ?? ''));

    $slotText = '';
    if ($slotPart !== '' && preg_match('/(\d+)/', $slotPart, $m)) {
        $slotText = ' (' . $m[1] . ' mins)';
    }

    $startFormatted = '';
    $endFormatted = '';

    if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $timePart, $m)) {
        $startFormatted = date('g:i A', strtotime($m[1]));
        $endFormatted   = date('g:i A', strtotime($m[2]));
    }

    $activeDays = [];

    if (preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s*-\s*(Mon|Tue|Wed|Thu|Fri|Sat|Sun)$/i', $dayPart, $m)) {
        $startDay = null;
        $endDay = null;

        foreach ($daysOrder as $d) {
            if (strcasecmp($d, $m[1]) === 0) $startDay = $d;
            if (strcasecmp($d, $m[2]) === 0) $endDay = $d;
        }

        if ($startDay !== null && $endDay !== null) {
            for ($i = $dayIndex[$startDay]; $i <= $dayIndex[$endDay]; $i++) {
                $activeDays[] = $daysOrder[$i];
            }
        }
    } elseif (preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)$/i', $dayPart, $m)) {
        foreach ($daysOrder as $d) {
            if (strcasecmp($d, $m[1]) === 0) {
                $activeDays[] = $d;
                break;
            }
        }
    }

    if (!empty($activeDays) && $startFormatted !== '' && $endFormatted !== '') {
        $lines = [];

        foreach ($daysOrder as $day) {
            if (in_array($day, $activeDays, true)) {
                $lines[] = '<div><span class="font-medium">' . h($day) . ':</span> '
                         . h($startFormatted . ' - ' . $endFormatted)
                         . '<span class="text-slate-500">' . h($slotText) . '</span></div>';
            } else {
                $lines[] = '<div><span class="font-medium">' . h($day) . ':</span> <span class="text-slate-400">Off</span></div>';
            }
        }

        return implode('', $lines);
    }

    // last fallback: just prettify times
    $text = preg_replace_callback(
        '/\b(\d{2}):(\d{2})\b/u',
        function ($m) {
            $ts = strtotime($m[0]);
            return $ts !== false ? date('g:i A', $ts) : $m[0];
        },
        $text
    );

    $text = preg_replace('/(\d+)\s*m\s+slots/iu', '$1 mins', $text);

    return '<div>' . h($text) . '</div>';
}
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-slate-900">Clinic Approvals</h2>

    <div class="flex gap-2">
        <a
            class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'PENDING' ? 'text-white' : 'bg-white text-slate-700' ?>"
            style="<?= $status === 'PENDING' ? 'background:var(--akas-blue);' : '' ?>"
            href="<?= $baseUrl ?>/superadmin/clinics.php?status=PENDING"
        >
            Pending
        </a>

        <a
            class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'APPROVED' ? 'text-white' : 'bg-white text-slate-700' ?>"
            style="<?= $status === 'APPROVED' ? 'background:var(--akas-blue);' : '' ?>"
            href="<?= $baseUrl ?>/superadmin/clinics.php?status=APPROVED"
        >
            Approved
        </a>

        <a
            class="px-5 py-2.5 rounded-full text-sm font-medium <?= $status === 'DECLINED' ? 'text-white' : 'bg-white text-slate-700' ?>"
            style="<?= $status === 'DECLINED' ? 'background:var(--akas-blue);' : '' ?>"
            href="<?= $baseUrl ?>/superadmin/clinics.php?status=DECLINED"
        >
            Declined
        </a>
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
    <?php if (!$clinics): ?>
        <div class="text-slate-500">No clinics found.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm">
                <thead class="text-slate-500">
                    <tr class="text-left border-b border-slate-200">
                        <th class="py-3 pr-4 w-[24%]">Clinic</th>
                        <th class="py-3 pr-4 w-[18%]">Contact</th>
                        <th class="py-3 pr-4 w-[20%]">Address</th>
                        <th class="py-3 pr-4 w-[10%]">Status</th>
                        <?php if ($status === 'PENDING'): ?>
                            <th class="py-3 text-right w-[28%]">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($clinics as $c): ?>
                        <?php
                        $clinicId = (int)$c['id'];
                        $docs = $doctorsByClinic[$clinicId] ?? [];
                        $docCount = count($docs);
                        $docWrapId = 'docsWrap_' . $clinicId;
                        $docBtnId  = 'docsBtn_' . $clinicId;
                        $docIconId = 'docsIcon_' . $clinicId;

                        $rowStatus = strtoupper((string)$c['approval_status']);

                        if ($rowStatus === 'APPROVED') {
                            $badgeStyle = 'background:var(--akas-blue);color:#ffffff;';
                            $statusLine = 'Approved on ' . format_display_datetime($c['updated_at'] ?? '');
                        } elseif ($rowStatus === 'DECLINED') {
                            $badgeStyle = 'background:#ef4444;color:#ffffff;';
                            $statusLine = 'Declined on ' . format_display_datetime($c['updated_at'] ?? '');
                        } else {
                            $badgeStyle = 'background:#f59e0b;color:#ffffff;';
                            $statusLine = 'Submitted on ' . format_display_datetime($c['updated_at'] ?? '');
                        }
                        ?>

                        <tr class="border-t border-slate-200 align-top">
                            <td class="py-4 pr-4">
                                <div class="font-semibold text-slate-900 break-words">
                                    <?= h($c['clinic_name']) ?>
                                </div>
                                <div class="text-slate-500 text-xs mt-1">
                                    Business ID: <?= h($c['business_id']) ?>
                                </div>
                                <div class="text-slate-500 text-xs">
                                    Specialty: <?= h($c['specialty']) ?>
                                </div>
                                <div class="text-slate-500 text-xs mt-1">
                                    <?= h($statusLine) ?>
                                </div>

                                <?php if ($rowStatus === 'DECLINED' && !empty($c['declined_reason'])): ?>
                                    <div class="text-red-500 text-xs mt-1">
                                        Comment: <?= h($c['declined_reason']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($docCount > 0): ?>
                                    <button
                                        type="button"
                                        id="<?= h($docBtnId) ?>"
                                        data-target="<?= h($docWrapId) ?>"
                                        data-icon="<?= h($docIconId) ?>"
                                        class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 transition"
                                    >
                                        See Doctors (<?= (int)$docCount ?>)
                                        <svg
                                            id="<?= h($docIconId) ?>"
                                            class="w-4 h-4 transition-transform"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path d="M6 9l6 6 6-6"/>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <div class="text-slate-400 text-xs mt-2">No registration doctors.</div>
                                <?php endif; ?>
                            </td>

                            <td class="py-4 pr-4">
                                <div><?= h($c['contact']) ?></div>
                                <div class="text-slate-500 text-xs break-all">
                                    <?= h($c['email'] ?: '-') ?>
                                </div>
                            </td>

                            <td class="py-4 pr-4 break-words">
                                <?= h($c['address'] ?: '-') ?>
                            </td>

                            <td class="py-4 pr-4">
                                <span
                                    class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold"
                                    style="<?= $badgeStyle ?>"
                                >
                                    <?= h($rowStatus) ?>
                                </span>
                            </td>

                            <?php if ($status === 'PENDING'): ?>
                                <td class="py-4 text-right">
                                    <?php if ($rowStatus === 'PENDING'): ?>
                                        <div class="flex flex-col items-end gap-2">
                                            <form method="POST" action="<?= $baseUrl ?>/superadmin/clinic_action.php">
                                                <input type="hidden" name="clinic_id" value="<?= (int)$c['id'] ?>">
                                                <button
                                                    name="action"
                                                    value="approve"
                                                    class="h-9 min-w-[90px] px-4 rounded-lg text-xs font-semibold text-white"
                                                    style="background:var(--akas-blue);"
                                                >
                                                    Approve
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                action="<?= $baseUrl ?>/superadmin/clinic_action.php"
                                                class="flex flex-col items-end gap-1"
                                            >
                                                <input type="hidden" name="clinic_id" value="<?= (int)$c['id'] ?>">

                                                <button
                                                    name="action"
                                                    value="decline"
                                                    class="h-9 min-w-[90px] px-4 rounded-lg text-xs font-semibold text-white"
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
                                                    class="h-9 w-[180px] rounded-lg border border-slate-200 bg-white px-3 text-xs"
                                                >
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>

                        <?php if ($docCount > 0): ?>
                            <tr class="hidden border-t" id="<?= h($docWrapId) ?>">
                                <td colspan="<?= $status === 'PENDING' ? '5' : '4' ?>" class="py-4">
                                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                        <div class="text-lg font-bold text-slate-900 mb-4">Registration Doctors</div>

                                        <div class="overflow-x-auto">
                                            <table class="w-full table-fixed text-sm">
                                                <thead class="text-slate-500">
                                                    <tr class="text-left border-b border-slate-200">
                                                        <th class="py-2 pr-3 w-[26%]">Doctor</th>
                                                        <th class="py-2 pr-3 w-[20%]">Email</th>
                                                        <th class="py-2 pr-3 w-[14%]">Contact</th>
                                                        <th class="py-2 pr-3 w-[22%]">Schedule</th>
                                                        <th class="py-2 pr-3 w-[10%]">Status</th>
                                                        <?php if ($status === 'PENDING'): ?>
                                                            <th class="py-2 text-right w-[18%]">Actions</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    <?php foreach ($docs as $d): ?>
                                                        <?php
                                                        $docStatus = strtoupper((string)($d['approval_status'] ?? 'PENDING'));

                                                        if ($docStatus === 'APPROVED') {
                                                            $docBadgeStyle = 'background:var(--akas-blue);color:#ffffff;';
                                                            $docStatusLine = 'Approved on ' . format_display_datetime($d['created_at'] ?? '');
                                                        } elseif ($docStatus === 'DECLINED') {
                                                            $docBadgeStyle = 'background:#ef4444;color:#ffffff;';
                                                            $docStatusLine = 'Declined on ' . format_display_datetime($d['created_at'] ?? '');
                                                        } else {
                                                            $docBadgeStyle = 'background:#f59e0b;color:#ffffff;';
                                                            $docStatusLine = 'Submitted on ' . format_display_datetime($d['created_at'] ?? '');
                                                        }
                                                        ?>
                                                        <tr class="border-t border-slate-200 align-top">
                                                            <td class="py-3 pr-3">
                                                                <div class="font-semibold text-slate-900 break-words">
                                                                    <?= h($d['name'] ?? '-') ?>
                                                                </div>

                                                                <div class="text-slate-500 text-xs leading-5 mt-1 space-y-1">
                                                                    <div>Specialization: <?= h($d['specialization'] ?? '-') ?></div>
                                                                    <div>PRC: <?= h($d['prc_no'] ?? '-') ?></div>
                                                                    <div>Birthdate: <?= h($d['birthdate'] ?? '-') ?></div>
                                                                    <div><?= h($docStatusLine) ?></div>

                                                                    <?php if ($docStatus === 'DECLINED' && trim((string)($d['declined_reason'] ?? '')) !== ''): ?>
                                                                        <div class="text-red-500">Comment: <?= h($d['declined_reason']) ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>

                                                            <td class="py-3 pr-3">
                                                                <div class="break-all text-slate-700">
                                                                    <?= h($d['email'] ?? '-') ?>
                                                                </div>
                                                            </td>

                                                            <td class="py-3 pr-3">
                                                                <?= h($d['contact_number'] ?? '-') ?>
                                                            </td>

                                                            <td class="py-3 pr-3 text-xs leading-5">
                                                                <div class="text-slate-700 break-words">
                                                                    <?= format_schedule_display($d['schedule'] ?? '') ?>
                                                                </div>
                                                            </td>

                                                            <td class="py-3 pr-3">
                                                                <span
                                                                    class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold"
                                                                    style="<?= $docBadgeStyle ?>"
                                                                >
                                                                    <?= h($docStatus) ?>
                                                                </span>
                                                            </td>

                                                            <?php if ($status === 'PENDING'): ?>
                                                                <td class="py-3 text-right">
                                                                    <?php if ($docStatus === 'PENDING'): ?>
                                                                        <div class="flex flex-col items-end gap-2">
                                                                            <form method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                                                                                <input type="hidden" name="doctor_id" value="<?= (int)($d['id'] ?? 0) ?>">
                                                                                <button
                                                                                    name="action"
                                                                                    value="approve"
                                                                                    class="h-8 min-w-[84px] px-3 rounded-lg text-xs font-semibold text-white"
                                                                                    style="background:var(--akas-blue);"
                                                                                >
                                                                                    Approve
                                                                                </button>
                                                                            </form>

                                                                            <form method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php" class="flex flex-col items-end gap-1">
                                                                                <input type="hidden" name="doctor_id" value="<?= (int)($d['id'] ?? 0) ?>">

                                                                                <button
                                                                                    name="action"
                                                                                    value="decline"
                                                                                    class="h-8 min-w-[84px] px-3 rounded-lg text-xs font-semibold text-white"
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
                                                                                    class="h-8 w-[160px] rounded-lg border border-slate-200 bg-white px-2 text-xs"
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
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    function toggleRow(btn) {
        const targetId = btn.getAttribute('data-target');
        const iconId   = btn.getAttribute('data-icon');

        const row  = document.getElementById(targetId);
        const icon = document.getElementById(iconId);

        if (!row) return;

        const isHidden = row.classList.contains('hidden');

        if (isHidden) {
            row.classList.remove('hidden');
            if (icon) icon.classList.add('rotate-180');
            btn.innerHTML = btn.innerHTML.replace('See Doctors', 'Hide Doctors');
        } else {
            row.classList.add('hidden');
            if (icon) icon.classList.remove('rotate-180');
            btn.innerHTML = btn.innerHTML.replace('Hide Doctors', 'See Doctors');
        }
    }

    document.querySelectorAll('button[data-target]').forEach(btn => {
        btn.addEventListener('click', () => toggleRow(btn));
    });
})();
</script>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>
