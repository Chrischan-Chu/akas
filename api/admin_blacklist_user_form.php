<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

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

  if (!$chk->fetchColumn()) {
    $pdo->rollBack();
    flash_set('error', 'User is not linked to this clinic.');
    header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
    exit;
  }

  if ($mode === 'UNBLACKLIST') {
    $upd = $pdo->prepare("
      UPDATE accounts
      SET is_blacklisted = 0,
          blacklisted_at = NULL,
          blacklist_reason = NULL
      WHERE id = :uid
      LIMIT 1
    ");
    $upd->execute([':uid' => $userId]);

    $pdo->commit();
    flash_set('success', 'User has been unblacklisted.');
    header('Location: ' . $baseUrl . '/admin/blacklisted-users.php');
    exit;
  }

  $upd = $pdo->prepare("
    UPDATE accounts
    SET is_blacklisted = 1,
        blacklisted_at = NOW(),
        blacklist_reason = 'Blacklisted by clinic admin'
    WHERE id = :uid
    LIMIT 1
  ");
  $upd->execute([':uid' => $userId]);

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