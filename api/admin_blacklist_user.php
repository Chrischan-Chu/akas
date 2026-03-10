<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic_blacklist.php';

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}

if (auth_role() !== 'clinic_admin') {
  json_out(['ok' => false, 'message' => 'Forbidden'], 403);
}

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  json_out(['ok' => false, 'message' => 'Clinic not linked.'], 400);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

$userId = (int)($payload['user_id'] ?? 0);
$mode   = strtoupper(trim((string)($payload['mode'] ?? 'BLACKLIST')));

if ($userId <= 0) {
  json_out(['ok' => false, 'message' => 'Invalid user_id'], 422);
}

if (!in_array($mode, ['BLACKLIST', 'UNBLACKLIST'], true)) {
  json_out(['ok' => false, 'message' => 'Invalid mode'], 422);
}

try {
  $pdo->beginTransaction();

  // Ensure user has at least one appointment in this clinic
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
    json_out(['ok' => false, 'message' => 'User is not linked to this clinic'], 404);
  }

  if ($mode === 'UNBLACKLIST') {
    $upd = $pdo->prepare("
      INSERT INTO account_clinic_blacklist
        (user_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason)
      VALUES
        (:uid, :cid, 0, 0, NULL, NULL)
      ON DUPLICATE KEY UPDATE
        cancel_count = 0,
        is_blacklisted = 0,
        blacklisted_at = NULL,
        blacklist_reason = NULL
    ");
    $upd->execute([':uid' => $userId, ':cid' => $clinicId]);

    if ($upd->rowCount() < 1) {
      $pdo->rollBack();
      json_out(['ok' => false, 'message' => 'Unblacklist failed'], 500);
    }

    $pdo->commit();
    json_out([
      'ok' => true,
      'mode' => 'UNBLACKLIST',
      'message' => 'User has been unblacklisted.'
    ]);
  }

  $upd = $pdo->prepare("
    INSERT INTO account_clinic_blacklist
      (user_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason)
    VALUES
      (:uid, :cid, 0, 1, NOW(), 'Blacklisted by clinic admin')
    ON DUPLICATE KEY UPDATE
      is_blacklisted = 1,
      blacklisted_at = NOW(),
      blacklist_reason = 'Blacklisted by clinic admin'
  ");
  $upd->execute([':uid' => $userId, ':cid' => $clinicId]);

  if ($upd->rowCount() < 1) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Blacklist failed'], 500);
  }

  $pdo->commit();
  json_out([
    'ok' => true,
    'mode' => 'BLACKLIST',
    'message' => 'User has been blacklisted.'
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(['ok' => false, 'message' => 'Server error'], 500);
}