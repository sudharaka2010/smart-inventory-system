<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Generate Invoice ID
$latestOrder = $conn->query("SELECT MAX(OrderID) AS MaxID FROM `Order`")->fetch_assoc();
$maxID = isset($latestOrder['MaxID']) ? intval($latestOrder['MaxID']) : 0;
$invoiceID = 'INV-' . str_pad($maxID + 1, 5, '0', STR_PAD_LEFT);

// Fetch customers and items
$customers = $conn->query("SELECT CustomerID, Name FROM Customer");
$items = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");
$itemOptions = "";
while ($item = $items->fetch_assoc()) {
  $itemOptions .= '<option value="'.$item['ItemID'].'" data-price="'.$item['Price'].'">'.
                  htmlspecialchars($item['Name']).' (LKR '.$item['Price'].')</option>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $invoiceID = $conn->real_escape_string($_POST['invoice_id']);
  $customerID = 0;

  if ($_POST['customer_type'] === 'new') {
    $name = $conn->real_escape_string($_POST['new_name']);
    $email = $conn->real_escape_string($_POST['new_email']);
    $phone = $conn->real_escape_string($_POST['new_phone']);
    $address = $conn->real_escape_string($_POST['new_address']);
    $conn->query("INSERT INTO Customer (Name, Email, Phone, Address) 
                  VALUES ('$name', '$email', '$phone', '$address')");
    $customerID = $conn->insert_id;
  } else {
    $customerID = intval($_POST['existing_customer']);
  }

  $subtotal = floatval($_POST['subtotal']);
  $discount = floatval($_POST['discount']);
  $vat = floatval($_POST['tax']);
  $total = floatval($_POST['total']);
  $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
  $amountPaid = floatval($_POST['amount_paid']);
  $balance = $amountPaid - $total;
  $status = $balance >= 0 ? 'Paid' : 'Pending';
  $notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;

  // Insert into Order table
  $conn->query("INSERT INTO `Order` 
    (InvoiceID, CustomerID, OrderDate, SubTotal, Discount, VAT, TotalAmount, 
     PaymentMethod, AmountPaid, Balance, Status, Notes)
    VALUES 
    ('$invoiceID', $customerID, NOW(), $subtotal, $discount, $vat, $total,
     '$paymentMethod', $amountPaid, $balance, '$status', " . ($notes ? "'$notes'" : "NULL") . ")");

  $orderID = $conn->insert_id;

  // Insert order items
  foreach ($_POST['items'] as $item) {
    if (!isset($item['item_id']) || !isset($item['quantity']) || $item['quantity'] <= 0) continue;
    $itemID = intval($item['item_id']);
    $qty = intval($item['quantity']);
    $price = floatval($item['price']);
    $conn->query("INSERT INTO OrderDetails 
      (OrderID, ItemID, Quantity, Subtotal) 
      VALUES ($orderID, $itemID, $qty, " . ($price * $qty) . ")");
  }

  header("Location: view_billing.php?id=$orderID");
  exit();
}

include 'header.php';
include 'sidebar.php';
?>


<link rel="stylesheet" href="../assets/css/billing.css">

<main class="main-content">
  <h2 class="page-title">Create New Invoice</h2>
  <form method="POST" class="billing-form" oninput="calculateTotals()" onsubmit="return validateForm()">
    <input type="hidden" name="invoice_id" value="<?= $invoiceID ?>">

    <!-- Customer Section -->
    <section class="form-section">
      <h3>Customer Details</h3>
      <div class="tabs">
        <label><input type="radio" name="customer_type" value="existing" checked onchange="switchCustomerType(this.value)"> Existing Customer</label>
        <label><input type="radio" name="customer_type" value="new" onchange="switchCustomerType(this.value)"> New Customer</label>
      </div>
      <div id="existing-customer-group" class="form-row">
        <label>Select Customer</label>
        <select name="existing_customer" required>
          <option value="">-- Select Customer --</option>
          <?php while($row = $customers->fetch_assoc()) {
              echo '<option value="'.$row['CustomerID'].'">'.htmlspecialchars($row['Name']).'</option>';
          } ?>
        </select>
      </div>
      <div id="new-customer-fields" class="form-grid" style="display:none;">
        <div class="form-row"><label>Full Name</label><input type="text" name="new_name" id="new_name"></div>
        <div class="form-row"><label>Email</label><input type="email" name="new_email" id="new_email"></div>
        <div class="form-row"><label>Phone</label><input type="tel" name="new_phone" id="new_phone"></div>
        <div class="form-row"><label>Address</label><textarea name="new_address" id="new_address"></textarea></div>
      </div>
    </section>

    <!-- Items -->
    <section class="form-section">
      <h3>Items</h3>
      <div id="items-container">
        <div class="item-row">
          <select name="items[0][item_id]" onchange="updatePrice(this)" required><?= $itemOptions ?></select>
          <input type="number" name="items[0][quantity]" min="1" value="1" required oninput="calculateTotals()">
          <input type="text" name="items[0][price]" readonly placeholder="Unit Price">
          <input type="text" name="items[0][line_total]" readonly placeholder="Line Total">
          <button type="button" onclick="removeItem(this)">X</button>
        </div>
      </div>
      <button type="button" class="add-button" onclick="addItem()">+ Add Item</button>
    </section>

    <!-- Totals -->
    <section class="form-section">
      <h3>Billing Summary</h3>
      <div class="form-grid">
        <div class="form-row"><label>Subtotal</label><input type="text" name="subtotal" id="subtotal" readonly></div>
        <div class="form-row"><label>Discount (%)</label><input type="number" name="discount" id="discount" value="0" min="0" max="100"></div>
        <div class="form-row"><label>VAT (%)</label><input type="number" name="tax" id="tax" value="0" min="0" max="100"></div>
        <div class="form-row"><label>Total</label><input type="text" name="total" id="total" readonly></div>
      </div>
    </section>

    <!-- Payment -->
    <section class="form-section">
      <h3>Payment</h3>
      <div class="form-grid">
        <div class="form-row"><label>Method</label>
          <select name="payment_method" required>
            <option value="Cash">Cash</option>
            <option value="Card">Card</option>
            <option value="Credit">Credit</option>
          </select>
        </div>
        <div class="form-row"><label>Amount Paid</label><input type="number" name="amount_paid" id="amount_paid" required min="0"></div>
        <div class="form-row"><label>Balance</label><input type="text" id="balance" readonly></div>
      </div>
    </section>

    <!-- Optional Notes -->
<section class="form-section">
  <h3>Additional Notes</h3>
  <div class="form-grid">
    <div class="form-row">
      <label>Notes</label>
      <textarea name="notes" placeholder="Enter any additional instructions or remarks..."></textarea>
    </div>
  </div>
</section>


    <div class="form-actions">
      <button type="submit" class="submit-btn">Save & Generate Invoice</button>
    </div>
  </form>
</main>

<script>
let itemIndex = 1;

function switchCustomerType(type) {
  const isNew = type === 'new';
  document.getElementById('existing-customer-group').style.display = isNew ? 'none' : 'block';
  document.getElementById('new-customer-fields').style.display = isNew ? 'grid' : 'none';

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
    <input type="number" name="items[${itemIndex}][quantity]" min="1" value="1" required oninput="calculateTotals()">
    <input type="text" name="items[${itemIndex}][price]" readonly placeholder="Unit Price">
    <input type="text" name="items[${itemIndex}][line_total]" readonly placeholder="Line Total">
    <button type="button" onclick="removeItem(this)">X</button>
  `;
  container.appendChild(row);
  updatePrice(row.querySelector('select'));
  itemIndex++;
}

function removeItem(btn) {
  btn.closest('.item-row').remove();
  calculateTotals();
}

function updatePrice(select) {
  const row = select.closest('.item-row');
  const price = parseFloat(select.selectedOptions[0]?.getAttribute('data-price')) || 0;
  const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
  row.querySelector('[name$="[price]"]').value = price.toFixed(2);
  row.querySelector('[name$="[line_total]"]').value = (price * qty).toFixed(2);
  calculateTotals();
}

function calculateTotals() {
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name$="[price]"]').value) || 0;
    const lineTotal = qty * price;
    row.querySelector('[name$="[line_total]"]').value = lineTotal.toFixed(2);
    subtotal += lineTotal;
  });

  const discount = parseFloat(document.getElementById('discount').value) || 0;
  const tax = parseFloat(document.getElementById('tax').value) || 0;
  const total = subtotal * (1 - discount / 100) * (1 + tax / 100);

  document.getElementById('subtotal').value = subtotal.toFixed(2);
  document.getElementById('total').value = total.toFixed(2);

  const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
  document.getElementById('balance').value = (paid - total).toFixed(2);
}

function validateForm() {
  const itemRows = document.querySelectorAll('.item-row');
  for (let row of itemRows) {
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    if (qty <= 0) {
      alert("Quantity must be at least 1");
      return false;
    }
  }
  return true;
}
</script>

<?php include 'footer.php'; ?>
