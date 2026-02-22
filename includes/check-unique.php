<?php
/**
 * Lightweight uniqueness checker for client-side (AJAX) validation.
 *
 * IMPORTANT:
 * - This is for UX only.
 * - Real enforcement MUST remain in DB UNIQUE constraints + server-side validation.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$type  = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$value = isset($_GET['value']) ? trim((string)$_GET['value']) : '';

if ($type === '' || $value === '') {
  echo json_encode(['ok' => false, 'available' => true]);
  exit;
}

// Allowlist types
if (!in_array($type, ['email', 'phone', 'clinic_contact'], true)) {
  echo json_encode(['ok' => false, 'available' => true]);
  exit;
}

try {
  $pdo = db();

  if ($type === 'email') {
    $stmt = $pdo->prepare('SELECT 1 FROM accounts WHERE email = ? LIMIT 1');
    $stmt->execute([$value]);
  } elseif ($type === 'phone') {
    $stmt = $pdo->prepare('SELECT 1 FROM accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$value]);
  } else { // clinic_contact
    $stmt = $pdo->prepare('SELECT 1 FROM clinics WHERE contact = ? LIMIT 1');
    $stmt->execute([$value]);
  }

  $exists = (bool)$stmt->fetchColumn();

  echo json_encode([
    'ok' => true,
    'available' => !$exists,
  ]);
} catch (Throwable $e) {
  // Fail-open: if checker fails, do not block user here.
  // Server-side + DB UNIQUE will still protect.
  echo json_encode(['ok' => false, 'available' => true]);
}
