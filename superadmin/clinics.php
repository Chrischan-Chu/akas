<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/top.php';
$pdo = db();

/* ======================================================
   ✅ HANDLE POST FIRST (REAPPLY)
   ====================================================== */
// ======================================================
// ✅ HANDLE REAPPLY FIRST (BEFORE SELECT)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reapply') {
  $reapplyError = null;

  // ✅ FIX: get clinicId from POST (it was missing!)
  $clinicId = (int)($_POST['clinic_id'] ?? 0);

  if ($clinicId <= 0) {
    $reapplyError = "Invalid clinic ID.";
  } else {

    // Re-check current status (security)
    $stmt = $pdo->prepare("SELECT approval_status FROM clinics WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $clinicId]);
    $curStatus = (string)($stmt->fetchColumn() ?: '');

    if ($curStatus !== 'DECLINED') {
      $reapplyError = "Reapply is only available when your clinic is declined.";
    } else {

      // Collect inputs
      $clinic_name     = trim((string)($_POST['clinic_name'] ?? ''));
      $specialty       = trim((string)($_POST['specialty'] ?? ''));
      $specialty_other = trim((string)($_POST['specialty_other'] ?? ''));
      $contact_number  = preg_replace('/\D+/', '', (string)($_POST['contact_number'] ?? ''));
      $clinic_email    = trim((string)($_POST['clinic_email'] ?? ''));
      $business_id     = trim((string)($_POST['business_id'] ?? ''));

      // Basic validation
      if ($clinic_name === '' || $specialty === '' || $business_id === '' || $contact_number === '') {
        $reapplyError = "Please fill in required fields.";
      } elseif ($specialty === 'Other' && $specialty_other === '') {
        $reapplyError = "Please specify your clinic type (Other).";
      } elseif (strlen($contact_number) !== 10) {
        $reapplyError = "Contact number must be 10 digits (e.g., 9123456789).";
      } elseif ($clinic_email !== '' && !filter_var($clinic_email, FILTER_VALIDATE_EMAIL)) {
        $reapplyError = "Invalid clinic email format.";
      } elseif (!preg_match('/^\d{10}$/', $business_id)) {
        $reapplyError = "Business ID must be exactly 10 digits.";
      } else {

        try {
          $pdo->beginTransaction();

          $upClinic = $pdo->prepare("
            UPDATE clinics
            SET clinic_name     = :clinic_name,
                specialty       = :specialty,
                specialty_other = :specialty_other,
                contact         = :contact,
                email           = :email,
                business_id     = :business_id,
                approval_status = 'PENDING',
                declined_reason = NULL,
                declined_at     = NULL
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
            SET approval_status = 'PENDING',
                declined_reason = NULL,
                declined_at = NULL,
                approved_at = NULL
            WHERE clinic_id = :cid
              AND created_via = 'REGISTRATION'
          ");
          $resetDocs->execute([':cid' => $clinicId]);

          $pdo->commit();

          flash_set('success', 'Reapply submitted. Your clinic is back to PENDING.');

          // ✅ FIX: redirect to the SUPERADMIN pending tab
          header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=PENDING');
          exit;

        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $reapplyError = "Failed to submit reapply: " . $e->getMessage();
        }
      }
    }
  }
}


/* ======================================================
   ✅ NOW DO THE FILTER + SELECT
   ====================================================== */
$status = strtoupper(trim((string)($_GET['status'] ?? 'PENDING')));
$allowed = ['PENDING','APPROVED','DECLINED'];
if (!in_array($status, $allowed, true)) $status = 'PENDING';

$stmt = $pdo->prepare("
  SELECT id, clinic_name, specialty, contact, email, address, business_id, approval_status, declined_reason, updated_at
  FROM clinics
  WHERE approval_status = :st
  ORDER BY updated_at DESC
");
$stmt->execute([':st' => $status]);
$clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Fetch REGISTRATION doctors for these clinics (SAFE + SIMPLE) ----
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
$error = flash_get('error');
?>


<div class="flex items-center justify-between mb-4">
  <h2 class="text-xl font-bold text-slate-900">Clinic Approvals</h2>

  <div class="flex gap-2">
    <a class="px-4 py-2 rounded-full text-sm <?= $status==='PENDING'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='PENDING'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/clinics.php?status=PENDING">Pending</a>

    <a class="px-4 py-2 rounded-full text-sm <?= $status==='APPROVED'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='APPROVED'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/clinics.php?status=APPROVED">Approved</a>

    <a class="px-4 py-2 rounded-full text-sm <?= $status==='DECLINED'?'text-white':'bg-white text-slate-700' ?>"
       style="<?= $status==='DECLINED'?'background:var(--akas-blue);':'' ?>"
       href="<?= $baseUrl ?>/superadmin/clinics.php?status=DECLINED">Declined</a>
  </div>
</div>

<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-4"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm p-4">
  <?php if (!$clinics): ?>
    <div class="text-slate-500">No clinics found.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Clinic</th>
            <th class="py-2">Contact</th>
            <th class="py-2">Address</th>
            <th class="py-2">Status</th>
            <th class="py-2 text-right">Actions</th>
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
          ?>

          <!-- CLINIC ROW -->
          <tr class="border-t align-top">
            <td class="py-3">
              <div class="font-semibold text-slate-900">
                <?= htmlspecialchars((string)$c['clinic_name']) ?>
              </div>
              <div class="text-slate-500 text-xs">Business ID: <?= htmlspecialchars((string)$c['business_id']) ?></div>
              <div class="text-slate-500 text-xs">Specialty: <?= htmlspecialchars((string)$c['specialty']) ?></div>

              <?php if ($docCount > 0): ?>
                <button
                  type="button"
                  id="<?= htmlspecialchars($docBtnId) ?>"
                  data-target="<?= htmlspecialchars($docWrapId) ?>"
                  data-icon="<?= htmlspecialchars($docIconId) ?>"
                  class="mt-2 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold
                         border border-slate-200 bg-white hover:bg-slate-50 transition"
                >
                  See Doctors (<?= (int)$docCount ?>)
                  <svg id="<?= htmlspecialchars($docIconId) ?>" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </button>
              <?php else: ?>
                <div class="text-slate-400 text-xs mt-2">No registration doctors.</div>
              <?php endif; ?>
            </td>

            <td class="py-3">
              <div><?= htmlspecialchars((string)$c['contact']) ?></div>
              <div class="text-slate-500 text-xs"><?= htmlspecialchars((string)($c['email'] ?? '-')) ?></div>
            </td>

            <td class="py-3"><?= htmlspecialchars((string)($c['address'] ?? '-')) ?></td>

            <td class="py-3">
              <span class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-700">
                <?= htmlspecialchars((string)$c['approval_status']) ?>
              </span>
              <?php if ($c['approval_status']==='DECLINED' && $c['declined_reason']): ?>
                <div class="text-slate-500 text-xs mt-1">Reason: <?= htmlspecialchars((string)$c['declined_reason']) ?></div>
              <?php endif; ?>
            </td>

            <td class="py-3 text-right">
              <?php if ($c['approval_status'] === 'PENDING'): ?>
                <form class="inline" method="POST" action="<?= $baseUrl ?>/superadmin/clinic_action.php">
                  <input type="hidden" name="clinic_id" value="<?= (int)$c['id'] ?>">
                  <button name="action" value="approve"
                          class="px-4 py-2 rounded-full text-white text-sm"
                          style="background:var(--akas-blue);">
                    Approve
                  </button>
                </form>

                <form class="inline ml-2" method="POST" action="<?= $baseUrl ?>/superadmin/clinic_action.php">
                  <input type="hidden" name="clinic_id" value="<?= (int)$c['id'] ?>">
                  <input name="declined_reason" class="border rounded-full px-3 py-2 text-sm w-52" placeholder="Decline reason (optional)">
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

          <!-- DOCTORS DROPDOWN ROW (HIDDEN) -->
          <?php if ($docCount > 0): ?>
            <tr class="border-t hidden" id="<?= htmlspecialchars($docWrapId) ?>">
              <td colspan="5" class="py-3">
                <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                  <div class="text-sm font-bold text-slate-900 mb-3">Registration Doctors</div>

                  <div class="overflow-auto">
                    <table class="w-full text-sm">
                      <thead class="text-slate-500">
                        <tr class="text-left">
                          <th class="py-2">Doctor</th>
                          <th class="py-2">Specialization</th>
                          <th class="py-2">PRC</th>
                          <th class="py-2">Email</th>
                          <th class="py-2">Contact</th>
                          <th class="py-2">Birthdate</th>
                          <th class="py-2">Schedule</th>
                          <th class="py-2">Status</th>
                          <th class="py-2">Created</th>
                          <th class="py-2 text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($docs as $d): ?>
                          <tr class="border-t">
                            <td class="py-2 font-semibold text-slate-900">
                              <?= htmlspecialchars((string)($d['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['specialization'] ?? '-')) ?></td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['prc_no'] ?? '-')) ?></td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['email'] ?? '-')) ?></td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['contact_number'] ?? '-')) ?></td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['birthdate'] ?? '-')) ?></td>
                            <td class="py-2"><?= htmlspecialchars((string)($d['schedule'] ?? '-')) ?></td>
                            <td class="py-2">
                              <span class="px-3 py-1 rounded-full text-xs bg-white border border-slate-200 text-slate-700">
                                <?= htmlspecialchars((string)($d['approval_status'] ?? '-')) ?>
                              </span>
                            </td>
                            <td class="py-2 text-slate-500"><?= htmlspecialchars((string)($d['created_at'] ?? '-')) ?></td>

                            <!-- ✅ DOCTOR ACTIONS (INLINE) -->
                            <td class="py-2 text-right">
                              <?php if ((string)($d['approval_status'] ?? '') === 'PENDING'): ?>
                                <form class="inline" method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                                  <input type="hidden" name="doctor_id" value="<?= (int)($d['id'] ?? 0) ?>">
                                  <button name="action" value="approve"
                                          class="px-3 py-1.5 rounded-full text-white text-xs"
                                          style="background:var(--akas-blue);">
                                    Approve
                                  </button>
                                </form>

                                <form class="inline ml-2" method="POST" action="<?= $baseUrl ?>/superadmin/doctor_action.php">
                                  <input type="hidden" name="doctor_id" value="<?= (int)($d['id'] ?? 0) ?>">
                                  <input name="declined_reason"
                                         class="border rounded-full px-2 py-1 text-xs w-44"
                                         placeholder="Reason (optional)">
                                  <button name="action" value="decline"
                                          class="px-3 py-1.5 rounded-full text-white text-xs bg-red-500">
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
(function(){

  function toggleRow(btn){
    const targetId = btn.getAttribute('data-target');
    const iconId   = btn.getAttribute('data-icon');

    const row  = document.getElementById(targetId);
    const icon = document.getElementById(iconId);

    if(!row) return;

    const isHidden = row.classList.contains('hidden');

    if (isHidden) {
      // open
      row.classList.remove('hidden');
      if(icon) icon.classList.add('rotate-180');

      // change label
      btn.innerHTML = btn.innerHTML.replace('See Doctors', 'Hide Doctors');

    } else {
      // close
      row.classList.add('hidden');
      if(icon) icon.classList.remove('rotate-180');

      // change label back
      btn.innerHTML = btn.innerHTML.replace('Hide Doctors', 'See Doctors');
    }
  }

  document.querySelectorAll('button[data-target]').forEach(btn=>{
    btn.addEventListener('click', ()=>toggleRow(btn));
  });

})();
</script>


<?php require_once __DIR__ . '/partials/bottom.php'; ?>