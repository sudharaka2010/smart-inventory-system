<?php
// ============================================================================
// RB Stores — Sidebar (Bootstrap 5 Offcanvas on mobile, Fixed rail on desktop)
// ----------------------------------------------------------------------------
// • Scoped: styles only apply inside [data-rb-scope="sidebar"] (see sidebar.css)
// • Mobile: Offcanvas; Desktop (≥1200px): fixed left rail with CSS only
// • Safe active detection: ignores query strings
// • Config flags below control whether we load vendor CSS/JS here
// ============================================================================

// ---------- ROUTING / ENV ----------
$APP_BASE = $APP_BASE ?? ''; // e.g., '/admin' if your pages live under /admin

$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};

// Current file for "active" highlights
$urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$current = basename($urlPath ?: ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

// ---------- CONFIG (override BEFORE include) ----------
$RB_SIDEBAR_LOAD_VENDOR = $RB_SIDEBAR_LOAD_VENDOR ?? false; // Bootstrap & Icons CSS/JS
$RB_SIDEBAR_LOAD_CSS    = $RB_SIDEBAR_LOAD_CSS    ?? false; // Load /assets/css/sidebar.css here
$RB_SIDEBAR_SHOW_BRAND  = $RB_SIDEBAR_SHOW_BRAND  ?? false; // Desktop brand row on top of menu
$RB_LOGOUT_PATH         = $RB_LOGOUT_PATH         ?? 'auth/logout.php';

// ---------- HELPERS ----------
function isActive($files){
  global $current; $files = (array)$files;
  return in_array($current, $files, true) ? 'active' : '';
}
function isOpen($files){
  global $current; $files = (array)$files;
  return in_array($current, $files, true) ? 'show' : '';
}
?>
<?php if ($RB_SIDEBAR_LOAD_VENDOR): ?>
  <!-- Vendor CSS (Bootstrap & Icons) — disable if your layout already loads these -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php endif; ?>

<?php if ($RB_SIDEBAR_LOAD_CSS): ?>
  <!-- Scoped sidebar CSS (skip if you import it via app.css) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($href('assets/css/sidebar.css'), ENT_QUOTES) ?>?v=2025-08-15">
<?php endif; ?>

<!-- Mobile Top Bar (hamburger) -->
<header class="rb-sb-topbar d-xl-none" role="banner" data-rb-scope="sidebar">
  <button class="btn rb-sb-topbar-btn fs-3" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#rbSidebar"
          aria-controls="rbSidebar" aria-label="Open sidebar">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>
  <div class="ms-2 fw-semibold">Menu</div>
</header>

<!-- Sidebar (Offcanvas on mobile, fixed rail on desktop) -->
<aside id="rbSidebar"
       class="offcanvas offcanvas-start rb-sb offcanvas-shadow"
       tabindex="-1"
       aria-labelledby="rbSidebarLabel"
       data-bs-scroll="true"
       data-bs-backdrop="false"
       data-rb-scope="sidebar">

  <!-- Offcanvas header (hidden on desktop via CSS) -->
  <div class="offcanvas-header d-xl-none">
    <h5 class="offcanvas-title" id="rbSidebarLabel">Navigation</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <nav class="offcanvas-body p-0 d-flex flex-column" role="navigation" aria-label="Main">
    <?php if ($RB_SIDEBAR_SHOW_BRAND): ?>
      <div class="rb-sb-brand d-none d-xl-flex align-items-center gap-2 px-3 py-3">
        <i class="bi bi-shop fs-5" aria-hidden="true"></i>
        <span class="rb-sb-brand-text">RB Stores</span>
      </div>
    <?php endif; ?>

    <div class="accordion" id="rbAccordion">

      <!-- Stock -->
      <?php $stockFiles = ['add_inventory.php','inventory.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingStock">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuStock"
                  aria-expanded="<?= isOpen($stockFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuStock">
            <i class="bi bi-box-seam me-2" aria-hidden="true"></i> Stock
          </button>
        </h2>
        <div id="menuStock" class="accordion-collapse collapse <?= isOpen($stockFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_inventory.php'); ?>"
                   href="<?= htmlspecialchars($href('add_inventory.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_inventory.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Inventory
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('inventory.php'); ?>"
                   href="<?= htmlspecialchars($href('inventory.php'), ENT_QUOTES); ?>"
                   <?= $current==='inventory.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Inventory
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Billing -->
      <?php $billingFiles = ['billing.php','view_billing.php','edit_billing.php','invoice.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingBilling">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuBilling"
                  aria-expanded="<?= isOpen($billingFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuBilling">
            <i class="bi bi-receipt me-2" aria-hidden="true"></i> Billing
          </button>
        </h2>
        <div id="menuBilling" class="accordion-collapse collapse <?= isOpen($billingFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('billing.php'); ?>"
                   href="<?= htmlspecialchars($href('billing.php'), ENT_QUOTES); ?>"
                   <?= $current==='billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-file-earmark-plus me-2" aria-hidden="true"></i>Billing Invoice
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('view_billing.php'); ?>"
                   href="<?= htmlspecialchars($href('view_billing.php'), ENT_QUOTES); ?>"
                   <?= $current==='view_billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-journal-text me-2" aria-hidden="true"></i>View Invoices
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('edit_billing.php'); ?>"
                   href="<?= htmlspecialchars($href('edit_billing.php'), ENT_QUOTES); ?>"
                   <?= $current==='edit_billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Billing
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('invoice.php'); ?>"
                   href="<?= htmlspecialchars($href('invoice.php'), ENT_QUOTES); ?>"
                   <?= $current==='invoice.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-printer me-2" aria-hidden="true"></i>Invoice Preview
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Supplier -->
      <?php $supplierFiles = ['add_supplier.php','supplier.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingSupplier">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuSupplier"
                  aria-expanded="<?= isOpen($supplierFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuSupplier">
            <i class="bi bi-truck me-2" aria-hidden="true"></i> Supplier
          </button>
        </h2>
        <div id="menuSupplier" class="accordion-collapse collapse <?= isOpen($supplierFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_supplier.php'); ?>"
                   href="<?= htmlspecialchars($href('add_supplier.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_supplier.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Supplier
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('supplier.php'); ?>"
                   href="<?= htmlspecialchars($href('supplier.php'), ENT_QUOTES); ?>"
                   <?= $current==='supplier.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Suppliers
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Employee -->
      <?php $employeeFiles = ['add_employee.php','employee.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingEmployee">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuEmployee"
                  aria-expanded="<?= isOpen($employeeFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuEmployee">
            <i class="bi bi-person-badge me-2" aria-hidden="true"></i> Employee
          </button>
        </h2>
        <div id="menuEmployee" class="accordion-collapse collapse <?= isOpen($employeeFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_employee.php'); ?>"
                   href="<?= htmlspecialchars($href('add_employee.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_employee.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Employee
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('employee.php'); ?>"
                   href="<?= htmlspecialchars($href('employee.php'), ENT_QUOTES); ?>"
                   <?= $current==='employee.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Employees
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Transport -->
      <?php $transportFiles = ['add_transport.php','transport.php','edit_transport.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingTransport">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuTransport"
                  aria-expanded="<?= isOpen($transportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuTransport">
            <i class="bi bi-truck-front me-2" aria-hidden="true"></i> Transport
          </button>
        </h2>
        <div id="menuTransport" class="accordion-collapse collapse <?= isOpen($transportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_transport.php'); ?>"
                   href="<?= htmlspecialchars($href('add_transport.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_transport.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('transport.php'); ?>"
                   href="<?= htmlspecialchars($href('transport.php'), ENT_QUOTES); ?>"
                   <?= $current==='transport.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('edit_transport.php'); ?>"
                   href="<?= htmlspecialchars($href('edit_transport.php'), ENT_QUOTES); ?>"
                   <?= $current==='edit_transport.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Transport
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Customer -->
      <?php $customerFiles = ['add_customer.php','customer.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingCustomer">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuCustomer"
                  aria-expanded="<?= isOpen($customerFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuCustomer">
            <i class="bi bi-people me-2" aria-hidden="true"></i> Customer
          </button>
        </h2>
        <div id="menuCustomer" class="accordion-collapse collapse <?= isOpen($customerFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_customer.php'); ?>"
                   href="<?= htmlspecialchars($href('add_customer.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_customer.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Customer
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('customer.php'); ?>"
                   href="<?= htmlspecialchars($href('customer.php'), ENT_QUOTES); ?>"
                   <?= $current==='customer.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Customers
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Returns -->
      <?php $returnFiles = ['add_return.php','view_return.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingReturns">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuReturns"
                  aria-expanded="<?= isOpen($returnFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReturns">
            <i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i> Returns
          </button>
        </h2>
        <div id="menuReturns" class="accordion-collapse collapse <?= isOpen($returnFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_return.php'); ?>"
                   href="<?= htmlspecialchars($href('add_return.php'), ENT_QUOTES); ?>"
                   <?= $current==='add_return.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Return
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('view_return.php'); ?>"
                   href="<?= htmlspecialchars($href('view_return.php'), ENT_QUOTES); ?>"
                   <?= $current==='view_return.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-list-ul me-2" aria-hidden="true"></i>View Returns
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Reports -->
      <?php $reportFiles = ['sales_report.php','inventory_report.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingReports">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuReports"
                  aria-expanded="<?= isOpen($reportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReports">
            <i class="bi bi-graph-up me-2" aria-hidden="true"></i> Reports
          </button>
        </h2>
        <div id="menuReports" class="accordion-collapse collapse <?= isOpen($reportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('sales_report.php'); ?>"
                   href="<?= htmlspecialchars($href('sales_report.php'), ENT_QUOTES); ?>"
                   <?= $current==='sales_report.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Sales Report
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('inventory_report.php'); ?>"
                   href="<?= htmlspecialchars($href('inventory_report.php'), ENT_QUOTES); ?>"
                   <?= $current==='inventory_report.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Inventory Report
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

    </div>

    <!-- Sidebar footer (sticks to bottom in the rail) -->
    <div class="mt-auto rb-sb-footer px-3 py-3">
      <a href="<?= htmlspecialchars($href($RB_LOGOUT_PATH), ENT_QUOTES); ?>"
         class="rb-sb-link d-inline-flex align-items-center"
         data-bs-dismiss="offcanvas">
        <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Logout
      </a>
    </div>
  </nav>
</aside>

<?php if ($RB_SIDEBAR_LOAD_VENDOR): ?>
  <!-- Vendor JS (Bootstrap bundle) — disable if your layout already loads it -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<!-- Small helper: sync aria-expanded with default "show" and close offcanvas on link click -->
<script>
(function(){
  var root = document.getElementById('rbSidebar');
  if(!root) return;

  // Sync buttons’ aria-expanded with default open sections
  root.querySelectorAll('.accordion-collapse').forEach(function(c){
    var btn = root.querySelector('[data-bs-target="#'+c.id+'"]');
    if (btn) btn.setAttribute('aria-expanded', c.classList.contains('show') ? 'true' : 'false');
  });

  // Close offcanvas after any link click on mobile
  root.addEventListener('click', function(e){
    var a = e.target.closest('a');
    if (!a) return;
    if (window.innerWidth < 1200 && window.bootstrap && window.bootstrap.Offcanvas){
      var inst = window.bootstrap.Offcanvas.getInstance(root) || new window.bootstrap.Offcanvas(root);
      inst.hide();
    }
  }, {capture:true});
})();
</script>
