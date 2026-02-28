<?php
// pages/contact.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

// Prefill for logged-in PATIENT USER only
$prefillName  = '';
$prefillEmail = '';
$isUserEmailReadonly = false;

if (auth_is_logged_in() && auth_role() === 'user') {
  $prefillName  = (string)(auth_name() ?? '');
  $prefillEmail = (string)(auth_email() ?? '');
  $isUserEmailReadonly = true;
}

// Load clinics from DB (use clinic signup email, not admin email)
$clinics = [];
try {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT id, clinic_name, address, email, contact
    FROM clinics
    WHERE approval_status = 'APPROVED'
      AND email IS NOT NULL AND TRIM(email) <> ''
    ORDER BY clinic_name ASC
  ");
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $clinics[] = [
      'id' => (int)$r['id'],
      'name' => (string)$r['clinic_name'],
      'address' => (string)($r['address'] ?? ''),
      'email' => (string)($r['email'] ?? ''),
      'phone' => (string)($r['contact'] ?? ''),
    ];
  }
} catch (Throwable $e) {
  $clinics = [];
}

function find_clinic_by_id(array $clinics, int $id): ?array {
  foreach ($clinics as $c) {
    if ((int)$c['id'] === $id) return $c;
  }
  return null;
}

function is_valid_email(string $email): bool {
  return (bool)preg_match('/^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/', $email);
}

function validate_full_name(string $name): bool {
  $v = trim(preg_replace('/\s+/', ' ', $name));
  if ($v === '' || strlen($v) > 50) return false;
  return (bool)preg_match('/^[A-Za-z]+(?:\s[A-Za-z]+)*$/', $v);
}

$errMsg = '';
$okMsg  = '';

// ✅ Show success ONLY when URL has ?contact_ok=1
if (isset($_GET['contact_ok']) && (string)$_GET['contact_ok'] === '1') {
  $okMsg = 'Your message has been sent sucessfully to the selected clinic.';
}

// ✅ Process POST only if this form submitted
$isContactPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['contact_form'] ?? '') === '1');

