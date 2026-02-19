<?php
require_once dirname(__DIR__, 2) . "/config.php";
if (!isset($appTitle)) { $appTitle = "AKAS"; }
if (!isset($baseUrl) || $baseUrl === "") { $baseUrl = defined('BASE_URL') ? (string)BASE_URL : ""; }
require_once dirname(__DIR__) . "/auth.php";

/**
 * IMPORTANT:
 * We DO NOT force clinic_admin/superadmin to stay inside /admin/*
 * because they should be able to view the public website (View Website button).
 * Admin page protection is handled by /admin/_guard.php and auth_require_role().
 */

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

  <script>
    (function () {
      if (location.hash) {
        document.documentElement.classList.add("nohash-snap");
      }
    })();
  </script>
  <script src="<?php echo $baseUrl; ?>/assets/js/global.js" defer></script>
</head>
