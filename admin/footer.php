<?php
// ============================ RB Stores — Footer (include) ============================
// Purpose: simple, responsive footer that plays nicely with the header & sidebar.
// It publishes its real height to --rb-footer-h so your main layout & sidebar
// can avoid overlap or gaps.

// Optional app settings (override before include if you want)
$APP_NAME   = $APP_NAME   ?? 'RB Stores';
$APP_BASE   = $APP_BASE   ?? '';              // e.g. '/admin'
$COPY_START = $COPY_START ?? 2023;            // first year your app went live
$YEAR       = (int)date('Y');

// Tiny URL helper (same signature as header/sidebar)
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};
?>
<!-- Footer styles (scoped & minimal).
     If you have /assets/css/footer.css, keep that as the source of truth and
     leave these rules as safe fallbacks. -->
<link rel="stylesheet" href="/assets/css/footer.css"/>

<style>
  :root{
    /* Fallback; will be updated by JS to real px height */
    --rb-footer-h: 56px;
  }
  .rb-footer{
    background: #0f172a;            /* slate-900 */
    color: #e5e7eb;                  /* gray-200 */
    border-top: 1px solid rgba(148,208,236,.28);
    font-family: Poppins, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
  }
  .rb-footer__inner{
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; padding: .75rem 1rem; min-height: var(--rb-footer-h); box-sizing: border-box;
  }
  .rb-footer__left, .rb-footer__right{
    display:flex; align-items:center; gap:.75rem; flex-wrap: wrap;
  }
  .rb-footer__link{
    color:#9fb9e8; text-decoration:none; padding:.25rem .5rem; border-radius:.375rem;
  }
  .rb-footer__link:hover{ background: rgba(255,255,255,.06); color:#e5e7eb; }
  .rb-footer__muted{ color:#9ca3af; font-weight:400; }
  .rb-footer__brand{ display:inline-flex; align-items:center; gap:.5rem; }
  .rb-footer__brand img{ width:22px; height:22px; object-fit:contain; }
  /* Back to top button (optional) */
  .rb-footer__top{
    display:inline-flex; align-items:center; gap:.35rem;
    background: transparent; border: 1px solid rgba(148,208,236,.28);
    color:#e5e7eb; border-radius:.5rem; padding:.35rem .6rem; cursor:pointer;
  }
  .rb-footer__top:hover{ background: rgba(255,255,255,.06); }

  /* Small screens: stack neatly */
  @media (max-width: 575.98px){
    .rb-footer__inner{ flex-direction: column; align-items: stretch; }
    .rb-footer__right{ justify-content: space-between; }
  }
</style>

<footer class="rb-footer" role="contentinfo" id="rbFooter">
  <div class="rb-footer__inner" aria-label="Footer">
    <div class="rb-footer__left">
      <span class="rb-footer__brand" aria-label="<?= htmlspecialchars($APP_NAME) ?>">
        <img src="/assets/images/rb.png" alt="" aria-hidden="true">
        <strong><?= htmlspecialchars($APP_NAME) ?></strong>
      </span>
      <span class="rb-footer__muted">
        &copy;
        <?php
          echo ($COPY_START && $COPY_START < $YEAR)
            ? ($COPY_START . '–' . $YEAR)
            : $YEAR;
        ?>
        • All rights reserved.
      </span>
    </div>

    <div class="rb-footer__right">
      <!-- Quick links: tweak paths as needed -->
      <a class="rb-footer__link" href="<?= htmlspecialchars($href('privacy.php'), ENT_QUOTES) ?>">Privacy</a>
      <a class="rb-footer__link" href="<?= htmlspecialchars($href('terms.php'),   ENT_QUOTES) ?>">Terms</a>
      <a class="rb-footer__link" href="<?= htmlspecialchars($href('support.php'), ENT_QUOTES) ?>">Support</a>

      <!-- Back to top -->
      <button type="button" class="rb-footer__top" id="rbBackToTop" aria-label="Back to top">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 19V5m0 0l-6 6m6-6l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Top
      </button>
    </div>
  </div>
</footer>

<script>
(() => {
  const footer = document.getElementById('rbFooter');
  const btnTop = document.getElementById('rbBackToTop');
  if (!footer) return;

  // Publish real footer height to CSS var so main & sidebar can align perfectly.
  const setFooterVar = () => {
    const h = footer.getBoundingClientRect().height || footer.offsetHeight || 0;
    if (h) document.documentElement.style.setProperty('--rb-footer-h', h + 'px');
  };

  // Initial and reactive sizing
  const onReady = () => setFooterVar();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }
  window.addEventListener('resize', setFooterVar);
  try { new ResizeObserver(setFooterVar).observe(footer); } catch(e){}

  // Back to top (smooth)
  if (btnTop) {
    btnTop.addEventListener('click', () => {
      try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
      catch(e){ window.scrollTo(0, 0); }
    });
  }
})();
</script>
