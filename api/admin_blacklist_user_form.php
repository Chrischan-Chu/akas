<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic_blacklist.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /admin/blacklisted-users.php');
  exit;
}

if (auth_role() !== 'clinic_admin') {
  header('Location: /pages/login.php');
  exit;
}

$baseUrl = '';
$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);

$userId = (int)($_POST['user_id'] ?? 0);
$mode   = strtoupper(trim((string)($_POST['mode'] ?? 'UNBLACKLIST')));

if ($clinicId <= 0 || $userId <= 0 || !in_array($mode, ['BLACKLIST','UNBLACKLIST'], true)) {
  flash_set('error', 'Invalid request.');
  header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
  exit;
}

try {
  $pdo->beginTransaction();

  $chk = $pdo->prepare("
    SELECT 1
    FROM appointments
    WHERE APT_UserID = :uid
      AND APT_ClinicID = :cid
    LIMIT 1
    FOR UPDATE
  ");
  $chk->execute([
    ':uid' => $userId,
    ':cid' => $clinicId,
  ]);

  ensure_clinic_blacklist_table($pdo);

  if (!$chk->fetchColumn()) {
    $pdo->rollBack();
    flash_set('error', 'User is not linked to this clinic.');
    header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
    exit;
  }

    if ($mode === 'UNBLACKLIST') {
      $upd = $pdo->prepare("
        INSERT INTO account_clinic_blacklist
          (account_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason)
        VALUES
          (:uid, :cid, 0, 0, NULL, NULL)
        ON DUPLICATE KEY UPDATE
          cancel_count = 0,
          is_blacklisted = 0,
          blacklisted_at = NULL,
          blacklist_reason = NULL
      ");
    $upd->execute([':uid' => $userId, ':cid' => $clinicId]);

    $pdo->commit();
    flash_set('success', 'User has been unblacklisted.');
    header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
    exit;
  }

  $upd = $pdo->prepare("
    INSERT INTO account_clinic_blacklist
      (account_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason)
    VALUES
      (:uid, :cid, 0, 1, NOW(), 'Blacklisted by clinic admin')
    ON DUPLICATE KEY UPDATE
      is_blacklisted = 1,
      blacklisted_at = NOW(),
      blacklist_reason = 'Blacklisted by clinic admin'
  ");
  $upd->execute([':uid' => $userId, ':cid' => $clinicId]);

  $pdo->commit();
  flash_set('success', 'User has been blacklisted.');
  header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('error', 'Server error.');
  header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
  exit;
}