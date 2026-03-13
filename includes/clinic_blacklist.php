<?php
declare(strict_types=1);

function ensure_clinic_blacklist_table(PDO $pdo): void {
  // Table is managed manually in phpMyAdmin.
  // Do not run CREATE TABLE here because DDL can cause implicit commits
  // and break active transactions.
}

function get_clinic_blacklist_row(PDO $pdo, int $userId, int $clinicId, bool $forUpdate = false): array {
  ensure_clinic_blacklist_table($pdo);

  $sql = '
    SELECT id, user_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason
    FROM account_clinic_blacklist
    WHERE user_id = ? AND clinic_id = ?
    LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId, $clinicId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    return $row;
  }

  return [
    'id' => 0,
    'user_id' => $userId,
    'clinic_id' => $clinicId,
    'cancel_count' => 0,
    'is_blacklisted' => 0,
    'blacklisted_at' => null,
    'blacklist_reason' => null,
  ];
}

function upsert_clinic_blacklist_row(
  PDO $pdo,
  int $userId,
  int $clinicId,
  int $cancelCount,
  int $isBlacklisted,
  ?string $reason = null,
  bool $setBlacklistedAt = false
): void {
  ensure_clinic_blacklist_table($pdo);

  $stmt = $pdo->prepare(<<<'SQL'
    INSERT INTO account_clinic_blacklist
      (user_id, clinic_id, cancel_count, is_blacklisted, blacklisted_at, blacklist_reason)
    VALUES
      (:user_id, :clinic_id, :cancel_count, :is_blacklisted,
       CASE WHEN :set_blacklisted_at = 1 THEN NOW() ELSE NULL END,
       :blacklist_reason)
    ON DUPLICATE KEY UPDATE
      cancel_count = VALUES(cancel_count),
      is_blacklisted = VALUES(is_blacklisted),
      blacklisted_at = CASE
        WHEN VALUES(is_blacklisted) = 1 AND :set_blacklisted_at = 1 THEN NOW()
        WHEN VALUES(is_blacklisted) = 0 THEN NULL
        ELSE blacklisted_at
      END,
      blacklist_reason = VALUES(blacklist_reason)
  SQL);

  $stmt->execute([
    ':user_id' => $userId,
    ':clinic_id' => $clinicId,
    ':cancel_count' => $cancelCount,
    ':is_blacklisted' => $isBlacklisted,
    ':set_blacklisted_at' => $setBlacklistedAt ? 1 : 0,
    ':blacklist_reason' => $reason,
  ]);
}