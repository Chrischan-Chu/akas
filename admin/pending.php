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

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * ✅ Option A:
 * If APPROVED => redirect to dashboard immediately
 * If PENDING  => show pending UI
 * If DECLINED => show declined UI + reapply modal
 */

// Handle REAPPLY (POST)
$reapplyError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'reapply') {

  // Re-check current status (security)
  $stmt = $pdo->prepare("SELECT approval_status FROM clinics WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $clinicId]);
  $cur = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  if ((string)($cur['approval_status'] ?? '') !== 'DECLINED') {
    $reapplyError = "Reapply is only available when your clinic is declined.";
  } else {

    // Collect inputs
    $clinic_name     = trim((string)($_POST['clinic_name'] ?? ''));
    $specialty       = trim((string)($_POST['specialty'] ?? ''));
    $specialty_other = trim((string)($_POST['specialty_other'] ?? ''));
    $contact_number  = preg_replace('/\D+/', '', (string)($_POST['contact_number'] ?? '')); // digits only
    $clinic_email    = trim((string)($_POST['clinic_email'] ?? ''));
    $business_id     = preg_replace('/\D+/', '', (string)($_POST['business_id'] ?? ''));

    // Basic validation
    if ($clinic_name === '' || $specialty === '' || $business_id === '' || $contact_number === '') {
      $reapplyError = "Please fill in required fields.";
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

        // ✅ Update clinic details AND reset status to PENDING
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
          ':so'  => ($specialty === 'Other') ? $specialty_other : NULL,
          ':ct'  => $contact_number,
          ':em'  => ($clinic_email !== '') ? $clinic_email : NULL,
          ':bid' => $business_id,
          ':id'  => $clinicId
        ]);

        // ✅ Reset REGISTRATION doctors back to PENDING
        $reset = $pdo->prepare("
          UPDATE clinic_doctors
          SET approval_status = 'PENDING',
              declined_reason = NULL
          WHERE clinic_id = :cid
            AND created_via = 'REGISTRATION'
        ");
        $reset->execute([':cid' => $clinicId]);

        // ✅ Update doctors (if included in the reapply form)
        if (!empty($_POST['doctor_id'] ?? [])) {
          foreach ((array)$_POST['doctor_id'] as $i => $did) {
            $did = (int)$did;
            if ($did <= 0) continue;

            $dname  = trim((string)($_POST['doctor_name'][$i] ?? ''));
            $dspec  = trim((string)($_POST['doctor_specialization'][$i] ?? ''));
            $dprc   = trim((string)($_POST['doctor_prc'][$i] ?? ''));
            $demail = trim((string)($_POST['doctor_email'][$i] ?? ''));
            $dphone = preg_replace('/\D+/', '', (string)($_POST['doctor_phone'][$i] ?? ''));
            $dsched = trim((string)($_POST['doctor_schedule'][$i] ?? ''));

            // light validation (don’t block reapply if doctor fields are blank)
            if ($dname === '' || $dspec === '' || $dprc === '' || $dsched === '') continue;

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
              ':e'   => ($demail !== '' ? $demail : NULL),
              ':c'   => ($dphone !== '' ? $dphone : NULL),
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
        if ($pdo->inTransaction()) $pdo->rollBack();
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

// ✅ OPTION A: if approved, redirect immediately (no approved UI here)
if ($status === 'APPROVED') {
  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

$success = function_exists('flash_get') ? (flash_get('success') ?: null) : null;

// Load doctors for this clinic (prefill in modal)
$stmt = $pdo->prepare("
  SELECT id, name, specialization, prc_no, email, contact_number, schedule
  FROM clinic_doctors
  WHERE clinic_id = :cid
  ORDER BY id ASC
");
$stmt->execute([':cid' => $clinicId]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prefill mapping
$pref_clinic_name = (string)($c['clinic_name'] ?? '');
$pref_specialty = (string)($c['specialty'] ?? '');
$pref_specialty_other = (string)($c['specialty_other'] ?? '');
$pref_contact = (string)($c['contact'] ?? '');
$pref_email = (string)($c['email'] ?? '');
$pref_business_id = (string)($c['business_id'] ?? '');

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Clinic Approval</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#f6f8fb] min-h-screen flex items-center justify-center p-6">
  <div class="bg-white rounded-3xl shadow-sm p-6 max-w-lg w-full border border-slate-200">
    <h2 class="text-2xl font-bold text-slate-900 mb-2">Clinic Approval Status</h2>

    <p class="text-slate-600 mb-4">
      Clinic: <b><?= h($pref_clinic_name) ?></b>
    </p>

    <?php if (!empty($success)): ?>
      <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4">
        <?= h($success) ?>
      </div>
    <?php endif; ?>

    <?php if ($reapplyError): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4">
        <?= h($reapplyError) ?>
      </div>
    <?php endif; ?>

    <?php if ($status === 'PENDING'): ?>
      <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-3">
        Your clinic is still in <b>PENDING</b>. Please wait for approval (up to 48 hours).
      </div>
    <?php else: ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3">
        Your clinic was <b>DECLINED</b>.
        <?php if (!empty($reason)): ?>
          <div class="mt-2 text-sm">Reason: <?= h($reason) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="mt-5 flex flex-col sm:flex-row gap-2 sm:items-center">
      <a href="<?= $baseUrl ?>/index.php"
        rel="noopener"
        class="px-5 py-2 rounded-full text-white font-semibold text-center"
        style="background:#4aa3ff;">
        ← Back to Website
      </a>

      <a class="px-5 py-2 rounded-full border border-slate-300 text-slate-900 font-semibold text-center hover:bg-slate-50"
        href="<?= $baseUrl ?>/logout.php">
        Logout
      </a>

      <div class="sm:ml-auto"></div>

      <?php if ($status === 'DECLINED'): ?>
        <button
          id="openReapply"
          type="button"
          class="px-5 py-2 rounded-full text-white font-semibold text-center
                hover:opacity-95 active:scale-[0.99] transition"
          style="background:#4aa3ff;">
          Reapply
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- REAPPLY MODAL -->
  <div id="reapplyModal" class="hidden fixed inset-0 z-50 items-center justify-center p-4" style="background: rgba(0,0,0,.55);">
    <div class="w-full max-w-xl max-h-[90vh] rounded-2xl bg-white shadow-xl border border-slate-200 flex flex-col overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between" style="background:#0b1220;">
        <div>
          <p class="text-white font-bold">Reapply Clinic</p>
          <p class="text-white/80 text-xs">Update your details and submit again.</p>
        </div>
        <button type="button" id="closeReapply" class="text-white/90 hover:text-white text-xl leading-none">&times;</button>
      </div>

      <form method="post" class="flex-1 p-5 space-y-3 overflow-y-auto">
        <input type="hidden" name="action" value="reapply" />

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Clinic Name *</label>
          <input name="clinic_name" value="<?= h($pref_clinic_name) ?>"
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                 required />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Clinic Type / Category *</label>
          <select id="reapplySpecialty" name="specialty"
                  class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                  required>
            <option value="" disabled <?= $pref_specialty===''?'selected':''; ?>>Select Clinic Type</option>
            <?php
              $opts = [
                "Optometry Clinic","Family Clinic","Dental Clinic","Veterinary Clinic",
                "Pediatric Clinic","Dermatology Clinic","Other"
              ];
              foreach ($opts as $opt):
            ?>
              <option value="<?= h($opt) ?>" <?= $pref_specialty===$opt?'selected':''; ?>>
                <?= h($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="reapplyOtherWrap" class="<?= ($pref_specialty==='Other') ? '' : 'hidden' ?>">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Specify (Other) *</label>
          <input name="specialty_other" value="<?= h($pref_specialty_other) ?>"
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                 placeholder="e.g., ENT Clinic" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Number *</label>
          <div class="flex gap-2">
            <div class="w-20 h-11 flex items-center justify-center rounded-xl bg-slate-50 text-slate-700 font-semibold border border-slate-200">
              +63
            </div>
            <input name="contact_number" value="<?= h($pref_contact) ?>"
                   maxlength="10" inputmode="numeric"
                   class="flex-1 h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                   placeholder="9123456789" required />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Clinic Email (Optional)</label>
          <input name="clinic_email" value="<?= h($pref_email) ?>"
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                 placeholder="clinic@email.com" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">10-Digit Business ID *</label>
          <input name="business_id" value="<?= h($pref_business_id) ?>"
                 maxlength="10" inputmode="numeric"
                 class="w-full h-11 rounded-xl border border-slate-200 px-4 text-slate-700"
                 placeholder="1234567890" required />
        </div>

        <hr class="my-4">

        <div>
          <h3 class="text-sm font-bold text-slate-800 mb-3">Doctors</h3>

          <?php if (!$doctors): ?>
            <p class="text-xs text-slate-500">No doctors registered.</p>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($doctors as $i => $d): ?>
                <div class="border rounded-xl p-3 bg-slate-50 space-y-2">
                  <input type="hidden" name="doctor_id[]" value="<?= (int)$d['id'] ?>">

                  <input name="doctor_name[]" value="<?= h($d['name']) ?>"
                        placeholder="Doctor Name"
                        class="w-full rounded-lg border px-3 py-2 text-sm">

                  <input name="doctor_specialization[]" value="<?= h($d['specialization']) ?>"
                        placeholder="Specialization"
                        class="w-full rounded-lg border px-3 py-2 text-sm">

                  <div class="grid grid-cols-2 gap-2">
                    <input name="doctor_prc[]" value="<?= h($d['prc_no']) ?>"
                          placeholder="PRC"
                          class="rounded-lg border px-3 py-2 text-sm">

                    <input name="doctor_phone[]" value="<?= h((string)$d['contact_number']) ?>"
                          placeholder="Contact"
                          class="rounded-lg border px-3 py-2 text-sm">
                  </div>

                  <input name="doctor_email[]" value="<?= h((string)$d['email']) ?>"
                        placeholder="Email"
                        class="w-full rounded-lg border px-3 py-2 text-sm">

                  <textarea name="doctor_schedule[]" rows="2"
                            placeholder="Schedule"
                            class="w-full rounded-lg border px-3 py-2 text-sm"><?= h((string)$d['schedule']) ?></textarea>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row gap-2">
          <button type="button" id="cancelReapply"
                  class="px-5 py-2 rounded-xl border border-slate-200 text-slate-800 font-semibold hover:bg-slate-50">
            Cancel
          </button>

          <button type="submit"
                  class="px-5 py-2 rounded-xl text-white font-semibold hover:opacity-95"
                  style="background:#4aa3ff;">
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

  function openModal(){
    if(!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(){
    if(!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  if(openBtn) openBtn.addEventListener('click', openModal);
  if(closeBtn) closeBtn.addEventListener('click', closeModal);
  if(cancelBtn) cancelBtn.addEventListener('click', closeModal);

  // Only close with ESC (NOT by clicking outside)
  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeModal();
  });

  // Show/hide "Other" input
  const specialty = document.getElementById('reapplySpecialty');
  const otherWrap = document.getElementById('reapplyOtherWrap');
  if (specialty && otherWrap) {
    specialty.addEventListener('change', () => {
      if (specialty.value === 'Other') otherWrap.classList.remove('hidden');
      else otherWrap.classList.add('hidden');
    });
  }
</script>

</body>
</html>
