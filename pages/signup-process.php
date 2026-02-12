<?php
declare(strict_types=1);

$baseUrl = '/AKAS';
require_once __DIR__ . '/../includes/auth.php';
flash_clear();

function redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

// clinic_admin goes back to step=2
function backToSignup(string $baseUrl, string $role): void {
  if ($role === 'clinic_admin') {
    redirect($baseUrl . '/pages/signup-admin.php?step=2');
  }
  redirect($baseUrl . '/pages/signup-user.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect($baseUrl . '/pages/signup.php');
}

if (auth_is_logged_in()) {
  $to = $baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top');
  redirect($to);
}

$role = trim((string)($_POST['role'] ?? ''));
if (!in_array($role, ['user', 'clinic_admin'], true)) {
  flash_set('error', 'Invalid account type.');
  redirect($baseUrl . '/pages/signup.php');
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$email = preg_replace('/\s+/', '', $email);
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid email.');
  backToSignup($baseUrl, $role);
}

if (!preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/', $email)) {
  flash_set('error', 'Please enter a valid email address.');
  backToSignup($baseUrl, $role);
}

$minLen = strlen($password) >= 8;
$hasUpper = preg_match('/[A-Z]/', $password) === 1;
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

if (!($minLen && $hasUpper && $hasSpecial)) {
  flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
  backToSignup($baseUrl, $role);
}

if ($password !== $confirmPassword) {
  flash_set('error', 'Passwords do not match.');
  backToSignup($baseUrl, $role);
}

$pdo = db();

// unique email
$stmt = $pdo->prepare('SELECT id FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
  flash_set('error', 'Email is already registered. Please login.');
  redirect($baseUrl . '/pages/login.php');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

/**
 * USER SIGNUP
 */
if ($role === 'user') {
  $name = trim((string)($_POST['name'] ?? ''));
  $name = preg_replace('/\s+/', ' ', $name); 
  $gender = trim((string)($_POST['gender'] ?? ''));
  $phone = trim((string)($_POST['contact_number'] ?? ''));
  $birthdate = trim((string)($_POST['birthdate'] ?? ''));

  if ($name === '') {
  flash_set('error', 'Please enter your full name.');
  redirect($baseUrl . '/pages/signup-user.php');
}

if (mb_strlen($name) > 50) {
  flash_set('error', 'Full name must be 50 characters or less.');
  redirect($baseUrl . '/pages/signup-user.php');
}


if (!preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $name)) {
  flash_set('error', 'Full name must contain letters and spaces only.');
  redirect($baseUrl . '/pages/signup-user.php');
}

  $allowedGenders = ['Male', 'Female', 'Prefer not to say'];
  if (!in_array($gender, $allowedGenders, true)) {
    flash_set('error', 'Please select a gender.');
    redirect($baseUrl . '/pages/signup-user.php');
  }

  $phoneVal = null;
  if ($phone !== '') {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (!preg_match('/^9\d{9}$/', $digits)) {
      flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
      redirect($baseUrl . '/pages/signup-user.php');
    }
    $phoneVal = $digits;
  }

  $birthdateVal = null;
if ($birthdate !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $birthdate);
  if (!$d) {
    flash_set('error', 'Enter a valid birth date.');
    redirect($baseUrl . '/pages/signup-user.php');
  }

  $d->setTime(0, 0, 0);
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  
  if ($d > $today) {
    flash_set('error', 'Birth date cannot be in the future.');
    redirect($baseUrl . '/pages/signup-user.php');
  }

  $age = $d->diff($today)->y;
  if ($age < 18) {
    flash_set('error', 'You must be at least 18 years old.');
    redirect($baseUrl . '/pages/signup-user.php');
  }

  $birthdateVal = $d->format('Y-m-d');
}


  $ins = $pdo->prepare('
    INSERT INTO accounts (role, name, gender, email, password_hash, phone, birthdate)
    VALUES (?,?,?,?,?,?,?)
  ');
  $ins->execute(['user', $name, $gender, $email, $hash, $phoneVal, $birthdateVal]);

  redirect($baseUrl . '/pages/signup-success.php?role=' . urlencode($role));
}

/**
 * CLINIC ADMIN SIGNUP
 */
$adminName      = trim((string)($_POST['admin_name'] ?? ''));
$clinicName     = trim((string)($_POST['clinic_name'] ?? ''));
$specialty      = trim((string)($_POST['specialty'] ?? ''));
$specialtyOther = trim((string)($_POST['specialty_other'] ?? ''));
$contactNumber  = trim((string)($_POST['contact_number'] ?? ''));
$clinicEmail    = strtolower(trim((string)($_POST['clinic_email'] ?? '')));
$businessId     = trim((string)($_POST['business_id'] ?? ''));

// OPTIONAL fields (your form currently does NOT send these — safe to keep optional)
$address     = trim((string)($_POST['address'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

// Doctors JSON (optional)
$doctorsJson = (string)($_POST['doctors_json'] ?? '[]');
$doctors = [];
if ($doctorsJson !== '') {
  try {
    $decoded = json_decode($doctorsJson, true, 512, JSON_THROW_ON_ERROR);
    if (is_array($decoded)) $doctors = $decoded;
  } catch (Throwable $e) {
    $doctors = [];
  }
}

if ($adminName === '') {
  flash_set('error', 'Please enter the admin name.');
  backToSignup($baseUrl, 'clinic_admin');
}

if ($clinicName === '' || $specialty === '' || $businessId === '' || $contactNumber === '') {
  flash_set('error', 'Please complete all required fields.');
  backToSignup($baseUrl, 'clinic_admin');
}

if ($specialty === 'Other' && $specialtyOther === '') {
  flash_set('error', 'Please specify your clinic type.');
  backToSignup($baseUrl, 'clinic_admin');
}

$businessIdDigits = preg_replace('/\D+/', '', $businessId) ?? '';
if (!preg_match('/^\d{10}$/', $businessIdDigits)) {
  flash_set('error', 'Business ID must be exactly 10 digits.');
  backToSignup($baseUrl, 'clinic_admin');
}

$contactDigits = preg_replace('/\D+/', '', $contactNumber) ?? '';
if (!preg_match('/^9\d{9}$/', $contactDigits)) {
  flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
  backToSignup($baseUrl, 'clinic_admin');
}

if ($clinicEmail !== '' && !filter_var($clinicEmail, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid clinic email address.');
  backToSignup($baseUrl, 'clinic_admin');
}

// unique business id
$stmt = $pdo->prepare('SELECT id FROM clinics WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessIdDigits]);
if ($stmt->fetch()) {
  flash_set('error', 'Business ID is already registered.');
  backToSignup($baseUrl, 'clinic_admin');
}

// Validate doctors payload (optional)
if (!is_array($doctors)) $doctors = [];
if (count($doctors) > 20) {
  flash_set('error', 'Too many doctors added. Please keep it at 20 or less.');
  backToSignup($baseUrl, 'clinic_admin');
}

foreach ($doctors as $i => $d) {
  if (!is_array($d)) {
    flash_set('error', 'Invalid doctor data. Please re-add the doctor(s).');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $fullName = trim((string)($d['full_name'] ?? ''));
  $birth    = trim((string)($d['birthdate'] ?? ''));
  $spec     = trim((string)($d['specialization'] ?? ''));
  $prc      = trim((string)($d['prc'] ?? ''));
  $sched    = trim((string)($d['schedule'] ?? ''));
  $dEmail   = strtolower(trim((string)($d['email'] ?? '')));
  $phone    = trim((string)($d['contact_number'] ?? ''));

  if ($fullName === '' || $birth === '' || $spec === '' || $prc === '' || $sched === '' || $dEmail === '' || $phone === '') {
    flash_set('error', 'Please complete all doctor fields (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $birthObj = date_create($birth);
  if (!$birthObj) {
    flash_set('error', 'Invalid doctor birthdate (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $birthVal = $birthObj->format('Y-m-d');
  $today = new DateTime('today');
  if ($birthObj > $today) {
    flash_set('error', 'Doctor birthdate cannot be in the future (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
  if (!preg_match('/^9\d{9}$/', $phoneDigits)) {
    flash_set('error', 'Invalid doctor contact number (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $doctors[$i] = [
    'full_name' => $fullName,
    'birthdate' => $birthVal,
    'specialization' => $spec,
    'prc' => $prc,
    'schedule' => $sched,
    'email' => $dEmail,
    'contact_number' => $phoneDigits,
  ];
}

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

  // Insert clinic (matches your SQL structure; extra fields are optional)
  $insClinic = $pdo->prepare('
    INSERT INTO clinics
      (clinic_name, specialty, specialty_other, logo_path, business_id, contact, email,
       description, address,
       approval_status, approved_at, declined_at, declined_reason,
       is_open, open_time, close_time,
       updated_at)
    VALUES
      (?,?,?,?,?,?,?,
       ?,?,
       "PENDING", NULL, NULL, NULL,
       1, NULL, NULL,
       NOW())
  ');

  $insClinic->execute([
    $clinicName,
    $specialty,
    ($specialty === 'Other' ? ($specialtyOther !== '' ? $specialtyOther : null) : null),
    $logoPath,
    $businessIdDigits,
    $contactDigits,
    ($clinicEmail !== '' ? $clinicEmail : null),

    ($description !== '' ? $description : null),
    ($address !== '' ? $address : null),
  ]);

  $clinicId = (int)$pdo->lastInsertId();

  // Insert admin account linked to clinic
  $insAdmin = $pdo->prepare('
    INSERT INTO accounts (role, clinic_id, name, email, password_hash, phone, admin_work_id_path)
    VALUES (?,?,?,?,?,?,?)
  ');
  $insAdmin->execute(['clinic_admin', $clinicId, $adminName, $email, $hash, null, $workIdPath]);

  // Insert doctors (optional)
  if (!empty($doctors)) {
    $insDoc = $pdo->prepare('
      INSERT INTO clinic_doctors
        (clinic_id, name, birthdate, specialization, prc_no, schedule, email, contact_number, approval_status, created_via)
      VALUES
        (?,?,?,?,?,?,?,?,?,?)
    ');

    foreach ($doctors as $d) {
      $insDoc->execute([
        $clinicId,
        $d['full_name'],
        $d['birthdate'],
        $d['specialization'],
        $d['prc'],
        $d['schedule'],
        $d['email'],
        $d['contact_number'],
        'PENDING',
        'REGISTRATION',
      ]);
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('[signup-process] ' . $e->getMessage());
  flash_set('error', 'Sign up failed. Please try again.');
  backToSignup($baseUrl, 'clinic_admin');
}

redirect($baseUrl . '/pages/signup-pending.php');
