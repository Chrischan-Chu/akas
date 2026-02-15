<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$appTitle = 'AKAS | Doctors';
$baseUrl  = '/AKAS';

require_once __DIR__ . '/../includes/auth.php';
auth_require_role('clinic_admin', $baseUrl);

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  flash_set('err', 'This admin account is not linked to a clinic.');
  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Ensure upload dir exists
$uploadDirFs = __DIR__ . '/../uploads/doctors';
$uploadDirWeb = $baseUrl . '/uploads/doctors';
if (!is_dir($uploadDirFs)) {
  @mkdir($uploadDirFs, 0777, true);
}

// Helpers
function save_doctor_image(array $file, string $uploadDirFs, string $uploadDirWeb): ?string {
  if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;

  // 2MB max
  if (($file['size'] ?? 0) > 2 * 1024 * 1024) return null;

  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) return null;

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($tmp);
  $ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => null,
  };
  if ($ext === null) return null;

  $name = 'doctor_' . bin2hex(random_bytes(12)) . '.' . $ext;
  $dest = rtrim($uploadDirFs, '/\\') . DIRECTORY_SEPARATOR . $name;
  if (!move_uploaded_file($tmp, $dest)) return null;

  return rtrim($uploadDirWeb, '/') . '/' . $name;
}

function status_badge(string $st): array {
  $st = strtoupper($st);
  return match ($st) {
    'APPROVED' => ['label' => 'APPROVED', 'cls' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    'DECLINED' => ['label' => 'DECLINED', 'cls' => 'bg-rose-50 text-rose-700 border-rose-200'],
    default    => ['label' => 'PENDING',  'cls' => 'bg-amber-50 text-amber-700 border-amber-200'],
  };
}

// Actions
$action = (string)($_POST['action'] ?? '');

// Delete
if ($action === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $stmt = $pdo->prepare('SELECT image_path FROM clinic_doctors WHERE id = ? AND clinic_id = ? LIMIT 1');
    $stmt->execute([$id, $clinicId]);
    $img = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM clinic_doctors WHERE id = ? AND clinic_id = ?');
    $stmt->execute([$id, $clinicId]);

    // best-effort delete file
    if ($img && is_string($img) && str_starts_with($img, $baseUrl . '/uploads/doctors/')) {
      $basename = basename($img);
      $fs = $uploadDirFs . DIRECTORY_SEPARATOR . $basename;
      if (is_file($fs)) @unlink($fs);
    }
  }

  flash_set('ok', 'Doctor deleted.');
  header('Location: ' . $baseUrl . '/admin/doctors.php');
  exit;
}

