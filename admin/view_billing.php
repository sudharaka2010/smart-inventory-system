<link rel="stylesheet" href="../assets/css/view_billing.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle search filters
$search_date = isset($_GET['search_date']) ? $conn->real_escape_string($_GET['search_date']) : '';
$search_customer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : '';

// Build query
$query = "SELECT b.BillingID, c.Name AS CustomerName, b.BillingDate, b.TotalAmount
          FROM Billing b
          JOIN Customer c ON b.CustomerID = c.CustomerID
          WHERE 1";

if ($search_date) {
    $query .= " AND DATE(b.BillingDate) = '$search_date'";
}

if ($search_customer) {
    $query .= " AND b.CustomerID = $search_customer";
}

$result = $conn->query($query);
?>

<main class="main-content">
    <h2 class="page-title">View Bills</h2>

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

        <!-- Billing Table -->
        <table class="billing-table">
            <thead>
                <tr>
                    <th>Billing ID</th>
                    <th>Customer Name</th>
                    <th>Billing Date</th>
                    <th>Total Amount (LKR)</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['BillingID']; ?></td>
                            <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
                            <td><?php echo $row['BillingDate']; ?></td>
                            <td>LKR <?php echo number_format($row['TotalAmount'], 2); ?></td>
                            <td>
                                <a href="view_bill_details.php?billing_id=<?php echo $row['BillingID']; ?>" class="details-btn">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No bills found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>
