<link rel="stylesheet" href="../assets/css/add_transport.css">
<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Check if a POST request was made
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Detect which form submitted
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'vehicle') {
        
        // 🚚 Vehicle Registration Form Submitted
        $vehicleNumber = $conn->real_escape_string($_POST['vehicle_number']);
        $vehicleType = $conn->real_escape_string($_POST['vehicle_type']);
        $maxLoad = floatval($_POST['max_load']);
        $driverName = $conn->real_escape_string($_POST['driver_name']);

        // Insert vehicle details into transport table
        $sql = "INSERT INTO transport (VehicleNumber, VehicleType, MaxLoadKg, DriverName)
                VALUES ('$vehicleNumber', '$vehicleType', $maxLoad, '$driverName')";

        if ($conn->query($sql)) {
            header("Location: add_transport.php?vehicle_success=1");
            exit();
        } else {
            echo "<p style='color:red;'>Error registering vehicle: " . $conn->error . "</p>";
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'transport') {
        // 🚛 Transport Record Form Submitted
        $orderID = intval($_POST['order_id']);
        $destination = $conn->real_escape_string($_POST['destination']);
        $vehicleNumber = $conn->real_escape_string($_POST['vehicle_number']);
        $vehicleType = $conn->real_escape_string($_POST['vehicle_type']);
        $maxLoad = floatval($_POST['max_load']);
        $driverName = $conn->real_escape_string($_POST['driver_name']);
        $employeeID = intval($_POST['employee_id']);
        $status = $conn->real_escape_string($_POST['status']);
        $deliveryDate = $conn->real_escape_string($_POST['delivery_date']);
        $deliveryTime = $conn->real_escape_string($_POST['delivery_time']);
        $notes = $conn->real_escape_string($_POST['notes']);

        // Check if vehicle already exists
        $vehicleCheck = $conn->prepare("SELECT COUNT(*) FROM transport WHERE VehicleNumber = ?");
        $vehicleCheck->bind_param("s", $vehicleNumber);
        $vehicleCheck->execute();
        $vehicleCheck->bind_result($vehicleExists);
        $vehicleCheck->fetch();
        $vehicleCheck->close();

        // Register vehicle if not already in table
        if ($vehicleExists == 0) {
            $registerVehicle = $conn->prepare("INSERT INTO transport (VehicleNumber, VehicleType, MaxLoadKg, DriverName)
                                               VALUES (?, ?, ?, ?)");
            $registerVehicle->bind_param("ssds", $vehicleNumber, $vehicleType, $maxLoad, $driverName);
            if (!$registerVehicle->execute()) {
                echo "<p style='color:red;'>Error registering vehicle: " . $registerVehicle->error . "</p>";
            }
            $registerVehicle->close();
        }

        // Insert transport record
        $sql = "INSERT INTO transport (OrderID, Destination, VehicleNumber, VehicleType, MaxLoadKg, DriverName, EmployeeID, STATUS, DeliveryDate, DeliveryTime, Notes)
                VALUES ($orderID, '$destination', '$vehicleNumber', '$vehicleType', $maxLoad, '$driverName', $employeeID, '$status', '$deliveryDate', '$deliveryTime', '$notes')";

        if ($conn->query($sql)) {
            header("Location: transport.php?success=1");
            exit();
        } else {
            echo "<p style='color:red;'>Error saving transport: " . $conn->error . "</p>";
        }
    }
}

// Fetch orders, employees, and drivers
$orderResult = $conn->query("SELECT o.OrderID, c.Name FROM `order` o JOIN customer c ON o.CustomerID = c.CustomerID");
$employeeResult = $conn->query("SELECT EmployeeID, NAME FROM employee");
$driverResult = $conn->query("SELECT EmployeeID, NAME FROM employee WHERE Role = 'Driver'");

include 'header.php';
include 'sidebar.php';
?>

<div class="container">
    <h1 class="page-title">Transport Management</h1>

    <div class="flex-container">
        <!-- Vehicle Registration Form -->
        <div class="card form-card">
            <h2 class="form-title">Register Vehicle</h2>
            <form method="POST" action="add_transport.php" class="vehicle-form">
                <input type="hidden" name="form_type" value="vehicle">
                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number:</label>
                    <input type="text" name="vehicle_number" id="vehicle_number" placeholder="Enter vehicle number" required>
                </div>

                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type:</label>
                    <input type="text" name="vehicle_type" id="vehicle_type" placeholder="E.g., Truck, Van" required>
                </div>

                <div class="form-group">
                    <label for="max_load">Max Load (Kg):</label>
                    <input type="number" name="max_load" id="max_load" step="0.01" placeholder="Enter max load in Kg" required>
                </div>

                <div class="form-group">
    <label for="driver_name">Driver Name:</label>
    <select name="driver_name" id="driver_name" required>
        <option value="">-- Select Driver --</option>
        <?php
        // Reload driver list for Vehicle Registration form
        $driverReload = $conn->query("SELECT EmployeeID, NAME FROM employee WHERE Role = 'Driver'");
        while ($d = $driverReload->fetch_assoc()) { ?>
            <option value="<?php echo htmlspecialchars($d['NAME']); ?>">
                <?php echo htmlspecialchars($d['NAME']); ?> (ID: <?php echo $d['EmployeeID']; ?>)
            </option>
        <?php } ?>
    </select>
</div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">Save Vehicle</button>
                </div>
            </form>
        </div>

        <!-- Transport Record Form -->
        <div class="card form-card">
            <h2 class="form-title">Add New Transport</h2>
            <form method="POST" action="add_transport.php" class="transport-form">
                <input type="hidden" name="form_type" value="transport">

                <!-- Order Section -->
                <div class="form-group">
                    <label for="order_id">Invoice (Order) ID:</label>
                    <select name="order_id" id="order_id" onchange="setCustomerName(this)" required>
                        <option value="">-- Select Invoice --</option>
                        <?php while ($o = $orderResult->fetch_assoc()) { ?>
                        <option value="<?php echo $o['OrderID']; ?>" data-customer="<?php echo htmlspecialchars($o['Name']); ?>">
                            Order #<?php echo $o['OrderID']; ?> - <?php echo htmlspecialchars($o['Name']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customer_name">Customer Name:</label>
                    <input type="text" id="customer_name" readonly>
                </div>

                <!-- Transport Details -->
                <div class="form-group">
                    <label for="destination">Destination:</label>
                    <input type="text" name="destination" id="destination" placeholder="Enter delivery destination" required>
                </div>

                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number:</label>
                    <input type="text" name="vehicle_number" id="vehicle_number" placeholder="Enter vehicle number" required>
                </div>

                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type:</label>
                    <input type="text" name="vehicle_type" id="vehicle_type" placeholder="E.g., Truck, Van" required>
                </div>

                <div class="form-group">
                    <label for="max_load">Max Load (Kg):</label>
                    <input type="number" name="max_load" id="max_load" step="0.01" placeholder="Enter max load in Kg" required>
                </div>

                <div class="form-group">
                    <label for="driver_name">Driver Name:</label>
                    <select name="driver_name" id="driver_name" required>
                        <option value="">-- Select Driver --</option>
                        <?php
                        mysqli_data_seek($driverResult, 0); // Reset result pointer
                        while ($d = $driverResult->fetch_assoc()) { ?>
                            <option value="<?php echo htmlspecialchars($d['NAME']); ?>">
                                <?php echo htmlspecialchars($d['NAME']); ?> (ID: <?php echo $d['EmployeeID']; ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="employee_id">Assign Employee:</label>
                    <select name="employee_id" id="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php while ($e = $employeeResult->fetch_assoc()) { ?>
                        <option value="<?php echo $e['EmployeeID']; ?>">
                            <?php echo htmlspecialchars($e['NAME']); ?> (ID: <?php echo $e['EmployeeID']; ?>)
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="delivery_date">Delivery Date:</label>
                    <input type="date" name="delivery_date" id="delivery_date" required>
                </div>

                <div class="form-group">
                    <label for="delivery_time">Delivery Time:</label>
                    <input type="time" name="delivery_time" id="delivery_time" required>
                </div>

                <div class="form-group">
                    <label for="status">Status:</label>
                    <input type="text" name="status" id="status" placeholder="e.g., Pending, Dispatched, Delivered" required>
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

<?php 
include 'footer.php';
?>
  