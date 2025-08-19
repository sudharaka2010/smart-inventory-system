<?php
// ========================== RB Stores — Header (include) ===========================
// Include at the very top of each page (after setting $APP_BASE if needed)

$APP_BASE = rtrim($APP_BASE ?? '', '/');

if (!function_exists('rb_href')) {
  function rb_href(string $path, string $base): string {
    // Normalize leading "./" and ensure single leading slash
    $path = preg_replace('#^(\./)+#', '', $path);
    $path = '/' . ltrim($path, '/');
    return ($base === '') ? $path : $base . $path;
  }
}

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

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

// Small helper closure so your calls stay terse
$href = fn(string $path) => rb_href($path, $APP_BASE);
?>
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Header CSS -->
<link rel="stylesheet" href="<?= h($href('/assets/css/header.css')); ?>">

<!-- (Optional) Prevent theme flash: set data-theme ASAP -->
<script>
(function(){try{
  var t = localStorage.getItem("rb-theme");
  if (t) document.documentElement.setAttribute("data-theme", t);
}catch(e){}})();
</script>

<header class="rb-header" data-rb-scope="header" role="banner">
  <div class="rb-header__inner">
    <!-- SAME toggle API as sidebar: data-sidebar-toggle (handled by sidebar.js) -->
    <button type="button" class="rb-icon-btn rb-header__menu" data-sidebar-toggle
            aria-controls="rbSidebar" aria-expanded="false" aria-label="Toggle sidebar">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <!-- Brand (uncomment and adjust if you want the logo visible) -->
    <!--
    <a class="rb-brand" href="<?= h($href('/')); ?>">
      <img src="<?= h($href('/assets/img/logo-dark.svg')); ?>" alt="RB Stores" class="only-dark">
      <img src="<?= h($href('/assets/img/logo-light.svg')); ?>" alt="RB Stores" class="only-light">
    </a>
    -->

    <!-- Center search (hidden <640px) -->
    <form class="rb-header__search" action="<?= h($href('/search.php')); ?>" method="get" role="search">
      <label class="sr-only" for="rbHeaderSearch">Search</label>
      <input id="rbHeaderSearch" class="rb-input" type="search" name="q"
             placeholder="Search orders, customers, items…" aria-label="Search" />
    </form>

    <!-- Actions (right) -->
    <div class="rb-header__actions" role="navigation" aria-label="Header actions">
      <a class="rb-chip" href="<?= h($href('/billing.php')); ?>" title="New Bill"<?= navActive(['billing.php'], 'aria'); ?>>
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <span>New Bill</span>
      </a>

      <button type="button" class="rb-icon-btn" id="rbThemeBtn" aria-label="Toggle theme">
        <i class="bi bi-brightness-high" aria-hidden="true"></i>
      </button>

      <!-- User menu (use disclosure pattern instead of ARIA menu for simplicity) -->
      <div class="rb-user">
        <button type="button" class="rb-user__btn" id="rbUserBtn" aria-expanded="false"
                aria-controls="rbUserMenu">
          <i class="bi bi-person-circle rb-avatar-ico" aria-hidden="true"></i>
          <span class="rb-user__name">Admin</span>
          <i class="bi bi-caret-down-fill" aria-hidden="true"></i>
        </button>

        <div class="rb-menu" id="rbUserMenu" hidden>
          <a href="<?= h($href('/profile.php')); ?>">Profile</a>
          <a href="<?= h($href('/settings.php')); ?>">Settings</a>
          <hr />
          <!-- TODO: Prefer POST with CSRF for sign out -->
          <a href="<?= h($href('/logout.php')); ?>">Sign out</a>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
/* ========================== RB Stores — Header behavior ==========================
   - Theme toggle persists to localStorage ("rb-theme")
   - User menu toggle with click-outside & Esc close, returns focus to button
   - Sidebar toggle handled by your sidebar.js via [data-sidebar-toggle]
================================================================================= */
(() => {
  const $ = (s, r=document)=>r.querySelector(s);
  const userBtn  = $("#rbUserBtn");
  const userMenu = $("#rbUserMenu");
  const themeBtn = $("#rbThemeBtn");

  // User menu (disclosure)
  if (userBtn && userMenu){
    const closeMenu = () => {
      if (!userMenu.hasAttribute("hidden")) {
        userMenu.setAttribute("hidden", "");
        userBtn.setAttribute("aria-expanded", "false");
        userBtn.focus();
      }
    };
    const openMenu = () => {
      userMenu.removeAttribute("hidden");
      userBtn.setAttribute("aria-expanded", "true");
      // move focus to first item for accessibility
      const first = userMenu.querySelector("a,button");
      if (first) first.focus();
    };

    userBtn.addEventListener("click", (e)=>{
      e.stopPropagation();
      const open = !userMenu.hasAttribute("hidden");
      open ? closeMenu() : openMenu();
    });

    document.addEventListener("click", (e)=>{
      if (!userMenu.contains(e.target) && e.target !== userBtn) closeMenu();
    });

    document.addEventListener("keydown", (e)=>{
      if (e.key === "Escape") closeMenu();
    });
  }

  // Theme toggle (dark <-> light)
  if (themeBtn){
    themeBtn.addEventListener("click", ()=>{
      const root = document.documentElement;
      const next = root.getAttribute("data-theme")==="light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem("rb-theme", next); } catch(e){}
    });
  }
})();
</script>

<script>
/* RB Stores — desktop rail detector
   - Adds body.has-rail when viewport ≥1200px (rail is docked)
   - Optional: add/remove body.rail--mini if you have a mini/collapsed mode
*/
(() => {
  const mql = window.matchMedia('(min-width: 1200px)');
  const apply = () => {
    document.body.classList.toggle('has-rail', mql.matches);
    // document.body.classList.toggle('rail--mini', isMiniRail());
  };
  (mql.addEventListener ? mql.addEventListener('change', apply) : mql.addListener(apply));
  apply();
})();
</script>
