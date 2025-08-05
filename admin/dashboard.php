<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not an Admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include DB connection
include('../includes/db_connect.php');

// Get counts
$totalItems = $conn->query("SELECT SUM(Quantity) AS total FROM InventoryItem")->fetch_assoc()['total'] ?? 0;
$lowStock = $conn->query("SELECT COUNT(*) AS low FROM InventoryItem WHERE Quantity <= 5")->fetch_assoc()['low'] ?? 0;
$pendingDeliveries = $conn->query("SELECT COUNT(*) AS pending FROM Transport WHERE Status='Pending'")->fetch_assoc()['pending'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - RB Stores</title>

    <!-- ✅ CSS LINKS (use relative paths!) -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>

    <!-- Include header and sidebar -->
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- ✅ Main Content -->
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

    <?php include 'footer.php'; ?>
</body>
</html>
