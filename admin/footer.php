<?php
// ============================ RB Stores — Footer (include) ============================
// Purpose: compact, stable-height, responsive footer; publishes --rb-footer-h.
// Notes:  - Main styles live in /assets/css/footer.css (scoped).
//         - Supports subfolder deploys via $APP_BASE.
//         - CSP-friendly option: move the inline script to /assets/js/footer.js and add defer.

$APP_NAME   = $APP_NAME   ?? 'RB Stores';
$APP_BASE   = $APP_BASE   ?? '';              // e.g. '/admin'
$COPY_START = $COPY_START ?? 2023;
$YEAR       = (int)date('Y');
$CSP_NONCE  = $CSP_NONCE  ?? '';              // set in header if you enforce CSP nonces

$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};
?>
<link rel="stylesheet" href="<?= htmlspecialchars($href('assets/css/footer.css'), ENT_QUOTES) ?>?v=2025-08-15" />

<footer class="rb-footer" id="rbFooter" role="contentinfo" data-rb-scope="footer">
  <div class="rb-footer__inner container-fluid">
    <div class="row g-2 align-items-center rb-footer__row flex-sm-nowrap">
      <!-- Left -->
      <div class="col d-flex align-items-center gap-2 min-w-0 rb-footer__left">
        <a class="rb-footer__brand d-inline-flex align-items-center text-truncate"
           href="<?= htmlspecialchars($href('index.php'), ENT_QUOTES) ?>"
           title="<?= htmlspecialchars($APP_NAME) ?>">
          <img src="<?= htmlspecialchars($href('assets/images/rb.png'), ENT_QUOTES) ?>"
               alt="" aria-hidden="true" class="rb-footer__logo">
          <strong class="rb-footer__appname text-truncate"><?= htmlspecialchars($APP_NAME) ?></strong>
        </a>
        <span class="rb-footer__muted text-truncate d-none d-sm-inline">
          &copy;
          <?php
            echo ($COPY_START && $COPY_START < $YEAR) ? ($COPY_START . '–' . $YEAR) : $YEAR;
          ?>
          • All rights reserved.
        </span>
      </div>

      <!-- Right -->
      <div class="col-auto d-flex align-items-center rb-footer__right">
        <nav aria-label="Footer">
          <ul class="rb-footer__nav">
            <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('privacy.php'), ENT_QUOTES) ?>">Privacy</a></li>
            <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('terms.php'),   ENT_QUOTES) ?>">Terms</a></li>
            <li><a class="rb-footer__link" href="<?= htmlspecialchars($href('support.php'), ENT_QUOTES) ?>">Support</a></li>
          </ul>
        </nav>

        <!-- Back to top -->
        <button type="button"
                class="rb-footer__top btn btn-outline-light btn-sm"
                id="rbBackToTop"
                title="Back to top">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 19V5m0 0l-6 6m6-6l6 6"
                  stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span class="d-none d-sm-inline">Top</span>
        </button>
      </div>
    </div>
  </div>
</footer>

<script nonce="<?= htmlspecialchars($CSP_NONCE, ENT_QUOTES) ?>">
(() => {
  const footer = document.getElementById('rbFooter');
  const btnTop = document.getElementById('rbBackToTop');
  if (!footer) return;

  // Publish real footer height (throttled via rAF)
  let rafId = null;
  const setFooterVar = () => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      const h = Math.ceil(footer.getBoundingClientRect().height || footer.offsetHeight || 0);
      if (h) document.documentElement.style.setProperty('--rb-footer-h', h + 'px');
    });
  };

  const onReady = () => setFooterVar();
  (document.readyState === 'loading')
    ? document.addEventListener('DOMContentLoaded', onReady, { once: true })
    : onReady();

  window.addEventListener('resize', setFooterVar, { passive: true });

  try { new ResizeObserver(setFooterVar).observe(footer); } catch(e) {}

  // Back to top (respect reduced motion)
  if (btnTop) {
    btnTop.addEventListener('click', () => {
      const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!prefersReduced && 'scrollBehavior' in document.documentElement.style) {
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); return; } catch(e){}
      }
      window.scrollTo(0, 0);
    });
  }
})();
</script>
