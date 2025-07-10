<link rel="stylesheet" href="/rbstorsg/assets/css/dashboard.css">

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Staff') {
    header("Location: ../auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RB Stores Staff Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Top Navbar -->
    <header class="top-header">
        <div class="logo">
            <h1>RB Stores - Staff</h1>
            <p>Rain Water Solution Management System</p>
        </div>
        <nav class="top-nav">
            <a href="../auth/logout.php" class="logout-btn">Logout <i class="fas fa-sign-out-alt"></i></a>
        </nav>
    </header>

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

    <!-- Footer -->
    <footer class="footer">
        <p>Code Counter Team Group - 15 | Developed by: <strong>Code Counter</strong></p>
        <p>Hotline: +94 77 123 4567</p>
    </footer>

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
</body>
</html>

