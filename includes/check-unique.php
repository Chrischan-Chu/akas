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

// Optional clinic email: empty means available (do not block)
if ($type === 'clinic_email' && $value === '') {
  echo json_encode(['ok' => true, 'available' => true]);
  exit;
}

if ($type === '' || $value === '') {
  echo json_encode(['ok' => false, 'available' => true]);
  exit;
}

// Allowlist types
$allowed = [
  'email',              // accounts.email
  'phone',              // accounts.phone (10 digits)
  'clinic_contact',     // clinics.contact (10 digits)
  'clinic_email',       // clinics.email (optional but unique if filled)
  'clinic_business_id', // clinics.business_id
];

if (!in_array($type, $allowed, true)) {
  echo json_encode(['ok' => false, 'available' => true]);
  exit;
}

try {
  $pdo = db();

  // Normalize 10-digit phone/contact (digits only)
  $digitsOnly = static function (string $v): string {
    return preg_replace('/\D+/', '', $v);
  };

  if ($type === 'email') {
    $stmt = $pdo->prepare('SELECT 1 FROM accounts WHERE email = ? LIMIT 1');
    $stmt->execute([$value]);

  } elseif ($type === 'phone') {
    $phone = $digitsOnly($value);
    $stmt = $pdo->prepare('SELECT 1 FROM accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);

  } elseif ($type === 'clinic_contact') {
    $contact = $digitsOnly($value);
    $stmt = $pdo->prepare('SELECT 1 FROM clinics WHERE contact = ? LIMIT 1');
    $stmt->execute([$contact]);

  } elseif ($type === 'clinic_email') {
    // Optional but unique when provided
    $stmt = $pdo->prepare('SELECT 1 FROM clinics WHERE email = ? LIMIT 1');
    $stmt->execute([$value]);

  } else { // clinic_business_id
    $biz = trim($value);
    // If Business ID should be digits only, uncomment:
    // $biz = $digitsOnly($biz);

    $stmt = $pdo->prepare('SELECT 1 FROM clinics WHERE business_id = ? LIMIT 1');
    $stmt->execute([$biz]);
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