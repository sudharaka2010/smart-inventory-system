<link rel="stylesheet" href="/rbstorsg/assets/css/dashboard.css">


<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

include('../includes/db_connect.php');
// Total Items
$totalItems = $conn->query("SELECT SUM(Quantity) AS total FROM InventoryItem")->fetch_assoc()['total'] ?? 0;

// Low Stock Alerts (Quantity <= 5)
$lowStock = $conn->query("SELECT COUNT(*) AS low FROM InventoryItem WHERE Quantity <= 5")->fetch_assoc()['low'] ?? 0;

// Pending Deliveries
$pendingDeliveries = $conn->query("SELECT COUNT(*) AS pending FROM Transport WHERE Status='Pending'")->fetch_assoc()['pending'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RB Stores Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Top Navbar -->
    <header class="top-header">
        <div class="logo" onclick="window.location='dashboard.php'">
            <img src="/rbstorsg/assets/images/rb.png" alt="RB Stores" class="logo-img">
            <h1>RB Stores</h1>
        </div>
        <nav class="top-nav">
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="support.php">Support <i class="fas fa-headset"></i></a>
            <a href="/rbstorsg/auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <ul>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-boxes"></i> Stock <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_inventory.php"><i class="fas fa-plus"></i> Add Inventory</a></li>
                    <li><a href="edit_inventory.php"><i class="fas fa-edit"></i> Edit Inventory</a></li>
                    <li><a href="inventory.php"><i class="fas fa-list"></i> View Inventory</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-truck"></i> Supplier <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_supplier.php"><i class="fas fa-plus"></i> Add Supplier</a></li>
                    <li><a href="edit_supplier.php"><i class="fas fa-edit"></i> Edit Supplier</a></li>
                    <li><a href="supplier.php"><i class="fas fa-list"></i> View Suppliers</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-user-tie"></i> Employee <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_employee.php"><i class="fas fa-plus"></i> Add Employee</a></li>
                    <li><a href="edit_employee.php"><i class="fas fa-edit"></i> Edit Employee</a></li>
                    <li><a href="employee.php"><i class="fas fa-list"></i> View Employees</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-shipping-fast"></i> Transport <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_transport.php"><i class="fas fa-plus"></i> Add Transport</a></li>
                    <li><a href="edit_transport.php"><i class="fas fa-edit"></i> Edit Transport</a></li>
                    <li><a href="transport.php"><i class="fas fa-list"></i> View Transport</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-users"></i> Customer <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_customer.php"><i class="fas fa-plus"></i> Add Customer</a></li>
                    <li><a href="edit_customer.php"><i class="fas fa-edit"></i> Edit Customer</a></li>
                    <li><a href="customer.php"><i class="fas fa-list"></i> View Customers</a></li>
                </ul>
            </li>
            <li><a href="feedback.php" class="no-highlight"><i class="fas fa-comments"></i> Feedback</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <h2>Welcome to <span class="highlight">RB Stores Dashboard</span></h2>
        <p>Select a menu item to get started.</p>
        <section class="status-panel">
    <h3>Live Inventory Status</h3>
    <div class="status-grid">
        <a href="inventory.php" class="status-card-link">
            <div class="status-card">
                <i class="fas fa-cubes"></i>
                <h4>Total Items</h4>
                <p><?php echo $totalItems; ?></p>
            </div>
        </a>
        <a href="low_stock.php" class="status-card-link">
            <div class="status-card">
                <i class="fas fa-warehouse"></i>
                <h4>Low Stock Alerts</h4>
                <p><?php echo $lowStock; ?></p>
            </div>
        </a>
        <a href="pending_deliveries.php" class="status-card-link">
            <div class="status-card">
                <i class="fas fa-truck-loading"></i>
                <h4>Pending Deliveries</h4>
                <p><?php echo $pendingDeliveries; ?></p>
            </div>
        </a>
    </div>
</section>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 Code Counters - Group 15 | RB Stores</p>
    </footer>

    <!-- Script -->
    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('open');
        }
    </script>
</body>
</html>
