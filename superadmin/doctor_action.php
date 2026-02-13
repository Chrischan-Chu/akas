<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash_set('error', 'Invalid request.');
  header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
  exit;
}

$doctorId = (int)($_POST['doctor_id'] ?? 0);
$action   = (string)($_POST['action'] ?? '');
$reason   = trim((string)($_POST['reason'] ?? ''));

if ($doctorId <= 0 || !in_array($action, ['approve','decline'], true)) {
  flash_set('error', 'Invalid input.');
  header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
  exit;
}

$stmt = $pdo->prepare("
  SELECT approval_status, created_via
  FROM clinic_doctors
  WHERE id=:id
  LIMIT 1
");
$stmt->execute([':id' => $doctorId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  flash_set('error', 'Doctor not found.');
  header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
  exit;
}

$via = (string)($row['created_via'] ?? '');
if (!in_array($via, ['REGISTRATION','CMS'], true)) {
  flash_set('error', 'Invalid doctor source.');
  header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
  exit;
}

if ((string)($row['approval_status'] ?? '') !== 'PENDING') {
  flash_set('error', 'Doctor already processed.');
  header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
  exit;
}

if ($action === 'approve') {
  $upd = $pdo->prepare("
    UPDATE clinic_doctors
    SET approval_status='APPROVED',
        approved_at=NOW(),
        declined_at=NULL,
        declined_reason=NULL,
        updated_at=NOW()
    WHERE id=:id
    LIMIT 1
  ");
  $upd->execute([':id' => $doctorId]);
  flash_set('success', 'Doctor approved.');
}

if ($action === 'decline') {
  if ($reason === '') {
    $reason = 'No reason provided.';
  } elseif (mb_strlen($reason) > 255) {
    $reason = mb_substr($reason, 0, 255);
  }

  $upd = $pdo->prepare("
    UPDATE clinic_doctors
    SET approval_status='DECLINED',
        declined_at=NOW(),
        declined_reason=:r,
        approved_at=NULL,
        updated_at=NOW()
    WHERE id=:id
    LIMIT 1
  ");
  $upd->execute([':id' => $doctorId, ':r' => $reason]);
  flash_set('success', 'Doctor declined.');
}

header('Location: ' . $baseUrl . '/superadmin/doctors.php?status=PENDING');
exit;
