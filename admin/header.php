<?php
// ================= RB Stores — Basic Header Include =================
// Put this file at: /includes/header.php  (or similar)
// Include it at the very top of each page.

// If you keep your admin app under a subfolder like /admin, set:
$APP_BASE = isset($APP_BASE) ? rtrim($APP_BASE, '/') : '/admin';

// --- Simple helpers (basic only) ---
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('route')) {
  // For app pages that live under $APP_BASE (e.g. /admin/billing.php)
  function route($path, $base){
    $path = '/' . ltrim($path, '/');
    return ($base === '' ? $path : $base . $path);
  }
}
if (!function_exists('asset')) {
  // For static files from the site root, never under /admin
  function asset($path){
    return '/' . ltrim($path, '/');
  }
}
if (!function_exists('navActive')) {
  function navActive($files, $return='class'){
    $uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php');
    $files   = (array)$files;
    $is      = in_array($current, $files, true);
    if ($return === 'aria') return $is ? ' aria-current="page"' : '';
    return $is ? ' is-active' : '';
  }
}
?>
<!-- Bootstrap Icons (CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Header CSS from site root -->
<link rel="stylesheet" href="<?= h(asset('assets/css/header.css')); ?>">

<!-- Apply saved theme early (prevents flash) -->
<script>
(function(){
  try{
    var t = localStorage.getItem('rb-theme');
    if (t) document.documentElement.setAttribute('data-theme', t);
  }catch(e){}
})();
</script>

<header class="rb-header" data-rb-scope="header">
  <div class="rb-header__inner">
    <!-- Sidebar toggle (your sidebar.js should listen to [data-sidebar-toggle]) -->
    <button type="button" class="rb-icon-btn rb-header__menu" data-sidebar-toggle aria-label="Toggle sidebar">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <!-- Search -->
    <form class="rb-header__search" action="<?= h(route('search.php', $APP_BASE)); ?>" method="get" role="search">
      <label for="rbHeaderSearch" class="sr-only">Search</label>
      <input id="rbHeaderSearch" class="rb-input" type="search" name="q"
             placeholder="Search orders, customers, items…" aria-label="Search">
    </form>

    <!-- Actions -->
    <div class="rb-header__actions">
      <a class="rb-chip" href="<?= h(route('billing.php', $APP_BASE)); ?>" title="New Bill"<?= navActive(['billing.php'], 'aria'); ?>>
        <i class="bi bi-plus-lg" aria-hidden="true"></i><span>New Bill</span>
      </a>

      <button type="button" class="rb-icon-btn" id="rbThemeBtn" aria-label="Toggle theme">
        <i class="bi bi-brightness-high" aria-hidden="true"></i>
      </button>

      <!-- User -->
      <div class="rb-user">
        <button type="button" class="rb-user__btn" id="rbUserBtn" aria-expanded="false">
          <i class="bi bi-person-circle rb-avatar-ico" aria-hidden="true"></i>
          <span class="rb-user__name">Admin</span>
          <i class="bi bi-caret-down-fill" aria-hidden="true"></i>
        </button>
        <div class="rb-menu" id="rbUserMenu" hidden>
          <a href="<?= h(route('profile.php',  $APP_BASE)); ?>">Profile</a>
          <a href="<?= h(route('settings.php', $APP_BASE)); ?>">Settings</a>
          <hr>
          <!-- TODO: switch to POST + CSRF for production -->
          <a href="<?= h(route('logout.php',   $APP_BASE)); ?>">Sign out</a>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
// ================= RB Stores — Basic Header JS =================
// - User dropdown: click to open/close, click outside or Esc to close
// - Theme toggle: toggles light/dark and remembers in localStorage
(function(){
  var userBtn  = document.getElementById('rbUserBtn');
  var userMenu = document.getElementById('rbUserMenu');
  var themeBtn = document.getElementById('rbThemeBtn');

  // User menu
  if (userBtn && userMenu){
    function closeMenu(){
      if (!userMenu.hasAttribute('hidden')){
        userMenu.setAttribute('hidden','');
        userBtn.setAttribute('aria-expanded','false');
      }
    }
    function openMenu(){
      userMenu.removeAttribute('hidden');
      userBtn.setAttribute('aria-expanded','true');
    }
    userBtn.addEventListener('click', function(e){
      e.stopPropagation();
      if (userMenu.hasAttribute('hidden')) openMenu(); else closeMenu();
    });
    document.addEventListener('click', function(e){
      if (!userMenu.contains(e.target) && e.target !== userBtn) closeMenu();
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeMenu();
    });
  }

  // Theme toggle
  if (themeBtn){
    themeBtn.addEventListener('click', function(){
      var root = document.documentElement;
      var next = (root.getAttribute('data-theme') === 'light') ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      try{ localStorage.setItem('rb-theme', next); }catch(e){}
    });
  }

  // Desktop rail flag (for CSS that offsets header/content when rail is docked)
  try{
    var mql = window.matchMedia('(min-width: 1200px)');
    function apply(){ document.body.classList.toggle('has-rail', mql.matches); }
    if (mql.addEventListener) mql.addEventListener('change', apply); else mql.addListener(apply);
    apply();
  }catch(e){}
})();
</script>
