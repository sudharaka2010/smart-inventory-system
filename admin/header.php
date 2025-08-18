<?php
// ========================== RB Stores — Header (include) ===========================
// Drop this file anywhere and include it from your pages.
// Set $APP_BASE (e.g., '/admin') BEFORE including if you deploy under a subfolder.

$APP_BASE = rtrim($APP_BASE ?? '', '/');
$href = function(string $path) use ($APP_BASE){
  $path = '/' . ltrim($path, '/');
  return ($APP_BASE === '') ? $path : $APP_BASE . $path;
};

// Safe "active" detection (works with subfolders & query strings)
$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php');
function navActive($files, $return = 'class'){
  global $current;
  $files  = (array)$files;
  $active = in_array($current, $files, true);
  if ($return === 'aria') return $active ? ' aria-current="page"' : '';
  return $active ? ' is-active' : '';
}

// Load CSS once
if (!defined('RB_HEADER_CSS')) {
  echo '<link rel="stylesheet" href="'.$href('/assets/css/header.css').'">' . PHP_EOL;
  define('RB_HEADER_CSS', 1);
}
?>

<header class="rb-header" data-rb-scope="header">
  <div class="rb-header__inner">
    <button class="rb-icon-btn rb-header__menu" id="rbMenuBtn" aria-controls="rbSidebar" aria-expanded="false" aria-label="Toggle sidebar">
      <!-- hamburger -->
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>

    <a class="rb-header__brand" href="<?= $href('/dashboard.php'); ?>">
      <span class="rb-logo" aria-hidden="true">🛒</span>
      <span class="rb-title">RB Stores</span>
    </a>

    <form class="rb-header__search" action="<?= $href('/search.php'); ?>" method="get" role="search">
      <input class="rb-input" type="search" name="q" placeholder="Search orders, customers, items…" aria-label="Search" />
    </form>

    <div class="rb-header__actions">
      <a class="rb-chip" href="<?= $href('/billing.php'); ?>" title="New Bill">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span>New Bill</span>
      </a>

      <button class="rb-icon-btn" id="rbThemeBtn" aria-label="Toggle theme">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.5 0-1-.1-1.5A7.5 7.5 0 0 1 12 3Z" fill="currentColor"/></svg>
      </button>

      <div class="rb-user">
        <button class="rb-user__btn" id="rbUserBtn" aria-expanded="false" aria-haspopup="menu">
          <img src="<?= $href('/assets/img/avatar.png'); ?>" alt="" class="rb-avatar" />
          <span class="rb-user__name">Admin</span>
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <div class="rb-menu" id="rbUserMenu" role="menu" hidden>
          <a role="menuitem" href="<?= $href('/profile.php'); ?>">Profile</a>
          <a role="menuitem" href="<?= $href('/settings.php'); ?>">Settings</a>
          <hr />
          <a role="menuitem" href="<?= $href('/logout.php'); ?>">Sign out</a>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
(function(){
  const $ = (s, r=document)=>r.querySelector(s);
  const menuBtn  = $("#rbMenuBtn");
  const sidebar  = $("#rbSidebar");
  const userBtn  = $("#rbUserBtn");
  const userMenu = $("#rbUserMenu");
  const themeBtn = $("#rbThemeBtn");

  // Sidebar toggle
  if (menuBtn && sidebar){
    menuBtn.addEventListener("click", () => {
      const open = sidebar.getAttribute("data-open") === "true";
      sidebar.setAttribute("data-open", String(!open));
      menuBtn.setAttribute("aria-expanded", String(!open));
      document.body.classList.toggle("rb-no-scroll", !open);
    });
  }

  // Close sidebar on overlay click
  document.addEventListener("click", (e)=>{
    if (!sidebar) return;
    if (e.target && e.target.classList && e.target.classList.contains("rb-sidebar__overlay")){
      sidebar.setAttribute("data-open","false");
      document.body.classList.remove("rb-no-scroll");
      $("#rbMenuBtn")?.setAttribute("aria-expanded","false");
    }
  });

  // User menu
  if (userBtn && userMenu){
    userBtn.addEventListener("click", (e)=>{
      e.stopPropagation();
      const open = userMenu.hasAttribute("hidden") ? false : true;
      userMenu.toggleAttribute("hidden", open);
      userBtn.setAttribute("aria-expanded", String(!open));
    });
    document.addEventListener("click", () => userMenu.setAttribute("hidden",""));
  }

  // Theme toggle (dark <-> light). Default = dark if no tokens.
  if (themeBtn){
    themeBtn.addEventListener("click", ()=>{
      const root = document.documentElement;
      const next = root.getAttribute("data-theme")==="light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem("rb-theme", next); } catch(e){}
    });
    // boot
    try {
      const saved = localStorage.getItem("rb-theme");
      if (saved) document.documentElement.setAttribute("data-theme", saved);
    } catch(e){}
  }
})();
</script>
