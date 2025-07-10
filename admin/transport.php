<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/transport.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM transport WHERE TransportID=$id");
    header("Location: transport.php");
    exit();
}

// Fetch transport records
$result = $conn->query("SELECT t.*, o.OrderID, c.NAME as CustomerName 
                        FROM transport t 
                        JOIN `order` o ON t.OrderID=o.OrderID
                        JOIN customer c ON o.CustomerID=c.CustomerID");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transport Management</title>
</head>
<body>
    <h1>Transport List</h1>
    <a href="add_transport.php">Add New Transport</a>
    <table border="1" cellpadding="10">
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Delivery Date</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['OrderID']; ?></td>
            <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
            <td><?php echo htmlspecialchars($row['STATUS']); ?></td>
            <td><?php echo $row['DeliveryDate']; ?></td>
            <td>
                <a href="edit_transport.php?id=<?php echo $row['TransportID']; ?>">Edit</a> |
                <a href="?delete=<?php echo $row['TransportID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
