<?php
// RB Stores — Responsive Header (include)

// Robust active-page detection (works with subfolders & query strings)
$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? ''));

/**
 * Return " is-active" class or aria-current for active link.
 * Usage:
 *   class="header__link<?= navActive('dashboard.php'); ?>"
 *   <?= navActive('dashboard.php', 'aria'); ?>
 */
function navActive($files, $return = 'class') {
  global $current;
  $files  = (array)$files;
  $active = in_array($current, $files, true);
  if ($return === 'aria') return $active ? ' aria-current="page"' : '';
  return $active ? ' is-active' : '';
}

// Optional: fallback title if you use $page_title elsewhere
if (!isset($page_title)) $page_title = "RB Stores";
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<link rel="stylesheet" href="/assets/css/header.css"/>

<header class="header" role="banner">
  <a class="skip-link" href="#main">Skip to content</a>

  <div class="header__container">
    <button class="header__toggle" id="menuToggle"
            aria-label="Open menu" aria-controls="navbar" aria-expanded="false">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <div class="header__logo" role="link" tabindex="0" aria-label="Go to Dashboard"
         onclick="window.location='dashboard.php'"
         onkeypress="if(event.key==='Enter') window.location='dashboard.php'">
      <img src="/assets/images/rb.png" alt="RB Stores logo" class="header__logo-img"/>
      <span class="header__logo-text">RB Stores</span>
    </div>

    <nav class="header__nav" id="navbar" role="navigation" aria-label="Primary">
      <a class="header__link<?= navActive('dashboard.php'); ?>"<?= navActive('dashboard.php','aria'); ?> href="dashboard.php">
        <i class="fas fa-tachometer-alt" aria-hidden="true"></i><span>Dashboard</span>
      </a>
      <a class="header__link<?= navActive('about.php'); ?>"<?= navActive('about.php','aria'); ?> href="about.php">About</a>
      <a class="header__link<?= navActive('contact.php'); ?>"<?= navActive('contact.php','aria'); ?> href="contact.php">Contact</a>
      <a class="header__link<?= navActive('support.php'); ?>"<?= navActive('support.php','aria'); ?> href="support.php">
        Support <i class="fas fa-headset" aria-hidden="true"></i>
      </a>
      <a class="header__link header__link--logout" href="/auth/logout.php">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> <span>Logout</span>
      </a>
    </nav>
  </div>
</header>

<script>
(() => {
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('navbar');
  if (!toggle || !nav) return;

  let lastFocused = null;

  const setOpen = (open) => {
    nav.classList.toggle('header__nav--active', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (open) {
      lastFocused = document.activeElement;
      const firstLink = nav.querySelector('a');
      if (firstLink) firstLink.focus();
      document.body.style.overflow = 'hidden';
    } else {
      if (lastFocused) lastFocused.focus();
      document.body.style.overflow = '';
    }
  };

  toggle.addEventListener('click', () => setOpen(!nav.classList.contains('header__nav--active')));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) setOpen(false); });
  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains('header__nav--active')) setOpen(false);
  });
})();
</script>
