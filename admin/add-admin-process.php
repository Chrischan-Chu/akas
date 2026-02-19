<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('clinic_admin', $baseUrl);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  flash_set('error', 'This admin account is not linked to a clinic.');
  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

$adminName = trim((string)($_POST['admin_name'] ?? ''));
$email     = strtolower(trim((string)($_POST['email'] ?? '')));
$password  = (string)($_POST['password'] ?? '');
$confirm   = (string)($_POST['confirm_password'] ?? '');

if ($adminName === '') {
  flash_set('error', 'Please enter the admin name.');
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid email.');
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

// Password rules (same as JS)
$minLen     = strlen($password) >= 8;
$hasUpper   = preg_match('/[A-Z]/', $password) === 1;
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

if (!($minLen && $hasUpper && $hasSpecial)) {
  flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

if ($password !== $confirm) {
  flash_set('error', 'Passwords do not match.');
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

// Unique email
$stmt = $pdo->prepare('SELECT id FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
  flash_set('error', 'Email is already registered.');
  header('Location: ' . $baseUrl . '/admin/add-admin.php');
  exit;
}

// Optional work ID upload
$workIdPath = null;
if (!empty($_FILES['admin_work_id']['name'])) {
  $tmp = $_FILES['admin_work_id']['tmp_name'] ?? '';
  if (is_uploaded_file($tmp)) {
    $ext = strtolower(pathinfo((string)($_FILES['admin_work_id']['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
      flash_set('error', 'Work ID must be a JPG, PNG, or WEBP image.');
      header('Location: ' . $baseUrl . '/admin/add-admin.php');
      exit;
    }

    $dir = __DIR__ . '/../uploads/work_ids';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    $fileName = 'workid_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $fileName;
    if (!move_uploaded_file($tmp, $dest)) {
      flash_set('error', 'Failed to upload Work ID. Please try again.');
      header('Location: ' . $baseUrl . '/admin/add-admin.php');
      exit;
    }
    $workIdPath = $baseUrl . '/uploads/work_ids/' . $fileName;
  }
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$ins = $pdo->prepare('INSERT INTO accounts (role, name, email, password_hash, clinic_id, admin_work_id_path)
                      VALUES (?,?,?,?,?,?)');
$ins->execute(['clinic_admin', $adminName, $email, $hash, $clinicId, $workIdPath]);

flash_set('ok', 'Admin account created. They can now log in and manage the same clinic.');
header('Location: ' . $baseUrl . '/admin/add-admin.php');
exit;
