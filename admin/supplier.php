<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/supplier.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM supplier WHERE SupplierID=$id");
    header("Location: supplier.php");
    exit();
}

// Fetch suppliers
$result = $conn->query("SELECT * FROM supplier");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Management</title>
</head>
<body>
    <h1>Supplier List</h1>
    <a href="add_supplier.php">Add New Supplier</a>
    <table border="1" cellpadding="10">
        <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['NAME']); ?></td>
            <td><?php echo htmlspecialchars($row['Contact']); ?></td>
            <td><?php echo htmlspecialchars($row['Address']); ?></td>
            <td>
                <a href="edit_supplier.php?id=<?php echo $row['SupplierID']; ?>">Edit</a> |
                <a href="?delete=<?php echo $row['SupplierID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
