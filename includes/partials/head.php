<?php
// includes/partials/head.php
if (!isset($appTitle)) { $appTitle = "AKAS"; }
if (!isset($baseUrl))  { $baseUrl  = "/AKAS"; }

// Cache-bust Tailwind build when file changes
$cssFile = dirname(__DIR__, 2) . "/assets/css/output.css";
$cssVer  = file_exists($cssFile) ? filemtime($cssFile) : time();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($appTitle); ?></title>

  <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/output.css?v=<?php echo $cssVer; ?>">



  <script>
    // ✅ If page loads with a hash (#clinics), disable smooth scrolling BEFORE paint
    (function () {
      if (location.hash) {
        document.documentElement.classList.add("nohash-snap");
      }
    })();
  </script>
</head>
