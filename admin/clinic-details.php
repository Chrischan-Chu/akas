<?php
declare(strict_types=1);
require_once __DIR__ . '/_guard.php';

$appTitle = 'AKAS | Clinic Details';
$baseUrl  = '/AKAS';

require_once __DIR__ . '/../includes/auth.php';
auth_require_role('clinic_admin', $baseUrl);

$pdo = db();
$clinicId = (int)(auth_clinic_id() ?? 0);
if ($clinicId <= 0) {
  flash_set('error', 'This admin account is not linked to a clinic.');
  header('Location: ' . $baseUrl . '/admin/dashboard.php');
  exit;
}

// --- Logo upload config ---
$logoDirFs  = __DIR__ . '/../uploads/logos';
$logoDirWeb = $baseUrl . '/uploads/logos';

if (!is_dir($logoDirFs)) {
  @mkdir($logoDirFs, 0777, true);
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function save_clinic_logo(array $file, string $logoDirFs, string $logoDirWeb): ?string {
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

  $name = 'logo_' . bin2hex(random_bytes(12)) . '.' . $ext;
  $dest = rtrim($logoDirFs, '/\\') . DIRECTORY_SEPARATOR . $name;

  if (!move_uploaded_file($tmp, $dest)) return null;

  return rtrim($logoDirWeb, '/') . '/' . $name;
}

// Load existing clinic
$stmt = $pdo->prepare('SELECT * FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$row = $stmt->fetch() ?: [];

// Current logo from clinics
$currentLogo = (string)($row['logo_path'] ?? '');

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

 // --- Clinic basic fields ---
$clinicName = trim((string)($_POST['clinic_name'] ?? ''));
$specialty  = trim((string)($_POST['specialty'] ?? ''));
$specialtyOther = trim((string)($_POST['specialty_other'] ?? ''));

if ($clinicName === '' || $specialty === '') {
  flash_set('error', 'Clinic Name and Type of Clinic are required.');
  header('Location: ' . $baseUrl . '/admin/clinic-details.php');
  exit;
}

// Only keep specialty_other if specialty = Other
if ($specialty !== 'Other') {
  $specialtyOther = '';
}

// If Other is selected, require the text
if ($specialty === 'Other' && $specialtyOther === '') {
  flash_set('error', 'Please specify your clinic type.');
  header('Location: ' . $baseUrl . '/admin/clinic-details.php');
  exit;
}

  // --- clinics fields ---
  $description = trim((string)($_POST['description'] ?? ''));
  $address     = trim((string)($_POST['address'] ?? ''));

  // contact required, store digits only (10 digits starting with 9)
  $contactRaw = (string)($_POST['contact'] ?? '');
  $contact    = preg_replace('/\D/', '', $contactRaw) ?? '';

  if (!preg_match('/^9\d{9}$/', $contact)) {
    flash_set('error', 'Contact must be a valid PH mobile number (10 digits, starts with 9).');
    header('Location: ' . $baseUrl . '/admin/clinic-details.php');
    exit;
  }

  // email optional
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address.');
    header('Location: ' . $baseUrl . '/admin/clinic-details.php');
    exit;
  }

  $isOpen    = ((int)($_POST['is_open'] ?? 0) === 1) ? 1 : 0;
  $openTime  = (string)($_POST['open_time'] ?? '');
  $closeTime = (string)($_POST['close_time'] ?? '');

  // basic time validation (HH:MM) -> store HH:MM:SS or NULL
  $openTime  = preg_match('/^\d{2}:\d{2}$/', $openTime) ? ($openTime . ':00') : null;
  $closeTime = preg_match('/^\d{2}:\d{2}$/', $closeTime) ? ($closeTime . ':00') : null;

  // Update clinic record
  $sql = "
    UPDATE clinics
       SET clinic_name     = :clinic_name,
           specialty       = :specialty,
           specialty_other = :specialty_other,
           description     = :description,
           address         = :address,
           contact         = :contact,
           email           = :email,
           is_open         = :is_open,
           open_time       = :open_time,
           close_time      = :close_time,
           updated_at      = CURRENT_TIMESTAMP
     WHERE id = :id
     LIMIT 1
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':id' => $clinicId,
    ':clinic_name' => $clinicName,
    ':specialty' => $specialty,
    ':specialty_other' => ($specialtyOther !== '' ? $specialtyOther : null),
    ':description' => $description,
    ':address' => $address,
    ':contact' => $contact,
    ':email' => ($email !== '' ? $email : null),
    ':is_open' => $isOpen,
    ':open_time' => $openTime,
    ':close_time' => $closeTime,
  ]);

  // Handle logo upload (optional) -> clinics.logo_path
  $newLogo = save_clinic_logo($_FILES['logo'] ?? [], $logoDirFs, $logoDirWeb);

  if ($newLogo) {
    // Delete old logo file (best effort)
    if (!empty($currentLogo) && str_starts_with($currentLogo, $baseUrl . '/uploads/logos/')) {
      $oldFile = $logoDirFs . DIRECTORY_SEPARATOR . basename($currentLogo);
      if (is_file($oldFile)) { @unlink($oldFile); }
    }

    $stmt = $pdo->prepare('UPDATE clinics SET logo_path = ? WHERE id = ?');
    $stmt->execute([$newLogo, $clinicId]);
  }

  flash_set('ok', 'Clinic details saved.');
  header('Location: ' . $baseUrl . '/admin/clinic-details.php');
  exit;
}

