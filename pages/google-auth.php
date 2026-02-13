<?php
declare(strict_types=1);

$baseUrl = '/AKAS';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_idtoken.php';

function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to($baseUrl . '/pages/login.php');
}

$mode = (string)($_POST['mode'] ?? 'login'); // login | signup
$role = (string)($_POST['role'] ?? 'user');  // user | clinic_admin
$idToken = (string)($_POST['credential'] ?? '');

if (!in_array($mode, ['login','signup'], true)) {
  flash_set('error', 'Invalid Google auth request.');
  redirect_to($baseUrl . '/pages/login.php');
}
if (!in_array($role, ['user','clinic_admin'], true)) {
  $role = 'user';
}

$payload = google_verify_id_token($idToken);
if (!$payload) {
  flash_set('error', 'Google sign-in failed. Please try again.');
  redirect_to($baseUrl . '/pages/login.php');
}

$email = strtolower(trim((string)$payload['email']));
$name  = trim((string)($payload['name'] ?? ''));
$sub   = trim((string)$payload['sub']);
$pic   = trim((string)($payload['picture'] ?? ''));

if ($name === '') {
  $name = trim((string)($payload['given_name'] ?? '')) . ' ' . trim((string)($payload['family_name'] ?? ''));
  $name = trim($name) ?: 'Google User';
}

$pdo = db();

// Find existing account by email
$stmt = $pdo->prepare("SELECT id, role, name, email, clinic_id FROM accounts WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($acc) {
  // Update Google fields
  $upd = $pdo->prepare("UPDATE accounts SET auth_provider='google', google_sub=?, google_picture=?, name=? WHERE id=?");
  $upd->execute([$sub, ($pic !== '' ? $pic : null), $name, (int)$acc['id']]);

  // CLINIC ADMIN: special routing
  if ((string)$acc['role'] === 'clinic_admin') {
    $cid = (int)($acc['clinic_id'] ?? 0);

    // Incomplete clinic signup (Google admin) -> allow them to finish Step 2
    if ($cid <= 0) {
      auth_set((int)$acc['id'], 'clinic_admin', (string)$acc['name'], (string)$acc['email'], null);
      redirect_to($baseUrl . '/pages/signup-admin.php?step=2&locked=1');
    }

    // Has a clinic -> log them in, then send to dashboard or pending based on status
    auth_set((int)$acc['id'], 'clinic_admin', (string)$acc['name'], (string)$acc['email'], $cid);

    $st = $pdo->prepare('SELECT approval_status FROM clinics WHERE id = ? LIMIT 1');
    $st->execute([$cid]);
    $status = (string)($st->fetchColumn() ?? 'PENDING');

    if ($status === 'APPROVED') {
      redirect_to($baseUrl . '/admin/dashboard.php');
    }
    // Not approved yet -> show pending page (requires login)
    redirect_to($baseUrl . '/admin/pending.php');
  }

  // USER
  auth_set((int)$acc['id'], 'user', (string)$acc['name'], (string)$acc['email'], null);
  redirect_to($baseUrl . '/index.php#home');
}

// No existing account: create depending on role
if ($role === 'clinic_admin') {
  // Create incomplete clinic admin (clinic_id null) then force step 2 locked
  $dummyHash = google_make_dummy_password_hash();

  $ins = $pdo->prepare("
    INSERT INTO accounts (role, clinic_id, name, email, password_hash, auth_provider, google_sub, google_picture)
    VALUES ('clinic_admin', NULL, ?, ?, ?, 'google', ?, ?)
  ");
  $ins->execute([$name, $email, $dummyHash, $sub, ($pic !== '' ? $pic : null)]);

  $newId = (int)$pdo->lastInsertId();
  auth_set($newId, 'clinic_admin', $name, $email, null);

  redirect_to($baseUrl . '/pages/signup-admin.php?step=2&locked=1');
}

// Default: create user
$dummyHash = google_make_dummy_password_hash();
$ins = $pdo->prepare("
  INSERT INTO accounts (role, name, gender, email, password_hash, phone, birthdate, auth_provider, google_sub, google_picture)
  VALUES ('user', ?, NULL, ?, ?, NULL, NULL, 'google', ?, ?)
");
$ins->execute([$name, $email, $dummyHash, $sub, ($pic !== '' ? $pic : null)]);

$newId = (int)$pdo->lastInsertId();
auth_set($newId, 'user', $name, $email, null);
redirect_to($baseUrl . '/index.php#home');
