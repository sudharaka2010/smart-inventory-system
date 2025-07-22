<link rel="stylesheet" href="../assets/css/add_inventory.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch suppliers for dropdown
$supplierResult = $conn->query("SELECT SupplierID, Name FROM Supplier");

$generatedInvoiceID = "";

// Function to auto-generate InvoiceID
function generateInvoiceID($conn) {
    $datePart = date('Ymd'); // YYYYMMDD
    $result = $conn->query("SELECT COUNT(*) AS count FROM InventoryItem WHERE DATE(ReceiveDate) = CURDATE()");
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1; // Increment count for today's invoices
    return 'INV' . $datePart . str_pad($count, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $generatedInvoiceID = generateInvoiceID($conn);
    $supplierID = intval($_POST['supplier_id']);
    $category = $conn->real_escape_string($_POST['category']);
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $receive_date = $conn->real_escape_string($_POST['receive_date']);
    $qty = intval($_POST['quantity']);
    $price = floatval($_POST['price']);

    $sql = "INSERT INTO InventoryItem (InvoiceID, SupplierID, Category, Name, Description, ReceiveDate, Quantity, Price)
            VALUES ('$generatedInvoiceID', $supplierID, '$category', '$name', '$desc', '$receive_date', $qty, $price)";

    if ($conn->query($sql)) {
        header("Location: inventory.php");
        exit();
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
include 'header.php';
include 'sidebar.php';
?>


<!-- Main Content -->
<main class="main-content">
    <h2>Add New Inventory Item</h2>
    <div class="content-grid">
        <!-- Add Inventory Form -->
        <div class="card">
            <form class="add-inventory-form" method="POST" action="" oninput="updatePreview()">
                <div class="form-group">
                    <label>Invoice ID</label>
                    <input type="text" name="invoice_id" id="invoice_id" value="<?php echo $generatedInvoiceID ?: 'AUTO-GENERATE ON SAVE'; ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="category" required>
                        <option value="">--Select Category--</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Construction">Construction</option>
                        <option value="Rainwater Management">Rainwater Management</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id" id="supplier" required>
                        <option value="">-- Select Supplier --</option>
                        <?php while ($row = $supplierResult->fetch_assoc()) { ?>
                        <option value="<?php echo $row['SupplierID']; ?>">
                            <?php echo htmlspecialchars($row['Name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="name" id="item_name" placeholder="Enter item name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" placeholder="Enter description"></textarea>
                </div>
                <div class="form-group">
                    <label>Items Receive Date</label>
                    <input type="datetime-local" name="receive_date" id="receive_date" required>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="quantity" placeholder="Enter quantity" required>
                </div>
                <div class="form-group">
                    <label>Price Per Item (LKR)</label>
                    <input type="number" name="price" id="price" placeholder="Enter price per item" step="0.01" required>
                </div>
                <button type="submit" class="submit-btn">Add Item</button>
            </form>
        </div>

        <!-- Invoice Preview -->
        <div class="card">
            <h3>Invoice Preview</h3>
            <div class="invoice-preview" id="invoice-preview">
                <p><strong>Invoice ID:</strong> <span id="preview_invoice"><?php echo $generatedInvoiceID ?: '--'; ?></span></p>
                <p><strong>Date & Time:</strong> <span id="preview_date">--</span></p>
                <p><strong>Category:</strong> <span id="preview_category">--</span></p>
                <p><strong>Item Name:</strong> <span id="preview_item">--</span></p>
                <p><strong>Description:</strong> <span id="preview_description">--</span></p>
                <p><strong>Quantity:</strong> <span id="preview_quantity">0</span></p>
                <p><strong>Price Per Item:</strong> LKR <span id="preview_price">0.00</span></p>
                <p><strong>Total:</strong> LKR <span id="preview_total">0.00</span></p>
            </div>
            <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print / Save PDF</button>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
function updatePreview() {
    document.getElementById('preview_date').textContent = document.getElementById('receive_date').value;
    document.getElementById('preview_category').textContent = document.getElementById('category').value;
    document.getElementById('preview_item').textContent = document.getElementById('item_name').value;
    document.getElementById('preview_description').textContent = document.getElementById('description').value;
    document.getElementById('preview_quantity').textContent = document.getElementById('quantity').value;
    document.getElementById('preview_price').textContent = parseFloat(document.getElementById('price').value).toFixed(2);
    let qty = parseFloat(document.getElementById('quantity').value) || 0;
    let price = parseFloat(document.getElementById('price').value) || 0;
    document.getElementById('preview_total').textContent = (qty * price).toFixed(2);
}
</script>

<?php include 'footer.php'; ?>
