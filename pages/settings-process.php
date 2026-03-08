<?php
declare(strict_types=1);

$baseUrl = '';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('user', $baseUrl);

function redirect($to) {
  header('Location: ' . $to);
  exit;
}

// Field-level errors for inline validators on settings.php
function settings_set_errors(array $errors, array $old = []) {
  $_SESSION['settings_errors'] = $errors;
  if (!empty($old)) $_SESSION['settings_old'] = $old;
}

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
  settings_set_errors(['name' => 'Please fill out this field.'], ['name' => $name]);
  redirect($baseUrl . '/pages/settings.php');
}

$allowedGender = ['Male','Female','Prefer not to say'];
if (!in_array($gender, $allowedGender, true)) {
  flash_set('error', 'Please select a valid gender.');
  settings_set_errors(['gender' => 'Please select a valid gender.'], ['gender' => $gender]);
  redirect($baseUrl . '/pages/settings.php');
}
$phoneVal = null;
if ($phone !== '') {
  $phone = preg_replace('/\D+/', '', $phone) ?? '';
  if (!preg_match('/^9\d{9}$/', $phone)) {
    flash_set('error', 'Enter a valid PH mobile number (ex: 9123456789).');
    settings_set_errors(['phone' => 'Enter a valid PH mobile number (ex: 9123456789).'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phone,
      'birthdate' => $birthdate,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }
  $phoneVal = $phone;
}
$birthdateVal = null;
if ($birthdate !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $birthdate);
  if (!$d) {
    flash_set('error', 'Enter a valid birth date.');
    settings_set_errors(['birthdate' => 'Please pick a valid birthdate.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdate,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }

  $d->setTime(0, 0, 0);
  $today = new DateTime('today');
  $today->setTime(0, 0, 0);

  // ✅ NEW: future date check
  if ($d > $today) {
    flash_set('error', 'Birth date cannot be in the future.');
    settings_set_errors(['birthdate' => 'Birthdate cannot be in the future.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdate,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }

  $age = $d->diff($today)->y;
  if ($age < 18) {
    flash_set('error', 'You must be at least 18 years old.');
    settings_set_errors(['birthdate' => 'You must be at least 18 years old.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdate,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }

  $birthdateVal = $d->format('Y-m-d');
}


$pdo = db();

// Guard against uncaught exceptions (prevents fatal white-page errors)
try {
$setPassword = false;
$hash = null;

if ($newPass !== '' || $confirm !== '') {
  if ($currentPass === '') {
    flash_set('error', 'Enter your current password.');
    settings_set_errors(['current_password' => 'Please enter your current password.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }
  $stmt = $pdo->prepare('SELECT password_hash FROM accounts WHERE id = ? LIMIT 1');
  $stmt->execute([auth_user_id()]);
  $row = $stmt->fetch();

  if (!$row || !password_verify($currentPass, $row['password_hash'])) {
    flash_set('error', 'Incorrect current password.');
    settings_set_errors(['current_password' => 'Incorrect current password.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }
  $minLen = strlen($newPass) >= 8;
  $hasUpper = preg_match('/[A-Z]/', $newPass) === 1;
  $hasSpecial = preg_match('/[^A-Za-z0-9]/', $newPass) === 1;

  if (!($minLen && $hasUpper && $hasSpecial)) {
    flash_set('error', 'Password must be 8+ chars, with 1 uppercase and 1 special character.');
    settings_set_errors(['new_password' => 'Password must be 8+ chars, with 1 uppercase and 1 special character.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }

  if ($newPass !== $confirm) {
    flash_set('error', 'Password confirmation does not match.');
    settings_set_errors(['confirm_password' => 'Password confirmation does not match.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
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

$oldPhoneRaw = $old['phone'] ?? null;

// Normalize PH phone numbers so "0912...", "63912...", "+63 912..." are treated
// as the same value ("9123456789"). This prevents false "already in use" errors
// when the user didn't actually change their phone.
$normPhone = static function ($v): ?string {
  if ($v === null) return null;
  $d = preg_replace('/\D+/', '', (string)$v) ?? '';
  if ($d === '') return null;

  // 63XXXXXXXXXX
  if (strlen($d) == 12 && str_starts_with($d, '63')) {
    $d = substr($d, 2);
  }

  // 0XXXXXXXXXX
  if (strlen($d) == 11 && str_starts_with($d, '0')) {
    $d = substr($d, 1);
  }

  // Expect 9XXXXXXXXX (10 digits)
  if (preg_match('/^9\d{9}$/', $d) !== 1) return null;
  return $d;
};

$oldPhone = $normPhone($oldPhoneRaw);
$newPhone = $phoneVal; // already validated earlier as 9XXXXXXXXX or null

if ($newPhone !== $oldPhone) {
  $changes[] = 'Phone Number';
}

// Phone uniqueness (ignore current account).
// IMPORTANT: treat 912..., 0912..., 63912... as the same phone.
if ($newPhone !== null && $newPhone !== $oldPhone) {
  $v1 = $newPhone;
  $v2 = '0' . $newPhone;
  $v3 = '63' . $newPhone;
  $v4 = '+63' . $newPhone;

  $stmt = $pdo->prepare(
    'SELECT 1 FROM accounts
      WHERE id <> ?
        AND (phone = ? OR phone = ? OR phone = ? OR phone = ?)
      LIMIT 1'
  );
  $stmt->execute([auth_user_id(), $v1, $v2, $v3, $v4]);
  if ($stmt->fetchColumn()) {
    flash_set('error', 'Phone number is already in use.');
    settings_set_errors(['phone' => 'Phone number is already in use.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $newPhone,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }
}


$oldBirth = $old['birthdate'] ?? null;
if ($birthdateVal !== $oldBirth) {
  $changes[] = 'Birthdate';
}

if ($setPassword) {
  $changes[] = 'Password';
}


try {
  if ($setPassword) {
    $stmt = $pdo->prepare('UPDATE accounts SET name=?, gender=?, phone=?, birthdate=?, password_hash=? WHERE id=?');
    $stmt->execute([$name, $gender, $phoneVal, $birthdateVal, $hash, auth_user_id()]);
  } else {
    $stmt = $pdo->prepare('UPDATE accounts SET name=?, gender=?, phone=?, birthdate=? WHERE id=?');
    $stmt->execute([$name, $gender, $phoneVal, $birthdateVal, auth_user_id()]);
  }
} catch (PDOException $e) {
  // Unique constraint fallback (race condition)
  if ((string)$e->getCode() === '23000') {
    flash_set('error', 'Phone number is already in use.');
    settings_set_errors(['phone' => 'Phone number is already in use.'], [
      'name' => $name,
      'gender' => $gender,
      'phone' => $phoneVal,
      'birthdate' => $birthdateVal,
    ]);
    redirect($baseUrl . '/pages/settings.php');
  }
  // Any other DB error
  flash_set('error', 'Something went wrong. Please try again.');
  settings_set_errors(['general' => 'Something went wrong. Please try again.'], [
    'name' => $name,
    'gender' => $gender,
    'phone' => $phoneVal,
    'birthdate' => $birthdateVal,
  ]);
  redirect($baseUrl . '/pages/settings.php');
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

unset($_SESSION['settings_errors'], $_SESSION['settings_old']);
redirect($baseUrl . '/pages/settings.php');

} catch (Throwable $e) {
  // Final safety net
  flash_set('error', 'Something went wrong. Please try again.');
  settings_set_errors(['general' => 'Something went wrong. Please try again.'], [
    'name' => $name ?? '',
    'gender' => $gender ?? '',
    'phone' => $phoneVal ?? '',
    'birthdate' => $birthdateVal ?? '',
  ]);
  redirect($baseUrl . '/pages/settings.php');
}

