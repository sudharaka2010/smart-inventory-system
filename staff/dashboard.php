<link rel="stylesheet" href="/rbstorsg/assets/css/dashboard.css">

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Staff') {
    header("Location: ../auth/login.php");
    exit();
}
include '../admin/header.php';
include '../admin/sidebar.php';

?>



  

    <!-- Sidebar -->
    <aside class="sidebar">
        <ul>
            <!-- Stock -->
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-boxes"></i> Stock <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="inventory.php"><i class="fas fa-list"></i> View Inventory</a></li>
                </ul>
            </li>
            <!-- Transport -->
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-shipping-fast"></i> Transport <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="transport.php"><i class="fas fa-list"></i> View Transport</a></li>
                </ul>
            </li>
            <!-- Customer -->
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-users"></i> Customer <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="customer.php"><i class="fas fa-list"></i> View Customers</a></li>
                </ul>
            </li>
            <!-- Feedback -->
            <li><a href="feedback.php" class="no-highlight"><i class="fas fa-comments"></i> Feedback</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <h2>Welcome, <span class="highlight">Staff</span></h2>
        <p>You have view-only access. Editing and adding are disabled.</p>
        <section class="status-panel">
            <h3>Live Inventory Status</h3>
            <div class="status-grid">
                <div class="status-card">
                    <i class="fas fa-cubes"></i>
                    <h4>Total Items</h4>
                    <p>1200</p>
                </div>
                <div class="status-card">
                    <i class="fas fa-warehouse"></i>
                    <h4>Low Stock Alerts</h4>
                    <p>5</p>
                </div>
                <div class="status-card">
                    <i class="fas fa-truck-loading"></i>
                    <h4>Pending Deliveries</h4>
                    <p>8</p>
                </div>
            </div>
        </section>
    </main>

  

    <script>
        function toggleDropdown(element) {
            document.querySelectorAll('.sidebar li').forEach(li => {
                if (li !== element.parentElement) {
                    li.classList.remove('open');
                }
            });
            element.parentElement.classList.toggle('open');
        }
    </script>
<?php
include '../admin/footer.php';
?>
