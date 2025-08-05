<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle Cancel Transport (Securely)
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancelID = intval($_GET['cancel']);
    $stmt = $conn->prepare("UPDATE transport SET STATUS = 'Cancelled' WHERE TransportID = ?");
    $stmt->bind_param("i", $cancelID);
    $stmt->execute();
    header("Location: transport.php?cancelled=1");
    exit();
}

// Fetch all transport records with JOINs
$query = "
    SELECT t.TransportID, o.OrderID, c.Name AS CustomerName,
           t.STATUS, t.DeliveryDate, t.DeliveryTime, t.Destination,
           v.VehicleNumber, e.Name AS AssignedEmployee
    FROM transport t
    LEFT JOIN `order` o ON t.OrderID = o.OrderID
    LEFT JOIN customer c ON o.CustomerID = c.CustomerID
    LEFT JOIN vehicle v ON t.VehicleID = v.VehicleID
    LEFT JOIN employee e ON t.EmployeeID = e.EmployeeID
    ORDER BY t.DeliveryDate DESC, t.DeliveryTime DESC
";
$result = $conn->query($query);
?>

<!-- Styles -->
<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/transport.css">

<main class="main-content">
  <div class="page-header">
    <h1>Transport Schedule</h1>
    <a href="add_transport.php" class="btn-primary">+ Add New Transport</a>
  </div>

  <?php if (isset($_GET['cancelled'])): ?>
    <div class="alert-success">
      ✅ Transport has been successfully cancelled.
    </div>
  <?php endif; ?>

  <div class="card table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Destination</th>
          <th>Vehicle</th>
          <th>Assigned Employee</th>
          <th>Delivery Date</th>
          <th>Time</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php $statusClass = strtolower(str_replace(' ', '-', $row['STATUS'])); ?>
          <tr class="status-<?php echo $statusClass; ?>">
            <td>#INV-<?php echo str_pad($row['OrderID'], 5, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo htmlspecialchars($row['CustomerName'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['Destination'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['VehicleNumber'] ?? 'Not Assigned'); ?></td>
            <td><?php echo htmlspecialchars($row['AssignedEmployee'] ?? 'Not Assigned'); ?></td>
            <td><?php echo htmlspecialchars($row['DeliveryDate'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['DeliveryTime'] ?? '-'); ?></td>
            <td>
              <span class="status-label <?php echo $statusClass; ?>">
                <?php echo htmlspecialchars($row['STATUS']); ?>
              </span>
            </td>
            <td>
              <a href="edit_transport.php?id=<?php echo $row['TransportID']; ?>" class="btn-secondary">Edit</a>
              <?php if ($row['STATUS'] !== 'Cancelled'): ?>
                <a href="?cancel=<?php echo $row['TransportID']; ?>" onclick="return confirm('Cancel this transport?');" class="btn-danger">Cancel</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include 'footer.php'; ?>
