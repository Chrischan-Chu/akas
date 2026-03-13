<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$baseUrl = '';

auth_require_role('clinic_admin', $baseUrl);

$pdo = db();
$clinicId = (int)auth_clinic_id();

if ($clinicId <= 0) {
    header('Location: ' . $baseUrl . '/logout.php');
    exit;
}

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * If APPROVED => redirect to dashboard immediately
 * If PENDING  => show pending UI
 * If DECLINED => show declined UI + reapply modal
 */

// Handle REAPPLY (POST)
$reapplyError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'reapply') {
    $stmt = $pdo->prepare("SELECT approval_status FROM clinics WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $clinicId]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ((string)($cur['approval_status'] ?? '') !== 'DECLINED') {
        $reapplyError = "Reapply is only available when your clinic is declined.";
    } else {
        $clinic_name     = trim((string)($_POST['clinic_name'] ?? ''));
        $clinic_name     = preg_replace('/\s+/', ' ', $clinic_name);
        $specialty       = trim((string)($_POST['specialty'] ?? ''));
        $specialty_other = trim((string)($_POST['specialty_other'] ?? ''));
        $contact_number  = preg_replace('/\D+/', '', (string)($_POST['contact_number'] ?? ''));
        $clinic_email    = trim((string)($_POST['clinic_email'] ?? ''));
        $business_id     = preg_replace('/\D+/', '', (string)($_POST['business_id'] ?? ''));

        if ($clinic_name === '' || $specialty === '' || $business_id === '' || $contact_number === '') {
            $reapplyError = "Please fill in required fields.";
        } elseif (mb_strlen($clinic_name) > 50 || !preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $clinic_name)) {
            $reapplyError = "You can only use letters and spacing (Maximum of 50 characters).";
        } elseif ($specialty === 'Other' && $specialty_other === '') {
            $reapplyError = "Please specify your clinic type (Other).";
        } elseif (!preg_match('/^9\d{9}$/', $contact_number)) {
            $reapplyError = "Contact number must be 10 digits starting with 9 (e.g., 9123456789).";
        } elseif ($clinic_email !== '' && !filter_var($clinic_email, FILTER_VALIDATE_EMAIL)) {
            $reapplyError = "Invalid clinic email format.";
        } elseif (!preg_match('/^\d{10}$/', $business_id)) {
            $reapplyError = "Business ID must be exactly 10 digits.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE clinics
                    SET clinic_name      = :cn,
                        specialty        = :sp,
                        specialty_other  = :so,
                        contact          = :ct,
                        email            = :em,
                        business_id      = :bid,
                        approval_status  = 'PENDING',
                        declined_reason  = NULL,
                        approved_at      = NULL,
                        declined_at      = NULL,
                        updated_at       = NOW()
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':cn'  => $clinic_name,
                    ':sp'  => $specialty,
                    ':so'  => ($specialty === 'Other') ? $specialty_other : null,
                    ':ct'  => $contact_number,
                    ':em'  => ($clinic_email !== '') ? $clinic_email : null,
                    ':bid' => $business_id,
                    ':id'  => $clinicId
                ]);

                $reset = $pdo->prepare("
                    UPDATE clinic_doctors
                    SET approval_status = 'PENDING',
                        declined_reason = NULL
                    WHERE clinic_id = :cid
                      AND created_via = 'REGISTRATION'
                ");
                $reset->execute([':cid' => $clinicId]);

                if (!empty($_POST['doctor_id'] ?? [])) {
                    foreach ((array)$_POST['doctor_id'] as $i => $did) {
                        $did = (int)$did;
                        if ($did <= 0) {
                            continue;
                        }

                        $dname  = trim((string)($_POST['doctor_name'][$i] ?? ''));
                        $dspec  = trim((string)($_POST['doctor_specialization'][$i] ?? ''));
                        $dprc   = trim((string)($_POST['doctor_prc'][$i] ?? ''));
                        $demail = trim((string)($_POST['doctor_email'][$i] ?? ''));
                        $dphone = preg_replace('/\D+/', '', (string)($_POST['doctor_phone'][$i] ?? ''));
                        $dsched = trim((string)($_POST['doctor_schedule'][$i] ?? ''));

                        if ($dname === '' || $dspec === '' || $dprc === '' || $dsched === '') {
                            continue;
                        }

                        $stmt = $pdo->prepare("
                            UPDATE clinic_doctors
                            SET name = :n,
                                specialization = :s,
                                prc_no = :p,
                                email = :e,
                                contact_number = :c,
                                schedule = :sch
                            WHERE id = :id
                              AND clinic_id = :cid
                            LIMIT 1
                        ");
                        $stmt->execute([
                            ':n'   => $dname,
                            ':s'   => $dspec,
                            ':p'   => $dprc,
                            ':e'   => ($demail !== '' ? $demail : null),
                            ':c'   => ($dphone !== '' ? $dphone : null),
                            ':sch' => $dsched,
                            ':id'  => $did,
                            ':cid' => $clinicId
                        ]);
                    }
                }

                $pdo->commit();

                if (function_exists('flash_set')) {
                    flash_set('success', 'Reapply submitted. Your clinic is now back to PENDING.');
                }

                header('Location: ' . $baseUrl . '/admin/pending.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $reapplyError = "Failed to submit reapply. Please try again.";
            }
        }
    }
}

