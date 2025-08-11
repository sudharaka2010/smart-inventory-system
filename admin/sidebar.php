<?php
// ===== RB Stores — Isolated Sidebar (no global CSS bleed) =====

// current file for active states
$urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$current = basename($urlPath) ?: 'index.php';

// helpers (same API you already use)
function isActive($files){ global $current; $files=(array)$files; return in_array($current,$files,true) ? 'active' : ''; }
function isOpen($files){ global $current; $files=(array)$files; return in_array($current,$files,true); }

// optional base path to work from any folder
$APP_BASE = $APP_BASE ?? ''; // e.g. set $APP_BASE = '/admin' before including

// load the isolated CSS (safe to include on all pages)
?>
<link rel="stylesheet" href="/assets/css/sidebar.css"/>

<aside class="rb-sb" data-rb-scope>
  <!-- mobile header (optional) -->
  <div class="rb-sb-top">
    <button class="rb-sb-toggle" aria-controls="rb-sb-panel" aria-expanded="false">☰</button>
    <span class="rb-sb-brand">RB Stores</span>
  </div>

  <div id="rb-sb-panel" class="rb-sb-panel">
    <!-- brand (desktop) -->
    <div class="rb-sb-logo">
      <span class="rb-sb-logo-mark">🏬</span>
      <span class="rb-sb-logo-text">RB Stores</span>
    </div>

    <!-- groups use details/summary; PHP opens the right one -->
    <?php $stockFiles = ['add_inventory.php','inventory.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($stockFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Stock</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_inventory.php'); ?>" href="<?=$APP_BASE?>/add_inventory.php">➕ Add Inventory</a>
        <a class="rb-sb-link <?php echo isActive('inventory.php'); ?>" href="<?=$APP_BASE?>/inventory.php">📃 View Inventory</a>
      </nav>
    </details>

    <?php $billingFiles = ['billing.php','view_billing.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($billingFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Billing</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('billing.php'); ?>" href="<?=$APP_BASE?>/billing.php">➕ Billing Invoice</a>
        <a class="rb-sb-link <?php echo isActive('view_billing.php'); ?>" href="<?=$APP_BASE?>/view_billing.php">📃 View Invoices</a>
      </nav>
    </details>

    <?php $supplierFiles = ['add_supplier.php','supplier.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($supplierFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Supplier</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_supplier.php'); ?>" href="<?=$APP_BASE?>/add_supplier.php">➕ Add Supplier</a>
        <a class="rb-sb-link <?php echo isActive('supplier.php'); ?>" href="<?=$APP_BASE?>/supplier.php">📃 View Suppliers</a>
      </nav>
    </details>

    <?php $employeeFiles = ['add_employee.php','employee.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($employeeFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Employee</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_employee.php'); ?>" href="<?=$APP_BASE?>/add_employee.php">➕ Add Employee</a>
        <a class="rb-sb-link <?php echo isActive('employee.php'); ?>" href="<?=$APP_BASE?>/employee.php">📃 View Employees</a>
      </nav>
    </details>

    <?php $transportFiles = ['add_transport.php','transport.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($transportFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Transport</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_transport.php'); ?>" href="<?=$APP_BASE?>/add_transport.php">➕ Add Transport</a>
        <a class="rb-sb-link <?php echo isActive('transport.php'); ?>" href="<?=$APP_BASE?>/transport.php">📃 View Transport</a>
      </nav>
    </details>

    <?php $customerFiles = ['add_customer.php','customer.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($customerFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Customer</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_customer.php'); ?>" href="<?=$APP_BASE?>/add_customer.php">➕ Add Customer</a>
        <a class="rb-sb-link <?php echo isActive('customer.php'); ?>" href="<?=$APP_BASE?>/customer.php">📃 View Customers</a>
      </nav>
    </details>

    <?php $returnFiles = ['add_return.php','view_return.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($returnFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Returns</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_return.php'); ?>" href="<?=$APP_BASE?>/add_return.php">➕ Add Return</a>
        <a class="rb-sb-link <?php echo isActive('view_return.php'); ?>" href="<?=$APP_BASE?>/view_return.php">📃 View Returns</a>
      </nav>
    </details>

    <?php $feedbackFiles = ['add_feedback.php','feedback.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($feedbackFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Feedback</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('add_feedback.php'); ?>" href="<?=$APP_BASE?>/add_feedback.php">➕ Add Feedback</a>
        <a class="rb-sb-link <?php echo isActive('feedback.php'); ?>" href="<?=$APP_BASE?>/feedback.php">💬 View Feedback</a>
      </nav>
    </details>

    <?php $reportFiles = ['sales_report.php','inventory_report.php']; ?>
    <details class="rb-sb-group" <?php echo isOpen($reportFiles) ? 'open':''; ?>>
      <summary class="rb-sb-summary"><span>Reports</span></summary>
      <nav class="rb-sb-nav">
        <a class="rb-sb-link <?php echo isActive('sales_report.php'); ?>" href="<?=$APP_BASE?>/sales_report.php">📄 Sales Report</a>
        <a class="rb-sb-link <?php echo isActive('inventory_report.php'); ?>" href="<?=$APP_BASE?>/inventory_report.php">📄 Inventory Report</a>
      </nav>
    </details>

    <div class="rb-sb-footer">
      <a class="rb-sb-link" href="<?=$APP_BASE?>/logout.php">⎋ Logout</a>
    </div>
  </div>
</aside>

<script>
// tiny mobile toggle (scoped)
(function(){
  const scope = document.querySelector('[data-rb-scope]');
  if(!scope) return;
  const btn = scope.querySelector('.rb-sb-toggle');
  const panel = scope.querySelector('#rb-sb-panel');
  if(!btn || !panel) return;

  btn.addEventListener('click', () => {
    const open = panel.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
