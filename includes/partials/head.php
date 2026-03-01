<?php
require_once dirname(__DIR__, 2) . "/config.php";
if (!isset($appTitle)) { $appTitle = "AKAS"; }
if (!isset($baseUrl) || $baseUrl === "") { $baseUrl = defined('BASE_URL') ? (string)BASE_URL : ""; }
require_once dirname(__DIR__) . "/auth.php";

$cssFile = dirname(__DIR__, 2) . "/assets/css/output.css";
$cssVer  = file_exists($cssFile) ? filemtime($cssFile) : time();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="<?= $baseUrl ?>/assets/img/akas-logo.png ">
  <title><?php echo htmlspecialchars($appTitle); ?></title>

  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css?v=<?php echo $cssVer; ?>">

  <!-- Leaflet (for Clinic Map on Contact page) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

  <script>
    (function () {
      if (location.hash) {
        document.documentElement.classList.add("nohash-snap");
      }
    })();
  </script>
  <script src="<?php echo $baseUrl; ?>/assets/js/global.js" defer></script>
</head>
