<?php
declare(strict_types=1);

$baseUrl = '/AKAS';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/pages/signup.php');
  exit;
}

// If already logged in, send where they belong
if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}

$role = trim((string)($_POST['role'] ?? ''));
if (!in_array($role, ['user', 'clinic_admin'], true)) {
  flash_set('error', 'Invalid account type.');
  header('Location: ' . $baseUrl . '/pages/signup.php');
  exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

// Basic email validation (always required for login)
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid email.');
  header('Location: ' . $baseUrl . ($role === 'clinic_admin' ? '/pages/signup-admin.php' : '/pages/signup-user.php'));
  exit;
}

// Password rules (same as JS)
$minLen = strlen($password) >= 8;
$hasUpper = preg_match('/[A-Z]/', $password) === 1;
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

if (!($minLen && $hasUpper && $hasSpecial)) {
  flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
  header('Location: ' . $baseUrl . ($role === 'clinic_admin' ? '/pages/signup-admin.php' : '/pages/signup-user.php'));
  exit;
}

if ($password !== $confirmPassword) {
  flash_set('error', 'Passwords do not match.');
  header('Location: ' . $baseUrl . ($role === 'clinic_admin' ? '/pages/signup-admin.php' : '/pages/signup-user.php'));
  exit;
}

$pdo = db();

