<?php $appTitle = "AKAS | Admin Sign Up"; ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $appTitle; ?></title>
  <link rel="stylesheet" href="/AKAS/assets/css/output.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/output.css'); ?>">
  <style>
    :root { --primary:#40B7FF; --secondary:#90D5FF; --accent:#ffbe8a; }
    .auth { min-height: 100vh; display:flex; background:#fff; }
    .left { flex:1; display:flex; justify-content:flex-start; padding:64px 48px; }
    .left-inner { width:100%; max-width:620px; margin-left:80px; }
    .right { flex:1; background:var(--secondary); display:none; border-left:1px solid rgba(0,0,0,.2); }
    @media (min-width:1024px){ .right{ display:flex; } }
    .img-box { width: calc(100% - 80px); height: calc(100% - 80px); margin:40px; border:1px solid rgba(0,0,0,.3); position:relative; }
    .img-box svg { position:absolute; inset:0; width:100%; height:100%; }
  </style>
</head>
<body>
  <main class="auth">
    <section class="left">
      <div class="left-inner">
        <h1 class="text-5xl font-semibold underline underline-offset-8">Hello</h1>
        <p class="mt-6 text-lg text-gray-700">Admin Sign Up</p>
        <form action="signup-process.php" method="POST" class="mt-8 space-y-5">
          <input type="hidden" name="role" value="admin" />
          <input type="text" name="admin_code" placeholder="Admin code" required class="w-full rounded-2xl px-6 py-5 text-xl outline-none border border-transparent focus:border-black/10" style="background:var(--secondary)" />
          <input type="text" name="name" placeholder="Name" required class="w-full rounded-2xl px-6 py-5 text-xl outline-none border border-transparent focus:border-black/10" style="background:var(--secondary)" />
          <input type="email" name="email" placeholder="example@email.com" required class="w-full rounded-2xl px-6 py-5 text-xl outline-none border border-transparent focus:border-black/10" style="background:var(--secondary)" />
          <input type="password" name="password" placeholder="password" required class="w-full rounded-2xl px-6 py-5 text-xl outline-none border border-transparent focus:border-black/10" style="background:var(--secondary)" />
          <input type="tel" name="number" placeholder="number" class="w-full rounded-2xl px-6 py-5 text-xl outline-none border border-transparent focus:border-black/10" style="background:var(--secondary)" />
          <div class="pt-4 flex items-center gap-8">
            <a href="login.php" class="px-12 py-3 rounded-full text-xl font-medium shadow-sm hover:opacity-95 transition inline-flex items-center justify-center" style="background:var(--accent)">Login</a>
            <button type="submit" class="px-12 py-3 rounded-full text-xl font-medium shadow-sm hover:opacity-95 transition" style="background:var(--accent)">Create Admin</button>
          </div>
          <div class="pt-4 text-sm text-gray-500">
            <a href="signup.php" class="underline hover:text-gray-700">Back to choose type</a>
          </div>
        </form>
      </div>
    </section>
    <section class="right">
      <div class="img-box">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none">
          <line x1="0" y1="0" x2="100" y2="100" stroke="rgba(0,0,0,0.25)" stroke-width="0.4"/>
          <line x1="100" y1="0" x2="0" y2="100" stroke="rgba(0,0,0,0.25)" stroke-width="0.4"/>
        </svg>
      </div>
    </section>
  </main>
</body>
</html>
              Login
            </a>

            <button type="submit"
              class="px-12 py-3 rounded-full text-xl font-medium shadow-sm hover:opacity-95 transition"
              style="background:var(--accent)">
              Create Admin
            </button>
          </div>

          <div class="pt-4 text-sm text-gray-500">
            <a href="signup.php" class="underline hover:text-gray-700">Back to choose type</a>
          </div>
        </form>
      </div>
    </section>

    <!-- RIGHT -->
    <section class="right">
      <div class="img-box">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none">
          <line x1="0" y1="0" x2="100" y2="100" stroke="rgba(0,0,0,0.25)" stroke-width="0.4"/>
          <line x1="100" y1="0" x2="0" y2="100" stroke="rgba(0,0,0,0.25)" stroke-width="0.4"/>
        </svg>
      </div>
    </section>

  </main>
</body>
</html>