// Load clinic info for display + modal prefill
$stmt = $pdo->prepare("
    SELECT clinic_name, approval_status, declined_reason,
           specialty, specialty_other, contact, email, business_id
    FROM clinics
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $clinicId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$status = (string)($c['approval_status'] ?? 'PENDING');
$reason = $c['declined_reason'] ?? null;

if ($status === 'APPROVED') {
    header('Location: ' . $baseUrl . '/admin/dashboard.php');
    exit;
}

$success = function_exists('flash_get') ? (flash_get('success') ?: null) : null;

// Load doctors for this clinic
$stmt = $pdo->prepare("
    SELECT id, name, specialization, prc_no, email, contact_number, schedule
    FROM clinic_doctors
    WHERE clinic_id = :cid
    ORDER BY id ASC
");
$stmt->execute([':cid' => $clinicId]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pref_clinic_name     = (string)($c['clinic_name'] ?? '');
$pref_specialty       = (string)($c['specialty'] ?? '');
$pref_specialty_other = (string)($c['specialty_other'] ?? '');
$pref_contact         = (string)($c['contact'] ?? '');
$pref_email           = (string)($c['email'] ?? '');
$pref_business_id     = (string)($c['business_id'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Clinic Approval</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css">
</head>

<body class="min-h-screen bg-slate-100">
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-xl">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="px-6 py-5 rounded-t-3xl" style="background: var(--primary);">
    <h1 class="text-2xl font-bold" style="color:#ffffff;">
        Clinic Approval Status
    </h1>

    <p class="mt-1 text-sm" style="color:rgba(255,255,255,0.85);">
        Review the current status of your clinic registration.
    </p>
</div>

                <div class="p-6">
                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clinic Name</p>
                        <p class="mt-1 text-lg font-bold text-slate-900"><?= h($pref_clinic_name) ?></p>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            <?= h($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($reapplyError): ?>
                        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            <?= h($reapplyError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'PENDING'): ?>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-lg">⏳</div>
                                <div>
                                    <p class="font-semibold text-amber-800">Pending Approval</p>
                                    <p class="mt-1 text-sm text-amber-700">
                                        Your clinic registration is currently under review. Approval may take up to 48 hours.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-lg">⚠️</div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-red-800">Clinic Registration Declined</p>
                                    <?php if (!empty($reason)): ?>
                                        <p class="mt-1 text-sm text-red-700">Reason: <?= h($reason) ?></p>
                                    <?php endif; ?>
                                    <p class="mt-1 text-xs text-red-600">You may update your details and submit again.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 flex items-center gap-2">
                        <a
                            href="<?= $baseUrl ?>/index.php"
                            rel="noopener"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-500 px-4 py-2 font-semibold text-white transition hover:bg-blue-600"
                        >
                            ← Back to Website
                        </a>

                        <a
                            href="<?= $baseUrl ?>/logout.php"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Logout
                        </a>

                        <?php if ($status === 'DECLINED'): ?>
                          <button
    id="openReapply"
    type="button"
    class="inline-flex items-center justify-center rounded-xl bg-blue-500 px-4 py-2 font-semibold text-white transition hover:bg-blue-600"
    style="margin-left:auto;"
>
    Reapply
</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REAPPLY MODAL -->
    <div
        id="reapplyModal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-4"
        style="background: rgba(0,0,0,.55);"
    >
        <div class="flex w-full max-w-xl max-h-[90vh] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
            <!-- Header -->
            <div class="shrink-0 px-5 py-4" style="background: var(--primary);">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-lg font-bold text-white">Reapply Clinic</p>
                           <p class="mt-1 text-sm" style="color:rgba(255,255,255,0.85);">
        Update your details and submit again.
    </p>
                    </div>

                    <button
                        type="button"
                        id="closeReapply"
                        class="text-xl leading-none text-gray-700 hover:text-black"
                    >
                        &times;
                    </button>
                </div>
            </div>

            <!-- Body -->
            <form method="post" class="flex-1 overflow-y-auto p-5">
                <input type="hidden" name="action" value="reapply" />

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Clinic Name *
                        </label>
                        <input
                            name="clinic_name"
                            value="<?= h($pref_clinic_name) ?>"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            required
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Clinic Type / Category *
                        </label>
                        <select
                            id="reapplySpecialty"
                            name="specialty"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            required
                        >
                            <option value="" disabled <?= $pref_specialty === '' ? 'selected' : ''; ?>>Select Clinic Type</option>
                            <?php
                            $opts = [
                                "Optometry Clinic",
                                "Family Clinic",
                                "Dental Clinic",
                                "Veterinary Clinic",
                                "Pediatric Clinic",
                                "Dermatology Clinic",
                                "Other"
                            ];
                            foreach ($opts as $opt):
                            ?>
                                <option value="<?= h($opt) ?>" <?= $pref_specialty === $opt ? 'selected' : ''; ?>>
                                    <?= h($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="reapplyOtherWrap" class="<?= ($pref_specialty === 'Other') ? '' : 'hidden' ?>">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Specify (Other) *
                        </label>
                        <input
                            name="specialty_other"
                            value="<?= h($pref_specialty_other) ?>"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            placeholder="e.g., ENT Clinic"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Contact Number *
                        </label>
                        <div class="flex gap-2">
                            <div class="flex h-11 w-20 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 font-semibold text-slate-700">
                                +63
                            </div>
                            <input
                                name="contact_number"
                                value="<?= h($pref_contact) ?>"
                                maxlength="10"
                                inputmode="numeric"
                                class="h-11 flex-1 rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                placeholder="9123456789"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Clinic Email (Optional)
                        </label>
                        <input
                            name="clinic_email"
                            value="<?= h($pref_email) ?>"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            placeholder="clinic@email.com"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                            10-Digit Business ID *
                        </label>
                        <input
                            name="business_id"
                            value="<?= h($pref_business_id) ?>"
                            maxlength="10"
                            inputmode="numeric"
                            class="h-11 w-full rounded-xl border border-slate-200 px-4 text-slate-700 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            placeholder="1234567890"
                            required
                        />
                    </div>

                    <hr class="my-2 border-slate-200">

                    <div>
                        <h3 class="mb-3 text-base font-bold text-slate-900">Doctors</h3>

                        <?php if (!$doctors): ?>
                            <p class="text-sm text-slate-500">No doctors registered.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($doctors as $i => $d): ?>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <input type="hidden" name="doctor_id[]" value="<?= (int)$d['id'] ?>">

                                        <div class="mb-3 flex items-center gap-3">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
                                                <?= (int)$i + 1 ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Doctor <?= (int)$i + 1 ?></p>
                                                <p class="text-xs text-slate-500">Update doctor details</p>
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                    Doctor Name
                                                </label>
                                                <input
                                                    name="doctor_name[]"
                                                    value="<?= h($d['name']) ?>"
                                                    placeholder="Doctor Name"
                                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                >
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                    Specialization
                                                </label>
                                                <input
                                                    name="doctor_specialization[]"
                                                    value="<?= h($d['specialization']) ?>"
                                                    placeholder="Specialization"
                                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                >
                                            </div>

                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                        PRC
                                                    </label>
                                                    <input
                                                        name="doctor_prc[]"
                                                        value="<?= h($d['prc_no']) ?>"
                                                        placeholder="PRC"
                                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                    >
                                                </div>

                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                        Contact
                                                    </label>
                                                    <input
                                                        name="doctor_phone[]"
                                                        value="<?= h((string)$d['contact_number']) ?>"
                                                        placeholder="Contact"
                                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                    >
                                                </div>
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                    Email
                                                </label>
                                                <input
                                                    name="doctor_email[]"
                                                    value="<?= h((string)$d['email']) ?>"
                                                    placeholder="Email"
                                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                >
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                    Schedule
                                                </label>
                                                <textarea
                                                    name="doctor_schedule[]"
                                                    rows="2"
                                                    placeholder="Schedule"
                                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                                ><?= h((string)$d['schedule']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-2 border-t border-slate-200 pt-4 sm:flex-row">
                    <button
                        type="button"
                        id="cancelReapply"
                        class="rounded-xl border border-slate-200 px-5 py-2.5 font-semibold text-slate-800 transition hover:bg-slate-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="rounded-xl bg-blue-500 px-5 py-2.5 font-semibold text-white transition hover:bg-blue-600"
                    >
                        Submit Reapply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('reapplyModal');
        const openBtn = document.getElementById('openReapply');
        const closeBtn = document.getElementById('closeReapply');
        const cancelBtn = document.getElementById('cancelReapply');

        function openModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        const specialty = document.getElementById('reapplySpecialty');
        const otherWrap = document.getElementById('reapplyOtherWrap');

        if (specialty && otherWrap) {
            specialty.addEventListener('change', () => {
                if (specialty.value === 'Other') {
                    otherWrap.classList.remove('hidden');
                } else {
                    otherWrap.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>