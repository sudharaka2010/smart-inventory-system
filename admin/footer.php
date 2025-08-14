<?php
// ============================ RB Stores — Footer (include) ============================
// Purpose: small, stable-height, responsive footer built with Bootstrap utilities.
// - Avoids "auto-growing" on scroll by preventing unintended wraps & reflows.
// - Publishes real height to --rb-footer-h for layout/spacing (sidebar/main).
// - Keeps styles scoped; primary styles live in /assets/css/footer.css.

// App settings (override these BEFORE including this file if needed)
$APP_NAME   = $APP_NAME   ?? 'RB Stores';
$APP_BASE   = $APP_BASE   ?? '';      // e.g. '/admin'
$COPY_START = $COPY_START ?? 2023;
$YEAR       = (int)date('Y');

// Tiny URL helper (same signature as header/sidebar)
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};
?>
<link rel="stylesheet" href="/assets/css/footer.css"/>

<footer class="rb-footer" id="rbFooter" role="contentinfo" data-rb-scope="footer">
  <div class="rb-footer__inner container-fluid">
    <div class="row g-2 align-items-center flex-nowrap rb-footer__row">
      <!-- Left -->
      <div class="col d-flex align-items-center gap-2 min-w-0 rb-footer__left">
        <span class="rb-footer__brand text-truncate" aria-label="<?= htmlspecialchars($APP_NAME) ?>">
          <img src="/assets/images/rb.png" alt="" aria-hidden="true" class="rb-footer__logo">
          <strong class="rb-footer__appname text-truncate"><?= htmlspecialchars($APP_NAME) ?></strong>
        </span>
        <span class="rb-footer__muted text-truncate d-none d-sm-inline">
          &copy;
          <?php
            echo ($COPY_START && $COPY_START < $YEAR)
              ? ($COPY_START . '–' . $YEAR)
              : $YEAR;
          ?>
          • All rights reserved.
        </span>
      </div>

      <!-- Right -->
      <div class="col-auto d-flex align-items-center gap-1 rb-footer__right">
        <a class="rb-footer__link" href="<?= htmlspecialchars($href('privacy.php'), ENT_QUOTES) ?>">Privacy</a>
        <a class="rb-footer__link" href="<?= htmlspecialchars($href('terms.php'),   ENT_QUOTES) ?>">Terms</a>
        <a class="rb-footer__link" href="<?= htmlspecialchars($href('support.php'), ENT_QUOTES) ?>">Support</a>

        <!-- Back to top -->
        <button type="button" class="rb-footer__top btn btn-outline-light btn-sm" id="rbBackToTop" aria-label="Back to top">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 19V5m0 0l-6 6m6-6l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="d-none d-sm-inline">Top</span>
        </button>
      </div>
    </div>
  </div>
</footer>

<script>
(() => {
  const footer = document.getElementById('rbFooter');
  const btnTop  = document.getElementById('rbBackToTop');
  if (!footer) return;

  // --- Publish real footer height (no layout thrash) -------------------------
  let rafId = null;
  const setFooterVar = () => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      const h = Math.ceil(footer.getBoundingClientRect().height || footer.offsetHeight || 0);
      if (h) document.documentElement.style.setProperty('--rb-footer-h', h + 'px');
    });
  };

  // Initial sizing & observers (throttled)
  const onReady = () => setFooterVar();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady, { once: true });
  } else {
    onReady();
  }
  window.addEventListener('resize', setFooterVar, { passive: true });

  try {
    const ro = new ResizeObserver(() => setFooterVar());
    ro.observe(footer);
  } catch(e) {}

  // --- Back to top (smooth fallback-safe) ------------------------------------
  if (btnTop) {
    btnTop.addEventListener('click', () => {
      try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
      catch(e){ window.scrollTo(0, 0); }
    });
  }
})();
</script>
