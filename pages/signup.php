<?php
declare(strict_types=1);

$appTitle = "AKAS | Sign Up";
$baseUrl  = "";

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google_config.php';

if (auth_is_logged_in()) {
  header('Location: ' . ($baseUrl . (auth_role() === 'clinic_admin' ? '/admin/dashboard.php' : '/index.php#top')));
  exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></title>

  <link rel="stylesheet"
      href="<?php echo $baseUrl; ?>/assets/css/output.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/output.css'); ?>">


  <style>
    :root{
      --primary:#40B7FF;
      --secondary:#0b3869;
      --accent:#FFA154;
    }
    .btnCard{
      border: 1px solid rgba(255,255,255,.35);
      box-shadow: 0 14px 40px rgba(0,0,0,.08);
    }
    .btnPrimary{ background: var(--primary); }
    .btnAccent{ background: var(--accent); }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-[var(--secondary)]/40 px-6">

<main class="w-full max-w-5xl">
  <div class="text-center mb-10">
    <h1 class="text-5xl font-bold text-[var(--primary)]">Welcome to AKAS</h1>
    <p class="mt-3 text-lg text-[var(--secondary)]/80">Choose how you want to sign up</p>
  </div>

  <!-- Google signup POST (used by both buttons) -->
  <form id="googleSignupForm" action="<?php echo $baseUrl; ?>/pages/google-auth.php" method="POST" class="hidden">
    <input type="hidden" name="mode" value="signup">
    <input type="hidden" name="role" id="googleRole" value="user">
    <input type="hidden" name="credential" id="googleCredential" value="">
  </form>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- USER CARD -->
    <section class="btnCard rounded-3xl p-8 bg-white/80 backdrop-blur">
      <h2 class="text-2xl font-bold text-[var(--secondary)]">User</h2>
      <p class="text-sm text-slate-600 mt-1">Book appointments & browse clinics</p>

      <div class="mt-6 space-y-3">
        <a href="signup-user.php"
           class="btnAccent w-full text-white rounded-2xl py-4 px-6 text-lg font-bold
                  shadow hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5
                  flex items-center justify-center">
          Continue as User
        </a>

        <button type="button"
                onclick="beginGoogleSignup('user')"
                class="w-full rounded-2xl py-4 px-6 text-lg font-bold
                       border border-slate-200 bg-white
                       shadow hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5
                       flex items-center justify-center gap-2">
          <span>Continue with Google</span>
        </button>
      </div>
    </section>

    <!-- ADMIN CARD -->
    <section class="btnCard rounded-3xl p-8 bg-white/80 backdrop-blur">
      <h2 class="text-2xl font-bold text-[var(--secondary)]">Clinic Admin</h2>
      <p class="text-sm text-slate-600 mt-1">Register a clinic & manage schedules</p>

      <div class="mt-6 space-y-3">
        <a href="signup-admin.php"
           class="btnPrimary w-full text-white rounded-2xl py-4 px-6 text-lg font-bold
                  shadow hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5
                  flex items-center justify-center">
          Continue as Admin
        </a>

        <button type="button"
                onclick="beginGoogleSignup('clinic_admin')"
                class="w-full rounded-2xl py-4 px-6 text-lg font-bold
                       border border-slate-200 bg-white
                       shadow hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5
                       flex items-center justify-center gap-2">
          <span>Continue with Google</span>
        </button>

        <p class="text-xs text-slate-500 mt-2">
          Google Admin signup will skip Step 1 and take you directly to Step 2.
        </p>
      </div>
    </section>

  </div>

  <div class="mt-10 text-center">
    <a href="/index.php#home" class="text-sm text-[var(--primary)] hover:underline">← Go to home</a>
  </div>
</main>

<script src="https://accounts.google.com/gsi/client" async defer></script>

<!-- hidden official Google buttons (we click them programmatically) -->
<div id="googleBtnUser" style="display:none"></div>
<div id="googleBtnAdmin" style="display:none"></div>

<script>
  let _googleReady = false;

  function initGoogleButtons() {
    if (_googleReady) return true;
    if (!window.google || !google.accounts || !google.accounts.id) return false;

    // USER button
    google.accounts.id.initialize({
      client_id: "<?php echo htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8'); ?>",
      callback: function (resp) {
        if (!resp || !resp.credential) return;
        document.getElementById('googleRole').value = window.__googleRole || 'user';
        document.getElementById('googleCredential').value = resp.credential;
        document.getElementById('googleSignupForm').submit();
      }
    });

    google.accounts.id.renderButton(
      document.getElementById("googleBtnUser"),
      { theme: "outline", size: "large", text: "continue_with", shape: "pill" }
    );

    google.accounts.id.renderButton(
      document.getElementById("googleBtnAdmin"),
      { theme: "outline", size: "large", text: "continue_with", shape: "pill" }
    );

    _googleReady = true;
    return true;
  }

  function beginGoogleSignup(role) {
    window.__googleRole = (role === 'clinic_admin') ? 'clinic_admin' : 'user';

    if (!initGoogleButtons()) {
      alert("Google is still loading. Refresh the page and try again.");
      return;
    }

    // Click the hidden official Google button (reliable sign-in popup)
    const btn = (window.__googleRole === 'clinic_admin')
      ? document.querySelector('#googleBtnAdmin div[role="button"]')
      : document.querySelector('#googleBtnUser div[role="button"]');

    if (!btn) {
      alert("Google button not ready. Refresh the page and try again.");
      return;
    }

    btn.click();
  }

  // Wait until GIS loads then initialize
  window.addEventListener('load', () => {
    const t = setInterval(() => {
      if (initGoogleButtons()) clearInterval(t);
    }, 80);
    setTimeout(() => clearInterval(t), 5000);
  });
</script>


</body>
</html>
