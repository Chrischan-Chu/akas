<?php
declare(strict_types=1);

$appTitle = 'AKAS | Settings';
$baseUrl  = '';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('user', $baseUrl);

$pdo = db();
$stmt = $pdo->prepare('SELECT name, gender, email, phone, birthdate FROM accounts WHERE id = ? LIMIT 1');
$stmt->execute([auth_user_id()]);
$me = $stmt->fetch() ?: [];

// Inline errors + old values (set by settings-process.php)
$settings_errors = $_SESSION['settings_errors'] ?? [];
$settings_old = $_SESSION['settings_old'] ?? [];
unset($_SESSION['settings_errors'], $_SESSION['settings_old']);

$val = function (string $key, string $fallback = '') use ($settings_old, $me): string {
  if (array_key_exists($key, $settings_old)) return (string)$settings_old[$key];
  return isset($me[$key]) ? (string)$me[$key] : $fallback;
};

$err = function (string $key) use ($settings_errors): string {
  return isset($settings_errors[$key]) ? (string)$settings_errors[$key] : '';
};

$successMsg = flash_get('success');
$errorMsg = flash_get('error');

include "../includes/partials/head.php";
?>

<body class="bg-white">

<?php include "../includes/partials/navbar.php"; ?>

<main class="max-w-4xl mx-auto px-4 py-10">
  <h1 class="text-3xl sm:text-4xl font-extrabold" style="color: var(--secondary);">Settings</h1>
  <p class="text-slate-600 mt-1">Update your profile details.</p>

  <?php if ($successMsg): ?>
    <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-green-800 text-sm">
      <?php echo htmlspecialchars($successMsg); ?>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-800 text-sm">
      <?php echo htmlspecialchars($errorMsg); ?>
    </div>
  <?php endif; ?>

  <section class="mt-6 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
    <form id="settingsForm"
      action="<?php echo $baseUrl; ?>/pages/settings-process.php"
      method="POST"
      class="grid gap-4"
      data-inline-errors="1"
      data-loading-text="Saving changes..."
      novalidate>

  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Full Name</label>
    <input
      id="name"
      name="name"
	      value="<?php echo htmlspecialchars($val('name')); ?>"
      required
	      data-validate="full-name"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
	    <p class="mt-1 text-sm text-red-600" data-err-for="name"><?php echo htmlspecialchars($err('name')); ?></p>
  </div>
  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Gender</label>

    <div class="relative">
      <select
        id="gender"
        name="gender"
        required
        class="appearance-none w-full rounded-xl bg-white px-4 pr-12 py-3
              text-slate-700 outline-none border border-slate-200
              focus:ring-2 focus:ring-slate-200">
	        <option value="" disabled hidden <?= empty($val('gender')) ? 'selected' : '' ?>>
          Select a Gender
        </option>

	        <option value="Male" <?= ($val('gender') ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
	        <option value="Female" <?= ($val('gender') ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
	        <option value="Prefer not to say" <?= ($val('gender') ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>
          Prefer not to say
        </option>
      </select>
      <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M6 9l6 6 6-6"/>
        </svg>
	    </div>
	    <p class="mt-1 text-sm text-red-600" data-err-for="gender"><?php echo htmlspecialchars($err('gender')); ?></p>
    </div>
  </div>
  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Email (Read-Only)</label>
    <input
      id="email"
      value="<?php echo htmlspecialchars((string)($me['email'] ?? '')); ?>"
      readonly
      class="w-full rounded-xl border border-slate-200 px-4 py-3 bg-slate-50 text-slate-600"
    />
  </div>
  <div class="grid gap-4 sm:grid-cols-2">
 <div>
  <label class="block text-xs font-bold text-slate-600 mb-2">
    Phone Number
  </label>

  <div class="flex w-full">
    
    <!-- +63 -->
    <span
      class="px-4 py-3 bg-slate-100 text-slate-600 text-sm font-semibold border border-r-0 border-slate-200 rounded-l-xl select-none">
      +63
    </span>

    <!-- Input -->
    <input
      type="tel"
      id="phone"
      name="phone"
      value="<?php echo htmlspecialchars((string)($me['phone'] ?? '')); ?>"
      maxlength="10"
      inputmode="numeric"
      placeholder="9XXXXXXXXX"
      data-validate="phone-ph"
      data-unique="accounts_phone"
      data-unique-original="<?php echo htmlspecialchars((string)($me['phone'] ?? '')); ?>"
      required
      class="flex-1 px-4 py-3 border border-slate-200 rounded-r-xl outline-none focus:ring-2 focus:ring-slate-200"
    />

  </div>

  <p data-err-for="phone" class="mt-2 text-sm text-red-600"></p>
</div>
  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Birthdate</label>

    <input
      type="date"
      id="birthdate"
      name="birthdate"
      value="<?php echo htmlspecialchars(!empty($me['birthdate']) ? date('Y-m-d', strtotime($me['birthdate'])) : ''); ?>"
      data-validate="age-18"
      required
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
    <p data-err-for="birthdate" class="mt-2 text-sm text-red-600"></p>
  </div>
  </div>
  <div class="rounded-2xl border border-slate-200 p-4">
  <div class="font-bold text-slate-900">Change Password (Optional)</div>

  <div class="grid gap-3 mt-3">
    <input
      type="password"
      id="current_password"
      name="current_password"
      placeholder="Current password"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
    <p data-err-for="current_password" class="text-sm text-red-600"></p>
    <input
      type="password"
      id="new_password"
      name="new_password"
      placeholder="New password (8+ chars, 1 uppercase, 1 special)"
      data-validate="password-optional"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
    <p data-err-for="new_password" class="text-sm text-red-600"></p>
    <input
      type="password"
      id="confirm_password"
      name="confirm_password"
      placeholder="Confirm new password"
      data-validate="password-confirm"
      data-match="new_password"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
    <p data-err-for="confirm_password" class="text-sm text-red-600"></p>

  </div>
</div>
  <div class="flex flex-col sm:flex-row gap-3 mt-2">
    <a href="<?php echo $baseUrl; ?>/index.php#home"
       class="flex-1 text-center py-3 rounded-xl font-bold text-slate-900"
       style="background: var(--accent);">Back</a>

    <button
      id="saveBtn"
      type="submit"
      disabled
      class="flex-1 py-3 rounded-xl font-bold text-white opacity-50 cursor-not-allowed"
      style="background: var(--secondary);"
      data-original-text="Save Changes"
      data-loading-text="Saving changes...">
      Save Changes
    </button>
  </div>

</form>

  </section>
</main>
<script src="<?php echo $baseUrl; ?>/assets/js/form-validators.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("settingsForm");
  const saveBtn = document.getElementById("saveBtn");
  const currentPw = document.getElementById("current_password");
  const newPw = document.getElementById("new_password");
  const confirmPw = document.getElementById("confirm_password");

  if (!form || !saveBtn) return;

  const fields = [
    "name",
    "gender",
    "phone",
    "birthdate",
    "new_password",
    "confirm_password"
  ].map(id => document.getElementById(id));

  const initial = {};
  fields.filter(Boolean).forEach(el => initial[el.id] = el.value);

  const togglePwRequired = () => {
    const hasNew = !!(newPw && String(newPw.value || "").trim().length);
    if (currentPw) currentPw.required = hasNew;
    if (confirmPw) confirmPw.required = hasNew;
  };

  const update = () => {
    togglePwRequired();
    const changed = fields.filter(Boolean).some(el => el.value !== initial[el.id]);

    saveBtn.disabled = !changed;
    saveBtn.classList.toggle("opacity-50", !changed);
    saveBtn.classList.toggle("cursor-not-allowed", !changed);
  };

  fields.filter(Boolean).forEach(el => {
    el.addEventListener("input", update);
    el.addEventListener("change", update);
  });

  newPw?.addEventListener("input", update);
  confirmPw?.addEventListener("input", update);

  togglePwRequired();
  update();
});
</script>

</body>
</html>
