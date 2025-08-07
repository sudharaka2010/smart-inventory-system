<?php
// ✅ Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Redirect if not logged in or not Admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /auth/login.php");
    exit();
}

// ✅ Include database connection
require_once(__DIR__ . '/../includes/db_connect.php');

// ✅ Safely fetch database stats
function getSafeValue($conn, $query, $field) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return $row[$field] ?? 0;
    }
    return 0;
}

$totalItems = getSafeValue($conn, "SELECT SUM(Quantity) AS total FROM InventoryItem", 'total');
$lowStock = getSafeValue($conn, "SELECT COUNT(*) AS low FROM InventoryItem WHERE Quantity <= 5", 'low');
$pendingDeliveries = getSafeValue($conn, "SELECT COUNT(*) AS pending FROM Transport WHERE Status='Pending'", 'pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - RB Stores</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- ✅ Include reusable layout parts -->
    <?php 
    $headerFile = __DIR__ . '/header.php';
    $sidebarFile = __DIR__ . '/sidebar.php';
    $footerFile = __DIR__ . '/footer.php';

    if (file_exists($headerFile)) include $headerFile;
    if (file_exists($sidebarFile)) include $sidebarFile;
    ?>

    <!-- ✅ Main Content -->
    <main class="main-content">
        <h2>Welcome to <span class="highlight">RB Stores Dashboard</span></h2>
        <p>Select a menu item to get started.</p>

        <section class="status-panel">
            <h3>Live Inventory Status</h3>
            <div class="status-grid">
                <a href="/admin/inventory.php" class="status-card-link">
                    <div class="status-card">
                        <i class="fas fa-cubes"></i>
                        <h4>Total Items</h4>
                        <p><?php echo htmlspecialchars($totalItems); ?></p>
                    </div>
                </a>
                <a href="/admin/low_stock.php" class="status-card-link">
                    <div class="status-card">
                        <i class="fas fa-warehouse"></i>
                        <h4>Low Stock Alerts</h4>
                        <p><?php echo htmlspecialchars($lowStock); ?></p>
                    </div>
                </a>
                <a href="/admin/pending_deliveries.php" class="status-card-link">
                    <div class="status-card">
                        <i class="fas fa-truck-loading"></i>
                        <h4>Pending Deliveries</h4>
                        <p><?php echo htmlspecialchars($pendingDeliveries); ?></p>
                    </div>
                </a>
            </div>
        </section>
    </main>

    <!-- ✅ Footer -->
    <?php if (file_exists($footerFile)) include $footerFile; ?>
    
</body>
</html>