$ok = flash_get('ok');
$error = flash_get('error');

$stmt = $pdo->prepare('SELECT * FROM clinics WHERE id = ? LIMIT 1');
$stmt->execute([$clinicId]);
$row = $stmt->fetch() ?: [];

$currentLogo = (string)($row['logo_path'] ?? '');

include __DIR__ . '/../includes/partials/head.php';
?>

<body class="min-h-screen bg-slate-50">

<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl sm:text-4xl font-extrabold" style="color: var(--secondary);">Clinic Details</h1>
      <p class="text-slate-600 mt-1">Update your clinic information shown on the public profile.</p>
    </div>

    <div class="flex gap-2">
      <a href="<?php echo $baseUrl; ?>/admin/dashboard.php"
         class="px-5 py-2 rounded-xl font-semibold border border-slate-200 bg-white hover:bg-slate-50">
        Back
      </a>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-5 py-4">
      <p class="font-semibold"><?php echo h($ok); ?></p>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 text-red-900 px-5 py-4">
      <p class="font-semibold"><?php echo h($error); ?></p>
    </div>
  <?php endif; ?>

  <section class="mt-6 rounded-3xl bg-white shadow-sm border border-slate-200 p-6">

    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Clinic Basic Info (FULL WIDTH) -->
      <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Clinic Name -->
        <div>
          <label class="block text-sm font-semibold text-slate-700">Clinic Name</label>
          <input
            type="text"
            name="clinic_name"
            required
            value="<?php echo h($row['clinic_name'] ?? ''); ?>"
            class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          />
        </div>

        <!-- Type of Clinic -->
        <div>
          <label class="block text-sm font-semibold text-slate-700">Type of Clinic</label>
          <select
            name="specialty"
            id="specialtySelect"
            required
            data-required-msg="Please select a Clinic Type."
            class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          >
            <option value="" disabled hidden <?php echo empty($row['specialty']) ? 'selected' : ''; ?>>
              Select Clinic Type
            </option>

            <?php
              // ✅ MUST MATCH signup-admin.php values EXACTLY
              $types = [
                "Optometry Clinic",
                "Family Clinic",
                "Dental Clinic",
                "Veterinary Clinic",
                "Pediatric Clinic",
                "Dermatology Clinic",
                "Other"
              ];

              $currentType = (string)($row['specialty'] ?? '');
              foreach ($types as $t):
            ?>
              <option value="<?php echo h($t); ?>" <?php echo ($currentType === $t) ? 'selected' : ''; ?>>
                <?php echo h($t); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Other (only when "Other" is selected) -->
          <div id="otherSpecialtyWrap" class="mt-3 <?php echo (($row['specialty'] ?? '') === 'Other') ? '' : 'hidden'; ?>">
            <input
              type="text"
              name="specialty_other"
              value="<?php echo h($row['specialty_other'] ?? ''); ?>"
              placeholder="Please specify your clinic type"
              class="w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
            />
          </div>
        </div>


      </div>

      <!-- LEFT (2 cols) -->
      <div class="lg:col-span-2">
        <label class="block text-sm font-semibold text-slate-700">Description</label>
        <textarea
          name="description"
          rows="8"
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40 resize-none"
          placeholder="Tell patients about your clinic, services, and what to expect..."
        ><?php echo h($row['description'] ?? ''); ?></textarea>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-slate-700">Address</label>
            <input
              type="text"
              name="address"
              value="<?php echo h($row['address'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
              placeholder="e.g., Angeles City, Pampanga"
            />
          </div>
