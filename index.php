<?php
$appTitle = "AKAS | Appointment Scheduling System";
include "includes/partials/head.php";
?>

<body class="bg-blue-100">

<?php include "includes/partials/navbar.php"; ?>

<main>
  <?php include "pages/home.php"; ?>
  <?php include "pages/about.php"; ?>
  <?php include "pages/clinics.php"; ?>
  <?php include "pages/contact.php"; ?>
</main>

<?php include "includes/partials/footer.php"; ?>

<!-- Global JS -->
<script src="/AKAS/assets/js/global.js" defer></script>
<!-- Contact page JS (only runs if elements exist) -->
<script src="/AKAS/assets/js/contact.js" defer></script>
