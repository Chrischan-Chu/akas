<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('user', $baseUrl);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $baseUrl . '/pages/settings.php');
  exit;
}

$name      = trim((string)($_POST['name'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$phone     = trim((string)($_POST['phone'] ?? ''));     
$birthdate = trim((string)($_POST['birthdate'] ?? '')); 
$currentPass = (string)($_POST['current_password'] ?? '');
$newPass     = (string)($_POST['new_password'] ?? '');
$confirm     = (string)($_POST['confirm_password'] ?? '');


if ($name === '') {
  flash_set('error', 'Name is required.');
  header('Location: ' . $baseUrl . '/pages/settings.php');
  exit;
}

$allowedGender = ['Male','Female','Prefer not to say'];
if (!in_array($gender, $allowedGender, true)) {
  flash_set('error', 'Please select a valid gender.');
  header('Location: ' . $baseUrl . '/pages/settings.php');
  exit;
}
$phoneVal = null;
if ($phone !== '') {
  $phone = preg_replace('/\D+/', '', $phone) ?? '';
  if (!preg_match('/^9\d{9}$/', $phone)) {
    flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
    header('Location: ' . $baseUrl . '/pages/settings.php');
    exit;
  }
  $phoneVal = $phone;
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

  // ✅ NEW: future date check
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


$pdo = db();
$setPassword = false;
$hash = null;

if ($newPass !== '' || $confirm !== '') {
  if ($currentPass === '') {
    flash_set('error', 'Enter your current password.');
    header('Location: ' . $baseUrl . '/pages/settings.php');
    exit;
  }
  $stmt = $pdo->prepare('SELECT password_hash FROM accounts WHERE id = ? LIMIT 1');
  $stmt->execute([auth_user_id()]);
  $row = $stmt->fetch();

  if (!$row || !password_verify($currentPass, $row['password_hash'])) {
    flash_set('error', 'Incorrect current password.');
    header('Location: ' . $baseUrl . '/pages/settings.php');
    exit;
  }
  $minLen = strlen($newPass) >= 8;
  $hasUpper = preg_match('/[A-Z]/', $newPass) === 1;
  $hasSpecial = preg_match('/[^A-Za-z0-9]/', $newPass) === 1;

  if (!($minLen && $hasUpper && $hasSpecial)) {
    flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
    header('Location: ' . $baseUrl . '/pages/settings.php');
    exit;
  }

  if ($newPass !== $confirm) {
    flash_set('error', 'Password confirmation does not match.');
    header('Location: ' . $baseUrl . '/pages/settings.php');
    exit;
  }

  $hash = password_hash($newPass, PASSWORD_DEFAULT);
  $setPassword = true;
}

$changes = [];
$stmt = $pdo->prepare('SELECT name, gender, phone, birthdate FROM accounts WHERE id = ? LIMIT 1');
$stmt->execute([auth_user_id()]);
$old = $stmt->fetch() ?: [];

if ($name !== ($old['name'] ?? '')) {
  $changes[] = 'Full Name';
}

if ($gender !== ($old['gender'] ?? '')) {
  $changes[] = 'Gender';
}

$oldPhone = $old['phone'] ?? null;
if ($phoneVal !== $oldPhone) {
  $changes[] = 'Phone Number';
}


$oldBirth = $old['birthdate'] ?? null;
if ($birthdateVal !== $oldBirth) {
  $changes[] = 'Birthdate';
}

if ($setPassword) {
  $changes[] = 'Password';
}


if ($setPassword) {
  $stmt = $pdo->prepare('UPDATE accounts SET name=?, gender=?, phone=?, birthdate=?, password_hash=? WHERE id=?');
  $stmt->execute([$name, $gender, $phoneVal, $birthdateVal, $hash, auth_user_id()]);
} else {
  $stmt = $pdo->prepare('UPDATE accounts SET name=?, gender=?, phone=?, birthdate=? WHERE id=?');
  $stmt->execute([$name, $gender, $phoneVal, $birthdateVal, auth_user_id()]);
}

$_SESSION['auth']['name'] = $name;


if ($changes) {

  if (count($changes) === 1) {
    $message = $changes[0] . ' has been changed successfully.';
  } else {
    $last = array_pop($changes);
    $message = implode(', ', $changes) . ' and ' . $last . ' have been changed successfully.';
  }

  flash_set('success', $message);

} else {
  flash_set('success', 'No changes were made.');
}

header('Location: ' . $baseUrl . '/pages/settings.php');
exit;

