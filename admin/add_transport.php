<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// --- VEHICLE REGISTRATION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['form_type'] === 'vehicle') {
    $vehicleNumber = trim($_POST['vehicle_number']);
    $vehicleType = trim($_POST['vehicle_type']);
    $maxLoad = floatval($_POST['max_load']);
    $driverID = intval($_POST['driver_id']);

    $check = $conn->prepare("SELECT VehicleID FROM vehicle WHERE VehicleNumber = ?");
    $check->bind_param("s", $vehicleNumber);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<p style='color:red;'>Vehicle already exists.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO vehicle (VehicleNumber, VehicleType, MaxLoadKg, DriverID) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $vehicleNumber, $vehicleType, $maxLoad, $driverID);

        if ($stmt->execute()) {
            header("Location: add_transport.php?vehicle_success=1");
            exit();
        } else {
            echo "<p style='color:red;'>Error registering vehicle: {$stmt->error}</p>";
        }
        $stmt->close();
    }
    $check->close();
}

// --- TRANSPORT RECORD HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['form_type'] === 'transport') {
    $orderID = intval($_POST['order_id']);
    $vehicleID = intval($_POST['vehicle_id']);
    $employeeID = intval($_POST['employee_id']);
    $destination = trim($_POST['destination']);
    $status = trim($_POST['status']);
    $deliveryDate = $_POST['delivery_date'];
    $deliveryTime = $_POST['delivery_time'];
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("INSERT INTO transport (OrderID, STATUS, DeliveryDate, Destination, VehicleID, EmployeeID, DeliveryTime, Notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssiis", $orderID, $status, $deliveryDate, $destination, $vehicleID, $employeeID, $deliveryTime, $notes);

    if ($stmt->execute()) {
        header("Location: transport.php?success=1");
        exit();
    } else {
        echo "<p style='color:red;'>Error saving transport: {$stmt->error}</p>";
    }
    $stmt->close();
}

// --- FETCH DROPDOWN DATA ---
$orderResult = $conn->query("SELECT o.OrderID, c.Name FROM `order` o JOIN customer c ON o.CustomerID = c.CustomerID");
$employeeResult = $conn->query("SELECT EmployeeID, NAME FROM employee");
$driverResult = $conn->query("SELECT EmployeeID, NAME FROM employee WHERE Role = 'Driver'");
$vehicleResult = $conn->query("SELECT VehicleID, VehicleNumber FROM vehicle");

include 'header.php';
include 'sidebar.php';
?>

<link rel="stylesheet" href="../assets/css/add_transport.css">
<div class="transport-wrapper">
  <div class="transport-container">
    <h1 class="page-title">Transport Management</h1>
    <div class="transport-flex">

      <!-- Register Vehicle Form -->
      <div class="card form-card">
        <h2 class="form-title">Register Vehicle</h2>
        <form method="POST" action="add_transport.php" class="vehicle-form">
          <input type="hidden" name="form_type" value="vehicle">

          <div class="form-group">
            <label for="vehicle_number">Vehicle Number:</label>
            <input type="text" name="vehicle_number" id="vehicle_number" required>
          </div>

          <div class="form-group">
            <label for="vehicle_type">Vehicle Type:</label>
            <input type="text" name="vehicle_type" id="vehicle_type" required>
          </div>

          <div class="form-group">
            <label for="max_load">Max Load (Kg):</label>
            <input type="number" name="max_load" id="max_load" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="driver_id">Driver:</label>
            <select name="driver_id" id="driver_id" required>
              <option value="">-- Select Driver --</option>
              <?php while ($d = $driverResult->fetch_assoc()): ?>
                <option value="<?= $d['EmployeeID'] ?>"><?= htmlspecialchars($d['NAME']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Save Vehicle</button>
          </div>
        </form>
      </div>

      <!-- Add Transport Form -->
      <div class="card form-card">
        <h2 class="form-title">Add New Transport</h2>
        <form method="POST" action="add_transport.php" class="transport-form">
          <input type="hidden" name="form_type" value="transport">

          <div class="form-group">
            <label for="order_id">Invoice (Order) ID:</label>
            <select name="order_id" id="order_id" required>
              <option value="">-- Select Invoice --</option>
              <?php while ($o = $orderResult->fetch_assoc()): ?>
                <option value="<?= $o['OrderID'] ?>">INV-<?= str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($o['Name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="destination">Destination:</label>
            <input type="text" name="destination" id="destination" required>
          </div>

          <div class="form-group">
            <label for="vehicle_id">Vehicle:</label>
            <select name="vehicle_id" id="vehicle_id" required>
              <option value="">-- Select Vehicle --</option>
              <?php while ($v = $vehicleResult->fetch_assoc()): ?>
                <option value="<?= $v['VehicleID'] ?>"><?= htmlspecialchars($v['VehicleNumber']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="employee_id">Assign Employee:</label>
            <select name="employee_id" id="employee_id" required>
              <option value="">-- Select Employee --</option>
              <?php while ($e = $employeeResult->fetch_assoc()): ?>
                <option value="<?= $e['EmployeeID'] ?>"><?= htmlspecialchars($e['NAME']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="delivery_date">Delivery Date:</label>
            <input type="date" name="delivery_date" id="delivery_date" required min="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label for="delivery_time">Delivery Time:</label>
            <input type="time" name="delivery_time" id="delivery_time" required>
          </div>

          <div class="form-group">
            <label for="status">Status:</label>
            <select name="status" id="status" required>
              <option value="Scheduled">Scheduled</option>
              <option value="In Transit">In Transit</option>
              <option value="Delivered">Delivered</option>
            </select>
          </div>

          <div class="form-group">
            <label for="notes">Notes:</label>
            <textarea name="notes" id="notes" placeholder="Enter any special instructions"></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Save Transport</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
<?php include 'footer.php'; ?>
