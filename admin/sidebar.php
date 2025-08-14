<?php
// ======================= RB Stores — Sidebar (Bootstrap 5 + Icons) =======================
// Purpose: Offcanvas on mobile, fixed rail on desktop (uses CSS vars from header/layout)
// Safe active-page detection (ignores query strings)
$urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$current = basename($urlPath ?: ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

// ---------- Helpers ----------
function isActive($files){
  global $current; $files = (array)$files;
  return in_array($current, $files, true) ? 'active' : '';
}
function isOpen($files){
  global $current; $files = (array)$files;
  return in_array($current, $files, true) ? 'show' : '';
}

// Optional base path, e.g. set $APP_BASE='/admin' before include if your pages are in a subfolder
$APP_BASE = $APP_BASE ?? '';
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};

// Control external assets (set false if your layout already loads Bootstrap & Icons)
$RB_SIDEBAR_LOAD_ASSETS = $RB_SIDEBAR_LOAD_ASSETS ?? true;

// Desktop brand row (avoid double logo under header). Default false.
$RB_SIDEBAR_SHOW_BRAND = $RB_SIDEBAR_SHOW_BRAND ?? false;

// Logout path (keep consistent with header). Change if your route differs.
$RB_LOGOUT_PATH = $RB_LOGOUT_PATH ?? 'auth/logout.php';
?>
<?php if ($RB_SIDEBAR_LOAD_ASSETS): ?>
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php endif; ?>

<!-- Sidebar Styles (scoped; won’t affect header/footer/main).
     Expects CSS variables from header.css/layout.css:
     --rb-header-h, --rb-footer-h, --rb-sidebar-w -->
<link rel="stylesheet" href="/assets/css/sidebar.css" />

<!-- Mobile Top Bar (hamburger) -->
<header class="rb-sb-topbar d-xl-none" role="banner" data-rb-scope="sidebar">
  <button class="btn rb-sb-topbar-btn fs-3" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#rbSidebar"
          aria-controls="rbSidebar" aria-label="Open sidebar">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>
  <div class="ms-2 fw-semibold">Menu</div>
</header>

<!-- Sidebar (Offcanvas on mobile, fixed rail on desktop by CSS) -->
<aside id="rbSidebar"
       class="offcanvas offcanvas-start rb-sb offcanvas-shadow"
       tabindex="-1"
       aria-labelledby="rbSidebarLabel"
       data-bs-scroll="true"
       data-bs-backdrop="false"
       data-rb-scope="sidebar">

  <!-- Offcanvas header (hidden on xl+ via CSS) -->
  <div class="offcanvas-header d-xl-none">
    <h5 class="offcanvas-title" id="rbSidebarLabel">Navigation</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <nav class="offcanvas-body p-0 d-flex flex-column" role="navigation" aria-label="Main">
    <!-- Optional desktop brand (default hidden to avoid double logo under header) -->
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
                   href="<?= $href('add_inventory.php'); ?>"
                   <?= $current==='add_inventory.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Inventory
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('inventory.php'); ?>"
                   href="<?= $href('inventory.php'); ?>"
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
                   href="<?= $href('billing.php'); ?>"
                   <?= $current==='billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-file-earmark-plus me-2" aria-hidden="true"></i>Billing Invoice
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('view_billing.php'); ?>"
                   href="<?= $href('view_billing.php'); ?>"
                   <?= $current==='view_billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-journal-text me-2" aria-hidden="true"></i>View Invoices
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('edit_billing.php'); ?>"
                   href="<?= $href('edit_billing.php'); ?>"
                   <?= $current==='edit_billing.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Billing
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('invoice.php'); ?>"
                   href="<?= $href('invoice.php'); ?>"
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
                   href="<?= $href('add_supplier.php'); ?>"
                   <?= $current==='add_supplier.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Supplier
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('supplier.php'); ?>"
                   href="<?= $href('supplier.php'); ?>"
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
                   href="<?= $href('add_employee.php'); ?>"
                   <?= $current==='add_employee.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Employee
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('employee.php'); ?>"
                   href="<?= $href('employee.php'); ?>"
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
                   href="<?= $href('add_transport.php'); ?>"
                   <?= $current==='add_transport.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('transport.php'); ?>"
                   href="<?= $href('transport.php'); ?>"
                   <?= $current==='transport.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('edit_transport.php'); ?>"
                   href="<?= $href('edit_transport.php'); ?>"
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
                   href="<?= $href('add_customer.php'); ?>"
                   <?= $current==='add_customer.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Customer
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('customer.php'); ?>"
                   href="<?= $href('customer.php'); ?>"
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
                   href="<?= $href('add_return.php'); ?>"
                   <?= $current==='add_return.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Return
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('view_return.php'); ?>"
                   href="<?= $href('view_return.php'); ?>"
                   <?= $current==='view_return.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-list-ul me-2" aria-hidden="true"></i>View Returns
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Feedback -->
      <?php $feedbackFiles = ['add_feedback.php','feedback.php']; ?>
      <div class="accordion-item rb-sb-acc-item">
        <h2 class="accordion-header" id="headingFeedback">
          <button class="accordion-button collapsed rb-sb-acc-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuFeedback"
                  aria-expanded="<?= isOpen($feedbackFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuFeedback">
            <i class="bi bi-chat-dots me-2" aria-hidden="true"></i> Feedback
          </button>
        </h2>
        <div id="menuFeedback" class="accordion-collapse collapse <?= isOpen($feedbackFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?= isActive('add_feedback.php'); ?>"
                   href="<?= $href('add_feedback.php'); ?>"
                   <?= $current==='add_feedback.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Feedback
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('feedback.php'); ?>"
                   href="<?= $href('feedback.php'); ?>"
                   <?= $current==='feedback.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>View Feedback
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
                   href="<?= $href('sales_report.php'); ?>"
                   <?= $current==='sales_report.php' ? 'aria-current="page"' : ''; ?>
                   data-bs-dismiss="offcanvas">
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Sales Report
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?= isActive('inventory_report.php'); ?>"
                   href="<?= $href('inventory_report.php'); ?>"
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

    <!-- Footer / Logout (sticks above page footer on desktop via CSS) -->
    <div class="mt-auto rb-sb-footer px-3 py-3">
      <a href="<?= $href($RB_LOGOUT_PATH); ?>" class="rb-sb-link d-inline-flex align-items-center" data-bs-dismiss="offcanvas">
        <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Logout
      </a>
    </div>
  </nav>
</aside>

<?php if ($RB_SIDEBAR_LOAD_ASSETS): ?>
  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<!-- Sync aria-expanded with initial "show" states; close offcanvas on nav click (mobile) -->
<script>
(function(){
  var root = document.getElementById('rbSidebar');
  if(!root) return;

  // Sync buttons' aria-expanded based on default open sections
  root.querySelectorAll('.accordion-collapse').forEach(function(c){
    var btn = root.querySelector('[data-bs-target="#'+c.id+'"]');
    if (btn) btn.setAttribute('aria-expanded', c.classList.contains('show') ? 'true' : 'false');
  });

  // Close offcanvas on link click (Bootstrap handles data-bs-dismiss, but this is a safety net)
  root.addEventListener('click', function(e){
    var a = e.target.closest('a');
    if (!a) return;
    if (window.innerWidth < 1200) {
      var offcanvasEl = bootstrap.Offcanvas.getInstance(root) || new bootstrap.Offcanvas(root);
      offcanvasEl.hide();
    }
  }, {capture:true});
})();
</script>
