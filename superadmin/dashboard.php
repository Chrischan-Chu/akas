<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/top.php';
$pdo = db();

/* ============================
   CLINICS TRACKER
============================ */
$totalClinics    = (int)$pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn();
$pendingClinics  = (int)$pdo->query("SELECT COUNT(*) FROM clinics WHERE approval_status='PENDING'")->fetchColumn();
$approvedClinics = (int)$pdo->query("SELECT COUNT(*) FROM clinics WHERE approval_status='APPROVED'")->fetchColumn();
$declinedClinics = (int)$pdo->query("SELECT COUNT(*) FROM clinics WHERE approval_status='DECLINED'")->fetchColumn();

$stmt = $pdo->query("
  SELECT id, clinic_name, specialty, business_id, updated_at
  FROM clinics
  WHERE approval_status='PENDING'
  ORDER BY updated_at DESC
  LIMIT 8
");
$pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================
   DOCTORS (REGISTRATION ONLY)
   - shown under dashboard (clinic approvals section)
   - these are doctors coming from the registration form
============================ */
$totalDoctorsReg = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE created_via='REGISTRATION'
")->fetchColumn();

$pendingDoctorsReg = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='PENDING'
    AND created_via='REGISTRATION'
")->fetchColumn();

$approvedDoctorsReg = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='APPROVED'
    AND created_via='REGISTRATION'
")->fetchColumn();

$declinedDoctorsReg = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='DECLINED'
    AND created_via='REGISTRATION'
")->fetchColumn();

$stmt = $pdo->query("
  SELECT d.id, d.name, d.specialization, d.prc_no, d.created_at,
         c.clinic_name
  FROM clinic_doctors d
  JOIN clinics c ON c.id = d.clinic_id
  WHERE d.approval_status='PENDING'
    AND d.created_via='REGISTRATION'
  ORDER BY d.created_at DESC, d.id DESC
  LIMIT 8
");
$pendingDoctorsRegList = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================
   CMS DOCTOR APPROVALS (CMS ONLY)
   - these are doctors added from the CMS
   - dashboard shows counts + recent pending
============================ */
$totalDoctorsCms = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE created_via='CMS'
")->fetchColumn();

$pendingDoctorsCms = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='PENDING'
    AND created_via='CMS'
")->fetchColumn();

$approvedDoctorsCms = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='APPROVED'
    AND created_via='CMS'
")->fetchColumn();

$declinedDoctorsCms = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clinic_doctors
  WHERE approval_status='DECLINED'
    AND created_via='CMS'
")->fetchColumn();

$stmt = $pdo->query("
  SELECT d.id, d.name, d.specialization, d.prc_no, d.created_at,
         c.clinic_name
  FROM clinic_doctors d
  JOIN clinics c ON c.id = d.clinic_id
  WHERE d.approval_status='PENDING'
    AND d.created_via='CMS'
  ORDER BY d.created_at DESC, d.id DESC
  LIMIT 8
");
$pendingDoctorsCmsList = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================
   USERS TRACKER (role='user')
============================ */
$totalUsers = (int)$pdo->query("
  SELECT COUNT(*)
  FROM accounts
  WHERE role='user'
")->fetchColumn();

$newUsersToday = (int)$pdo->query("
  SELECT COUNT(*)
  FROM accounts
  WHERE role='user'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

$newUsersThisWeek = (int)$pdo->query("
  SELECT COUNT(*)
  FROM accounts
  WHERE role='user'
    AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
")->fetchColumn();

$newUsersThisMonth = (int)$pdo->query("
  SELECT COUNT(*)
  FROM accounts
  WHERE role='user'
    AND YEAR(created_at) = YEAR(CURDATE())
    AND MONTH(created_at) = MONTH(CURDATE())
")->fetchColumn();

$stmt = $pdo->query("
  SELECT id, name, email, phone, created_at
  FROM accounts
  WHERE role='user'
  ORDER BY created_at DESC
  LIMIT 8
");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ===========================
  CLINICS
=========================== -->
<div class="mb-4">
  <h1 class="text-2xl font-bold text-slate-900">Clinics</h1>
  <div class="h-px bg-slate-200 mt-2"></div>
</div>

<!-- Clinic Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Total Clinics</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$totalClinics ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Pending</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$pendingClinics ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Approved</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$approvedClinics ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Declined</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$declinedClinics ?></div>
  </div>
</div>

<!-- Pending Clinics table -->
<div class="bg-white rounded-2xl shadow-sm p-4">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-slate-900">Pending Clinics</h2>
    <a class="text-sm text-slate-500 hover:underline" href="<?= $baseUrl ?>/superadmin/clinics.php">View all</a>
  </div>

  <?php if (!$pendingList): ?>
    <div class="text-slate-500">No pending clinics.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Clinic</th>
            <th class="py-2">Specialty</th>
            <th class="py-2">Business ID</th>
            <th class="py-2">Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingList as $c): ?>
            <tr class="border-t">
              <td class="py-2 font-semibold text-slate-900"><?= htmlspecialchars((string)$c['clinic_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="py-2"><?= htmlspecialchars((string)$c['specialty'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="py-2"><?= htmlspecialchars((string)$c['business_id'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="py-2 text-slate-500"><?= htmlspecialchars((string)$c['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>


<!-- ===========================
  DOCTORS (FROM REGISTRATION)
  shown under clinic approvals area (dashboard)
=========================== -->
<div class="mt-10 mb-4">
  <h1 class="text-2xl font-bold text-slate-900">Doctors (Registration)</h1>
  <div class="h-px bg-slate-200 mt-2"></div>
</div>

<!-- Doctors (Registration) Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Total Doctors</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$totalDoctorsReg ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Pending</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$pendingDoctorsReg ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Approved</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$approvedDoctorsReg ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Declined</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$declinedDoctorsReg ?></div>
  </div>
</div>

<!-- Pending Doctors (Registration) table -->
<div class="bg-white rounded-2xl shadow-sm p-4">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-slate-900">Pending Doctors (Registration)</h2>
    <a class="text-sm text-slate-500 hover:underline" href="<?= $baseUrl ?>/superadmin/doctors.php?created_via=REGISTRATION">View all</a>
  </div>

  <?php if (!$pendingDoctorsRegList): ?>
    <div class="text-slate-500">No pending registration doctors.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Doctor</th>
            <th class="py-2">Clinic</th>
            <th class="py-2">Specialization</th>
            <th class="py-2">PRC</th>
            <th class="py-2">Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingDoctorsRegList as $d): ?>
            <tr class="border-t">
              <td class="py-2 font-semibold text-slate-900">
                <?= htmlspecialchars((string)($d['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['specialization'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['prc_no'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2 text-slate-500">
                <?= htmlspecialchars((string)($d['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>


<!-- ===========================
  CMS DOCTOR APPROVALS (CMS ONLY)
=========================== -->
<div class="mt-10 mb-4">
  <h1 class="text-2xl font-bold text-slate-900">CMS Doctor Approvals</h1>
  <div class="h-px bg-slate-200 mt-2"></div>
</div>

<!-- CMS Doctors Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Total CMS Doctors</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$totalDoctorsCms ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Pending</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$pendingDoctorsCms ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Approved</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$approvedDoctorsCms ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Declined</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$declinedDoctorsCms ?></div>
  </div>
</div>

<!-- Pending CMS Doctors table -->
<div class="bg-white rounded-2xl shadow-sm p-4">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-slate-900">Pending CMS Doctors</h2>
    <a class="text-sm text-slate-500 hover:underline" href="<?= $baseUrl ?>/superadmin/cms-doctors.php">View all</a>
  </div>

  <?php if (!$pendingDoctorsCmsList): ?>
    <div class="text-slate-500">No pending CMS doctors.</div>
  <?php else: ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-500">
          <tr class="text-left">
            <th class="py-2">Doctor</th>
            <th class="py-2">Clinic</th>
            <th class="py-2">Specialization</th>
            <th class="py-2">PRC</th>
            <th class="py-2">Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingDoctorsCmsList as $d): ?>
            <tr class="border-t">
              <td class="py-2 font-semibold text-slate-900">
                <?= htmlspecialchars((string)($d['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['specialization'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($d['prc_no'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2 text-slate-500">
                <?= htmlspecialchars((string)($d['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>


<!-- ===========================
  USERS
=========================== -->
<div class="mt-10 mb-4">
  <h1 class="text-2xl font-bold text-slate-900">Users</h1>
  <div class="h-px bg-slate-200 mt-2"></div>
</div>

<!-- Users Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">Total Users</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$totalUsers ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">New Today</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$newUsersToday ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">New This Week</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$newUsersThisWeek ?></div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <div class="text-slate-500 text-sm">New This Month</div>
    <div class="text-3xl font-bold text-slate-900"><?= (int)$newUsersThisMonth ?></div>
  </div>
</div>

<!-- Recent Users table -->
<div class="bg-white rounded-2xl shadow-sm p-4">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-bold text-slate-900">Recent Users</h2>
    <a class="text-sm text-slate-500 hover:underline" href="<?= $baseUrl ?>/superadmin/users.php">View all</a>
  </div>

  <?php if (!$recentUsers): ?>
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
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr class="border-t">
              <td class="py-2 font-semibold text-slate-900">
                <?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2">
                <?= htmlspecialchars((string)($u['phone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="py-2 text-slate-500">
                <?= htmlspecialchars((string)($u['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/bottom.php'; ?>
