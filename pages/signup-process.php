<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_verification.php';
flash_clear();

function redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

// clinic_admin goes back to step=2
function backToSignup(string $baseUrl, string $role): void {
  $locked = ((string)($_POST['google_locked'] ?? '0') === '1');

  if ($role === 'clinic_admin') {
    redirect($baseUrl . ($locked ? '/pages/signup-admin.php?step=2&locked=1' : '/pages/signup-admin.php?step=2'));
  }

  redirect($baseUrl . '/pages/signup-user.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect($baseUrl . '/pages/signup.php');
}

/**
 * ✅ Allow logged-in clinic_admin with NO clinic yet to finish Step 2 (Google flow)
 * Everyone else who is logged in should be redirected away.
 */
if (auth_is_logged_in()) {
  if (auth_role() === 'clinic_admin' && (int)(auth_clinic_id() ?? 0) <= 0) {
    // allow to proceed
  } else {
    $to = $baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top');
    redirect($to);
  }
}

$role = trim((string)($_POST['role'] ?? ''));
if (!in_array($role, ['user', 'clinic_admin'], true)) {
  flash_set('error', 'Invalid account type.');
  redirect($baseUrl . '/pages/signup.php');
}

$googleLocked = ((string)($_POST['google_locked'] ?? '0') === '1');

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$email = preg_replace('/\s+/', '', $email);

$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid email.');
  backToSignup($baseUrl, $role);
}

// stricter email regex (optional)
if (!preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/', $email)) {
  flash_set('error', 'Please enter a valid email address.');
  backToSignup($baseUrl, $role);
}

if (!$googleLocked) {
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
}

$pdo = db();

// ✅ Unique email check
$stmt = $pdo->prepare('SELECT id FROM accounts WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existingId = (int)($stmt->fetchColumn() ?? 0);

if ($existingId > 0) {
  // ✅ Google clinic-admin flow: allow if this is the same logged-in account
  if ($googleLocked && auth_is_logged_in() && auth_role() === 'clinic_admin') {
    if ($existingId !== (int)auth_user_id()) {
      flash_set('error', 'Email is already in use. Please use your own Google account.');
      backToSignup($baseUrl, $role);
    }
  } else {
    flash_set('error', 'Email is already in use. Please log in.');
    redirect($baseUrl . '/pages/login.php');
  }
}

// Password hash
$hash = $googleLocked
  ? password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)
  : password_hash($password, PASSWORD_DEFAULT);

/**
 * =========================
 * USER SIGNUP (NORMAL ONLY)
 * =========================
 * Requirement: normal signups must login after registering.
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
    flash_set('error', 'You can only use letters and spacing (Maximum of 50 characters).');
    redirect($baseUrl . '/pages/signup-user.php');
  }

  if (!preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $name)) {
    flash_set('error', 'You can only use letters and spacing (Maximum of 50 characters).');
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

  // ✅ Unique phone check (only when provided)
  if ($phoneVal !== null) {
    $stmt = $pdo->prepare('SELECT id FROM accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneVal]);
    if ((int)($stmt->fetchColumn() ?? 0) > 0) {
      flash_set('error', 'Phone number is already in use.');
      redirect($baseUrl . '/pages/signup-user.php');
    }
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
  $newId = (int)$pdo->lastInsertId();

  // ✅ Manual accounts require email verification
  if (!$googleLocked) {
    $token = akas_create_email_verify_token($pdo, $newId, 30);
    $sent  = akas_send_verification_email($baseUrl, $email, $name, $token);

    flash_set('success', $sent
      ? 'Account created! Please check your email to verify your account before logging in.'
      : 'Account created, but we could not send the verification email. Please configure SMTP and resend verification.'
    );

    redirect($baseUrl . '/pages/signup-success.php?role=user');
  }

  // (fallback) Google-created users are already verified
  flash_set('success', 'Account created successfully. Please log in.');
  redirect($baseUrl . '/pages/login.php');
}

/**
 * =========================
 * CLINIC ADMIN SIGNUP
 * =========================
 * Normal: Step 1+2 then login later (not auto-login).
 * Google: Step 2 only (locked), then logout after submission and login later.
 */

$adminName      = trim((string)($_POST['admin_name'] ?? ''));
$adminName      = preg_replace('/\s+/', ' ', $adminName);
$clinicName     = trim((string)($_POST['clinic_name'] ?? ''));
$clinicName     = preg_replace('/\s+/', ' ', $clinicName);
$specialty      = trim((string)($_POST['specialty'] ?? ''));
$specialtyOther = trim((string)($_POST['specialty_other'] ?? ''));
$contactNumber  = trim((string)($_POST['contact_number'] ?? ''));
$clinicEmail    = strtolower(trim((string)($_POST['clinic_email'] ?? '')));
$businessId     = trim((string)($_POST['business_id'] ?? ''));

// OPTIONAL (safe)
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

