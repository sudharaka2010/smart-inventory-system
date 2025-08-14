<?php
// ========================== RB Stores — Responsive Header (include) ==========================
// Safe active-page detection (works with subfolders & query strings)
$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php');

/**
 * Return ' is-active' class or aria-current for active link.
 * Usage:
 *   class="header__link<?= navActive('dashboard.php'); ?>"
 *   <?= navActive('dashboard.php', 'aria'); ?>
 */
function navActive($files, $return = 'class'){
  global $current;
  $files  = (array)$files;
  $active = in_array($current, $files, true);
  if ($return === 'aria') return $active ? ' aria-current="page"' : '';
  return $active ? ' is-active' : '';
}

// Optional: fallback title if you use $page_title elsewhere
if (!isset($page_title)) $page_title = "RB Stores";

// Optional: base path helper (set $APP_BASE = '/admin' in pages before include if needed)
$APP_BASE = $APP_BASE ?? '';
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};
?>
<!-- Fonts / Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<!-- Header styles (provide consistent layout tokens for sidebar/main) -->
<link rel="stylesheet" href="/assets/css/header.css"/>


<!-- Fallback: publish CSS variables if header.css isn't loaded yet -->
<style>
  :root{
    /* Single source of truth for layout spacing; tweak as needed */
    --rb-header-h: clamp(56px, 9vh, 68px);
    --rb-footer-h: 56px;
    --rb-sidebar-w: 280px;
  }
  .header{ box-sizing: border-box; min-height: var(--rb-header-h); }
  .skip-link{
    position: absolute; left: -9999px; top: auto; width:1px; height:1px; overflow:hidden;
  }
  .skip-link:focus{ position: fixed; left: 1rem; top: 1rem; z-index: 1100; padding:.5rem .75rem; background:#111827; color:#fff; border-radius:.5rem; }
</style>

<header class="header" role="banner" id="rbHeader">
  <a class="skip-link" href="#main">Skip to content</a>

  <div class="header__container" style="display:flex;align-items:center;gap:1rem;padding:.5rem 1rem;">
    <!-- Mobile menu toggle -->
    <button class="header__toggle" id="menuToggle"
            aria-label="Open menu" aria-controls="navbar" aria-expanded="false"
            style="background:none;border:0;font-size:1.25rem;display:inline-flex;align-items:center;justify-content:center;">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Brand -->
    <a class="header__logo"
       href="<?= htmlspecialchars($href('dashboard.php'), ENT_QUOTES) ?>"
       aria-label="Go to Dashboard"
       style="display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;">
      <img src="/assets/images/rb.png" alt="RB Stores logo" class="header__logo-img" style="height:36px;width:auto"/>
      <span class="header__logo-text" style="font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-weight:700;color:#e5e7eb;">RB Stores</span>
    </a>

    <!-- Primary Nav -->
    <nav class="header__nav" id="navbar" role="navigation" aria-label="Primary"
         style="margin-left:auto;display:flex;gap:.5rem;align-items:center;">
      <a class="header__link<?= navActive(['index.php','dashboard.php']); ?>"<?= navActive(['index.php','dashboard.php'],'aria'); ?>
         href="<?= htmlspecialchars($href('dashboard.php'), ENT_QUOTES) ?>">
        <i class="fas fa-tachometer-alt" aria-hidden="true"></i><span>Dashboard</span>
      </a>

      <a class="header__link<?= navActive('about.php'); ?>"<?= navActive('about.php','aria'); ?>
         href="<?= htmlspecialchars($href('about.php'), ENT_QUOTES) ?>">About</a>

      <a class="header__link<?= navActive('contact.php'); ?>"<?= navActive('contact.php','aria'); ?>
         href="<?= htmlspecialchars($href('contact.php'), ENT_QUOTES) ?>">Contact</a>

      <a class="header__link<?= navActive('support.php'); ?>"<?= navActive('support.php','aria'); ?>
         href="<?= htmlspecialchars($href('support.php'), ENT_QUOTES) ?>">
        Support <i class="fas fa-headset" aria-hidden="true"></i>
      </a>

      <a class="header__link header__link--logout"
         href="<?= htmlspecialchars($href('auth/logout.php'), ENT_QUOTES) ?>">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> <span>Logout</span>
      </a>
    </nav>
  </div>
</header>

<script>
(() => {
  const header = document.getElementById('rbHeader');
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('navbar');
  if (!header || !toggle || !nav) return;

  // Utility: set CSS var to actual header height (keeps sidebar/main perfectly aligned)
  const setHeaderVar = () => {
    const h = header.getBoundingClientRect().height || header.offsetHeight || 0;
    if (h) document.documentElement.style.setProperty('--rb-header-h', h + 'px');
  };

  // Mobile menu state
  let lastFocused = null;
  const openClass = 'header__nav--active';

  const setOpen = (open) => {
    nav.classList.toggle(openClass, open);
    toggle.setAttribute('aria-expanded', String(open));
    if (open) {
      lastFocused = document.activeElement;
      const firstLink = nav.querySelector('a');
      if (firstLink) firstLink.focus();
      document.body.style.overflow = 'hidden';
    } else {
      if (lastFocused) try { lastFocused.focus(); } catch(e){}
      document.body.style.overflow = '';
    }
  };

  // Click to toggle
  toggle.addEventListener('click', () => setOpen(!nav.classList.contains(openClass)));

  // Close on ESC
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

  // Click outside to close (mobile)
  document.addEventListener('click', (e) => {
    if (window.innerWidth >= 992) return; // desktop nav is inline
    if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains(openClass)) {
      setOpen(false);
    }
  });

  // On resize: ensure menu is closed and body scroll restored when entering desktop
  const onResize = () => {
    setHeaderVar();
    if (window.innerWidth >= 992) setOpen(false);
  };

  // Initial sync
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { setHeaderVar(); });
  } else {
    setHeaderVar();
  }
  window.addEventListener('resize', onResize);
  // Also observe header size changes (logo loads, font swap, etc.)
  try {
    new ResizeObserver(setHeaderVar).observe(header);
  } catch(e) { /* older browsers: ignore */ }
})();
</script>

<!-- Recommended minimal CSS hooks for links (keep or move to header.css) -->
<style>
  .header__link{
    display:inline-flex; align-items:center; gap:.5rem;
    padding:.5rem .75rem; border-radius:.5rem; text-decoration:none;
    color:#e5e7eb; font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    white-space:nowrap;
  }
  .header__link:hover{ background:rgba(255,255,255,.06); }
  .header__link.is-active{ background:rgba(99,117,255,.16); }
  /* Mobile layout: stack nav when toggled via class .header__nav--active (style in header.css ideally) */
  @media (max-width: 991.98px){
    .header__nav{
      position: fixed; inset: var(--rb-header-h) 0 auto 0;
      display: none; flex-direction: column; gap:.25rem; padding: .75rem;
      background:#0f172a; border-bottom:1px solid rgba(148,208,236,.28); z-index: 1099;
    }
    .header__nav.header__nav--active{ display:flex; }
  }
</style>
