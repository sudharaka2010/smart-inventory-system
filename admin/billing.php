<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Generate invoice ID
$latestOrder = $conn->query("SELECT MAX(OrderID) AS MaxID FROM `Order`")->fetch_assoc();
$invoiceID = 'INV-' . str_pad(($latestOrder['MaxID'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

// Fetch customers and items
$customers = $conn->query("SELECT CustomerID, Name FROM Customer");
$items = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");
$itemOptions = "";
while ($item = $items->fetch_assoc()) {
    $itemOptions .= '<option value="'.$item['ItemID'].'" data-price="'.$item['Price'].'">'.
                    htmlspecialchars($item['Name']).' (LKR '.$item['Price'].')</option>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = 0;
    if ($_POST['customer_type'] === 'new') {
        $name = $conn->real_escape_string($_POST['new_name']);
        $email = $conn->real_escape_string($_POST['new_email']);
        $phone = $conn->real_escape_string($_POST['new_phone']);
        $address = $conn->real_escape_string($_POST['new_address']);
        $conn->query("INSERT INTO Customer (Name, Email, Phone, Address) VALUES ('$name', '$email', '$phone', '$address')");
        $customerID = $conn->insert_id;
    } else {
        $customerID = intval($_POST['existing_customer']);
    }

    $subtotal = floatval($_POST['subtotal']);
    $discount = floatval($_POST['discount']);
    $tax = floatval($_POST['tax']);
    $total = floatval($_POST['total']);
    $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
    $amountPaid = floatval($_POST['amount_paid']);
    $balance = $amountPaid - $total;

    $conn->query("INSERT INTO `Order` (CustomerID, OrderDate, TotalAmount, PaymentMethod, AmountPaid, Balance)
              VALUES ($customerID, NOW(), $total, '$paymentMethod', $amountPaid, $balance)");

    $orderID = $conn->insert_id;

    foreach ($_POST['items'] as $item) {
        $itemID = intval($item['item_id']);
        $qty = intval($item['quantity']);
        $price = floatval($item['price']);
        $conn->query("INSERT INTO OrderDetails (OrderID, ItemID, Quantity, Subtotal) VALUES ($orderID, $itemID, $qty, " . ($price * $qty) . ")");
    }

    header("Location: view_billing.php?id=$orderID");
    exit();
}

include 'header.php';
include 'sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/billing.css">
<main class="main-content">
  <h2 class="page-title">Create Customer Bill</h2>
  <form method="POST" class="billing-form" oninput="calculateTotals()">
    <input type="hidden" name="invoice_id" value="<?= $invoiceID ?>">
    <div class="tabs">
      <label><input type="radio" name="customer_type" value="existing" checked onchange="switchCustomerType(this.value)"> Existing Customer</label>
      <label><input type="radio" name="customer_type" value="new" onchange="switchCustomerType(this.value)"> New Customer</label>
    </div>

    <div id="existing-customer-group" class="form-group">
      <label>Select Customer</label>
      <select name="existing_customer" required>
        <option value="">-- Select Customer --</option>
        <?php while($row = $customers->fetch_assoc()) {
            echo '<option value="'.$row['CustomerID'].'">'.htmlspecialchars($row['Name']).'</option>';
        } ?>
      </select>
    </div>

<div id="new-customer-fields" class="form-group" style="display:none;">
  <input type="text" name="new_name" placeholder="Full Name" id="new_name">
  <input type="email" name="new_email" placeholder="Email" id="new_email">
  <input type="tel" name="new_phone" placeholder="Phone" id="new_phone">
  <textarea name="new_address" placeholder="Address" id="new_address"></textarea>
</div>


    <h3>Items</h3>
    <div id="items-container">
      <div class="item-row">
        <select name="items[0][item_id]" onchange="updatePrice(this)" required><?= $itemOptions ?></select>
        <input type="number" name="items[0][quantity]" min="1" value="1" required>
        <input type="text" name="items[0][price]" readonly>
        <button type="button" onclick="removeItem(this)">X</button>
      </div>
    </div>
    <button type="button" onclick="addItem()">+ Add Item</button>

    <div class="totals">
      <label>Subtotal</label><input type="text" name="subtotal" id="subtotal" readonly>
      <label>Discount (%)</label><input type="number" name="discount" id="discount" value="0">
      <label>VAT (%)</label><input type="number" name="tax" id="tax" value="0">
      <label>Total</label><input type="text" name="total" id="total" readonly>
    </div>

    <div class="payment-group">
      <label>Payment Method</label>
      <select name="payment_method" required>
        <option value="Cash">Cash</option>
        <option value="Card">Card</option>
        <option value="Credit">Credit</option>
      </select>
      <label>Amount Paid</label><input type="number" name="amount_paid" id="amount_paid" required>
      <label>Balance</label><input type="text" id="balance" readonly>
    </div>

    <button type="submit">Save & Generate Invoice</button>
  </form>
</main>
<script>

let itemIndex = 1;

function switchCustomerType(type) {
  const isNew = type === 'new';
  document.getElementById('existing-customer-group').style.display = isNew ? 'none' : 'block';
  document.getElementById('new-customer-fields').style.display = isNew ? 'block' : 'none';

  // Toggle required attributes
  document.getElementById('new_name').required = isNew;
  document.getElementById('new_email').required = isNew;
  document.getElementById('new_phone').required = isNew;
  document.getElementById('new_address').required = isNew;

  document.querySelector('[name="existing_customer"]').required = !isNew;
}

function addItem() {
  const container = document.getElementById('items-container');
  const row = document.createElement('div');
  row.classList.add('item-row');
  row.innerHTML = `
    <select name="items[${itemIndex}][item_id]" onchange="updatePrice(this)" required><?= $itemOptions ?></select>
    <input type="number" name="items[${itemIndex}][quantity]" min="1" value="1" required>
    <input type="text" name="items[${itemIndex}][price]" readonly>
    <button type="button" onclick="removeItem(this)">X</button>
  `;
  container.appendChild(row);
  itemIndex++;
}
function removeItem(btn) {
  btn.closest('.item-row').remove();
  calculateTotals();
}
function updatePrice(select) {
  const price = select.selectedOptions[0].getAttribute('data-price');
  const row = select.closest('.item-row');
  row.querySelector('[name$="[price]"]').value = price;
  calculateTotals();
}
function calculateTotals() {
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name$="[price]"]').value) || 0;
    subtotal += qty * price;
  });
  const discount = parseFloat(document.getElementById('discount').value) || 0;
  const tax = parseFloat(document.getElementById('tax').value) || 0;
  const total = subtotal * (1 - discount / 100) * (1 + tax / 100);
  document.getElementById('subtotal').value = subtotal.toFixed(2);
  document.getElementById('total').value = total.toFixed(2);
  const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
  document.getElementById('balance').value = (paid - total).toFixed(2);
}
</script>
<?php include 'footer.php'; ?>
