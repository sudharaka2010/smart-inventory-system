<link rel="stylesheet" href="../assets/css/view_billing.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle search filters
$search_date = isset($_GET['search_date']) ? $conn->real_escape_string($_GET['search_date']) : '';
$search_customer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : '';

// Build query based on `Order` table
$query = "SELECT o.OrderID, c.Name AS CustomerName, o.OrderDate, o.TotalAmount
          FROM `Order` o
          JOIN Customer c ON o.CustomerID = c.CustomerID
          WHERE 1";

if ($search_date) {
    $query .= " AND DATE(o.OrderDate) = '$search_date'";
}

if ($search_customer) {
    $query .= " AND o.CustomerID = $search_customer";
}

$result = $conn->query($query);
?>

<main class="main-content">
    <h2 class="page-title">View Orders</h2>

    <!-- Unified Filter and Table Card -->
    <div class="card unified-card">
        <!-- Search Filters -->
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label for="search_date">Search by Date:</label>
                <input type="date" name="search_date" id="search_date" value="<?php echo htmlspecialchars($search_date); ?>">
            </div>
            <div class="form-group">
                <label for="customer_id">Search by Customer ID:</label>
                <input type="number" name="customer_id" id="customer_id" value="<?php echo htmlspecialchars($search_customer); ?>" placeholder="Enter Customer ID">
            </div>
            <button type="submit" class="filter-btn">Search</button>
        </form>

        <!-- Orders Table -->
        <table class="billing-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Name</th>
                    <th>Order Date</th>
                    <th>Total Amount (LKR)</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['OrderID']; ?></td>
                            <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
                            <td><?php echo $row['OrderDate']; ?></td>
                            <td>LKR <?php echo number_format($row['TotalAmount'], 2); ?></td>
                            <td>
                                <a href="invoice.php?order_id=<?php echo $row['OrderID']; ?>" class="details-btn">View</a>

                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>
