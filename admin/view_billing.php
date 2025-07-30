<link rel="stylesheet" href="../assets/css/view_billing.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle delete securely
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM `order` WHERE OrderID = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: view_billing.php");
    exit();
}

// Filters
$search_date = $_GET['search_date'] ?? '';
$search_customer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : '';

$query = "SELECT o.OrderID, o.InvoiceID, c.Name AS CustomerName, o.OrderDate,
                 o.SubTotal, o.Discount, o.VAT, o.TotalAmount,
                 o.AmountPaid, o.Balance, o.Status
          FROM `order` o
          JOIN Customer c ON o.CustomerID = c.CustomerID
          WHERE 1";
$params = [];
$types = "";

if (!empty($search_date)) {
    $query .= " AND DATE(o.OrderDate) = ?";
    $params[] = $search_date;
    $types .= "s";
}
if (!empty($search_customer)) {
    $query .= " AND o.CustomerID = ?";
    $params[] = $search_customer;
    $types .= "i";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<main class="main-content">
    <h2 class="page-title">View Orders</h2>

    <div class="card unified-card">
        <!-- Filters -->
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label for="search_date">Search by Date:</label>
                <input type="date" name="search_date" id="search_date" value="<?php echo htmlspecialchars($search_date); ?>">
            </div>
            <div class="form-group">
                <label for="customer_id">Search by Customer:</label>
                <select name="customer_id" id="customer_id">
                    <option value="">All Customers</option>
                    <?php
                    $cust_result = $conn->query("SELECT CustomerID, Name FROM Customer");
                    while ($cust = $cust_result->fetch_assoc()) {
                        $selected = ($cust['CustomerID'] == $search_customer) ? 'selected' : '';
                        echo "<option value='{$cust['CustomerID']}' $selected>" . htmlspecialchars($cust['Name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="filter-btn">Search</button>
        </form>

        <!-- Actions -->
        <div class="actions-bar">
            <button onclick="printTable()" class="action-btn">🖨️ Print</button>
            <button onclick="exportTableToExcel('ordersTable')" class="action-btn">📁 Export to Excel</button>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table class="billing-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order ID</th>
                        <th>Invoice ID</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Sub Total (LKR)</th>
                        <th>Discount (%)</th>
                        <th>VAT (%)</th>
                        <th>Total (LKR)</th>
                        <th>Paid (LKR)</th>
                        <th>Balance (LKR)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $sn = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td><?php echo $row['OrderID']; ?></td>
                                <td><?php echo htmlspecialchars($row['InvoiceID']); ?></td>
                                <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
                                <td><?php echo htmlspecialchars($row['OrderDate']); ?></td>
                                <td>LKR <?php echo number_format($row['SubTotal'], 2); ?></td>
                                <td><?php echo rtrim(rtrim(number_format($row['Discount'], 2), '0'), '.'); ?>%</td>
                                <td><?php echo rtrim(rtrim(number_format($row['VAT'], 2), '0'), '.'); ?>%</td>
                                <td><strong>LKR <?php echo number_format($row['TotalAmount'], 2); ?></strong></td>
                                <td>LKR <?php echo number_format($row['AmountPaid'], 2); ?></td>
                                <td>LKR <?php echo number_format($row['Balance'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['Status']); ?></td>
                                <td>
                                    <a href="invoice.php?order_id=<?php echo $row['OrderID']; ?>" class="details-btn">View</a>
                                    <a href="edit_billing.php?order_id=<?php echo $row['OrderID']; ?>" class="edit-btn">Edit</a>
                                    <a href="?delete=<?php echo $row['OrderID']; ?>" onclick="return confirm('Are you sure to delete this order?')" class="delete-btn">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="13">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- SheetJS Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- JS for Print and Export -->
<script>
function printTable() {
    let printWindow = window.open('', '', 'height=600,width=1000');
    printWindow.document.write('<html><head><title>Print Orders</title>');
    printWindow.document.write('<link rel="stylesheet" href="../assets/css/view_billing.css">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(document.getElementById('ordersTable').outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

function exportTableToExcel(tableID, filename = 'Orders_Export') {
    var table = document.getElementById(tableID);
    var workbook = XLSX.utils.table_to_book(table, {sheet: "Orders"});
    XLSX.writeFile(workbook, filename + ".xlsx");
}
</script>

<?php include 'footer.php'; ?>
