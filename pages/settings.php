<?php
declare(strict_types=1);

$appTitle = 'AKAS | Settings';
$baseUrl  = '/AKAS';
require_once __DIR__ . '/../includes/auth.php';

auth_require_role('user', $baseUrl);

$pdo = db();
$stmt = $pdo->prepare('SELECT name, gender, email, phone, birthdate FROM accounts WHERE id = ? LIMIT 1');
$stmt->execute([auth_user_id()]);
$me = $stmt->fetch() ?: [];

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
      class="grid gap-4">

  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Full Name</label>
    <input
      id="name"
      name="name"
      value="<?php echo htmlspecialchars((string)($me['name'] ?? '')); ?>"
      required
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
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
        <option value="" disabled hidden <?= empty($me['gender']) ? 'selected' : '' ?>>
          Select a Gender
        </option>

        <option value="Male" <?= ($me['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= ($me['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
        <option value="Prefer not to say" <?= ($me['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>
          Prefer not to say
        </option>
      </select>
      <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M6 9l6 6 6-6"/>
        </svg>
      </div>
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
  <div>
    <label class="block text-xs font-bold text-slate-600 mb-2">Phone Number</label>

    <div class="flex items-center w-full rounded-xl border border-slate-200 overflow-hidden focus-within:ring-2 focus-within:ring-slate-200">
      <span class="px-4 py-4 bg-slate-100 text-slate-600 text-sm font-semibold border-r border-slate-200 select-none">
        +63
      </span>

      <input
        type="tel"
        id="phone"
        name="phone"
        value="<?php echo htmlspecialchars((string)($me['phone'] ?? '')); ?>"
        maxlength="10"
        inputmode="numeric"
        placeholder="9XXXXXXXXX"
        data-validate="phone-ph"
        required
        class="flex-1 px-4 py-3 outline-none"
      />
    </div>
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
    <input
      type="password"
      id="new_password"
      name="new_password"
      placeholder="New password (8+ chars, 1 uppercase, 1 special)"
      data-validate="password-optional"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />
    <input
      type="password"
      id="confirm_password"
      name="confirm_password"
      placeholder="Confirm new password"
      data-validate="password-confirm"
      data-match="new_password"
      class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:ring-2 focus:ring-slate-200"
    />

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
      style="background: var(--secondary);">
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
  fields.forEach(el => initial[el.id] = el.value);

  const update = () => {
    const changed = fields.some(el => el.value !== initial[el.id]);

    saveBtn.disabled = !changed;
    saveBtn.classList.toggle("opacity-50", !changed);
    saveBtn.classList.toggle("cursor-not-allowed", !changed);
  };

  fields.forEach(el => {
    el.addEventListener("input", update);
    el.addEventListener("change", update);
  });
});
</script>

</body>
</html>