// ✅ FILTER OUT "EMPTY DOCTORS" (fixes false Doctor #1 errors)
if (!is_array($doctors)) $doctors = [];
$doctors = array_values(array_filter($doctors, function ($d) {
  if (!is_array($d)) return false;

  // Any of these being present means it's a "real" doctor
  $name = trim((string)($d['full_name'] ?? $d['name'] ?? $d['fullName'] ?? ''));
  $email = trim((string)($d['email'] ?? ''));
  $spec = trim((string)($d['specialization'] ?? ''));
  $prc  = trim((string)($d['prc'] ?? $d['prc_no'] ?? $d['prcNo'] ?? ''));

  return ($name !== '' || $email !== '' || $spec !== '' || $prc !== '');
}));

if ($adminName === '') {
  flash_set('error', 'Please enter the admin name.');
  backToSignup($baseUrl, 'clinic_admin');
}

// ✅ Admin full name: letters + spaces only, max 50
if (mb_strlen($adminName) > 50 || !preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $adminName)) {
  flash_set('error', 'You can only use letters and spacing (Maximum of 50 characters).');
  backToSignup($baseUrl, 'clinic_admin');
}

if ($clinicName === '' || $specialty === '' || $businessId === '' || $contactNumber === '') {
  flash_set('error', 'Please complete all required fields.');
  backToSignup($baseUrl, 'clinic_admin');
}

