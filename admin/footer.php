<?php
// ============================ RB Stores — Simple Footer ============================
// Purpose: clean, stable-height, responsive footer (no JS).
// Main styles in /assets/css/footer.css.

$APP_NAME   = $APP_NAME   ?? 'RB Stores';
$APP_BASE   = $APP_BASE   ?? '';      // e.g. '/admin'
$COPY_START = $COPY_START ?? 2023;
$YEAR       = (int)date('Y');

$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};
?>
<link rel="stylesheet" href="<?= htmlspecialchars($href('assets/css/app.css'), ENT_QUOTES) ?>?v=2025-08-15" />

<footer class="rb-footer py-3 border-top" role="contentinfo" data-rb-scope="footer">
  <div class="container-fluid d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
    <!-- Left: Brand & Copy -->
    <div class="d-flex align-items-center gap-2 text-truncate">
      <img src="<?= htmlspecialchars($href('assets/images/rb.png'), ENT_QUOTES) ?>"
           alt=""
           class="rb-footer__logo">
      <strong class="text-truncate"><?= htmlspecialchars($APP_NAME) ?></strong>
      <span class="text-muted small text-truncate">
        &copy;
        <?php
          echo ($COPY_START && $COPY_START < $YEAR) ? ($COPY_START . '–' . $YEAR) : $YEAR;
        ?>
        • All rights reserved.
      </span>
    </div>

    <!-- Right: Links -->
    <nav aria-label="Footer">
      <ul class="list-unstyled d-flex mb-0 gap-3 small">
        <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('privacy.php'), ENT_QUOTES) ?>">Privacy</a></li>
        <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('terms.php'),   ENT_QUOTES) ?>">Terms</a></li>
        <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('support.php'), ENT_QUOTES) ?>">Support</a></li>
      </ul>
    </nav>
  </div>
</footer>
