<link rel="stylesheet" href="/rbstorsg/assets/css/dashboard.css">
<?php



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}
// admin/dashboard.php
include 'header.php';
include 'sidebar.php';
include('../includes/db_connect.php');
// Total Items
$totalItems = $conn->query("SELECT SUM(Quantity) AS total FROM InventoryItem")->fetch_assoc()['total'] ?? 0;

// Low Stock Alerts (Quantity <= 5)
$lowStock = $conn->query("SELECT COUNT(*) AS low FROM InventoryItem WHERE Quantity <= 5")->fetch_assoc()['low'] ?? 0;

// Pending Deliveries
$pendingDeliveries = $conn->query("SELECT COUNT(*) AS pending FROM Transport WHERE Status='Pending'")->fetch_assoc()['pending'] ?? 0;
?>



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


<?php 
include 'footer.php';