// Make sure email is unique
$stmt = $pdo->prepare('SELECT id FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
  flash_set('error', 'Email is already registered. Please login.');
  header('Location: ' . $baseUrl . '/pages/login.php');
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// -----------------------
// USER SIGN UP
// -----------------------
if ($role === 'user') {
  $name = trim((string)($_POST['name'] ?? ''));
  $gender = trim((string)($_POST['gender'] ?? ''));
  $phone = trim((string)($_POST['contact_number'] ?? ''));
  $birthdate = trim((string)($_POST['birthdate'] ?? ''));

  if ($name === '') {
    flash_set('error', 'Please enter your name.');
    header('Location: ' . $baseUrl . '/pages/signup-user.php');
    exit;
  }

  // Gender validation
  $allowedGenders = ['Male', 'Female', 'Prefer not to say'];
  if (!in_array($gender, $allowedGenders, true)) {
    flash_set('error', 'Please select a gender.');
    header('Location: ' . $baseUrl . '/pages/signup-user.php');
    exit;
  }

  // Phone validation (expects 10 digits starting with 9)
  $phoneVal = null;
  if ($phone !== '') {
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (!preg_match('/^9\d{9}$/', $phone)) {
      flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
      header('Location: ' . $baseUrl . '/pages/signup-user.php');
      exit;
    }
    $phoneVal = $phone;
  }

  // Birthdate validation + 18+
  $birthdateVal = null;
  if ($birthdate !== '') {
    $d = date_create($birthdate);
    if (!$d) {
      flash_set('error', 'Enter a valid birth date.');
      header('Location: ' . $baseUrl . '/pages/signup-user.php');
      exit;
    }
    $birthdateVal = $d->format('Y-m-d');

    $today = new DateTime('today');
    $age = $d->diff($today)->y;
    if ($age < 18) {
      flash_set('error', 'You must be at least 18 years old.');
      header('Location: ' . $baseUrl . '/pages/signup-user.php');
      exit;
    }
  }

  $ins = $pdo->prepare('INSERT INTO accounts (role, name, gender, email, password_hash, phone, birthdate)
                        VALUES (?,?,?,?,?,?,?)');
  $ins->execute(['user', $name, $gender, $email, $hash, $phoneVal, $birthdateVal]);

  header('Location: ' . $baseUrl . '/pages/signup-success.php?role=' . urlencode($role));
  exit;
}

// -----------------------
// CLINIC ADMIN SIGN UP (creates a NEW clinic + first admin)
// -----------------------
$adminName      = trim((string)($_POST['admin_name'] ?? ''));
$clinicName     = trim((string)($_POST['clinic_name'] ?? ''));
$specialty      = trim((string)($_POST['specialty'] ?? ''));
$specialtyOther = trim((string)($_POST['specialty_other'] ?? ''));
$contactNumber  = trim((string)($_POST['contact_number'] ?? ''));
$clinicEmail    = strtolower(trim((string)($_POST['clinic_email'] ?? '')));
$businessId     = trim((string)($_POST['business_id'] ?? ''));

if ($adminName === '') {
  flash_set('error', 'Please enter the admin name.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

if ($clinicName === '' || $specialty === '' || $businessId === '' || $contactNumber === '') {
  flash_set('error', 'Please complete all required fields.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

if ($specialty === 'Other' && $specialtyOther === '') {
  flash_set('error', 'Please specify your clinic type.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

// Business ID validation (exactly 10 digits)
$businessIdDigits = preg_replace('/\D+/', '', $businessId) ?? '';
if (!preg_match('/^\d{10}$/', $businessIdDigits)) {
  flash_set('error', 'Business ID must be exactly 10 digits.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

// Contact number required, digits only (10 digits starting with 9)
$contactDigits = preg_replace('/\D+/', '', $contactNumber) ?? '';
if (!preg_match('/^9\d{9}$/', $contactDigits)) {
  flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

// Clinic email optional
if ($clinicEmail !== '' && !filter_var($clinicEmail, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid clinic email address.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

// Unique Business ID (clinic)
$stmt = $pdo->prepare('SELECT id FROM clinics WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessIdDigits]);
if ($stmt->fetch()) {
  flash_set('error', 'Business ID is already registered.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

// Upload helper
function upload_image_optional(array $file, string $dirFs, string $dirWeb, array $extAllow, string $prefix): ?string {
  if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;

  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) return null;

  $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
  if (!in_array($ext, $extAllow, true)) return null;

  if (!is_dir($dirFs)) { @mkdir($dirFs, 0777, true); }

  $fileName = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $dest = rtrim($dirFs, '/\\') . DIRECTORY_SEPARATOR . $fileName;
  if (!move_uploaded_file($tmp, $dest)) return null;

  return rtrim($dirWeb, '/') . '/' . $fileName;
}

$logoPath = upload_image_optional(
  $_FILES['logo'] ?? [],
  __DIR__ . '/../uploads/logos',
  $baseUrl . '/uploads/logos',
  ['jpg', 'jpeg', 'png', 'webp'],
  'logo'
);

$workIdPath = upload_image_optional(
  $_FILES['admin_work_id'] ?? [],
  __DIR__ . '/../uploads/work-ids',
  $baseUrl . '/uploads/work-ids',
  ['jpg', 'jpeg', 'png', 'webp'],
  'workid'
);

try {
  $pdo->beginTransaction();

  // Create clinic
  $insClinic = $pdo->prepare('INSERT INTO clinics
      (clinic_name, specialty, specialty_other, logo_path, business_id, contact, email)
    VALUES
      (?,?,?,?,?,?,?)');
  $insClinic->execute([
    $clinicName,
    $specialty,
    ($specialty === 'Other' ? ($specialtyOther !== '' ? $specialtyOther : null) : null),
    $logoPath,
    $businessIdDigits,
    $contactDigits,
    ($clinicEmail !== '' ? $clinicEmail : null),
  ]);
  $clinicId = (int)$pdo->lastInsertId();

  // Create first clinic admin account (linked to the clinic)
  $ins = $pdo->prepare('INSERT INTO accounts (role, clinic_id, name, email, password_hash, phone, admin_work_id_path)
                        VALUES (?,?,?,?,?,?,?)');
  $ins->execute(['clinic_admin', $clinicId, $adminName, $email, $hash, $contactDigits, $workIdPath]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('error', 'Sign up failed. Please try again.');
  header('Location: ' . $baseUrl . '/pages/signup-admin.php');
  exit;
}

header('Location: ' . $baseUrl . '/pages/signup-success.php?role=' . urlencode($role));
exit;
