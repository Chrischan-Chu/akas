<?php $appTitle = "AKAS | Choose Account Type"; ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $appTitle; ?></title>

  <link rel="stylesheet"
        href="/AKAS/assets/css/output.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/output.css'); ?>">

  <style>
    :root{
      --primary:#40B7FF;
      --secondary:#0b3869;
      --accent:#FFA154;
    }
  </style>
</head>


<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-white to-[var(--secondary)]/40 px-6">

  <main class="text-center w-full max-w-3xl">

    <!-- TITLE -->
    <h1 class="text-5xl font-bold mb-4 text-[var(--primary)]">
      Welcome to AKAS
    </h1>

    <p class="text-lg mb-14 text-[var(--primary)]">
      Choose how you want to continue
    </p>


    <!-- CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

      <!-- USER -->
      <a href="signup-user.php"
         class="bg-[var(--accent)] text-white rounded-2xl py-8 px-6 text-2xl font-bold
                shadow-lg hover:shadow-xl transition-all duration-200 hover:-translate-y-2
                flex flex-col items-center gap-3">

        Continue as User
        <span class="text-sm font-semibold">
          Book appointments & browse clinics
        </span>
      </a>


      <!-- ADMIN -->
      <a href="signup-admin.php"
         class="bg-[var(--primary)] text-white rounded-2xl py-8 px-6 text-2xl font-bold
                shadow-lg hover:shadow-xl transition-all duration-200 hover:-translate-y-2
                flex flex-col items-center gap-3">

        Continue as Admin
        <span class="text-sm font-semibold">
          Manage clinics & schedules
        </span>
      </a>

    </div>


    <!-- BACK -->
    <div class="mt-14">
      <a href="/AKAS/index.php#home"
         class="text-sm text-[var(--primary)] hover:underline transition">
        ← Go to home
      </a>
    </div>

  </main>

</body>
</html>
