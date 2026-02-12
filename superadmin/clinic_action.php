<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash_set('error', 'Invalid request.');
  header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=PENDING');
  exit;
}

$clinicId = (int)($_POST['clinic_id'] ?? 0);
$action   = (string)($_POST['action'] ?? '');
$reason   = trim((string)($_POST['reason'] ?? ''));

if ($clinicId <= 0 || !in_array($action, ['approve', 'decline', 'reapply'], true)) {
  flash_set('error', 'Invalid input.');
  header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=PENDING');
  exit;
}

$stmt = $pdo->prepare("SELECT approval_status FROM clinics WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $clinicId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  flash_set('error', 'Clinic not found.');
  header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=PENDING');
  exit;
}

$status = (string)($row['approval_status'] ?? '');

/**
 * Only PENDING clinics can be approved/declined.
 * Only DECLINED clinics can be re-applied.
 */
if (($action === 'approve' || $action === 'decline') && $status !== 'PENDING') {
  flash_set('error', 'Clinic already processed.');
  header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=' . urlencode($status));
  exit;
}

if ($action === 'reapply' && $status !== 'DECLINED') {
  flash_set('error', 'Only declined clinics can be re-applied.');
  header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=DECLINED');
  exit;
}

try {
  $pdo->beginTransaction();

  if ($action === 'approve') {
    $upd = $pdo->prepare("
      UPDATE clinics
      SET approval_status='APPROVED',
          approved_at=NOW(),
          declined_at=NULL,
          declined_reason=NULL,
          updated_at=NOW()
      WHERE id=:id
      LIMIT 1
    ");
    $upd->execute([':id' => $clinicId]);

    // ✅ IMPORTANT: DO NOT TOUCH clinic_doctors here
    flash_set('success', 'Clinic approved.');
  }

  if ($action === 'decline') {
    $declineReason = ($reason !== '' ? $reason : 'Clinic declined');

    $upd = $pdo->prepare("
      UPDATE clinics
      SET approval_status='DECLINED',
          declined_at=NOW(),
          declined_reason=:r,
          updated_at=NOW()
      WHERE id=:id
      LIMIT 1
    ");
    $upd->execute([
      ':id' => $clinicId,
      ':r'  => $declineReason
    ]);

    // ✅ IMPORTANT: DO NOT TOUCH clinic_doctors here
    flash_set('success', 'Clinic declined.');
  }

  if ($action === 'reapply') {
    $upd = $pdo->prepare("
      UPDATE clinics
      SET approval_status='PENDING',
          approved_at=NULL,
          declined_at=NULL,
          declined_reason=NULL,
          updated_at=NOW()
      WHERE id=:id
      LIMIT 1
    ");
    $upd->execute([':id' => $clinicId]);

    // ✅ IMPORTANT: DO NOT TOUCH clinic_doctors here
    flash_set('success', 'Clinic re-applied and set back to PENDING.');
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('[clinic_action] ' . $e->getMessage());
  flash_set('error', 'Action failed. Please try again.');
}

$toStatus = 'PENDING';
if ($action === 'approve') $toStatus = 'APPROVED';
if ($action === 'decline') $toStatus = 'DECLINED';
if ($action === 'reapply') $toStatus = 'PENDING';

header('Location: ' . $baseUrl . '/superadmin/clinics.php?status=' . $toStatus);
exit;
