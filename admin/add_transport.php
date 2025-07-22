<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/transport.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Fetch orders for dropdown
$orders = $conn->query("SELECT OrderID FROM `order`");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = intval($_POST['order_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $delivery_date = $_POST['delivery_date'];

    $conn->query("INSERT INTO transport (OrderID, STATUS, DeliveryDate) 
                  VALUES ($order_id, '$status', '$delivery_date')");
    header("Location: transport.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Transport</title>
</head>
<body>
    <h1>Add New Transport</h1>
    <form method="POST">
        Order ID: 
        <select name="order_id" required>
            <?php while ($order = $orders->fetch_assoc()) { ?>
            <option value="<?php echo $order['OrderID']; ?>"><?php echo $order['OrderID']; ?></option>
            <?php } ?>
        </select><br><br>
        Status: 
        <select name="status" required>
            <option value="Upcoming">Upcoming</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Delayed">Delayed</option>
            <option value="Completed">Completed</option>
        </select><br><br>
        Delivery Date: <input type="date" name="delivery_date" required><br><br>
        <button type="submit">Add Transport</button>
    </form>
</body>
</html>
