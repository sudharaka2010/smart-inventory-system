<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/inventory.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM inventoryitem WHERE ItemID=$id");
    header("Location: inventory.php");
    exit();
}

// Fetch all inventory items
$result = $conn->query("SELECT * FROM inventoryitem");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
</head>
<body>
    <h1>Inventory List</h1>
    <a href="add_inventory.php">Add New Item</a>
    <table border="1" cellpadding="10">
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['NAME']); ?></td>
            <td><?php echo htmlspecialchars($row['Description']); ?></td>
            <td><?php echo $row['Quantity']; ?></td>
            <td><?php echo $row['Price']; ?></td>
            <td>
                <a href="edit_inventory.php?id=<?php echo $row['ItemID']; ?>">Edit</a> | 
                <a href="?delete=<?php echo $row['ItemID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