if ($isContactPost) {
  $clinicId = (int)($_POST['clinic_id'] ?? 0);
  $fullName = trim((string)($_POST['full_name'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $message  = trim((string)($_POST['message'] ?? ''));

  // Enforce readonly email for logged-in patient user (prevents tampering)
  if ($isUserEmailReadonly) {
    $email = $prefillEmail;
  }

  if ($clinicId <= 0) {
    $errMsg = 'Please select a clinic before sending your message.';
  } else {
    $clinic = find_clinic_by_id($clinics, $clinicId);
    if (!$clinic) {
      $errMsg = 'Selected clinic was not found or is not available.';
    } elseif (!validate_full_name($fullName)) {
      $errMsg = 'You can only use letters and spacing (Maximum of 50 characters).';
    } elseif (!is_valid_email($email)) {
      $errMsg = 'Enter a valid email (ex: name@gmail.com).';
    } elseif ($message === '') {
      $errMsg = 'Message is required.';
    } else {
      $toEmail = (string)$clinic['email'];
      $toName  = (string)$clinic['name'];

      $subject = "AKAS: New message from {$fullName}";

      $clinicAddress = trim((string)$clinic['address']);
      if ($clinicAddress === '' || $clinicAddress === '-') $clinicAddress = '—';

      $clinicPhone = trim((string)$clinic['phone']);
      if ($clinicPhone === '' || $clinicPhone === '-') $clinicPhone = '—';

      $htmlBody = '
        <div style="font-family:Arial, sans-serif; line-height:1.5; color:#0f172a;">
          <h2 style="margin:0 0 10px;">New Contact Message (AKAS)</h2>

          <p style="margin:0 0 12px;">
            <strong>Clinic:</strong> ' . h($toName) . '<br>
            <strong>Clinic Address:</strong> ' . h($clinicAddress) . '<br>
            <strong>Clinic Contact:</strong> ' . h($clinicPhone) . '
          </p>

          <hr style="border:none;border-top:1px solid #e5e7eb;margin:14px 0;">

          <p style="margin:0 0 8px;"><strong>Sender Name:</strong> ' . h($fullName) . '</p>
          <p style="margin:0 0 14px;"><strong>Sender Email:</strong> ' . h($email) . '</p>

          <p style="margin:0 0 6px;"><strong>Message:</strong></p>
          <div style="white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; padding:12px; border-radius:10px;">
            ' . nl2br(h($message)) . '
          </div>

          <p style="margin-top:14px; font-size:12px; color:#64748b;">
            This message was sent via the AKAS Contact page.
          </p>
        </div>
      ';

      // ✅ DB log
      $msgRowId = null;
      try {
        $pdoLog = db();
        $senderAccountId = null;
        $senderRole = 'guest';

        if (auth_is_logged_in()) {
          $senderAccountId = (int)(auth_user_id() ?? 0);
          if ($senderAccountId <= 0) $senderAccountId = null;
          $senderRole = (string)(auth_role() ?? 'guest');
        }

        $stmtLog = $pdoLog->prepare("
          INSERT INTO contact_messages
            (clinic_id, sender_account_id, sender_role, sender_name, sender_email, message,
             clinic_name_snapshot, clinic_email_snapshot, email_sent)
          VALUES
            (:clinic_id, :sender_account_id, :sender_role, :sender_name, :sender_email, :message,
             :clinic_name, :clinic_email, 0)
        ");
        $stmtLog->execute([
          ':clinic_id' => $clinicId,
          ':sender_account_id' => $senderAccountId,
          ':sender_role' => $senderRole,
          ':sender_name' => $fullName,
          ':sender_email' => $email,
          ':message' => $message,
          ':clinic_name' => $toName,
          ':clinic_email' => $toEmail,
        ]);

        $msgRowId = (int)$pdoLog->lastInsertId();
      } catch (Throwable $e) {
        error_log('[contact_messages] insert failed: ' . $e->getMessage());
        $msgRowId = null;
      }

      $sent = akas_send_mail($toEmail, $toName, $subject, $htmlBody, $email, $fullName);

      if ($sent && $msgRowId) {
        try {
          $pdoLog = db();
          $pdoLog->prepare("UPDATE contact_messages SET email_sent = 1 WHERE id = ? LIMIT 1")
                 ->execute([$msgRowId]);
        } catch (Throwable $e) {
          error_log('[contact_messages] update failed: ' . $e->getMessage());
        }
      }

      if ($sent) {
        // ✅ PRG without header(): JS redirect so refresh will NOT re-submit
        $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/index.php'), '?');
        $redirect = $self . '?contact_ok=1#contact';

        echo '<script>location.replace(' . json_encode($redirect) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($redirect) . '"></noscript>';
        echo '<p style="padding:16px;font-family:Arial">Redirecting… <a href="' . h($redirect) . '">Continue</a></p>';
        exit;
      }

      $errMsg = 'Unable to send message right now. Please try again later.';
    }
  }
}
?>



<section id="contact" class="scroll-mt-24" style="background-color: white;">
  <section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
            
      <?php if ($errMsg): ?>
    <div class="rounded-2xl bg-white/95 p-4 border border-red-200 shadow-sm mb-6">
        <p class="text-red-700 font-semibold"><?= h($errMsg); ?></p>
    </div>
<?php endif; ?>

<?php if ($okMsg): ?>
    <div class="rounded-2xl bg-white/95 p-4 border border-green-200 shadow-sm mb-6" id="contactOkBox">
        <p class="text-green-700 font-semibold"><?= h($okMsg); ?></p>
    </div>
<?php endif; ?>

      <div class="rounded-3xl shadow-sm overflow-hidden border border-slate-100">
        <div class="p-6 md:p-10" style="background-color: var(--secondary);">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

            <!-- LEFT: CLINICS -->
            <div class="text-white">
              <h2 class="text-2xl font-bold tracking-wide">Clinics</h2>
              <p class="text-white/80 mt-1 text-sm">
                Choose a clinic to view details, then send your message. Your message will be delivered to the clinic’s registered email.
              </p>

              <div class="mt-6">
                <label class="block text-sm font-semibold text-white/90 mb-2">Select Clinic</label>

                <div class="relative">
                  <select
                    id="clinicSelect"
                    class="appearance-none w-full rounded-2xl px-4 pr-12 py-3 font-medium border border-white/10 focus:outline-none focus:ring-2"
                    style="background-color: rgba(255,255,255,0.95); color:#0f172a;"
                  >
                    <option value="" selected disabled>-- Select a clinic name --</option>

                    <?php if (empty($clinics)): ?>
                      <option value="" disabled>No approved clinics found</option>
                    <?php else: ?>
                      <?php foreach ($clinics as $c): ?>
                        <option
                          value="<?= (int)$c['id']; ?>"
                          data-name="<?= h($c['name']); ?>"
                          data-address="<?= h($c['address']); ?>"
                          data-email="<?= h($c['email']); ?>"
                          data-phone="<?= h($c['phone']); ?>"
                        >
                          <?= h($c['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>

                  <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M6 9l6 6 6-6"/>
                    </svg>
                  </div>
                </div>

                <p class="text-xs text-white/70 mt-2">
                  The clinic information below will update based on your selection.
                </p>
              </div>

              <div id="clinicDetails" class="mt-6 rounded-3xl p-5 border border-white/10" style="background: rgba(255,255,255,0.10);">
                <div class="flex items-center justify-between gap-3">
                  <h3 class="font-bold text-lg" id="cdName">No clinic selected</h3>
                  <span class="text-xs font-semibold px-3 py-1 rounded-full" style="background: rgba(0,0,0,0.18);">
                    Clinic Details
                  </span>
                </div>

                <div class="mt-4 space-y-3 text-sm">
                  <div class="grid grid-cols-[90px_1fr] gap-3">
                    <span class="text-white/70">Address</span>
                    <span id="cdAddress"
                      class="font-medium whitespace-pre-line break-words"
                      style="max-height:96px; overflow-y:auto; overflow-x:hidden;"
                      title=""
                    >—</span>
                  </div>

                  <div class="grid grid-cols-[90px_1fr] gap-3">
                    <span class="text-white/70">Email</span>
                    <span class="font-medium break-words" id="cdEmail">—</span>
                  </div>

                  <div class="grid grid-cols-[90px_1fr] gap-3">
                    <span class="text-white/70">Contact</span>
                    <span class="font-medium break-words" id="cdPhone">—</span>
                  </div>
                </div>
              </div>

              <!-- Available Clinics (4 per page) -->
              <div class="mt-6">
                <div class="flex items-center justify-between">
                  <h4 class="text-sm font-semibold text-white/90">Available Clinics</h4>
                  <div class="flex items-center gap-2">
                    <button type="button" id="namesPrev"
                      class="h-10 w-10 rounded-xl border border-white/20 text-white hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed"
                      style="background: rgba(255,255,255,0.10);"
                      aria-label="Previous">&lt;</button>
                    <button type="button" id="namesNext"
                      class="h-10 w-10 rounded-xl border border-white/20 text-white hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed"
                      style="background: rgba(255,255,255,0.10);"
                      aria-label="Next">&gt;</button>
                  </div>
                </div>

                <div id="clinicCards" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <?php foreach ($clinics as $c): ?>
                    <div class="clinic-card rounded-2xl p-4 border border-white/10" style="background: rgba(255,255,255,0.08);">
                      <div class="font-semibold text-white/95"><?= h($c['name']); ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

            </div>

            <!-- RIGHT: FORM -->
            <div>
              <div class="text-white">
                <h2 class="text-2xl font-bold tracking-wide">Send a Message</h2>
                <p class="text-white/80 mt-1 text-sm">
                  Complete the form below. Clinics can reply directly to you through your email.
                </p>
              </div>

              <form id="contactForm" class="mt-6 space-y-4" method="post" action="#contact" novalidate>
                <input type="hidden" name="contact_form" value="1">
                <input type="hidden" name="clinic_id" id="clinicHidden" value="">
                <p id="clinicErr" class="text-sm font-semibold leading-snug" style="display:none;"></p>

                <div class="field">
                  <label class="block text-sm font-semibold text-white/90 mb-2">Full Name</label>
                  <input
                    id="fullName"
                    type="text"
                    name="full_name"
                    value="<?= h((string)($_POST['full_name'] ?? $prefillName)); ?>"
                    placeholder="Enter your full name"
                    class="w-full rounded-2xl px-4 py-3 text-slate-900 placeholder-slate-400 border border-white/10 focus:outline-none focus:ring-2"
                    style="background-color: rgba(255,255,255,0.95);"
                    required
                  />
                  <p id="errFullName" class="mt-1 text-sm font-semibold leading-snug"></p>
                </div>

                <div class="field">
                  <label class="block text-sm font-semibold text-white/90 mb-2">Email</label>
                  <input
                    id="email"
                    type="text"
                    name="email"
                    value="<?= h((string)($_POST['email'] ?? $prefillEmail)); ?>"
                    placeholder="Enter your email"
                    class="w-full rounded-2xl px-4 py-3 text-slate-900 placeholder-slate-400 border border-white/10 focus:outline-none focus:ring-2 <?= $isUserEmailReadonly ? 'bg-slate-100 cursor-not-allowed' : '' ?>"
                    style="background-color: rgba(255,255,255,0.95);"
                    <?= $isUserEmailReadonly ? 'readonly' : '' ?>
                    required
                  />
                  <p id="errEmail" class="mt-1 text-sm font-semibold leading-snug"></p>

                  <?php if ($isUserEmailReadonly): ?>
                    <p class="text-xs text-white/70 mt-1">Your registered account email will be used when sending this message.</p>
                  <?php endif; ?>
                </div>

                <div class="field">
                  <label class="block text-sm font-semibold text-white/90 mb-2">Message</label>
                  <textarea
                    id="message"
                    name="message"
                    rows="8"
                    placeholder="Write your message here..."
                    class="w-full rounded-2xl px-4 py-3 text-slate-900 placeholder-slate-400 border border-white/10 focus:outline-none focus:ring-2"
                    style="background-color: rgba(255,255,255,0.95);"
                    required
                  ><?= h((string)($_POST['message'] ?? '')); ?></textarea>
                  <p id="errMessage" class="mt-1 text-sm font-semibold leading-snug"></p>
                </div>

                <div class="pt-2 flex gap-3">
                  <button type="reset" id="btnClear"
                    class="flex-1 rounded-2xl py-3 font-semibold text-slate-900 border border-white/20 hover:opacity-95 transition"
                    style="background: rgba(255,255,255,0.90);">
                    Clear
                  </button>

                  <button type="submit" id="btnSend"
                    class="flex-1 rounded-2xl py-3 font-semibold text-slate-900 transition"
                    style="background: var(--accent); opacity:.65; cursor:not-allowed;"
                    disabled>
                    Send
                  </button>
                </div>

                <p class="text-xs text-white/80 pt-2">
                  Select a clinic first, then complete your Full Name, Email, and Message. The Send button will be enabled once all required fields are valid.
                </p>
              </form>

            </div>

          </div>
        </div>
      </div>

    </div>
  </section>
 
</section>