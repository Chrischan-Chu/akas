<?php
// You can later replace this with DB data using $_GET['id'] and a query.
$doctor = [
  "name" => "Dr. Alexandra Reyes",
  "specialty" => "General Practitioner",
  "license" => "PRC Lic. No. 0123456",
  "clinic" => "AKAS Health Clinic – Angeles City",
  "experience" => "7+ years experience",
  "languages" => ["English", "Filipino"],
  "rating" => 4.8,
  "reviews" => 126,
  "about" => "Dr. Reyes focuses on preventive care, general consultations, minor illness management, and wellness planning. Patients appreciate her clear explanations and friendly approach.",
  "services" => [
    "General Consultation",
    "Follow-up Checkups",
    "Medical Certificates",
    "Basic Wellness Advice",
    "Blood Pressure Monitoring"
  ],
  "availability" => [
    ["day" => "Mon", "time" => "9:00 AM – 4:00 PM"],
    ["day" => "Tue", "time" => "10:00 AM – 5:00 PM"],
    ["day" => "Wed", "time" => "9:00 AM – 2:00 PM"],
    ["day" => "Thu", "time" => "10:00 AM – 5:00 PM"],
    ["day" => "Fri", "time" => "9:00 AM – 3:00 PM"],
  ],
  "fee" => "₱300 – ₱500",
  "address" => "Angeles City, Pampanga",
  "education" => [
    "Doctor of Medicine – University of the Philippines (UP)",
    "Residency – Family Medicine (Regional Medical Center)"
  ]
];

$clinicId = $_GET['clinic_id'] ?? 1;
?>

