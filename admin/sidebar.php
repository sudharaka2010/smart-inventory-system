<?php
// ================= RB Stores — Sidebar (Bootstrap 5) =================
// Detect current page for active states (safe, query-less)
$urlPath  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$current  = basename($urlPath) ?: 'index.php';

// Helpers
function isActive($files) {
  global $current;
  $files = (array)$files;
  return in_array($current, $files, true) ? 'active' : '';
}
function isOpen($files) {
  global $current;
  $files = (array)$files;
  return in_array($current, $files, true) ? 'show' : '';
}
?>
<!-- Fonts / Icons / Bootstrap -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Sidebar Stylesheet -->
<link rel="stylesheet" href="/assets/css/sidebar.css" />

<!-- Mobile Top Bar -->
<header class="rb-topbar d-xl-none" role="banner">
  <button class="btn btn-link text-decoration-none fs-3" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open sidebar">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
  </button>
  <div class="ms-2 fw-semibold">RB Stores</div>
</header>

<!-- Sidebar (Offcanvas on mobile, fixed on desktop) -->
<aside id="appSidebar"
       class="offcanvas offcanvas-start rb-sidebar"
       tabindex="-1"
       aria-labelledby="appSidebarLabel"
       data-bs-scroll="true" data-bs-backdrop="false">
  <div class="offcanvas-header d-xl-none">
    <h5 class="offcanvas-title" id="appSidebarLabel">Navigation</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <nav class="offcanvas-body p-0 d-flex flex-column" role="navigation" aria-label="Main">
    <!-- Brand (desktop) -->
    <div class="rb-brand d-none d-xl-flex align-items-center gap-2 px-3 py-3">
      <i class="fa-solid fa-store fa-lg" aria-hidden="true"></i>
      <span class="rb-brand-text">RB Stores</span>
    </div>

    <div class="accordion accordion-flush" id="rbAccordion">

      <!-- Stock -->
      <?php $stockFiles = ['add_inventory.php','inventory.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingStock">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuStock"
                  aria-expanded="<?php echo isOpen($stockFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuStock">
            <i class="fa-solid fa-boxes me-2" aria-hidden="true"></i> Stock
          </button>
        </h2>
        <div id="menuStock" class="accordion-collapse collapse <?php echo isOpen($stockFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_inventory.php'); ?>"
                   href="add_inventory.php"
                   <?php echo $current==='add_inventory.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Inventory
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('inventory.php'); ?>"
                   href="inventory.php"
                   <?php echo $current==='inventory.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Inventory
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Billing -->
      <?php $billingFiles = ['billing.php','view_billing.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingBilling">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuBilling"
                  aria-expanded="<?php echo isOpen($billingFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuBilling">
            <i class="fa-solid fa-file-invoice-dollar me-2" aria-hidden="true"></i> Billing
          </button>
        </h2>
        <div id="menuBilling" class="accordion-collapse collapse <?php echo isOpen($billingFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('billing.php'); ?>"
                   href="billing.php"
                   <?php echo $current==='billing.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Billing Invoice
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('view_billing.php'); ?>"
                   href="view_billing.php"
                   <?php echo $current==='view_billing.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Invoices
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Supplier -->
      <?php $supplierFiles = ['add_supplier.php','supplier.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSupplier">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuSupplier"
                  aria-expanded="<?php echo isOpen($supplierFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuSupplier">
            <i class="fa-solid fa-truck me-2" aria-hidden="true"></i> Supplier
          </button>
        </h2>
        <div id="menuSupplier" class="accordion-collapse collapse <?php echo isOpen($supplierFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_supplier.php'); ?>"
                   href="add_supplier.php"
                   <?php echo $current==='add_supplier.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Supplier
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('supplier.php'); ?>"
                   href="supplier.php"
                   <?php echo $current==='supplier.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Suppliers
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Employee -->
      <?php $employeeFiles = ['add_employee.php','employee.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingEmployee">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuEmployee"
                  aria-expanded="<?php echo isOpen($employeeFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuEmployee">
            <i class="fa-solid fa-user-tie me-2" aria-hidden="true"></i> Employee
          </button>
        </h2>
        <div id="menuEmployee" class="accordion-collapse collapse <?php echo isOpen($employeeFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_employee.php'); ?>"
                   href="add_employee.php"
                   <?php echo $current==='add_employee.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Employee
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('employee.php'); ?>"
                   href="employee.php"
                   <?php echo $current==='employee.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Employees
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Transport -->
      <?php $transportFiles = ['add_transport.php','transport.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingTransport">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuTransport"
                  aria-expanded="<?php echo isOpen($transportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuTransport">
            <i class="fa-solid fa-shipping-fast me-2" aria-hidden="true"></i> Transport
          </button>
        </h2>
        <div id="menuTransport" class="accordion-collapse collapse <?php echo isOpen($transportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_transport.php'); ?>"
                   href="add_transport.php"
                   <?php echo $current==='add_transport.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Transport
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('transport.php'); ?>"
                   href="transport.php"
                   <?php echo $current==='transport.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Transport
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Customer -->
      <?php $customerFiles = ['add_customer.php','customer.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCustomer">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuCustomer"
                  aria-expanded="<?php echo isOpen($customerFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuCustomer">
            <i class="fa-solid fa-users me-2" aria-hidden="true"></i> Customer
          </button>
        </h2>
        <div id="menuCustomer" class="accordion-collapse collapse <?php echo isOpen($customerFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_customer.php'); ?>"
                   href="add_customer.php"
                   <?php echo $current==='add_customer.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Customer
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('customer.php'); ?>"
                   href="customer.php"
                   <?php echo $current==='customer.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list me-2" aria-hidden="true"></i>View Customers
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Returns -->
      <?php $returnFiles = ['add_return.php','view_return.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingReturns">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuReturns"
                  aria-expanded="<?php echo isOpen($returnFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReturns">
            <i class="fa-solid fa-rotate-left me-2" aria-hidden="true"></i> Returns
          </button>
        </h2>
        <div id="menuReturns" class="accordion-collapse collapse <?php echo isOpen($returnFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_return.php'); ?>"
                   href="add_return.php"
                   <?php echo $current==='add_return.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus-circle me-2" aria-hidden="true"></i>Add Return
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('view_return.php'); ?>"
                   href="view_return.php"
                   <?php echo $current==='view_return.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-list-ul me-2" aria-hidden="true"></i>View Returns
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Feedback -->
      <?php $feedbackFiles = ['add_feedback.php','feedback.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingFeedback">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuFeedback"
                  aria-expanded="<?php echo isOpen($feedbackFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuFeedback">
            <i class="fa-solid fa-comments me-2" aria-hidden="true"></i> Feedback
          </button>
        </h2>
        <div id="menuFeedback" class="accordion-collapse collapse <?php echo isOpen($feedbackFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('add_feedback.php'); ?>"
                   href="add_feedback.php"
                   <?php echo $current==='add_feedback.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Add Feedback
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('feedback.php'); ?>"
                   href="feedback.php"
                   <?php echo $current==='feedback.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-solid fa-comment-dots me-2" aria-hidden="true"></i>View Feedback
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Reports -->
      <?php $reportFiles = ['sales_report.php','inventory_report.php']; ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingReports">
          <button class="accordion-button collapsed rb-accordion-btn" type="button"
                  data-bs-toggle="collapse" data-bs-target="#menuReports"
                  aria-expanded="<?php echo isOpen($reportFiles) ? 'true' : 'false'; ?>"
                  aria-controls="menuReports">
            <i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i> Reports
          </button>
        </h2>
        <div id="menuReports" class="accordion-collapse collapse <?php echo isOpen($reportFiles); ?>"
             data-bs-parent="#rbAccordion">
          <div class="accordion-body p-0">
            <ul class="rb-menu list-unstyled">
              <li>
                <a class="rb-link <?php echo isActive('sales_report.php'); ?>"
                   href="sales_report.php"
                   <?php echo $current==='sales_report.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-regular fa-file-lines me-2" aria-hidden="true"></i>Sales Report
                </a>
              </li>
              <li>
                <a class="rb-link <?php echo isActive('inventory_report.php'); ?>"
                   href="inventory_report.php"
                   <?php echo $current==='inventory_report.php' ? 'aria-current="page"' : ''; ?>>
                  <i class="fa-regular fa-file-lines me-2" aria-hidden="true"></i>Inventory Report
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

    </div>

    <!-- Footer / Logout -->
    <div class="mt-auto rb-footer px-3 py-3">
      <a href="logout.php" class="rb-link d-inline-flex align-items-center">
        <i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i> Logout
      </a>
    </div>
  </nav>
</aside>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Small enhancement: sync aria-expanded on load for server-opened sections -->
<script>
  document.querySelectorAll('.accordion-collapse').forEach(c => {
    const btn = document.querySelector('[data-bs-target="#'+c.id+'"]');
    if (btn) btn.setAttribute('aria-expanded', c.classList.contains('show') ? 'true' : 'false');
  });
</script>
