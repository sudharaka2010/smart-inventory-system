<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch suppliers
$suppliers = $conn->query("SELECT SupplierID, Name FROM supplier");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceID = $conn->real_escape_string($_POST['invoice_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $supplierID = intval($_POST['supplier_id']);

    // ✅ Fixed: category cannot be blank or 0
    $category = isset($_POST['category']) && $_POST['category'] !== ''
                ? $conn->real_escape_string($_POST['category'])
                : 'Unknown';

    $receiveDate = $conn->real_escape_string($_POST['receive_date']);

    $stmt = $conn->prepare("INSERT INTO inventoryitem (InvoiceID, NAME, Description, Quantity, Price, SupplierID, Category, ReceiveDate) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdisis", $invoiceID, $name, $description, $quantity, $price, $supplierID, $category, $receiveDate);

    if ($stmt->execute()) {
        $message = "✅ Item successfully added.";
    } else {
        $message = "❌ Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Inventory Item</title>
    <link rel="stylesheet" href="../assets/css/add_inventory.css">
    <script>
        function updateTotal() {
            const qty = parseFloat(document.getElementById('quantity').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;
            const total = qty * price;
            document.getElementById('totalPreview').textContent = `Total Value: LKR ${total.toFixed(2)}`;
        }
    </script>
</head>
<body>
<?php include 'header.php'; include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Add Inventory Item</h2>

    <?php if (isset($message)): ?>
        <div style="color: green; margin-bottom: 15px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="add-inventory-form" oninput="updateTotal()">
        <div class="form-group">
            <label for="invoice_id">Invoice ID</label>
            <input type="text" name="invoice_id" id="invoice_id" placeholder="e.g., INV20250725001" required>
        </div>

        <div class="form-group">
            <label for="name">Item Name</label>
            <input type="text" name="name" id="name" placeholder="e.g., PVC Pipe" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" placeholder="Describe the item" required></textarea>
        </div>

        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" id="quantity" min="1" required>
        </div>

        <div class="form-group">
            <label for="price">Price (LKR)</label>
            <input type="number" step="0.01" name="price" id="price" min="0" required>
        </div>

        <p id="totalPreview" style="margin-top: 5px; color: #1e40af;">Total Value: LKR 0.00</p>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select name="supplier_id" id="supplier_id" required>
                <option value="">-- Select Supplier --</option>
                <?php while($s = $suppliers->fetch_assoc()): ?>
                    <option value="<?= $s['SupplierID'] ?>"><?= htmlspecialchars($s['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- ✅ Fixed Category Dropdown -->
        <div class="form-group">
            <label for="category">Category</label>
            <select name="category" id="category" required>
                <option value="">-- Select Category --</option>
                <option value="Construction">Construction</option>
                <option value="Plumbing">Plumbing</option>
                <option value="Tools">Tools</option>
                <option value="Electrical">Electrical</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label for="receive_date">Receive Date</label>
            <input type="datetime-local" name="receive_date" id="receive_date" required>
        </div>

        <button type="submit" class="submit-btn">Add Item</button>
    </form>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
