<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RB Stores - Sidebar</title>

  <!-- Google Fonts & Font Awesome -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <!-- Sidebar Stylesheet -->
  <link rel="stylesheet" href="/rbstorsg/assets/css/sidebar.css" />
</head>
<body>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle-btn" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <ul>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-boxes"></i> Stock <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="add_inventory.php"><i class="fas fa-plus"></i> Add Inventory</a></li>
        <li><a href="inventory.php"><i class="fas fa-list"></i> View Inventory</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-file-invoice-dollar"></i> Billing <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="billing.php"><i class="fas fa-plus"></i> Billing Invoice</a></li>
        <li><a href="view_billing.php"><i class="fas fa-list"></i> View Invoices</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-truck"></i> Supplier <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="add_supplier.php"><i class="fas fa-plus"></i> Add Supplier</a></li>
        <li><a href="supplier.php"><i class="fas fa-list"></i> View Suppliers</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-user-tie"></i> Employee <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="add_employee.php"><i class="fas fa-plus"></i> Add Employee</a></li>
        <li><a href="employee.php"><i class="fas fa-list"></i> View Employees</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-shipping-fast"></i> Transport <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="add_transport.php"><i class="fas fa-plus"></i> Add Transport</a></li>
        <li><a href="transport.php"><i class="fas fa-list"></i> View Transport</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-users"></i> Customer <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="add_customer.php"><i class="fas fa-plus"></i> Add Customer</a></li>
        <li><a href="customer.php"><i class="fas fa-list"></i> View Customers</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-comments"></i> Feedback <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="feedback.php"><i class="fas fa-comment-alt"></i> View Feedback</a></li>
        <li><a href="add_feedback.php"><i class="fas fa-plus"></i> Add Feedback</a></li>
      </ul>
    </li>
    <li>
      <div class="menu-item" onclick="toggleDropdown(this)">
        <i class="fas fa-chart-line"></i> Reports <i class="fas fa-chevron-down dropdown-icon"></i>
      </div>
      <ul class="submenu">
        <li><a href="sales_report.php"><i class="fas fa-file-alt"></i> Sales Report</a></li>
        <li><a href="inventory_report.php"><i class="fas fa-file-alt"></i> Inventory Report</a></li>
      </ul>
    </li>
  </ul>
</aside>

<!-- Sidebar Script -->
<script>
  function toggleDropdown(element) {
    element.parentElement.classList.toggle('open');
  }

  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
  }
</script>

</body>
</html>
