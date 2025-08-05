<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

$success = $error = "";

// Fetch inventory items with join to supplier
$items = $conn->query("
    SELECT i.ItemID, i.NAME, i.InvoiceID, i.Quantity, s.SupplierID, s.Name AS SupplierName 
    FROM inventoryitem i
    JOIN supplier s ON i.SupplierID = s.SupplierID
");

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemID = intval($_POST['item_id']);
    $invoiceID = $_POST['invoice_id'];
    $supplierID = intval($_POST['supplier_id']);
    $supplierName = $_POST['supplier_name'];
    $returnQty = intval($_POST['return_quantity']);
    $reason = trim($_POST['return_reason']);

    // Validate stock availability
    $check = $conn->query("SELECT Quantity FROM inventoryitem WHERE ItemID = $itemID");
    $row = $check->fetch_assoc();

    if ($row && $returnQty > 0 && $returnQty <= $row['Quantity']) {
        $stmt = $conn->prepare("INSERT INTO returnitem (ItemID, InvoiceID, ReturnQuantity, ReturnReason, SupplierID, SupplierName) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isisis", $itemID, $invoiceID, $returnQty, $reason, $supplierID, $supplierName);
        if ($stmt->execute()) {
            $success = "Return successfully recorded.";
        } else {
            $error = "Failed to record return.";
        }
    } else {
        $error = "Invalid return quantity.";
    }
}
?>

<link rel="stylesheet" href="../assets/css/add_return.css">

<main class="return-wrapper">
    <div class="form-container">
        <h2>Inventory Return Form</h2>

        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php elseif ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST" class="return-form">
            <label for="item_id">Select Item</label>
            <select name="item_id" id="item_id" required onchange="updateFields(this)">
                <option value="">-- Select an Item --</option>
                <?php while ($item = $items->fetch_assoc()): ?>
                    <option value="<?= $item['ItemID'] ?>"
                        data-invoice="<?= $item['InvoiceID'] ?>"
                        data-supplier="<?= $item['SupplierID'] ?>"
                        data-supplier-name="<?= $item['SupplierName'] ?>">
                        <?= $item['NAME'] ?> (Qty: <?= $item['Quantity'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>

            <input type="hidden" name="supplier_id" id="supplier_id">
            <input type="hidden" name="supplier_name" id="supplier_name">

            <label for="invoice_id">Invoice ID</label>
            <input type="text" name="invoice_id" id="invoice_id" readonly required>

            <label for="return_quantity">Return Quantity</label>
            <input type="number" name="return_quantity" min="1" required>

            <label for="return_reason">Return Reason</label>
            <textarea name="return_reason" required></textarea>

            <button type="submit" class="submit-btn">Submit Return</button>
        </form>
    </div>
</main>

<script>
function updateFields(select) {
    const invoiceID = select.selectedOptions[0].getAttribute('data-invoice');
    const supplierID = select.selectedOptions[0].getAttribute('data-supplier');
    const supplierName = select.selectedOptions[0].getAttribute('data-supplier-name');

    document.getElementById('invoice_id').value = invoiceID;
    document.getElementById('supplier_id').value = supplierID;
    document.getElementById('supplier_name').value = supplierName;
}
</script>
