<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

$transportID = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch transport record
$transport = $conn->query("
    SELECT * FROM transport WHERE TransportID = $transportID
")->fetch_assoc();

// Fetch dropdown data
$orderResult = $conn->query("SELECT o.OrderID, c.Name FROM `order` o JOIN customer c ON o.CustomerID = c.CustomerID");
$employeeResult = $conn->query("SELECT EmployeeID, NAME FROM employee");
$vehicleResult = $conn->query("SELECT VehicleID, VehicleNumber FROM vehicle");

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderID = intval($_POST['order_id']);
    $vehicleID = intval($_POST['vehicle_id']);
    $employeeID = intval($_POST['employee_id']);
    $destination = trim($_POST['destination']);
    $status = trim($_POST['status']);
    $deliveryDate = $_POST['delivery_date'];
    $deliveryTime = $_POST['delivery_time'];
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("
        UPDATE transport 
        SET OrderID=?, STATUS=?, DeliveryDate=?, Destination=?, 
            VehicleID=?, EmployeeID=?, DeliveryTime=?, Notes=?
        WHERE TransportID=?
    ");
    $stmt->bind_param("issssiisi", $orderID, $status, $deliveryDate, $destination, $vehicleID, $employeeID, $deliveryTime, $notes, $transportID);

    if ($stmt->execute()) {
        header("Location: transport.php?updated=1");
        exit();
    } else {
        echo "<p style='color:red;'>Error updating transport: {$stmt->error}</p>";
    }
    $stmt->close();
}

include 'header.php';
include 'sidebar.php';
?>

<link rel="stylesheet" href="../assets/css/edit_transport.css">
<div class="transport-wrapper">
  <div class="transport-container">
    <h1 class="page-title">Edit Transport</h1>
    <div class="transport-flex">

      <div class="card form-card">
        <h2 class="form-title">Update Transport Details</h2>
        <form method="POST" action="edit_transport.php?id=<?= $transportID ?>" class="transport-form">

          <div class="form-group">
            <label for="order_id">Invoice (Order) ID:</label>
            <select name="order_id" id="order_id" required>
              <option value="">-- Select Invoice --</option>
              <?php while ($o = $orderResult->fetch_assoc()): ?>
                <option value="<?= $o['OrderID'] ?>"
                  <?= $transport['OrderID'] == $o['OrderID'] ? 'selected' : '' ?>>
                  INV-<?= str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($o['Name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="destination">Destination:</label>
            <input type="text" name="destination" id="destination" required value="<?= htmlspecialchars($transport['Destination']) ?>">
          </div>

          <div class="form-group">
            <label for="vehicle_id">Vehicle:</label>
            <select name="vehicle_id" id="vehicle_id" required>
              <option value="">-- Select Vehicle --</option>
              <?php $vehicleResult->data_seek(0); while ($v = $vehicleResult->fetch_assoc()): ?>
                <option value="<?= $v['VehicleID'] ?>"
                  <?= $transport['VehicleID'] == $v['VehicleID'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v['VehicleNumber']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="employee_id">Assign Employee:</label>
            <select name="employee_id" id="employee_id" required>
              <option value="">-- Select Employee --</option>
              <?php while ($e = $employeeResult->fetch_assoc()): ?>
                <option value="<?= $e['EmployeeID'] ?>"
                  <?= $transport['EmployeeID'] == $e['EmployeeID'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($e['NAME']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="delivery_date">Delivery Date:</label>
            <input type="date" name="delivery_date" id="delivery_date"
              value="<?= $transport['DeliveryDate'] ?>" required min="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label for="delivery_time">Delivery Time:</label>
            <input type="time" name="delivery_time" id="delivery_time"
              value="<?= $transport['DeliveryTime'] ?>" required>
          </div>

          <div class="form-group">
            <label for="status">Status:</label>
            <select name="status" id="status" required>
              <option value="Scheduled" <?= $transport['STATUS'] == 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
              <option value="In Transit" <?= $transport['STATUS'] == 'In Transit' ? 'selected' : '' ?>>In Transit</option>
              <option value="Delivered" <?= $transport['STATUS'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
              <option value="Cancelled" <?= $transport['STATUS'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
          </div>

          <div class="form-group">
            <label for="notes">Notes:</label>
            <textarea name="notes" id="notes"><?= htmlspecialchars($transport['Notes']) ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Update Transport</button>
            <a href="transport.php" class="btn-secondary">Cancel</a>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
