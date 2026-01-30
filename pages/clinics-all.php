
<?php
// pages/clinics-all.php — All Clinics page (standalone)
$appTitle  = "AKAS | All Clinics";
$baseUrl   = "/AKAS";
include __DIR__ . "/../includes/partials/head.php";
?>

<body class="bg-blue-100">

<?php include __DIR__ . "/../includes/partials/navbar.php"; ?>

<main class="px-4 py-10">
  <div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-3xl font-bold text-white">All Clinics</h1>
        <p class="text-white/90 text-sm mt-1">Search and browse clinics</p>
      </div>
      <a href="<?php echo $baseUrl; ?>/index.php#clinics"
         class="px-5 py-2 rounded-full font-semibold text-white transition-all duration-300"
         style="background-color: var(--primary);">
        Back to Home
      </a>
    </div>

    <!-- SEARCH FILTER -->
    <div class="search-box text-white rounded-2xl p-6 mb-8">
      <h5 class="text-center mb-4 font-semibold">FIND A CLINIC NEAR YOU</h5>
      <form class="grid grid-cols-1 md:grid-cols-3 gap-3" onsubmit="return false;">
        <div>
          <input type="text" class="w-full px-4 py-2 rounded-full text-gray-900" placeholder="Name">
        </div>
        <div>
          <input type="text" class="w-full px-4 py-2 rounded-full text-gray-900" placeholder="Medical Specialty">
        </div>
        <div>
          <button type="button"
                  class="w-full px-4 py-2 rounded-full bg-white text-gray-900 font-semibold">
            Search
          </button>
        </div>
      </form>
    </div>

    <!-- 10 clinics in a single column -->
    <div class="space-y-4">
      <?php for ($i = 1; $i <= 10; $i++): ?>
        <?php
          // Fallback return (if user opens profile in new tab or no history)
          $returnUrl = $baseUrl . "/pages/clinics-all.php";
        ?>
        <a class="clinicLink block rounded-2xl"
           href="<?php echo $baseUrl; ?>/pages/clinic-profile.php?id=<?php echo urlencode($i); ?>&return=<?php echo urlencode($returnUrl); ?>">
          <div class="rounded-2xl shadow-sm bg-white overflow-hidden hover:shadow-md transition">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-0">
              <div class="flex items-center justify-center h-40 md:h-auto"
                   style="background: rgba(64, 183, 255, .10);">
                <img
                  src="https://cdn-icons-png.flaticon.com/512/2967/2967350.png"
                  width="90"
                  alt="Clinic"
                >
              </div>
              <div class="md:col-span-2 p-5">
                <h5 class="text-lg font-semibold">Clinic Name <?php echo $i; ?></h5>
                <p class="text-gray-600 text-sm">Medical Specialty</p>
                <p class="text-gray-700 mt-4">
                  Short clinic description goes here. This can be fetched from the database.
                </p>
              </div>
            </div>
          </div>
        </a>
      <?php endfor; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . "/../includes/partials/footer.php"; ?>

<!-- JS -->
<script src="<?php echo $baseUrl; ?>/assets/js/global.js" defer></script>

</body>
</html>