// ✅ Clinic name: letters + spaces only, max 50
if (mb_strlen($clinicName) > 50 || !preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $clinicName)) {
  flash_set('error', 'You can only use letters and spacing (Maximum of 50 characters).');
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

// ✅ Unique clinic contact number check
$stmt = $pdo->prepare('SELECT id FROM clinics WHERE contact = ? LIMIT 1');
$stmt->execute([$contactDigits]);
if ($stmt->fetch()) {
  flash_set('error', 'Phone number is already in use.');
  backToSignup($baseUrl, 'clinic_admin');
}

if ($clinicEmail !== '' && !filter_var($clinicEmail, FILTER_VALIDATE_EMAIL)) {
  flash_set('error', 'Please enter a valid clinic email address.');
  backToSignup($baseUrl, 'clinic_admin');


// ✅ Unique clinic email (optional but must be unique when provided)
if ($clinicEmail !== '') {
  $stmt = $pdo->prepare('SELECT id FROM clinics WHERE email = ? LIMIT 1');
  $stmt->execute([$clinicEmail]);
  if ($stmt->fetch()) {
    flash_set('error', 'Clinic email is already in use.');
    backToSignup($baseUrl, 'clinic_admin');
  }
}

}

// unique business id
$stmt = $pdo->prepare('SELECT id FROM clinics WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessIdDigits]);
if ($stmt->fetch()) {
  flash_set('error', 'Business ID is already registered.');
  backToSignup($baseUrl, 'clinic_admin');
}

// Validate doctors payload (optional)
if (count($doctors) > 20) {
  flash_set('error', 'Too many doctors added. Please keep it at 20 or less.');
  backToSignup($baseUrl, 'clinic_admin');
}

// ✅ More flexible doctor validation (accepts multiple key names + auto-schedule)
foreach ($doctors as $i => $d) {
  if (!is_array($d)) {
    flash_set('error', 'Invalid doctor data. Please re-add the doctor(s).');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $fullName = trim((string)($d['full_name'] ?? $d['name'] ?? $d['fullName'] ?? ''));
  $birth    = trim((string)($d['birthdate'] ?? $d['birth_date'] ?? ''));
  $spec     = trim((string)($d['specialization'] ?? $d['specialty'] ?? ''));
  $prc      = trim((string)($d['prc'] ?? $d['prc_no'] ?? $d['prcNo'] ?? ''));
  $dEmail   = strtolower(trim((string)($d['email'] ?? '')));
  $phone    = trim((string)($d['contact_number'] ?? $d['phone'] ?? $d['contact'] ?? ''));

  // schedule: accept schedule OR availability OR build from parts
  $sched = trim((string)($d['schedule'] ?? $d['availability'] ?? ''));

  // If your JS stores pieces, build a simple schedule string
  if ($sched === '') {
    $slot = trim((string)($d['slot_mins'] ?? $d['slotMins'] ?? ''));
    $start = trim((string)($d['start_time'] ?? $d['startTime'] ?? ''));
    $end = trim((string)($d['end_time'] ?? $d['endTime'] ?? ''));
    $days = $d['days'] ?? null; // could be array like ["Mon","Tue"]

    if ($start !== '' && $end !== '') {
      $daysText = '';
      if (is_array($days) && $days) {
        $daysText = implode(',', array_map('strval', $days));
      }
      $sched = trim(($daysText !== '' ? $daysText . ' ' : '') . $start . '-' . $end . ($slot !== '' ? ' (' . $slot . 'm)' : ''));
    }
  }

  // ✅ Required doctor fields (schedule required only if doctor exists)
  if ($fullName === '' || $birth === '' || $spec === '' || $prc === '' || $dEmail === '' || $phone === '' || $sched === '') {
    flash_set('error', 'Please complete all doctor fields (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $birthObj = date_create($birth);
  if (!$birthObj) {
    flash_set('error', 'Invalid doctor birthdate (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $birthObj->setTime(0, 0, 0);
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  if ($birthObj > $today) {
    flash_set('error', 'Doctor birthdate cannot be in the future (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
  if (!preg_match('/^9\d{9}$/', $phoneDigits)) {
    flash_set('error', 'Invalid doctor contact number (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  if (!filter_var($dEmail, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Invalid doctor email (Doctor #' . ($i + 1) . ').');
    backToSignup($baseUrl, 'clinic_admin');
  }

  // ✅ normalize
  $doctors[$i] = [
    'full_name' => $fullName,
    'birthdate' => $birthObj->format('Y-m-d'),
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

  // Insert clinic
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

  // Insert / attach admin account linked to clinic
  if ($googleLocked) {
    if (!auth_is_logged_in() || auth_role() !== 'clinic_admin' || (int)(auth_clinic_id() ?? 0) > 0) {
      flash_set('error', 'Invalid clinic registration state. Please login again.');
      backToSignup($baseUrl, 'clinic_admin');
    }

    $acctId = (int)auth_user_id();

    $updAdmin = $pdo->prepare("
      UPDATE accounts
      SET clinic_id = ?, name = ?, email = ?
      WHERE id = ? AND role = 'clinic_admin'
      LIMIT 1
    ");
    $updAdmin->execute([$clinicId, $adminName, $email, $acctId]);
    // ✅ update session too, so pending.php sees clinic_id
auth_set($acctId, 'clinic_admin', $adminName, $email, $clinicId);


  } else {
    $adminAccountId = 0;
    $insAdmin = $pdo->prepare('
      INSERT INTO accounts (role, clinic_id, name, email, password_hash, phone, admin_work_id_path)
      VALUES (?,?,?,?,?,?,?)
    ');
    $insAdmin->execute(['clinic_admin', $clinicId, $adminName, $email, $hash, null, $workIdPath]);
    $adminAccountId = (int)$pdo->lastInsertId();
  }

  // Insert doctors (optional)
  if (!empty($doctors)) {
    $insDoc = $pdo->prepare('
      INSERT INTO clinic_doctors
        (clinic_id, name, birthdate, specialization, prc_no, schedule, email, contact_number, approval_status, created_via)
      VALUES
        (?,?,?,?,?,?,?,?,?,?,?)
    ');

    // Helper: convert availability JSON to a readable schedule string (for the schedule column)
    $fmtSchedule = function (?string $availabilityJson): ?string {
      if (!$availabilityJson) return null;
      $a = json_decode($availabilityJson, true);
      if (!is_array($a)) return null;

      $days = $a['days'] ?? null;
      $start = (string)($a['start'] ?? '');
      $end   = (string)($a['end'] ?? '');
      $mins  = (int)($a['slot_mins'] ?? 0);

      $dayLabel = '';
      if (is_array($days)) {
        sort($days);
        $days = array_values(array_map('intval', $days));
        if ($days === [1,2,3,4,5]) $dayLabel = 'Mon–Fri';
        elseif ($days === [0,1,2,3,4,5,6]) $dayLabel = 'Daily';
        else {
          $map = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
          $labels = [];
          foreach ($days as $d) { if (isset($map[$d])) $labels[] = $map[$d]; }
          $dayLabel = implode(', ', $labels);
        }
      }

      $parts = [];
      if ($dayLabel !== '') $parts[] = $dayLabel;
      if ($start !== '' && $end !== '') $parts[] = $start . '–' . $end;
      if ($mins > 0) $parts[] = $mins . 'm slots';

      return $parts ? implode(' • ', $parts) : null;
    };

    foreach ($doctors as $d) {
      // Coming from signup-admin-doctors.js:
      // - $d['availability'] is a JSON string like {"days":[1,2,3,4,5],"start":"09:00","end":"17:00","slot_mins":30}
      $availabilityJson = (string)($d['availability'] ?? '');

      $insDoc->execute([
        $clinicId,
        (string)($d['full_name'] ?? ''),
        (string)($d['birthdate'] ?? null),
        (string)($d['specialization'] ?? null),
        (string)($d['prc'] ?? null),
        $fmtSchedule($availabilityJson),         // schedule (readable)
        ($availabilityJson !== '' ? $availabilityJson : null), // availability (JSON for calendar)
        (string)($d['email'] ?? null),
        (string)($d['contact_number'] ?? null),
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

  // ✅ Send email verification for MANUAL admin accounts
  if (!$googleLocked && $adminAccountId > 0) {
    $token = akas_create_email_verify_token($pdo, $adminAccountId, 30);
    $sent  = akas_send_verification_email($baseUrl, $email, $adminName, $token);

    flash_set('success', $sent
      ? 'Registration submitted! Please verify your email (check inbox/spam). After verification, you can log in, but your clinic will still need superadmin approval.'
      : 'Registration submitted, but we could not send the verification email. Please configure SMTP and resend verification.'
    );
  }

// ✅ Always require login after admin registration
// ✅ If Google admin finished Step 2, log them out (they must login later)


// ✅ Redirect to pending page (NOT login)
redirect($baseUrl . '/admin/pending.php');

