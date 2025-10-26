<?php
// sidebar.php (FULL UPDATED — responsive, no bottom clipping, auto-open active group)
$CURRENT = basename($_SERVER['PHP_SELF'] ?? '');
function isActive($file){ global $CURRENT; return $CURRENT === $file ? 'active' : ''; }
function isOpen(array $files){ global $CURRENT; return in_array($CURRENT, $files, true) ? 'open' : ''; }
?>
<!-- Mobile Toggle Button -->
<button class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar" aria-label="Primary">
  <ul>
    <li class="<?php echo isOpen(['add_inventory.php','inventory.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-boxes"></i> Stock <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_inventory.php'); ?>" href="add_inventory.php"><i class="fas fa-plus"></i> Add Inventory</a></li>
        <li><a class="<?php echo isActive('inventory.php'); ?>" href="inventory.php"><i class="fas fa-list"></i> View Inventory</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['billing.php','view_billing.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-file-invoice-dollar"></i> Billing <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('billing.php'); ?>" href="billing.php"><i class="fas fa-plus"></i> Billing Invoice</a></li>
        <li><a class="<?php echo isActive('view_billing.php'); ?>" href="view_billing.php"><i class="fas fa-list"></i> View Invoices</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_supplier.php','supplier.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-truck"></i> Supplier <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_supplier.php'); ?>" href="add_supplier.php"><i class="fas fa-plus"></i> Add Supplier</a></li>
        <li><a class="<?php echo isActive('supplier.php'); ?>" href="supplier.php"><i class="fas fa-list"></i> View Suppliers</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_employee.php','employee.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-user-tie"></i> Employee <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_employee.php'); ?>" href="add_employee.php"><i class="fas fa-plus"></i> Add Employee</a></li>
        <li><a class="<?php echo isActive('employee.php'); ?>" href="employee.php"><i class="fas fa-list"></i> View Employees</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_transport.php','transport.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-shipping-fast"></i> Transport <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_transport.php'); ?>" href="add_transport.php"><i class="fas fa-plus"></i> Add Transport</a></li>
        <li><a class="<?php echo isActive('transport.php'); ?>" href="transport.php"><i class="fas fa-list"></i> View Transport</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_customer.php','customer.php','customer_returns.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-users"></i> Customer <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_customer.php'); ?>" href="add_customer.php"><i class="fas fa-plus"></i> Add Customer</a></li>
        <li><a class="<?php echo isActive('customer.php'); ?>" href="customer.php"><i class="fas fa-list"></i> View Customers</a></li>
        <li><a class="<?php echo isActive('customer_returns.php'); ?>" href="customer_returns.php"><i class="fas fa-undo-alt"></i> Customer Returns</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_return.php','view_return.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-undo-alt"></i> Returns <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_return.php'); ?>" href="add_return.php"><i class="fas fa-plus-circle"></i> Add Return</a></li>
        <li><a class="<?php echo isActive('view_return.php'); ?>" href="view_return.php"><i class="fas fa-list-alt"></i> View Returns</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['add_feedback.php','feedback.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-comments"></i> Feedback <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('add_feedback.php'); ?>" href="add_feedback.php"><i class="fas fa-plus"></i> Add Feedback</a></li>
        <li><a class="<?php echo isActive('feedback.php'); ?>" href="feedback.php"><i class="fas fa-comment-alt"></i> View Feedback</a></li>
      </ul>
    </li>

    <li class="<?php echo isOpen(['sales_report.php','inventory_report.php']); ?>">
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-chart-line"></i> Reports <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a class="<?php echo isActive('sales_report.php'); ?>" href="sales_report.php"><i class="fas fa-file-alt"></i> Sales Report</a></li>
        <li><a class="<?php echo isActive('inventory_report.php'); ?>" href="inventory_report.php"><i class="fas fa-file-alt"></i> Inventory Report</a></li>
      </ul>
    </li>

    <?php $isLogs = (isset($_GET['tab']) && $_GET['tab'] === 'logs'); ?>

<li class="<?php echo isOpen(['db_backup.php']); ?>">
  <div class="menu-item" onclick="toggleDropdown(this)">
    <i class="fas fa-database"></i> Data Backup
    <i class="fas fa-chevron-down dropdown-icon"></i>
  </div>
  <ul class="submenu">
    <!-- Main settings + manual backup -->
    <li>
      <a class="<?php echo !$isLogs ? isActive('db_backup.php') : ''; ?>" href="db_backup.php">
        <i class="fas fa-download"></i> Backup &amp; Schedule
      </a>
    </li>

    <!-- Logs view -->
    <li>
      <a class="<?php echo $isLogs ? 'active' : ''; ?>" href="db_backup.php?tab=logs">
        <i class="fas fa-clipboard-list"></i> Logs
      </a>
    </li>
  </ul>
</li>

  </ul>
</aside>

<style>
/* ===== Responsive Business Sidebar Navigation (FIXED HEIGHT + SAFE AREA) ===== */

/* Root */
:root{
  --sidebar-bg:#3b5683;
  --sidebar-text:#f8fafc;
  --sidebar-hover:#3e69a5;
  --submenu-bg:#3b5683;
  --active-bg:#2563eb;
  --active-text:#ffffff;
  --border-color:#1e3c66;
  --transition:.3s;
  --font-main:'Poppins',sans-serif;
  /* If you don't have a fixed header, set this to 0px */
  --header-height:60px;
}

/* Reset */
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font-main);background:#f1f5f9;color:#111827}

/* Sidebar container */
.sidebar{
  position:fixed;
  left:0;
  top:var(--header-height);

  /* Dynamic viewport height (prevents mobile browser UI clipping) */
  height:calc(100dvh - var(--header-height));
}
@supports not (height: 100dvh){
  .sidebar{ height:calc(100vh - var(--header-height)); }
}

.sidebar{
  width:260px;
  background:var(--sidebar-bg);
  color:var(--sidebar-text);

  overflow-y:auto;
  overflow-x:hidden;
  scrollbar-gutter: stable both-edges;

  padding-top:20px;
  /* Safe breathing room so last item never hides */
  padding-bottom: max(20px, env(safe-area-inset-bottom, 12px));

  transition:transform var(--transition);
  z-index:999;
}

/* Extra spacer so expanded items don't stick to the edge on tiny screens */
.sidebar::after{content:"";display:block;height:16px}

/* Scrollbar styling (optional) */
.sidebar::-webkit-scrollbar{width:8px}
.sidebar::-webkit-scrollbar-thumb{background:#64748b;border-radius:6px}

/* Toggle Button (Mobile) */
.sidebar-toggle-btn{
  display:none;
  position:fixed; top:15px; left:15px;
  z-index:1001;
  background:var(--sidebar-bg);
  color:var(--sidebar-text);
  border:none; border-radius:6px;
  padding:10px 14px;
  font-size:20px; cursor:pointer;
}

/* Menu list */
.sidebar ul{list-style:none}

/* Menu Item */
.sidebar .menu-item{
  display:flex; justify-content:space-between; align-items:center;
  padding:14px 20px; font-size:16px; font-weight:500; cursor:pointer;
  transition:background var(--transition);
  border-bottom:1px solid var(--border-color); user-select:none;
}
.sidebar .menu-item:hover{background:var(--sidebar-hover)}
.sidebar .menu-item i{margin-right:10px}

/* Dropdown arrow */
.dropdown-icon{transition:transform var(--transition)}
.sidebar li.open .dropdown-icon{transform:rotate(180deg)}

/* Submenu */
.submenu{
  display:none; flex-direction:column; background:var(--submenu-bg);
  will-change: height; /* smoother expand */
}
.sidebar li.open .submenu{display:flex}
.submenu li a{
  padding:12px 20px; display:block; text-decoration:none;
  color:#cbd5e1; font-size:15px; transition:background var(--transition);
  border-bottom:1px solid var(--border-color);
}
.submenu li a:hover{background:var(--sidebar-hover); color:var(--active-text)}

/* Active state */
.sidebar a.active,.sidebar .menu-item.active{
  background:var(--active-bg); color:var(--active-text);
}

/* Optional content offset */
.main-content{
  margin-left:260px; margin-top:var(--header-height); padding:20px;
  transition:margin-left var(--transition);
}

/* Responsive widths */
@media (max-width:1024px){
  .sidebar{width:240px}
  .sidebar .menu-item{font-size:15px}
  .main-content{margin-left:240px}
}
@media (max-width:768px){
  .sidebar{transform:translateX(-100%); width:240px}
  .sidebar.show{transform:translateX(0)}
  .sidebar-toggle-btn{display:block}
  .main-content{margin-left:0; margin-top:var(--header-height)}
}
</style>

<script>
  function toggleDropdown(el){
    const group = el.parentElement;
    group.classList.toggle('open');

    // When opening near the bottom, keep it visible
    if (group.classList.contains('open')) {
      const sidebar = document.getElementById('sidebar');
      const rect = group.getBoundingClientRect();
      const srect = sidebar.getBoundingClientRect();
      if (rect.bottom > (srect.bottom - 16)) {
        group.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }
  }

  function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('show');
  }

  // Auto-open active group and make sure it's visible
  (function(){
    const active = document.querySelector('.sidebar a.active');
    if(active){
      const group = active.closest('li')?.closest('li');
      if(group){
        group.classList.add('open');
        const sidebar = document.getElementById('sidebar');
        const rect = group.getBoundingClientRect();
        const srect = sidebar.getBoundingClientRect();
        if (rect.bottom > (srect.bottom - 16)) {
          group.scrollIntoView({ behavior: 'instant', block: 'nearest' });
        }
      }
    }
  })();
</script>