// Add/Update/Reapply (same handler)
if ($action === 'save') {
  $id = (int)($_POST['id'] ?? 0);
  $reapplyFromId = (int)($_POST['reapply_from_id'] ?? 0); // if >0 => reapply same row

  $name = trim((string)($_POST['name'] ?? ''));
  $about = trim((string)($_POST['about'] ?? ''));

  // fields
  $birthdate      = trim((string)($_POST['birthdate'] ?? ''));
  $specialization = trim((string)($_POST['specialization'] ?? ''));
  $prcNo          = trim((string)($_POST['prc_no'] ?? ''));
  $email          = trim((string)($_POST['email'] ?? ''));
  $contactNumber  = trim((string)($_POST['contact_number'] ?? ''));

  // Validation
  $errors = [];

  if ($name === '' || !preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿ'.\- ]{2,190}$/u", $name)) {
    $errors[] = "Doctor name is required and can only contain letters, spaces, and . ' -";
  }

  if ($birthdate === '' || strtotime($birthdate) === false) {
    $errors[] = 'Birthdate is required.';
  } else {
    $b = new DateTime($birthdate);
    $t = new DateTime('today');
    if ($b > $t) $errors[] = 'Birthdate cannot be in the future.';
    if ($t->diff($b)->y < 18) $errors[] = 'Doctor must be at least 18 years old.';
  }

  if ($specialization === '' || !preg_match('/^[A-Za-z0-9À-ÖØ-öø-ÿ&\/().,\- ]{2,120}$/u', $specialization)) {
    $errors[] = 'Specialization contains invalid characters.';
  }

  if ($prcNo === '' || !preg_match('/^[A-Za-z0-9\-\/ ]{3,50}$/', $prcNo)) {
    $errors[] = 'PRC must be 3–50 characters and can include letters/numbers/-/ /.';
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
  }

  if ($contactNumber === '' || !preg_match('/^9\d{9}$/', $contactNumber)) {
    $errors[] = 'Contact number must be 10 digits starting with 9 (e.g., 9123456789).';
  }

  // ---------------------------------------------
  // BUILD WEEKLY SCHEDULE JSON (15,20,30 only)
  // ---------------------------------------------
  $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  $scheduleArr = [];

  $hasAtLeastOneDay = false;

  foreach ($days as $day) {
    $enabled = in_array($day, $_POST['days'] ?? [], true);

    $start = trim((string)($_POST["start_$day"] ?? ''));
    $end   = trim((string)($_POST["end_$day"] ?? ''));
    $interval = (int)($_POST["interval_$day"] ?? 30);

    if (!in_array($interval, [15,20,30], true)) {
      $errors[] = "$day interval must be 15, 20, or 30 minutes.";
    }

    if ($enabled) {
      $hasAtLeastOneDay = true;

      if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
        $errors[] = "$day start time is invalid.";
      }
      if (!preg_match('/^\d{2}:\d{2}$/', $end)) {
        $errors[] = "$day end time is invalid.";
      }

      $s = DateTime::createFromFormat('H:i', $start);
      $e = DateTime::createFromFormat('H:i', $end);

      if (!$s || !$e || $e <= $s) {
        $errors[] = "$day end time must be after start time.";
      }
    }

    $scheduleArr[$day] = [
      'enabled'   => $enabled,
      'start'     => $start,
      'end'       => $end,
      'slot_mins' => $interval,
    ];
  }

  if (!$hasAtLeastOneDay) {
    $errors[] = "Please enable at least one working day.";
  }

  if ($errors) {
    flash_set('err', implode(' ', $errors));
    $redirId = ($id > 0 ? $id : ($reapplyFromId > 0 ? $reapplyFromId : 0));
    header('Location: ' . $baseUrl . '/admin/doctors.php' . ($redirId > 0 ? '?edit=' . $redirId : ''));
    exit;
  }

  $schedule = json_encode($scheduleArr, JSON_UNESCAPED_SLASHES);

  $newImage = save_doctor_image($_FILES['image'] ?? [], $uploadDirFs, $uploadDirWeb);

  // ✅ REAPPLY: update SAME doctor row (only allowed if currently DECLINED)
  if ($reapplyFromId > 0) {
    $stmt = $pdo->prepare("SELECT approval_status FROM clinic_doctors WHERE id=? AND clinic_id=? LIMIT 1");
    $stmt->execute([$reapplyFromId, $clinicId]);
    $curSt = (string)($stmt->fetchColumn() ?: '');

    if ($curSt === '') {
      flash_set('err', 'Invalid reapply request.');
      header('Location: ' . $baseUrl . '/admin/doctors.php');
      exit;
    }

    if (strtoupper($curSt) !== 'DECLINED') {
      flash_set('err', 'You can only reapply a DECLINED doctor.');
      header('Location: ' . $baseUrl . '/admin/doctors.php');
      exit;
    }

    if ($newImage) {
      $stmt = $pdo->prepare("
        UPDATE clinic_doctors
        SET name=?,
            about=?,
            image_path=?,
            birthdate=?,
            specialization=?,
            prc_no=?,
            schedule=?,
            email=?,
            contact_number=?,
            approval_status='PENDING',
            declined_reason=NULL,
            created_via='CMS',
            updated_at=CURRENT_TIMESTAMP
        WHERE id=? AND clinic_id=?
        LIMIT 1
      ");
      $stmt->execute([
        $name, $about, $newImage,
        $birthdate, $specialization, $prcNo, $schedule, $email, $contactNumber,
        $reapplyFromId, $clinicId
      ]);
    } else {
      $stmt = $pdo->prepare("
        UPDATE clinic_doctors
        SET name=?,
            about=?,
            birthdate=?,
            specialization=?,
            prc_no=?,
            schedule=?,
            email=?,
            contact_number=?,
            approval_status='PENDING',
            declined_reason=NULL,
            created_via='CMS',
            updated_at=CURRENT_TIMESTAMP
        WHERE id=? AND clinic_id=?
        LIMIT 1
      ");
      $stmt->execute([
        $name, $about,
        $birthdate, $specialization, $prcNo, $schedule, $email, $contactNumber,
        $reapplyFromId, $clinicId
      ]);
    }

    flash_set('ok', 'Doctor re-applied (updated & sent for approval).');
    header('Location: ' . $baseUrl . '/admin/doctors.php');
    exit;
  }

  // ✅ NORMAL UPDATE (edit existing doctor): update SAME row & set to PENDING
  if ($id > 0) {
    if ($newImage) {
      $stmt = $pdo->prepare("
        UPDATE clinic_doctors
        SET name=?,
            about=?,
            image_path=?,
            birthdate=?,
            specialization=?,
            prc_no=?,
            schedule=?,
            email=?,
            contact_number=?,
            approval_status='PENDING',
            declined_reason=NULL,
            created_via='CMS',
            updated_at=CURRENT_TIMESTAMP
        WHERE id=? AND clinic_id=?
        LIMIT 1
      ");
      $stmt->execute([
        $name, $about, $newImage,
        $birthdate, $specialization, $prcNo, $schedule, $email, $contactNumber,
        $id, $clinicId
      ]);
    } else {
      $stmt = $pdo->prepare("
        UPDATE clinic_doctors
        SET name=?,
            about=?,
            birthdate=?,
            specialization=?,
            prc_no=?,
            schedule=?,
            email=?,
            contact_number=?,
            approval_status='PENDING',
            declined_reason=NULL,
            created_via='CMS',
            updated_at=CURRENT_TIMESTAMP
        WHERE id=? AND clinic_id=?
        LIMIT 1
      ");
      $stmt->execute([
        $name, $about,
        $birthdate, $specialization, $prcNo, $schedule, $email, $contactNumber,
        $id, $clinicId
      ]);
    }

    flash_set('ok', 'Doctor updated (sent for approval).');
    header('Location: ' . $baseUrl . '/admin/doctors.php');
    exit;
  }

  // ✅ NORMAL INSERT (new doctor)
  $stmt = $pdo->prepare("
    INSERT INTO clinic_doctors
      (clinic_id, name, about, image_path,
      birthdate, specialization, prc_no, schedule, email, contact_number,
      approval_status, declined_reason, created_via)
    VALUES
      (?,?,?,?,?,?,?,?,?,?, 
      'PENDING', NULL, 'CMS')
  ");

  $stmt->execute([
    $clinicId,
    $name,
    $about,
    $newImage,
    $birthdate,
    $specialization,
    $prcNo,
    $schedule,
    $email,
    $contactNumber
  ]);


  flash_set('ok', 'Doctor added (pending approval).');
  header('Location: ' . $baseUrl . '/admin/doctors.php');
  exit;
}

// Edit mode
$editId = (int)($_GET['edit'] ?? 0);
$reapplyId = (int)($_GET['reapply'] ?? 0);

$edit = null;
$reapplyFromId = 0;

// Reapply loads SAME doctor row (not new)
if ($reapplyId > 0) {
  $stmt = $pdo->prepare('SELECT * FROM clinic_doctors WHERE id=? AND clinic_id=? LIMIT 1');
  $stmt->execute([$reapplyId, $clinicId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($row) {
    $reapplyFromId = (int)$row['id'];
    $edit = $row;
    flash_set('ok', 'Reapply mode: editing the same doctor record (no new row).');
  }
} elseif ($editId > 0) {
  $stmt = $pdo->prepare('SELECT * FROM clinic_doctors WHERE id=? AND clinic_id=? LIMIT 1');
  $stmt->execute([$editId, $clinicId]);
  $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// List
$stmt = $pdo->prepare('
  SELECT id, name, about, image_path, created_at, updated_at,
         birthdate, specialization, prc_no, schedule, email, contact_number,
         approval_status, declined_reason, created_via
  FROM clinic_doctors
  WHERE clinic_id=?
  ORDER BY id DESC
');
$stmt->execute([$clinicId]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ok  = flash_get('ok');
$err = flash_get('err');

include __DIR__ . '/../includes/partials/head.php';

// Parse schedule JSON for prefilling
function parse_schedule_json(?string $raw): array {
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
$pref = parse_schedule_json($edit['schedule'] ?? '');
?>

<body class="min-h-screen bg-slate-50">

<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold" style="color: var(--secondary);">Doctors</h1>
      <p class="text-slate-600 mt-1">Add and manage the doctors shown on your clinic profile.</p>
    </div>

    <div class="flex gap-2">
      <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
         class="px-5 py-2 rounded-xl font-semibold border border-slate-200 bg-white hover:bg-slate-50">
        Back
      </a>
    </div>
  </div>

  <?php if ($ok): ?>
    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-5 py-4">
      <p class="font-semibold"><?php echo h($ok); ?></p>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 text-red-900 px-5 py-4">
      <p class="font-semibold"><?php echo h($err); ?></p>
    </div>
  <?php endif; ?>

  <!-- Add / Edit Form -->
  <section class="mt-6 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
    <h2 class="text-xl font-extrabold" style="color: var(--secondary);">
      <?php echo $edit ? (($reapplyFromId > 0) ? 'Reapply Doctor' : 'Edit Doctor') : 'Add Doctor'; ?>
    </h2>

    <form method="post" enctype="multipart/form-data" class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>" />
      <input type="hidden" name="reapply_from_id" value="<?php echo (int)$reapplyFromId; ?>" />

      <div class="lg:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Doctor Name</label>
        <input type="text" name="name" required
          value="<?php echo h($edit['name'] ?? ''); ?>"
          class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          placeholder="e.g., Dr. Juan Dela Cruz" />

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700">Birthdate</label>
            <input type="date" name="birthdate" required
              value="<?php echo h($edit['birthdate'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700">Specialization</label>
            <input type="text" name="specialization" required maxlength="120"
              value="<?php echo h($edit['specialization'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
              placeholder="e.g., Pediatrics" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700">PRC</label>
            <input type="text" name="prc_no" required maxlength="50"
              value="<?php echo h($edit['prc_no'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
              placeholder="e.g., 0123456" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700">Email</label>
            <input type="email" name="email" required
              value="<?php echo h($edit['email'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
              placeholder="doctor@email.com" />
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-slate-700">Contact Number</label>
            <div class="mt-2 flex">
              <span class="inline-flex items-center px-4 rounded-l-2xl border border-slate-200 bg-slate-50 text-slate-600 font-semibold">+63</span>
              <input type="text" name="contact_number" required maxlength="10"
                value="<?php echo h($edit['contact_number'] ?? ''); ?>"
                class="w-full h-12 rounded-r-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
                placeholder="9123456789" />
            </div>
          </div>

          <div class="md:col-span-2">
            <div class="mt-4">
              <label class="block text-sm font-semibold text-slate-700">Description / About</label>
              <textarea name="about" rows="6"
                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
                placeholder="Short description about the doctor..."><?php echo h($edit['about'] ?? ''); ?></textarea>
            </div>
          </div>

          <!-- ✅ NEW Weekly Schedule UI (keeps your look) -->
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-slate-700 mb-3">Weekly Schedule</label>

            <div class="space-y-3">
              <?php
              $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
              foreach ($days as $day):
                $row = $pref[$day] ?? [];
                $enabled = (bool)($row['enabled'] ?? false);
                $start = (string)($row['start'] ?? '');
                $end = (string)($row['end'] ?? '');
                $slot = (int)($row['slot_mins'] ?? 30);
                if (!in_array($slot, [15,20,30], true)) $slot = 30;
              ?>
                <?php
                $row = $pref[$day] ?? [];
                $enabled = (bool)($row['enabled'] ?? false);
                $start = (string)($row['start'] ?? '');
                $end = (string)($row['end'] ?? '');
                $slot = (int)($row['slot_mins'] ?? 30);
                if (!in_array($slot, [15,20,30], true)) $slot = 30;

                $wrapId = "row_$day";
                $chkId  = "chk_$day";
              ?>
              <div id="<?php echo $wrapId; ?>"
                  class="schedule-row flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 transition-all duration-200">
                
                <!-- Day Checkbox -->
                <label class="flex items-center gap-2 w-24 font-semibold text-slate-700">
                  <input id="<?php echo $chkId; ?>"
                        type="checkbox"
                        name="days[]"
                        value="<?php echo $day; ?>"
                        class="day-toggle h-4 w-4 rounded border-slate-300"
                        <?php echo $enabled ? 'checked' : ''; ?>
                        data-target="<?php echo $wrapId; ?>">
                  <?php echo $day; ?>
                </label>

                <!-- Start -->
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold text-slate-500">Start</span>
                  <input type="time"
                        name="start_<?php echo $day; ?>"
                        value="<?php echo h($start); ?>"
                        class="time-field h-10 w-[130px] rounded-xl border border-slate-200 px-3 bg-white text-sm cursor-pointer
                                focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
                        onclick="this.showPicker && this.showPicker();"
                        <?php echo $enabled ? '' : 'disabled'; ?>>
                </div>

                <!-- End -->
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold text-slate-500">End</span>
                  <input type="time"
                        name="end_<?php echo $day; ?>"
                        value="<?php echo h($end); ?>"
                        class="time-field h-10 w-[130px] rounded-xl border border-slate-200 px-3 bg-white text-sm cursor-pointer
                                focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
                        onclick="this.showPicker && this.showPicker();"
                        <?php echo $enabled ? '' : 'disabled'; ?>>
                </div>

                <!-- Interval -->
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold text-slate-500">Interval</span>
                  <select name="interval_<?php echo $day; ?>"
                          class="time-field h-10 rounded-xl border border-slate-200 px-3 bg-white text-sm
                                focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
                          <?php echo $enabled ? '' : 'disabled'; ?>>
                    <option value="15" <?php echo $slot===15?'selected':''; ?>>15 mins</option>
                    <option value="20" <?php echo $slot===20?'selected':''; ?>>20 mins</option>
                    <option value="30" <?php echo $slot===30?'selected':''; ?>>30 mins</option>
                  </select>
                </div>

              </div>

              <?php endforeach; ?>
            </div>

            <p class="mt-3 text-xs text-slate-500">Only checked days will be considered active.</p>
          </div>

        </div>

      </div>

      <div class="rounded-3xl border border-slate-200 p-5">
        <label class="block text-sm font-semibold text-slate-700">Doctor Image</label>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp"
          class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold hover:file:bg-slate-200" />

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3 flex items-center justify-center min-h-[160px]">
          <?php $preview = $edit['image_path'] ?? null; ?>
          <?php if ($preview): ?>
            <img src="<?php echo h($preview); ?>" alt="Doctor" class="max-h-[150px] rounded-xl object-cover" />
          <?php else: ?>
            <span class="text-slate-400 text-sm">No image</span>
          <?php endif; ?>
        </div>

        <button type="submit"
          class="mt-5 w-full py-3 rounded-2xl font-extrabold text-white"
          style="background: var(--primary);">
          <?php echo $edit ? (($reapplyFromId > 0) ? 'Submit Reapply' : 'Save Changes') : 'Add Doctor'; ?>
        </button>

        <?php if ($edit): ?>
          <a href="<?php echo $baseUrl; ?>/admin/doctors.php"
             class="mt-3 block w-full text-center py-3 rounded-2xl font-bold border border-slate-200 bg-white hover:bg-slate-50">
            Cancel
          </a>
        <?php endif; ?>

        <p class="mt-4 text-xs text-slate-500">Allowed: JPG, PNG, WEBP. Max 2MB.</p>
      </div>
    </form>
  </section>

  <!-- List -->
  <section class="mt-6 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">
    <h2 class="text-xl font-extrabold" style="color: var(--secondary);">Your Doctors</h2>

    <?php if (empty($doctors)): ?>
      <p class="mt-3 text-sm text-slate-600">No doctors yet. Add your first doctor above.</p>
    <?php else: ?>
      <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($doctors as $d): ?>
          <?php
            $st = (string)($d['approval_status'] ?? 'PENDING');
            $badge = status_badge($st);
            $declinedReason = trim((string)($d['declined_reason'] ?? ''));
            $createdVia = (string)($d['created_via'] ?? '');
            $isDeclined = (strtoupper($st) === 'DECLINED');
          ?>

          <div class="rounded-3xl border border-slate-200 overflow-hidden">
            <div class="flex gap-4 p-4">
              <div class="h-16 w-16 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                <?php if (!empty($d['image_path'])): ?>
                  <img src="<?php echo h($d['image_path']); ?>" alt="Doctor" class="h-full w-full object-cover" />
                <?php else: ?>
                  <img src="<?php echo $baseUrl; ?>/assets/img/doctor1.png" alt="Doctor" class="h-10 w-10 opacity-80" />
                <?php endif; ?>
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="font-extrabold text-slate-900 truncate"><?php echo h($d['name']); ?></p>
                    <p class="text-sm text-slate-600 line-clamp-2 mt-1"><?php echo h($d['about'] ?? ''); ?></p>
                  </div>

                  <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold border <?php echo h($badge['cls']); ?>">
                    <?php echo h($badge['label']); ?>
                  </span>
                </div>

                <?php if ($isDeclined): ?>
                  <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <p class="text-xs font-bold text-rose-700">Declined</p>
                    <p class="text-xs text-rose-700 mt-1">
                      Reason: <?php echo $declinedReason !== '' ? h($declinedReason) : 'No reason provided.'; ?>
                    </p>
                  </div>
                <?php endif; ?>

                <?php if (!empty($createdVia)): ?>
                  <p class="text-[11px] text-slate-400 mt-2">Source: <?php echo h($createdVia); ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-slate-200 bg-slate-50">
              <?php if ($isDeclined): ?>
                <a href="<?php echo $baseUrl; ?>/admin/doctors.php?reapply=<?php echo (int)$d['id']; ?>"
                   class="px-4 py-2 rounded-xl font-semibold text-white"
                   style="background: var(--primary);">
                  Reapply (CMS)
                </a>
              <?php else: ?>
                <a href="<?php echo $baseUrl; ?>/admin/doctors.php?edit=<?php echo (int)$d['id']; ?>"
                   class="px-4 py-2 rounded-xl font-semibold text-white"
                   style="background: var(--secondary);">
                  Edit
                </a>
              <?php endif; ?>

              <form method="post" onsubmit="return confirm('Delete this doctor?');">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>" />
                <button type="submit"
                  class="px-4 py-2 rounded-xl font-semibold text-white"
                  style="background: #ef4444;">
                  Delete
                </button>
              </form>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<script>
  (function () {
    function updateRow(rowEl, enabled) {
      if (!rowEl) return;

      const fields = rowEl.querySelectorAll('.time-field');
      fields.forEach(f => {
        f.disabled = !enabled;
      });

      // animation + greyed out
      rowEl.classList.toggle('is-disabled', !enabled);

      // subtle “pop” when enabling
      if (enabled) {
        rowEl.classList.add('ring-2', 'ring-[var(--secondary)]/20');
        setTimeout(() => rowEl.classList.remove('ring-2', 'ring-[var(--secondary)]/20'), 180);
      }
    }

    // init all rows on load
    document.querySelectorAll('.day-toggle').forEach(chk => {
      const rowId = chk.getAttribute('data-target');
      const rowEl = document.getElementById(rowId);
      updateRow(rowEl, chk.checked);

      chk.addEventListener('change', () => {
        updateRow(rowEl, chk.checked);
      });
    });
  })();
</script>


</body>
</html>
