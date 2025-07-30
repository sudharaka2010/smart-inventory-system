<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color:red;'>Invalid Item ID.</p><a href='inventory.php'>Back to Inventory</a>";
    exit();
}

$itemID = intval($_GET['id']);

// Fetch current item
$stmt = $conn->prepare("SELECT * FROM inventoryitem WHERE ItemID = ?");
$stmt->bind_param("i", $itemID);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo "<p style='color:red;'>Item not found!</p><a href='inventory.php'>Back to Inventory</a>";
    exit();
}

// Fetch suppliers
$suppliers = $conn->query("SELECT SupplierID, Name FROM supplier");

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierID = intval($_POST['supplier_id']);
    $invoiceID = $conn->real_escape_string($_POST['invoice_id']);
    $receiveDate = $conn->real_escape_string($_POST['receive_date']);

    // Get Supplier Name
    $result = $conn->query("SELECT Name FROM supplier WHERE SupplierID = $supplierID");
    $supplierName = $result ? $result->fetch_assoc()['Name'] : 'Unknown';

    $name = $conn->real_escape_string(trim($_POST['name']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $category = !empty($_POST['category']) ? $conn->real_escape_string($_POST['category']) : 'Unknown';

    if ($name && $description && $quantity > 0 && $price >= 0) {
        $stmt = $conn->prepare("UPDATE inventoryitem 
            SET InvoiceID=?, NAME=?, Description=?, Quantity=?, Price=?, SupplierID=?, SupplierName=?, Category=?, ReceiveDate=? 
            WHERE ItemID=?");
        $stmt->bind_param("sssdissssi", $invoiceID, $name, $description, $quantity, $price, $supplierID, $supplierName, $category, $receiveDate, $itemID);
        if ($stmt->execute()) {
            $message = "✅ Item updated successfully.";
        } else {
            $message = "❌ Failed to update item.";
        }
    } else {
        $message = "❌ Please fill all required fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Inventory Item</title>
    <link rel="stylesheet" href="../assets/css/edit_inventory.css">
    <style>
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, textarea, select { width: 100%; padding: 8px; }
        .submit-btn { margin-top: 15px; padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 5px; font-weight: 600; }
    </style>
</head>
<body>
<?php include 'header.php'; include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Edit Inventory Item</h2>

    <?php if (!empty($message)): ?>
        <div style="margin: 10px 0; padding: 10px; background-color: #dcfce7; color: #065f46;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="supplier_id">Select Supplier</label>
            <select name="supplier_id" id="supplier_id" required>
                <option value="">-- Choose Supplier --</option>
                <?php while($s = $suppliers->fetch_assoc()): ?>
                    <option value="<?= $s['SupplierID'] ?>" <?= $item['SupplierID'] == $s['SupplierID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['Name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="invoice_id">Invoice ID</label>
            <input type="text" name="invoice_id" id="invoice_id" required value="<?= htmlspecialchars($item['InvoiceID']) ?>">
        </div>

        <div class="form-group">
            <label for="receive_date">Receive Date</label>
            <input type="datetime-local" name="receive_date" id="receive_date"
                   value="<?= date('Y-m-d\TH:i', strtotime($item['ReceiveDate'])) ?>"
                   required min="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="form-group">
            <label>Item Name</label>
            <input name="name" required value="<?= htmlspecialchars($item['NAME']) ?>">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" required><?= htmlspecialchars($item['Description']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" required value="<?= $item['Quantity'] ?>">
        </div>

        <div class="form-group">
            <label>Price (LKR)</label>
            <input type="number" name="price" step="0.01" min="0" required value="<?= $item['Price'] ?>">
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category" required>
                <option value="">-- Select Category --</option>
                <option value="Construction" <?= $item['Category'] === 'Construction' ? 'selected' : '' ?>>Construction</option>
                <option value="Plumbing" <?= $item['Category'] === 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
                <option value="Tools" <?= $item['Category'] === 'Tools' ? 'selected' : '' ?>>Tools</option>
                <option value="Electrical" <?= $item['Category'] === 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                <option value="Other" <?= $item['Category'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <button type="submit" class="submit-btn">Update Item</button>
    </form>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
