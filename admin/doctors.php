<?php
declare(strict_types=1);

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

// Add/Update
if ($action === 'save') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim((string)($_POST['name'] ?? ''));
  $about = trim((string)($_POST['about'] ?? ''));
  $availability = trim((string)($_POST['availability'] ?? ''));

  if ($name === '') {
    flash_set('err', 'Doctor name is required.');
    header('Location: ' . $baseUrl . '/admin/doctors.php');
    exit;
  }

  $newImage = save_doctor_image($_FILES['image'] ?? [], $uploadDirFs, $uploadDirWeb);

  if ($id > 0) {
    // Update
    if ($newImage) {
      $stmt = $pdo->prepare('UPDATE clinic_doctors SET name=?, about=?, availability=?, image_path=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND clinic_id=?');
      $stmt->execute([$name, $about, $availability, $newImage, $id, $clinicId]);
    } else {
      $stmt = $pdo->prepare('UPDATE clinic_doctors SET name=?, about=?, availability=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND clinic_id=?');
      $stmt->execute([$name, $about, $availability, $id, $clinicId]);
    }
    flash_set('ok', 'Doctor updated.');
  } else {
    // Insert
    $stmt = $pdo->prepare('INSERT INTO clinic_doctors (clinic_id, name, about, availability, image_path) VALUES (?,?,?,?,?)');
    $stmt->execute([$clinicId, $name, $about, $availability, $newImage]);
    flash_set('ok', 'Doctor added.');
  }

  header('Location: ' . $baseUrl . '/admin/doctors.php');
  exit;
}

// Edit mode
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
  $stmt = $pdo->prepare('SELECT * FROM clinic_doctors WHERE id=? AND clinic_id=? LIMIT 1');
  $stmt->execute([$editId, $clinicId]);
  $edit = $stmt->fetch() ?: null;
}

// List
$stmt = $pdo->prepare('SELECT id, name, about, availability, image_path, created_at FROM clinic_doctors WHERE clinic_id=? ORDER BY id DESC');
$stmt->execute([$clinicId]);
$doctors = $stmt->fetchAll();

$ok  = flash_get('ok');
$err = flash_get('err');

include __DIR__ . '/../includes/partials/head.php';
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
      <?php echo $edit ? 'Edit Doctor' : 'Add Doctor'; ?>
    </h2>

    <form method="post" enctype="multipart/form-data" class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>" />

      <div class="lg:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Doctor Name</label>
        <input
          type="text"
          name="name"
          required
          value="<?php echo h($edit['name'] ?? ''); ?>"
          class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          placeholder="e.g., Dr. Juan Dela Cruz"
        />

        <div class="mt-4">
          <label class="block text-sm font-semibold text-slate-700">Description / About</label>
          <textarea
            name="about"
            rows="6"
            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
            placeholder="Short description about the doctor..."
          ><?php echo h($edit['about'] ?? ''); ?></textarea>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-semibold text-slate-700">Availability</label>
          <textarea
            name="availability"
            rows="4"
            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
            placeholder="Example:\nMon-Fri: 9:00 AM - 4:00 PM\nSat: 9:00 AM - 12:00 PM"
          ><?php echo h($edit['availability'] ?? ''); ?></textarea>
          <p class="mt-2 text-xs text-slate-500">Tip: Use one line per schedule to keep it readable.</p>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-200 p-5">
        <label class="block text-sm font-semibold text-slate-700">Doctor Image</label>
        <input
          type="file"
          name="image"
          accept="image/png,image/jpeg,image/webp"
          class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold hover:file:bg-slate-200"
        />

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3 flex items-center justify-center min-h-[160px]">
          <?php $preview = $edit['image_path'] ?? null; ?>
          <?php if ($preview): ?>
            <img src="<?php echo h($preview); ?>" alt="Doctor" class="max-h-[150px] rounded-xl object-cover" />
          <?php else: ?>
            <span class="text-slate-400 text-sm">No image</span>
          <?php endif; ?>
        </div>

        <button
          type="submit"
          class="mt-5 w-full py-3 rounded-2xl font-extrabold text-white"
          style="background: var(--primary);"
        >
          <?php echo $edit ? 'Save Changes' : 'Add Doctor'; ?>
        </button>

        <?php if ($edit): ?>
          <a
            href="<?php echo $baseUrl; ?>/admin/doctors.php"
            class="mt-3 block w-full text-center py-3 rounded-2xl font-bold border border-slate-200 bg-white hover:bg-slate-50"
          >
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
                <p class="font-extrabold text-slate-900 truncate"><?php echo h($d['name']); ?></p>
                <p class="text-sm text-slate-600 line-clamp-2 mt-1"><?php echo h($d['about'] ?? ''); ?></p>
                <?php if (!empty($d['availability'])): ?>
                  <p class="text-xs text-slate-500 mt-2 line-clamp-2">🗓 <?php echo h($d['availability']); ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-slate-200 bg-slate-50">
              <a
                href="<?php echo $baseUrl; ?>/admin/doctors.php?edit=<?php echo (int)$d['id']; ?>"
                class="px-4 py-2 rounded-xl font-semibold text-white"
                style="background: var(--secondary);"
              >
                Edit
              </a>

              <form method="post" onsubmit="return confirm('Delete this doctor?');">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>" />
                <button
                  type="submit"
                  class="px-4 py-2 rounded-xl font-semibold text-white"
                  style="background: #ef4444;"
                >
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

</body>
</html>
