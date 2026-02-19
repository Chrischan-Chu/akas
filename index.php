<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
<script src="/assets/js/contact.js" defer></script>
