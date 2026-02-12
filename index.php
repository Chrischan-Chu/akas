<?php
$appTitle = "AKAS | Appointment Scheduling System";
include "includes/partials/head.php";
?>

<body class="bg-blue-100">

<?php include "includes/partials/navbar.php"; ?>

<main>
  <?php include "pages/home.php"; ?>
  <?php include "pages/about.php"; ?>
  <?php include "pages/mission-vision.php"; ?>
  <?php include "pages/clinics.php"; ?>
  <?php include "pages/contact.php"; ?>
</main>

<?php include "includes/partials/footer.php"; ?>
<button id="backToTop" type="button" aria-label="Back to top">↑</button>
<script src="/AKAS/assets/js/global.js" defer></script>
<script src="/AKAS/assets/js/contact.js" defer></script>
