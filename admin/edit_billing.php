<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$orderID = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$orderID) {
  die("Invalid Order ID.");
}

// Fetch existing order
$order = $conn->query("SELECT * FROM `Order` WHERE OrderID = $orderID")->fetch_assoc();
if (!$order) {
  die("Order not found.");
}

$invoiceID = $order['InvoiceID'];

// Fetch customers and items
$customers = $conn->query("SELECT CustomerID, Name FROM Customer");
$items = $conn->query("SELECT ItemID, Name, Price FROM InventoryItem");
$itemOptions = "";
while ($item = $items->fetch_assoc()) {
  $itemOptions .= '<option value="'.$item['ItemID'].'" data-price="'.$item['Price'].'">'.
                  htmlspecialchars($item['Name']).' (LKR '.$item['Price'].')</option>';
}

// Fetch existing order items
$orderItems = $conn->query("SELECT od.*, i.Price FROM OrderDetails od JOIN InventoryItem i ON od.ItemID = i.ItemID WHERE OrderID = $orderID");
$existingItems = [];
while ($row = $orderItems->fetch_assoc()) {
  $existingItems[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $customerID = intval($_POST['existing_customer']);
  $subtotal = floatval($_POST['subtotal']);
  $discount = floatval($_POST['discount']);
  $vat = floatval($_POST['tax']);
  $total = floatval($_POST['total']);
  $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
  $amountPaid = floatval($_POST['amount_paid']);
  $balance = $amountPaid - $total;
  $status = $balance >= 0 ? 'Paid' : 'Pending';
  $notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;

  $conn->query("UPDATE `Order` SET 
    CustomerID = $customerID,
    SubTotal = $subtotal,
    Discount = $discount,
    VAT = $vat,
    TotalAmount = $total,
    PaymentMethod = '$paymentMethod',
    AmountPaid = $amountPaid,
    Balance = $balance,
    Status = '$status',
    Notes = " . ($notes ? "'$notes'" : "NULL") . "
    WHERE OrderID = $orderID");

  $conn->query("DELETE FROM OrderDetails WHERE OrderID = $orderID");

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
  <h2 class="page-title">Edit Invoice (<?= htmlspecialchars($invoiceID) ?>)</h2>
  <form method="POST" class="billing-form" oninput="calculateTotals()" onsubmit="return validateForm()">
    <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invoiceID) ?>">

    <section class="form-section">
      <h3>Customer Details</h3>
      <div class="form-row">
        <label>Select Customer</label>
        <select name="existing_customer" required>
          <option value="">-- Select Customer --</option>
          <?php $customers->data_seek(0); while($row = $customers->fetch_assoc()) {
            $selected = $row['CustomerID'] == $order['CustomerID'] ? 'selected' : '';
            echo '<option value="'.$row['CustomerID'].'" '.$selected.'>'.htmlspecialchars($row['Name']).'</option>';
          } ?>
        </select>
      </div>
    </section>

    <section class="form-section">
      <h3>Items</h3>
      <div id="items-container">
        <?php foreach ($existingItems as $index => $item) { ?>
          <div class="item-row">
            <select name="items[<?= $index ?>][item_id]" onchange="updatePrice(this)" required>
              <?= str_replace('value="'.$item['ItemID'].'"', 'value="'.$item['ItemID'].'" selected', $itemOptions) ?>
            </select>
            <input type="number" name="items[<?= $index ?>][quantity]" min="1" value="<?= $item['Quantity'] ?>" required oninput="calculateTotals()">
            <input type="text" name="items[<?= $index ?>][price]" value="<?= number_format($item['Price'], 2) ?>" readonly placeholder="Unit Price">
            <input type="text" name="items[<?= $index ?>][line_total]" value="<?= number_format($item['Price'] * $item['Quantity'], 2) ?>" readonly placeholder="Line Total">
            <button type="button" onclick="removeItem(this)">X</button>
          </div>
        <?php } ?>
      </div>
      <button type="button" class="add-button" onclick="addItem()">+ Add Item</button>
    </section>

    <section class="form-section">
      <h3>Billing Summary</h3>
      <div class="form-grid">
        <div class="form-row"><label>Subtotal</label><input type="text" name="subtotal" id="subtotal" value="<?= $order['SubTotal'] ?>" readonly></div>
        <div class="form-row"><label>Discount (%)</label><input type="number" name="discount" id="discount" value="<?= $order['Discount'] ?>" min="0" max="100"></div>
        <div class="form-row"><label>VAT (%)</label><input type="number" name="tax" id="tax" value="<?= $order['VAT'] ?>" min="0" max="100"></div>
        <div class="form-row"><label>Total</label><input type="text" name="total" id="total" value="<?= $order['TotalAmount'] ?>" readonly></div>
      </div>
    </section>

    <section class="form-section">
      <h3>Payment</h3>
      <div class="form-grid">
        <div class="form-row"><label>Method</label>
          <select name="payment_method" required>
            <option value="Cash" <?= $order['PaymentMethod'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
            <option value="Card" <?= $order['PaymentMethod'] === 'Card' ? 'selected' : '' ?>>Card</option>
            <option value="Credit" <?= $order['PaymentMethod'] === 'Credit' ? 'selected' : '' ?>>Credit</option>
          </select>
        </div>
        <div class="form-row"><label>Amount Paid</label><input type="number" name="amount_paid" id="amount_paid" required min="0" value="<?= $order['AmountPaid'] ?>"></div>
        <div class="form-row"><label>Balance</label><input type="text" id="balance" value="<?= $order['Balance'] ?>" readonly></div>
      </div>
    </section>

    <section class="form-section">
      <h3>Additional Notes</h3>
      <div class="form-grid">
        <div class="form-row">
          <label>Notes</label>
          <textarea name="notes"><?= htmlspecialchars($order['Notes']) ?></textarea>
        </div>
      </div>
    </section>

    <div class="form-actions">
      <button type="submit" class="submit-btn">Update Invoice</button>
    </div>
  </form>
</main>

<script>
let itemIndex = <?= count($existingItems) ?>;
<?= file_get_contents('billing.js') ?>

window.addEventListener('DOMContentLoaded', () => {
  calculateTotals();
});
</script>

<?php include 'footer.php'; ?>
