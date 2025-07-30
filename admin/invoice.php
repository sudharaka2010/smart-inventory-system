<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Get order ID
$orderID = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order and customer data
$orderQuery = "
    SELECT o.OrderID, o.InvoiceID, o.OrderDate, o.SubTotal, o.Discount, o.VAT, o.TotalAmount, o.AmountPaid, o.Balance,
           c.Name AS CustomerName, c.Email, c.Phone, c.Address
    FROM `Order` o
    JOIN Customer c ON o.CustomerID = c.CustomerID
    WHERE o.OrderID = $orderID
";
$orderResult = $conn->query($orderQuery);
$order = $orderResult->fetch_assoc();

// Fetch item details
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
    <link rel="stylesheet" href="../assets/css/view_billing.css">
    <style>
        .invoice-container {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            max-width: 1000px;
            margin: auto;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 30px;
        }

        .company-info {
            font-size: 14px;
            line-height: 1.6;
        }

        .company-info h2 {
            margin: 0 0 8px;
            font-size: 22px;
            color: #1e293b;
        }

        .invoice-meta {
            text-align: right;
            font-size: 14px;
        }

        .invoice-meta p {
            margin: 4px 0;
        }

        .customer-details {
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 14px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 15px;
        }

        .invoice-table th, .invoice-table td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #f8fafc;
            font-weight: 600;
        }

        .total-section {
            text-align: right;
            font-size: 16px;
            font-weight: 600;
        }

        .print-btn {
            margin: 20px auto;
            display: block;
            padding: 10px 18px;
            background-color: #2563eb;
            color: #fff;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .print-btn:hover {
            background-color: #1d4ed8;
        }

        @media (max-width: 768px) {
            .invoice-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .invoice-meta {
                text-align: center;
            }

            .total-section {
                text-align: left;
            }

            .print-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="invoice-container" id="invoiceArea">
        <div class="invoice-header">
            <div class="company-info">
                <h2>RB Stores</h2>
                <p>Godagama, Matara, Sri Lanka</p>
                <p>Email: info@rbstores.lk</p>
                <p>Phone: +94 77 123 4567</p>
            </div>
            <div class="invoice-meta">
                <p><strong>Invoice #:</strong> <?php echo $order['InvoiceID']; ?></p>
                <p><strong>Date:</strong> <?php echo $order['OrderDate']; ?></p>
            </div>
        </div>

        <div class="customer-details">
            <h3>Billing To:</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['CustomerName']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['Email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['Phone']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['Address']); ?></p>
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
            <p>Sub Total: LKR <?php echo number_format($order['SubTotal'], 2); ?></p>
            <p>Discount: <?php echo rtrim(rtrim(number_format($order['Discount'], 2), '0'), '.'); ?>%</p>
            <p>VAT: <?php echo rtrim(rtrim(number_format($order['VAT'], 2), '0'), '.'); ?>%</p>
            <p><strong>Total: LKR <?php echo number_format($order['TotalAmount'], 2); ?></strong></p>
            <p>Paid: LKR <?php echo number_format($order['AmountPaid'], 2); ?></p>
            <p>Balance: LKR <?php echo number_format($order['Balance'], 2); ?></p>
        </div>
    </div>

    <!-- Print Button -->
    <button class="print-btn" onclick="printInvoice()">🖨️ Print Invoice</button>
</main>

<script>
function printInvoice() {
    let printContents = document.getElementById('invoiceArea').outerHTML;
    let printWindow = window.open('', '', 'width=1000,height=700');
    printWindow.document.write('<html><head><title>Print Invoice</title>');
    printWindow.document.write('<link rel="stylesheet" href="../assets/css/view_billing.css">');
    printWindow.document.write('<style>body{font-family:Poppins,sans-serif;padding:20px;} .invoice-container{max-width:900px;margin:auto;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
