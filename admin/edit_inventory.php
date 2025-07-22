<link rel="stylesheet" href="../assets/css/add_inventory.css">

<?php

include('../includes/auth.php');
include('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['username']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Staff')) {
    header("Location: ../login.php");
    exit();
}

// Validate ItemID from GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color:red;'>Invalid Item ID.</p>";
    echo "<a href='inventory.php'>Back to Inventory</a>";
    exit();
}

$itemID = intval($_GET['id']);

// Fetch item details
$stmt = $conn->prepare("SELECT * FROM InventoryItem WHERE ItemID = ?");
$stmt->bind_param("i", $itemID);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo "<p style='color:red;'>Item not found!</p>";
    echo "<a href='inventory.php'>Back to Inventory</a>";
    exit();
}

// Fetch suppliers for dropdown
$suppliers = $conn->query("SELECT SupplierID, Name FROM Supplier");

// Update item on POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoiceID   = $conn->real_escape_string($_POST['invoice_id']);
    $name        = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $quantity    = intval($_POST['quantity']);
    $price       = floatval($_POST['price']);
    $supplierID  = intval($_POST['supplier_id']);
    $category    = $conn->real_escape_string($_POST['category']);
    $receiveDate = $conn->real_escape_string($_POST['receive_date']);

    $updateQuery = $conn->prepare(
        "UPDATE InventoryItem SET InvoiceID = ?, Name = ?, Description = ?, Quantity = ?, Price = ?, SupplierID = ?, Category = ?, ReceiveDate = ? WHERE ItemID = ?"
    );
    $updateQuery->bind_param("sssidissi", $invoiceID, $name, $description, $quantity, $price, $supplierID, $category, $receiveDate, $itemID);

    if ($updateQuery->execute()) {
        header("Location: inventory.php?updated=1");
        exit();
    } else {
        echo "<p style='color:red;'>Error updating item: " . htmlspecialchars($conn->error) . "</p>";
    }
}

include 'header.php'; 
include 'sidebar.php'; 

?>



<!-- Main Content -->
<main class="main-content">
    <h2>Edit Inventory Item</h2>
    <div class="content-grid">
        <!-- Edit Inventory Form -->
        <div class="card">
            <form class="add-inventory-form" method="POST" oninput="updatePreview()">
                <!-- Hidden ItemID -->
                <input type="hidden" name="item_id" value="<?php echo intval($item['ItemID']); ?>">

                <div class="form-group">
                    <label>Invoice ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($item['InvoiceID']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" value="<?php echo htmlspecialchars($item['Category']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" value="<?php echo htmlspecialchars($item['SupplierID']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="name" id="item_name" value="<?php echo htmlspecialchars($item['NAME']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description"><?php echo htmlspecialchars($item['Description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="quantity" value="<?php echo $item['Quantity']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Price (LKR)</label>
                    <input type="number" name="price" id="price" value="<?php echo $item['Price']; ?>" step="0.01" required>
                </div>
                <button type="submit" class="submit-btn">Update Item</button>
            </form>
        </div>

        <!-- Invoice Preview -->
        <div class="card">
            <h3>Invoice Preview</h3>
            <div class="invoice-preview" id="invoice-preview">
                <p><strong>Invoice ID:</strong> <span id="preview_invoice"><?php echo htmlspecialchars($item['InvoiceID']); ?></span></p>
                <p><strong>Category:</strong> <span id="preview_category"><?php echo htmlspecialchars($item['Category']); ?></span></p>
                <p><strong>Supplier ID:</strong> <span id="preview_supplier"><?php echo htmlspecialchars($item['SupplierID']); ?></span></p>
                <p><strong>Item Name:</strong> <span id="preview_item"><?php echo htmlspecialchars($item['NAME']); ?></span></p>
                <p><strong>Description:</strong> <span id="preview_description"><?php echo htmlspecialchars($item['Description']); ?></span></p>
                <p><strong>Quantity:</strong> <span id="preview_quantity"><?php echo $item['Quantity']; ?></span></p>
                <p><strong>Price:</strong> LKR <span id="preview_price"><?php echo number_format($item['Price'], 2); ?></span></p>
                <p><strong>Total:</strong> LKR <span id="preview_total"><?php echo number_format($item['Quantity'] * $item['Price'], 2); ?></span></p>
            </div>
            <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print / Save PDF</button>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
function updatePreview() {
    document.getElementById('preview_item').textContent = document.getElementById('item_name').value;
    document.getElementById('preview_description').textContent = document.getElementById('description').value;
    document.getElementById('preview_quantity').textContent = document.getElementById('quantity').value;
    let price = parseFloat(document.getElementById('price').value) || 0;
    document.getElementById('preview_price').textContent = price.toFixed(2);
    let qty = parseFloat(document.getElementById('quantity').value) || 0;
    document.getElementById('preview_total').textContent = (qty * price).toFixed(2);
}
</script>


<?php include 'footer.php'; ?>
