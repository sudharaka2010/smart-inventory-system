<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch suppliers
$suppliers = $conn->query("SELECT SupplierID, Name FROM supplier");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierID = intval($_POST['supplier_id']);
    $invoiceID = $conn->real_escape_string($_POST['invoice_id']);
    $receiveDate = $conn->real_escape_string($_POST['receive_date']);

    // Get Supplier Name
    $result = $conn->query("SELECT Name FROM supplier WHERE SupplierID = $supplierID");
    $supplierName = $result ? $result->fetch_assoc()['Name'] : 'Unknown';

    $items = $_POST['items'];
    $success = 0; $fail = 0;

    foreach ($items as $item) {
        $name = $conn->real_escape_string(trim($item['name']));
        $description = $conn->real_escape_string(trim($item['description']));
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $category = !empty($item['category']) ? $conn->real_escape_string($item['category']) : 'Unknown';

        if ($name && $description && $quantity > 0 && $price >= 0) {
            $stmt = $conn->prepare("INSERT INTO inventoryitem (InvoiceID, NAME, Description, Quantity, Price, SupplierID, SupplierName, Category, ReceiveDate) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdissss", $invoiceID, $name, $description, $quantity, $price, $supplierID, $supplierName, $category, $receiveDate);
            $stmt->execute() ? $success++ : $fail++;
        } else {
            $fail++;
        }
    }

    $message = "✅ $success item(s) added successfully.";
    if ($fail > 0) {
        $message .= " ❌ $fail item(s) failed to add.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Inventory Items</title>
    <link rel="stylesheet" href="../assets/css/add_inventory.css">
    <style>
        .item-card {
            border: 1px solid #ccc; padding: 15px; margin-bottom: 10px;
            border-radius: 5px; background: #f9fafb;
        }
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, textarea, select { width: 100%; padding: 8px; }
        .remove-btn { color: red; cursor: pointer; margin-top: 5px; }
        .add-btn { margin-top: 10px; padding: 8px 12px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .submit-btn { margin-top: 15px; padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 5px; font-weight: 600; }
    </style>
    <script>
        let itemIndex = 0;

        function addItem() {
            const container = document.getElementById('items-container');
            const html = `
                <div class="item-card" id="item-${itemIndex}">
                    <div class="form-group"><label>Item Name</label>
                        <input name="items[${itemIndex}][name]" required></div>
                    <div class="form-group"><label>Description</label>
                        <textarea name="items[${itemIndex}][description]" required></textarea></div>
                    <div class="form-group"><label>Quantity</label>
                        <input type="number" name="items[${itemIndex}][quantity]" min="1" required oninput="updateTotal(${itemIndex})"></div>
                    <div class="form-group"><label>Price (LKR)</label>
                        <input type="number" name="items[${itemIndex}][price]" step="0.01" min="0" required oninput="updateTotal(${itemIndex})"></div>
                    <div class="form-group"><label>Category</label>
                        <select name="items[${itemIndex}][category]" required>
                            <option value="">-- Select Category --</option>
                            <option value="Construction">Construction</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Tools">Tools</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <p id="total-${itemIndex}" style="color: #1e40af;">Total: LKR 0.00</p>
                    <span class="remove-btn" onclick="removeItem(${itemIndex})">🗑 Remove</span>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            itemIndex++;
        }

        function removeItem(index) {
            const el = document.getElementById(`item-${index}`);
            if (el) el.remove();
        }

        function updateTotal(index) {
            const qty = parseFloat(document.querySelector(`[name="items[${index}][quantity]"]`).value) || 0;
            const price = parseFloat(document.querySelector(`[name="items[${index}][price]"]`).value) || 0;
            document.getElementById(`total-${index}`).textContent = `Total: LKR ${(qty * price).toFixed(2)}`;
        }

        window.addEventListener('DOMContentLoaded', () => addItem());
    </script>
</head>
<body>
<?php include 'header.php'; include 'sidebar.php'; ?>

<div class="main-content">
    <h2>Add Inventory Items </h2>

    <?php if (isset($message)): ?>
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
                    <option value="<?= $s['SupplierID'] ?>"><?= htmlspecialchars($s['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="invoice_id">Invoice ID</label>
            <input type="text" name="invoice_id" id="invoice_id" required placeholder="e.g., INV20250730001">
        </div>

        <div class="form-group">
            <label for="receive_date">Receive Date</label>
            <input type="datetime-local" name="receive_date" id="receive_date" required min="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div id="items-container"></div>
        <button type="button" class="add-btn" onclick="addItem()">➕ Add Another Item</button>
        <br><br>
        <button type="submit" class="submit-btn">Save All Items</button>
    </form>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
