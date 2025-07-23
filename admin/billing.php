<link rel="stylesheet" href="../assets/css/billing.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch customers and inventory items
$customerResult = $conn->query("SELECT CustomerID, Name FROM Customer");
$itemResult = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a new customer
    if ($_POST['customer_id'] === 'new') {
        $name = $conn->real_escape_string($_POST['new_name']);
        $email = $conn->real_escape_string($_POST['new_email']);
        $phone = $conn->real_escape_string($_POST['new_phone']);
        $address = $conn->real_escape_string($_POST['new_address']);

        $conn->query("INSERT INTO Customer (Name, Email, Phone, Address)
                      VALUES ('$name', '$email', '$phone', '$address')");

        $customerID = $conn->insert_id; // Use newly created CustomerID
    } else {
        $customerID = intval($_POST['customer_id']);
    }

    $totalAmount = floatval($_POST['total_amount']);

    $sqlBilling = "INSERT INTO `Order` (CustomerID, OrderDate, TotalAmount)
                   VALUES ($customerID, NOW(), $totalAmount)";
    if ($conn->query($sqlBilling)) {
        $orderID = $conn->insert_id;

        foreach ($_POST['items'] as $item) {
            $itemID = intval($item['item_id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            $subtotal = $quantity * $price;

            $sqlDetails = "INSERT INTO OrderDetails (OrderID, ItemID, Quantity, Subtotal)
                           VALUES ($orderID, $itemID, $quantity, $subtotal)";
            $conn->query($sqlDetails);
        }
        header("Location: billing.php?success=1");
        exit();
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
include 'header.php';
include 'sidebar.php';
?>

<main class="main-content">
    <h2 class="page-title">Create New Bill</h2>
    <div class="content-wrapper">
        <div class="card billing-card">
            <h3>Billing Form</h3>
            <form class="billing-form" method="POST" oninput="updatePreview()">
                <div class="form-group">
                    <label>Customer</label>
                    <select name="customer_id" id="customer" onchange="toggleNewCustomerFields()" required>
                        <option value="">-- Select Customer --</option>
                        <?php while ($row = $customerResult->fetch_assoc()) { ?>
                        <option value="<?php echo $row['CustomerID']; ?>">
                            <?php echo htmlspecialchars($row['Name'] . " (ID: " . $row['CustomerID'] . ")"); ?>
                        </option>
                        <?php } ?>
                        <option value="new">+ Register New Customer</option>
                    </select>
                </div>

                <!-- New Customer Fields (Hidden by default) -->
                <div id="new-customer-fields" style="display:none; margin-top:10px;">
                    <h4>New Customer Details</h4>
                    <input type="text" name="new_name" placeholder="Full Name" required>
                    <input type="email" name="new_email" placeholder="Email" required>
                    <input type="text" name="new_phone" placeholder="Phone" required>
                    <textarea name="new_address" placeholder="Address" required></textarea>
                </div>

                <div id="items-container">
                    <h4>Items</h4>
                    <!-- First Item Row -->
                    <div class="item-row">
                        <select name="items[0][item_id]" class="item-select" onchange="updatePrice(this)" required>
                            <option value="">-- Select Item --</option>
                            <?php
                            $itemResult = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");
                            while ($item = $itemResult->fetch_assoc()) {
                                echo '<option value="'.$item['ItemID'].'" data-price="'.$item['Price'].'">'
                                     .htmlspecialchars($item['Name']).' (LKR '.$item['Price'].')</option>';
                            }
                            ?>
                        </select>
                        <input type="number" name="items[0][quantity]" placeholder="Qty" min="1" class="quantity-input" required>
                        <input type="text" name="items[0][price]" placeholder="Price" readonly class="price-input">
                        <button type="button" onclick="removeItem(this)" class="remove-btn">X</button>
                    </div>
                </div>
                <button type="button" onclick="addItem()" class="add-item-btn">+ Add Item</button>

                <div class="form-group total-group">
                    <label>Total Amount (LKR)</label>
                    <input type="number" name="total_amount" id="total_amount" readonly>
                </div>
                <button type="submit" class="submit-btn">Save Bill</button>
            </form>
        </div>

        <!-- Bill Preview -->
        <div class="card preview-card">
            <h3>Bill Preview</h3>
            <div class="bill-preview" id="bill-preview">
                <p><strong>Customer:</strong> <span id="preview_customer">-- Select Customer --</span></p>
                <p><strong>Date:</strong> <span id="preview_date"></span></p>
                <div id="preview_items"></div>
                <p><strong>Total:</strong> LKR <span id="preview_total">0.00</span></p>
            </div>
            <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print / Save PDF</button>
        </div>
    </div>
</main>

<script>
function updateDateTime() {
    const now = new Date();
    const formatted = now.getFullYear() + "-" +
        String(now.getMonth() + 1).padStart(2, '0') + "-" +
        String(now.getDate()).padStart(2, '0') + " " +
        String(now.getHours()).padStart(2, '0') + ":" +
        String(now.getMinutes()).padStart(2, '0') + ":" +
        String(now.getSeconds()).padStart(2, '0');
    document.getElementById('preview_date').textContent = formatted;
}
setInterval(updateDateTime, 1000); // Update every second
updateDateTime(); // Run immediately on load
</script>

<script>
let itemIndex = 1;

function toggleNewCustomerFields() {
    let customerSelect = document.getElementById('customer');
    let newCustomerFields = document.getElementById('new-customer-fields');
    if (customerSelect.value === 'new') {
        newCustomerFields.style.display = 'block';
    } else {
        newCustomerFields.style.display = 'none';
    }
}

function addItem() {
    let container = document.getElementById('items-container');
    let row = document.createElement('div');
    row.classList.add('item-row');
    row.innerHTML = `
        <select name="items[${itemIndex}][item_id]" class="item-select" onchange="updatePrice(this)" required>
            <option value="">-- Select Item --</option>
            <?php
            $itemResult = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");
            while ($item = $itemResult->fetch_assoc()) {
                echo '<option value="'.$item['ItemID'].'" data-price="'.$item['Price'].'">'
                     .htmlspecialchars($item['Name']).' (LKR '.$item['Price'].')</option>';
            }
            ?>
        </select>
        <input type="number" name="items[${itemIndex}][quantity]" placeholder="Qty" min="1" class="quantity-input" required>
        <input type="text" name="items[${itemIndex}][price]" placeholder="Price" readonly class="price-input">
        <button type="button" onclick="removeItem(this)" class="remove-btn">X</button>
    `;
    container.appendChild(row);
    itemIndex++;
}

function removeItem(btn) {
    btn.parentElement.remove();
    updatePreview();
}

function updatePrice(select) {
    let price = select.options[select.selectedIndex].getAttribute('data-price');
    let priceInput = select.nextElementSibling.nextElementSibling;
    priceInput.value = price;
    updatePreview();
}

function updatePreview() {
    let customerName = document.getElementById('customer').options[document.getElementById('customer').selectedIndex].text;
    document.getElementById('preview_customer').textContent = customerName;

    let total = 0;
    let itemsHTML = '';
    document.querySelectorAll('.item-row').forEach(row => {
        let itemText = row.querySelector('.item-select').selectedOptions[0].text;
        let qty = row.querySelector('.quantity-input').value || 0;
        let price = parseFloat(row.querySelector('.price-input').value) || 0;
        let subtotal = qty * price;
        total += subtotal;
        itemsHTML += `<p>${itemText} - ${qty} pcs @ LKR ${price} = LKR ${subtotal.toFixed(2)}</p>`;
    });
    document.getElementById('preview_items').innerHTML = itemsHTML;
    document.getElementById('preview_total').textContent = total.toFixed(2);
    document.getElementById('total_amount').value = total.toFixed(2);
}
</script>

<?php include 'footer.php'; ?>