<?php
$clinicName = "Name of Clinic";
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($clinicName); ?> | AKAS</title>

  <!-- Tailwind build -->
  <link rel="stylesheet" href="../assets/css/output.css">

  <style>
    :root { --primary:#40B7FF; --secondary:#90D5FF; --accent:#ffbe8a; }
    .nav-link:hover { color: var(--accent) !important; }
  </style>
</head>
<body class="bg-blue-100">

<!-- HEADER -->
<section class="py-6 text-center" style="background:var(--secondary)">
  <div class="max-w-6xl mx-auto px-4 relative">

    <!-- Back to clinic -->
    <?php $clinicId = $_GET['clinic_id'] ?? 1; ?>
    <a href="clinic-profile.php?id=<?php echo urlencode($clinicId); ?>" class="absolute left-0 top-1/2 -translate-y-1/2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </a>

    <h1 class="text-3xl tracking-widest font-light text-white">
      DOCTOR PROFILE
    </h1>
  </div>
</section>

<!-- MAIN -->
<section class="py-10 px-4">
  <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- LEFT CARD -->
    <div class="bg-white rounded-2xl p-8 flex flex-col items-center text-center">
      <div class="w-44 h-44 rounded-full flex items-center justify-center" style="background:rgba(255,190,138,.65)">
        <img src="https://cdn-icons-png.flaticon.com/512/387/387561.png" class="w-28" alt="Doctor" />
      </div>

      <h2 class="mt-6 text-2xl font-semibold text-gray-800">
        <?php echo $doctor["name"]; ?>
      </h2>

      <p class="mt-1 text-gray-600 font-medium">
        <?php echo $doctor["specialty"]; ?>
      </p>

      <p class="mt-3 text-sm text-gray-500">
        <?php echo $doctor["experience"]; ?> • <?php echo $doctor["license"]; ?>
      </p>

      <!-- Rating -->
      <div class="mt-4 flex items-center gap-2">
        <div class="flex items-center gap-1 text-yellow-500">
          <?php
            $stars = floor($doctor["rating"]);
            for($i=1; $i<=5; $i++){
              echo $i <= $stars ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
            }
          ?>
        </div>
        <span class="text-sm text-gray-600">
          <?php echo $doctor["rating"]; ?> (<?php echo $doctor["reviews"]; ?> reviews)
        </span>
      </div>

      <!-- Quick chips -->
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" style="background:var(--primary)">
          Verified Doctor
        </span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold text-gray-800" style="background:var(--accent)">
          Walk-in & Online
        </span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" style="background:rgba(75,182,245,.85)">
          Friendly Consultation
        </span>
      </div>
    </div>

    <!-- RIGHT DETAILS PANEL -->
    <div class="rounded-2xl p-6 md:p-8 text-white" style="background:var(--primary)">

      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
          <h3 class="text-2xl font-semibold"><?php echo $doctor["name"]; ?></h3>
          <p class="text-white/90"><?php echo $doctor["clinic"]; ?></p>
          <p class="text-white/80 text-sm mt-1">
            <i class="bi bi-geo-alt-fill"></i> <?php echo $doctor["address"]; ?>
          </p>
        </div>

        <div class="bg-white/15 rounded-xl px-4 py-3">
          <p class="text-xs uppercase tracking-wider text-white/80">Consultation Fee</p>
          <p class="text-lg font-bold"><?php echo $doctor["fee"]; ?></p>
        </div>
      </div>

      <!-- About -->
      <div class="mt-6">
        <h4 class="font-semibold text-lg">About the Doctor</h4>
        <p class="mt-2 text-white/90 leading-relaxed">
          <?php echo $doctor["about"]; ?>
        </p>
      </div>

      <!-- Services -->
      <div class="mt-6">
        <h4 class="font-semibold text-lg">Services</h4>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
          <?php foreach($doctor["services"] as $svc): ?>
            <div class="bg-white/15 rounded-xl px-4 py-2 flex items-center gap-2">
              <i class="bi bi-check-circle-fill"></i>
              <span class="text-white/95 text-sm font-medium"><?php echo $svc; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php
      $doctor = [
        "name" => "Dr. Alexandra Reyes",
        "specialty" => "General Practitioner",
        "license" => "PRC Lic. No. 0123456",
        "clinic" => "AKAS Health Clinic – Angeles City",
        "experience" => "7+ years experience",
        "languages" => ["English", "Filipino"],
        "rating" => 4.8,
        "reviews" => 126,
        "about" => "Dr. Reyes focuses on preventive care, general consultations, minor illness management, and wellness planning. Patients appreciate her clear explanations and friendly approach.",
        "services" => [
          "General Consultation",
          "Follow-up Checkups",
          "Medical Certificates",
          "Basic Wellness Advice",
          "Blood Pressure Monitoring"
        ],
        "availability" => [
          ["day" => "Mon", "time" => "9:00 AM – 4:00 PM"],
          ["day" => "Tue", "time" => "10:00 AM – 5:00 PM"],
          ["day" => "Wed", "time" => "9:00 AM – 2:00 PM"],
          ["day" => "Thu", "time" => "10:00 AM – 5:00 PM"],
          ["day" => "Fri", "time" => "9:00 AM – 3:00 PM"],
        ],
        "fee" => "₱300 – ₱500",
        "address" => "Angeles City, Pampanga",
        "education" => [
          "Doctor of Medicine – University of the Philippines (UP)",
          "Residency – Family Medicine (Regional Medical Center)"
        ]
      ];
      $clinicId = $_GET['clinic_id'] ?? 1;
      $clinicName = "Name of Clinic";
      ?>
      <!DOCTYPE html>
      <html lang="en" class="scroll-smooth">
      <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?php echo htmlspecialchars($clinicName); ?> | AKAS</title>
        <link rel="stylesheet" href="../assets/css/output.css">
        <style>
          :root { --primary:#40B7FF; --secondary:#90D5FF; --accent:#ffbe8a; }
          .nav-link:hover { color: var(--accent) !important; }
        </style>
      </head>
      <body class="bg-blue-100">
      <section class="py-6 text-center" style="background:var(--secondary)">
        <div class="max-w-6xl mx-auto px-4 relative">

    </div>
  </div>
</section>

<!-- Back to Top Button -->
<button id="backToTopBtn"
  class="fixed bottom-8 right-8 w-12 h-12 rounded-full hidden items-center justify-center shadow-lg text-white"
  style="background:var(--primary)">
  <i class="bi bi-chevron-up text-xl"></i>
</button>

<script>
(function () {
  const btn = document.getElementById("backToTopBtn");
  if (!btn) return;
  function onScroll() {
    if (window.scrollY > 300) btn.classList.remove("hidden");
    else btn.classList.add("hidden");
  }
  window.addEventListener("scroll", onScroll);
  btn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  onScroll();
})();
</script>
</body>
</html>