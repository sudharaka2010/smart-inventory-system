<?php
// ============================ RB Stores — Sidebar (include) ============================
// - Offcanvas on mobile, fixed rail on desktop
// - Adds body.has-rail to push main content on wide screens
// - Strictly scoped CSS to avoid clashes

$APP_BASE = rtrim($APP_BASE ?? '', '/');
$href = function(string $path) use ($APP_BASE){
  $path = '/' . ltrim($path, '/');
  return ($APP_BASE === '') ? $path : $APP_BASE . $path;
};

// Load CSS once


// Ensure body has the helper class to reserve rail space on desktop
echo '<script>document.body.classList.add("has-rail");</script>';
?>

<link rel="stylesheet" href="/assets/css/sidebar.css">

<aside id="rbSidebar" class="rb-sidebar" data-rb-scope="sidebar" data-open="false" aria-label="Primary">
  <div class="rb-sidebar__overlay" aria-hidden="true"></div>
  <nav class="rb-sidebar__panel" role="navigation">
    <div class="rb-sidebar__brand">
      <span class="rb-logo">🛒</span>
      <span class="rb-name">RB Stores</span>
    </div>

    <ul class="rb-nav">
      <li><a class="rb-link<?= navActive(['index.php','dashboard.php']); ?>" href="<?= $href('/dashboard.php'); ?>" <?= navActive(['index.php','dashboard.php'], 'aria'); ?>>
        <span class="i">
          <svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 12l9-7 9 7v7a2 2 0 0 1-2 2h-4v-6H9v6H5a2 2 0 0 1-2-2z" fill="currentColor"/></svg>
        </span><span>Dashboard</span></a></li>

      <div class="rb-section">Sales</div>
      <li><a class="rb-link<?= navActive(['billing.php','edit_billing.php','view_billing.php']); ?>" href="<?= $href('/billing.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 7h16v10H4zM8 7V5h8v2" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Billing</span></a></li>
      <li><a class="rb-link<?= navActive(['orders.php','order_list.php']); ?>" href="<?= $href('/order_list.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Orders</span></a></li>
      <li><a class="rb-link<?= navActive(['return.php']); ?>" href="<?= $href('/return.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 5v6l4 2" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"/></svg>
      </span><span>Returns</span></a></li>

      <div class="rb-section">Inventory</div>
      <li><a class="rb-link<?= navActive(['add_inventory.php','edit_inventory.php','inventory_list.php']); ?>" href="<?= $href('/inventory_list.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 7h18v10H3zM7 7V5h10v2" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Inventory</span></a></li>
      <li><a class="rb-link<?= navActive(['supplier.php','supplier_list.php']); ?>" href="<?= $href('/supplier_list.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5Z" fill="currentColor"/></svg>
      </span><span>Suppliers</span></a></li>
      <li><a class="rb-link<?= navActive(['customers.php','customer_list.php']); ?>" href="<?= $href('/customer_list.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 7a4 4 0 1 1-4 4 4 4 0 0 1 4-4Z M4 20a8 8 0 0 1 16 0" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Customers</span></a></li>

      <div class="rb-section">Logistics</div>
      <li><a class="rb-link<?= navActive(['transport.php','edit_transport.php']); ?>" href="<?= $href('/transport.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 16h13l3-5h2v8h-3" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Transport</span></a></li>
      <li><a class="rb-link<?= navActive(['vehicle.php']); ?>" href="<?= $href('/vehicle.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="7" cy="17" r="2" /><circle cx="17" cy="17" r="2" /><path d="M5 17H3v-5l3-4h8l3 4h4v5h-2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
      </span><span>Vehicles</span></a></li>

      <div class="rb-section">People & Feedback</div>
      <li><a class="rb-link<?= navActive(['employee.php','employee_list.php']); ?>" href="<?= $href('/employee_list.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5Z" fill="currentColor"/></svg>
      </span><span>Employees</span></a></li>
      <li><a class="rb-link<?= navActive(['feedback.php']); ?>" href="<?= $href('/feedback.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 4h16v12H7l-3 3z" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Feedback</span></a></li>

      <div class="rb-section">Reports</div>
      <li><a class="rb-link<?= navActive(['reports.php']); ?>" href="<?= $href('/reports.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 20h16M6 16V8m6 8V4m6 16v-6" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Analytics</span></a></li>

      <div class="rb-section">System</div>
      <li><a class="rb-link<?= navActive(['settings.php']); ?>" href="<?= $href('/settings.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm9 4a9 9 0 1 1-9-9" stroke="currentColor" fill="none" stroke-width="2"/></svg>
      </span><span>Settings</span></a></li>
      <li><a class="rb-link" href="<?= $href('/logout.php'); ?>"><span class="i">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M10 17l5-5-5-5M15 12H3" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"/></svg>
      </span><span>Logout</span></a></li>
    </ul>
  </nav>
</aside>
