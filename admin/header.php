<?php
// ========================== RB Stores — Header (include) ===========================
// Include this at the top of pages (after setting $APP_BASE if needed)

$APP_BASE = rtrim($APP_BASE ?? '', '/');
$href = function(string $path) use ($APP_BASE){
  $path = '/' . ltrim($path, '/');
  return ($APP_BASE === '') ? $path : $APP_BASE . $path;
};

// Active detection (works with subfolders & query strings)
$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php');
if (!function_exists('navActive')) {
  function navActive($files, $return = 'class'){
    global $current;
    $files  = (array)$files;
    $active = in_array($current, $files, true);
    if ($return === 'aria') return $active ? ' aria-current="page"' : '';
    return $active ? ' is-active' : '';
  }
}
?>
<!-- Icons (Bootstrap Icons CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Header CSS -->
<link rel="stylesheet" href="/assets/css/header.css">

<header class="rb-header" data-rb-scope="header">
  <div class="rb-header__inner">
    <!-- SAME toggle API as sidebar: data-sidebar-toggle -->
    <button class="rb-icon-btn rb-header__menu" data-sidebar-toggle aria-controls="rbSidebar" aria-expanded="false" aria-label="Toggle sidebar">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <a class="rb-header__brand" href="<?= $href('/dashboard.php'); ?>">
      <i class="bi bi-bag-check rb-logo" aria-hidden="true"></i>
      <span class="rb-title">RB Stores</span>
    </a>

    <form class="rb-header__search" action="<?= $href('/search.php'); ?>" method="get" role="search">
      <input class="rb-input" type="search" name="q" placeholder="Search orders, customers, items…" aria-label="Search" />
    </form>

    <div class="rb-header__actions">
      <a class="rb-chip" href="<?= $href('/billing.php'); ?>" title="New Bill">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <span>New Bill</span>
      </a>

      <button class="rb-icon-btn" id="rbThemeBtn" aria-label="Toggle theme">
        <i class="bi bi-brightness-high" aria-hidden="true"></i>
      </button>

      <div class="rb-user">
        <button class="rb-user__btn" id="rbUserBtn" aria-expanded="false" aria-haspopup="menu">
          <img src="<?= $href('/assets/img/avatar.png'); ?>" alt="" class="rb-avatar" />
          <span class="rb-user__name">Admin</span>
          <i class="bi bi-caret-down-fill" aria-hidden="true"></i>
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
/* Header behavior (no sidebar toggling here—sidebar.js handles [data-sidebar-toggle]) */
(() => {
  const $ = (s, r=document)=>r.querySelector(s);
  const userBtn  = $("#rbUserBtn");
  const userMenu = $("#rbUserMenu");
  const themeBtn = $("#rbThemeBtn");

  // User menu (click outside to close)
  if (userBtn && userMenu){
    userBtn.addEventListener("click", (e)=>{
      e.stopPropagation();
      const open = !userMenu.hasAttribute("hidden");
      userMenu.toggleAttribute("hidden", open);
      userBtn.setAttribute("aria-expanded", String(!open));
    });
    document.addEventListener("click", () => userMenu.setAttribute("hidden",""));
  }

  // Theme toggle (dark <-> light)
  if (themeBtn){
    themeBtn.addEventListener("click", ()=>{
      const root = document.documentElement;
      const next = root.getAttribute("data-theme")==="light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem("rb-theme", next); } catch(e){}
    });
    try {
      const saved = localStorage.getItem("rb-theme");
      if (saved) document.documentElement.setAttribute("data-theme", saved);
    } catch(e){}
  }
})();
</script>
