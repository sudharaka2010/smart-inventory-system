<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Get order ID from URL
$orderID = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order & customer info
$orderQuery = "
    SELECT o.OrderID, o.OrderDate, o.TotalAmount,
           c.Name AS CustomerName, c.Email, c.Phone, c.Address
    FROM `Order` o
    JOIN Customer c ON o.CustomerID = c.CustomerID
    WHERE o.OrderID = $orderID
";
$orderResult = $conn->query($orderQuery);
$order = $orderResult->fetch_assoc();

// Fetch itemized order details
$itemsQuery = "
    SELECT i.Name, i.Price, od.Quantity, od.Subtotal
    FROM OrderDetails od
    JOIN InventoryItem i ON od.ItemID = i.ItemID
    WHERE od.OrderID = $orderID
";
$itemsResult = $conn->query($itemsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $orderID; ?></title>
    <link rel="stylesheet" href="../assets/css/invoice.css">
</head>
<body>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="invoice-container">
        <div class="invoice-header">
            <div>
                <h1>INVOICE</h1>
            </div>
            <div class="invoice-meta">
                <p><strong>Invoice #:</strong> <?php echo $order['OrderID']; ?></p>
                <p><strong>Date:</strong> <?php echo $order['OrderDate']; ?></p>
            </div>
        </div>

        <div class="customer-details">
            <h3>Billing To:</h3>
            <p><?php echo htmlspecialchars($order['CustomerName']); ?></p>
            <p>Email: <?php echo htmlspecialchars($order['Email']); ?></p>
            <p>Phone: <?php echo htmlspecialchars($order['Phone']); ?></p>
            <p>Address: <?php echo htmlspecialchars($order['Address']); ?></p>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Unit Price (LKR)</th>
                    <th>Quantity</th>
                    <th>Subtotal (LKR)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $itemsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Name']); ?></td>
                        <td><?php echo number_format($item['Price'], 2); ?></td>
                        <td><?php echo $item['Quantity']; ?></td>
                        <td><?php echo number_format($item['Subtotal'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="total-section">
            <h3>Total: LKR <?php echo number_format($order['TotalAmount'], 2); ?></h3>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>
</html>
