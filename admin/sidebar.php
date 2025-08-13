<?php
// ================= RB Stores — Isolated Sidebar (Bootstrap 5 + Bootstrap Icons) =================
// Safe current page detection (ignores query strings)
$urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$current = basename($urlPath) ?: 'index.php';

// Helpers
function isActive($files){ global $current; $files=(array)$files; return in_array($current,$files,true) ? 'active' : ''; }
function isOpen($files){ global $current; $files=(array)$files; return in_array($current,$files,true) ? 'show' : ''; }

// Base path (set before include if your pages are in subfolders, e.g. $APP_BASE='/admin')
$APP_BASE = $APP_BASE ?? '';

// Tiny URL helper for hrefs
$href = function(string $path) use ($APP_BASE){
  $base = rtrim($APP_BASE, '/');
  $path = ltrim($path, '/');
  return ($base === '') ? "/{$path}" : "{$base}/{$path}";
};

// Control asset loading (set false if header.php already loads Bootstrap & Icons)
$RB_SIDEBAR_LOAD_ASSETS = $RB_SIDEBAR_LOAD_ASSETS ?? true;

// Desktop brand row (avoid duplicate logo under header). Default: false.
$RB_SIDEBAR_SHOW_BRAND = $RB_SIDEBAR_SHOW_BRAND ?? false;
?>

<?php if ($RB_SIDEBAR_LOAD_ASSETS): ?>
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php endif; ?>

<!-- Sidebar Styles (scoped; won’t affect header/footer/main) -->
<link rel="stylesheet" href="/assets/css/sidebar.css" />

<!-- Mobile Top Bar (toggle) -->
<header class="rb-sb-topbar d-xl-none" role="banner" data-rb-scope="sidebar">
  <button class="btn rb-sb-topbar-btn fs-3" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#rbSidebar"
          aria-controls="rbSidebar" aria-label="Open sidebar">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>
  <div class="ms-2 fw-semibold">RB Stores</div>
</header>

