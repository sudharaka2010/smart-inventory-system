<link rel="stylesheet" href="/rbstorsg/assets/css/sidebar.css">


<!-- Sidebar -->
    <aside class="sidebar">
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
                    <li><a href="add_invoice.php"><i class="fas fa-plus"></i> Billing Invoice</a></li>
                    <li><a href="invoice.php"><i class="fas fa-list"></i> View Invoices</a></li>
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
    </aside>

     <!-- Script -->
    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('open');
        }
    </script>
