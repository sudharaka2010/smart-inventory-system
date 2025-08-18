<?php
// ============================ RB Stores — Sidebar (include) ============================
// - Offcanvas on mobile, fixed rail on desktop (≥ 1200px)
// - Adds/removes body.has-rail automatically based on viewport
// - Strictly scoped CSS to avoid clashes
// - Uses Bootstrap Icons via CDN (no emojis)

$APP_BASE = rtrim($APP_BASE ?? '', '/');
$href = function(string $path) use ($APP_BASE){
  $path = '/' . ltrim($path, '/');
  return ($APP_BASE === '') ? $path : $APP_BASE . $path;
};

// Optional: navActive helper (include once globally; keep here if not already loaded)
if (!function_exists('navActive')) {
  function navActive($files, $return = 'class'){
    $uri     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $current = basename($uri ?: ($_SERVER['SCRIPT_NAME'] ?? '') ?: 'index.php');
    $files   = (array)$files;
    $active  = in_array($current, $files, true);
    if ($return === 'aria') return $active ? ' aria-current="page"' : '';
    return $active ? ' is-active' : '';
  }
}
?>
<!-- Icons (Bootstrap Icons) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Sidebar CSS -->
<link rel="stylesheet" href="/assets/css/sidebar.css">

<aside id="rbSidebar" class="rb-sidebar" data-rb-scope="sidebar" data-open="false" aria-label="Primary">
  <div class="rb-sidebar__overlay" aria-hidden="true"></div>

  <nav class="rb-sidebar__panel" role="navigation" aria-label="Main">
    <div class="rb-sidebar__brand">
      <i class="bi bi-bag-check rb-logo" aria-hidden="true"></i>
      <span class="rb-name">RB Stores</span>
    </div>

    <ul class="rb-nav">
      <li>
        <a class="rb-link<?= navActive(['index.php','dashboard.php']); ?>" href="<?= $href('/dashboard.php'); ?>" <?= navActive(['index.php','dashboard.php'], 'aria'); ?>>
          <span class="i"><i class="bi bi-house-door"></i></span><span>Dashboard</span>
        </a>
      </li>

      <div class="rb-section">Sales</div>
      <li>
        <a class="rb-link<?= navActive(['billing.php','edit_billing.php','view_billing.php']); ?>" href="<?= $href('/billing.php'); ?>">
          <span class="i"><i class="bi bi-receipt-cutoff"></i></span><span>Billing</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['orders.php','order_list.php']); ?>" href="<?= $href('/order_list.php'); ?>">
          <span class="i"><i class="bi bi-card-checklist"></i></span><span>Orders</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['return.php']); ?>" href="<?= $href('/return.php'); ?>">
          <span class="i"><i class="bi bi-arrow-counterclockwise"></i></span><span>Returns</span>
        </a>
      </li>

      <div class="rb-section">Inventory</div>
      <li>
        <a class="rb-link<?= navActive(['add_inventory.php','edit_inventory.php','inventory_list.php']); ?>" href="<?= $href('/inventory_list.php'); ?>">
          <span class="i"><i class="bi bi-box-seam"></i></span><span>Inventory</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['supplier.php','supplier_list.php']); ?>" href="<?= $href('/supplier_list.php'); ?>">
          <span class="i"><i class="bi bi-truck"></i></span><span>Suppliers</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['customers.php','customer_list.php']); ?>" href="<?= $href('/customer_list.php'); ?>">
          <span class="i"><i class="bi bi-people"></i></span><span>Customers</span>
        </a>
      </li>

      <div class="rb-section">Logistics</div>
      <li>
        <a class="rb-link<?= navActive(['transport.php','edit_transport.php']); ?>" href="<?= $href('/transport.php'); ?>">
          <span class="i"><i class="bi bi-signpost-split"></i></span><span>Transport</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['vehicle.php']); ?>" href="<?= $href('/vehicle.php'); ?>">
          <span class="i"><i class="bi bi-truck-front"></i></span><span>Vehicles</span>
        </a>
      </li>

      <div class="rb-section">People & Feedback</div>
      <li>
        <a class="rb-link<?= navActive(['employee.php','employee_list.php']); ?>" href="<?= $href('/employee_list.php'); ?>">
          <span class="i"><i class="bi bi-person-badge"></i></span><span>Employees</span>
        </a>
      </li>
      <li>
        <a class="rb-link<?= navActive(['feedback.php']); ?>" href="<?= $href('/feedback.php'); ?>">
          <span class="i"><i class="bi bi-chat-left-text"></i></span><span>Feedback</span>
        </a>
      </li>

      <div class="rb-section">Reports</div>
      <li>
        <a class="rb-link<?= navActive(['reports.php']); ?>" href="<?= $href('/reports.php'); ?>">
          <span class="i"><i class="bi bi-graph-up"></i></span><span>Analytics</span>
        </a>
      </li>

      <div class="rb-section">System</div>
      <li>
        <a class="rb-link<?= navActive(['settings.php']); ?>" href="<?= $href('/settings.php'); ?>">
          <span class="i"><i class="bi bi-gear"></i></span><span>Settings</span>
        </a>
      </li>
      <li>
        <a class="rb-link" href="<?= $href('/logout.php'); ?>">
          <span class="i"><i class="bi bi-box-arrow-right"></i></span><span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<script>
// ============================ RB Sidebar Controller ============================
// - Toggle via any element: [data-sidebar-toggle]
// - Close via overlay or Escape
// - Adds body.has-rail only on desktop (≥1200px)

(() => {
  const sidebar = document.getElementById('rbSidebar');
  if (!sidebar) return;

  const mq = window.matchMedia('(min-width: 1200px)');
  const setRailClass = () => {
    if (mq.matches) document.body.classList.add('has-rail');
    else document.body.classList.remove('has-rail');
  };
  setRailClass();
  mq.addEventListener?.('change', setRailClass);

  const open  = () => sidebar.setAttribute('data-open', 'true');
  const close = () => sidebar.setAttribute('data-open', 'false');
  const toggle = () => sidebar.getAttribute('data-open') === 'true' ? close() : open();

  // Global toggle buttons (e.g., a burger in header)
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-sidebar-toggle]');
    if (t) {
      e.preventDefault();
      toggle();
    }
    if (e.target.closest('.rb-sidebar__overlay')) close();
  });

  // Keyboard: Esc closes on mobile (offcanvas)
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !mq.matches) close();
  });

  // Expose minimal API if needed
  window.RBSidebar = { open, close, toggle };
})();
</script>
