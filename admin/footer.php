<?php
// ========================== RB Stores — Footer (include) ===========================

$APP_BASE = rtrim($APP_BASE ?? '', '/');
$href = function(string $path) use ($APP_BASE){
  $path = '/' . ltrim($path, '/');
  return ($APP_BASE === '') ? $path : $APP_BASE . $path;
};

// Load CSS once
if (!defined('RB_FOOTER_CSS')) {
  echo '<link rel="stylesheet" href="'.$href('/assets/css/footer.css').'">' . PHP_EOL;
  define('RB_FOOTER_CSS', 1);
}
?>

<footer class="rb-footer" data-rb-scope="footer" role="contentinfo">
  <div class="rb-footer__inner">
    <div class="rb-foot-left">
      <span>© <span id="rbYear"></span> RB Stores</span>
      <span class="rb-dot">•</span>
      <a href="<?= $href('/privacy.php'); ?>">Privacy</a>
      <span class="rb-dot">•</span>
      <a href="<?= $href('/terms.php'); ?>">Terms</a>
    </div>

    <div class="rb-foot-right">
      <a href="<?= $href('/help.php'); ?>">Help</a>
      <a href="<?= $href('/contact.php'); ?>">Contact</a>
      <a href="<?= $href('/changelog.php'); ?>">Changelog</a>
    </div>
  </div>
</footer>

<script>
  document.getElementById('rbYear').textContent = new Date().getFullYear();
</script>