<div>
  <label class="block text-sm font-semibold text-slate-700">Contact</label>

  <div class="mt-2 flex gap-2">
    <span class="h-12 px-4 flex items-center justify-center rounded-2xl bg-slate-100 text-slate-700 font-semibold select-none">
      +63
    </span>

    <input
      id="contactInput"
      type="text"
      name="contact"
      value="<?php echo h($row['contact'] ?? ''); ?>"
      data-validate="phone-ph"
      data-ui="pill"  
      inputmode="numeric"
      maxlength="10"
      required
      placeholder="9XXXXXXXXX"
      class="flex-1 h-12 px-4 rounded-2xl border border-slate-200 bg-white outline-none
             focus:ring-2 focus:ring-[var(--secondary)]/40 transition"
    />
  </div>
</div>


          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-slate-700">Email (Optional)</label>
            <input
              type="email"
              name="email"
              value="<?php echo h($row['email'] ?? ''); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
              placeholder="clinic@gmail.com"
            />
          </div>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="rounded-3xl border border-slate-200 p-5 self-start">
        <h2 class="text-lg font-extrabold" style="color: var(--secondary);">Clinic Info</h2>
        <p class="text-sm text-slate-600 mt-1">Set your current status and hours.</p>

        <!-- Logo Upload -->
        <div class="mt-5">
          <label class="block text-sm font-semibold text-slate-700">Clinic Logo</label>

          <div class="mt-2 flex items-center gap-4">
            <div class="h-16 w-16 rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
              <?php if (!empty($currentLogo)): ?>
                <img src="<?php echo h($currentLogo); ?>" alt="Clinic Logo" class="h-full w-full object-cover" />
              <?php else: ?>
                <span class="text-slate-400 text-xs font-semibold">No Logo</span>
              <?php endif; ?>
            </div>

            <div class="flex-1">
              <input
                type="file"
                name="logo"
                accept="image/png,image/jpeg,image/webp"
                class="block w-full text-sm text-slate-700
                       file:mr-4 file:py-2 file:px-4
                       file:rounded-xl file:border-0
                       file:text-sm file:font-semibold
                       file:bg-slate-100 file:text-slate-800
                       hover:file:bg-slate-200"
              />
              <p class="text-xs text-slate-500 mt-1">PNG/JPG/WEBP only (max 2MB).</p>
            </div>
          </div>
        </div>

        <div class="mt-5">
          <label class="block text-sm font-semibold text-slate-700">Status</label>
          <select
            name="is_open"
            class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
          >
            <option value="1" <?php echo ((int)($row['is_open'] ?? 1) === 1) ? 'selected' : ''; ?>>Open</option>
            <option value="0" <?php echo ((int)($row['is_open'] ?? 1) === 0) ? 'selected' : ''; ?>>Closed</option>
          </select>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-slate-700">Open Time</label>
            <input
              type="time"
              name="open_time"
              value="<?php echo h(isset($row['open_time']) ? substr((string)$row['open_time'], 0, 5) : '09:00'); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
            />
          </div>
          <div>
            <label class="block text-sm font-semibold text-slate-700">Close Time</label>
            <input
              type="time"
              name="close_time"
              value="<?php echo h(isset($row['close_time']) ? substr((string)$row['close_time'], 0, 5) : '17:00'); ?>"
              class="mt-2 w-full h-12 rounded-2xl border border-slate-200 bg-white px-4 text-slate-800 focus:outline-none focus:ring-2 focus:ring-[var(--secondary)]/40"
            />
          </div>
        </div>

        <button
          type="submit"
          class="mt-6 w-full py-3 rounded-2xl font-extrabold text-white"
          style="background: var(--primary);"
        >
          Save
        </button>
      </div>

    </form>

  </section>

</main>

<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js" defer></script>

<script>
  (function () {
    const sel = document.getElementById('specialtySelect');
    const wrap = document.getElementById('otherSpecialtyWrap');
    const input = wrap ? wrap.querySelector('input[name="specialty_other"]') : null;

    function toggle() {
      const isOther = (sel && sel.value === 'Other');
      if (!wrap || !input) return;
      if (isOther) {
        wrap.classList.remove('hidden');
        input.required = true;
      } else {
        wrap.classList.add('hidden');
        input.required = false;
        input.value = '';
      }
    }

    sel && sel.addEventListener('change', toggle);
    toggle();
  })();
</script>
</body>
</html>