<!-- Sidebar (Offcanvas on mobile, fixed rail on desktop) -->
<aside id="rbSidebar"
       class="offcanvas offcanvas-start rb-sb offcanvas-shadow"
       tabindex="-1"
       aria-labelledby="rbSidebarLabel"
       data-bs-scroll="true" data-bs-backdrop="false"
       data-rb-scope="sidebar">

  <!-- Offcanvas header (hidden on xl+ via CSS) -->
  <div class="offcanvas-header d-xl-none">
    <h5 class="offcanvas-title" id="rbSidebarLabel">Navigation</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <nav class="offcanvas-body p-0 d-flex flex-column" role="navigation" aria-label="Main">
    <!-- Desktop brand (optional; hidden by default to avoid double logo under header) -->
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
                  aria-expanded="<?php echo isOpen($stockFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuStock">
            <i class="bi bi-box-seam me-2" aria-hidden="true"></i> Stock
          </button>
        </h2>
        <div id="menuStock" class="accordion-collapse collapse <?php echo isOpen($stockFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_inventory.php'); ?>"
                   href="<?php echo $href('add_inventory.php'); ?>"
                   <?php echo $current==='add_inventory.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Inventory
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('inventory.php'); ?>"
                   href="<?php echo $href('inventory.php'); ?>"
                   <?php echo $current==='inventory.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($billingFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuBilling">
            <i class="bi bi-receipt me-2" aria-hidden="true"></i> Billing
          </button>
        </h2>
        <div id="menuBilling" class="accordion-collapse collapse <?php echo isOpen($billingFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('billing.php'); ?>"
                   href="<?php echo $href('billing.php'); ?>"
                   <?php echo $current==='billing.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-file-earmark-plus me-2" aria-hidden="true"></i>Billing Invoice
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('view_billing.php'); ?>"
                   href="<?php echo $href('view_billing.php'); ?>"
                   <?php echo $current==='view_billing.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-journal-text me-2" aria-hidden="true"></i>View Invoices
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('edit_billing.php'); ?>"
                   href="<?php echo $href('edit_billing.php'); ?>"
                   <?php echo $current==='edit_billing.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Billing
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('invoice.php'); ?>"
                   href="<?php echo $href('invoice.php'); ?>"
                   <?php echo $current==='invoice.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($supplierFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuSupplier">
            <i class="bi bi-truck me-2" aria-hidden="true"></i> Supplier
          </button>
        </h2>
        <div id="menuSupplier" class="accordion-collapse collapse <?php echo isOpen($supplierFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_supplier.php'); ?>"
                   href="<?php echo $href('add_supplier.php'); ?>"
                   <?php echo $current==='add_supplier.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Supplier
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('supplier.php'); ?>"
                   href="<?php echo $href('supplier.php'); ?>"
                   <?php echo $current==='supplier.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($employeeFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuEmployee">
            <i class="bi bi-person-badge me-2" aria-hidden="true"></i> Employee
          </button>
        </h2>
        <div id="menuEmployee" class="accordion-collapse collapse <?php echo isOpen($employeeFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_employee.php'); ?>"
                   href="<?php echo $href('add_employee.php'); ?>"
                   <?php echo $current==='add_employee.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Employee
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('employee.php'); ?>"
                   href="<?php echo $href('employee.php'); ?>"
                   <?php echo $current==='employee.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($transportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuTransport">
            <i class="bi bi-truck-front me-2" aria-hidden="true"></i> Transport
          </button>
        </h2>
        <div id="menuTransport" class="accordion-collapse collapse <?php echo isOpen($transportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_transport.php'); ?>"
                   href="<?php echo $href('add_transport.php'); ?>"
                   <?php echo $current==='add_transport.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('transport.php'); ?>"
                   href="<?php echo $href('transport.php'); ?>"
                   <?php echo $current==='transport.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-card-list me-2" aria-hidden="true"></i>View Transport
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('edit_transport.php'); ?>"
                   href="<?php echo $href('edit_transport.php'); ?>"
                   <?php echo $current==='edit_transport.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($customerFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuCustomer">
            <i class="bi bi-people me-2" aria-hidden="true"></i> Customer
          </button>
        </h2>
        <div id="menuCustomer" class="accordion-collapse collapse <?php echo isOpen($customerFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_customer.php'); ?>"
                   href="<?php echo $href('add_customer.php'); ?>"
                   <?php echo $current==='add_customer.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Customer
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('customer.php'); ?>"
                   href="<?php echo $href('customer.php'); ?>"
                   <?php echo $current==='customer.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($returnFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReturns">
            <i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i> Returns
          </button>
        </h2>
        <div id="menuReturns" class="accordion-collapse collapse <?php echo isOpen($returnFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_return.php'); ?>"
                   href="<?php echo $href('add_return.php'); ?>"
                   <?php echo $current==='add_return.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Return
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('view_return.php'); ?>"
                   href="<?php echo $href('view_return.php'); ?>"
                   <?php echo $current==='view_return.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($feedbackFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuFeedback">
            <i class="bi bi-chat-dots me-2" aria-hidden="true"></i> Feedback
          </button>
        </h2>
        <div id="menuFeedback" class="accordion-collapse collapse <?php echo isOpen($feedbackFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('add_feedback.php'); ?>"
                   href="<?php echo $href('add_feedback.php'); ?>"
                   <?php echo $current==='add_feedback.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add Feedback
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('feedback.php'); ?>"
                   href="<?php echo $href('feedback.php'); ?>"
                   <?php echo $current==='feedback.php' ? 'aria-current="page"' : ''; ?>>
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
                  aria-expanded="<?php echo isOpen($reportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReports">
            <i class="bi bi-graph-up me-2" aria-hidden="true"></i> Reports
          </button>
        </h2>
        <div id="menuReports" class="accordion-collapse collapse <?php echo isOpen($reportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-sb-menu list-unstyled">
              <li>
                <a class="rb-sb-link <?php echo isActive('sales_report.php'); ?>"
                   href="<?php echo $href('sales_report.php'); ?>"
                   <?php echo $current==='sales_report.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Sales Report
                </a>
              </li>
              <li>
                <a class="rb-sb-link <?php echo isActive('inventory_report.php'); ?>"
                   href="<?php echo $href('inventory_report.php'); ?>"
                   <?php echo $current==='inventory_report.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Inventory Report
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

    </div>

    <!-- Footer / Logout -->
    <div class="mt-auto rb-sb-footer px-3 py-3">
      <a href="<?php echo $href('logout.php'); ?>" class="rb-sb-link d-inline-flex align-items-center">
        <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Logout
      </a>
    </div>
  </nav>
</aside>

<?php if ($RB_SIDEBAR_LOAD_ASSETS): ?>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<!-- Sync aria-expanded with initial "show" states (scoped) -->
<script>
  (function(){
    var root = document.querySelector('#rbSidebar');
    if(!root) return;
    root.querySelectorAll('.accordion-collapse').forEach(function(c){
      var btn = root.querySelector('[data-bs-target="#'+c.id+'"]');
      if (btn) btn.setAttribute('aria-expanded', c.classList.contains('show') ? 'true' : 'false');
    });
  })();
</script>
