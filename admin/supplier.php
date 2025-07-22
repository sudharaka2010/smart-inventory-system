
<link rel="stylesheet" href="/rbstorsg/assets/css/supplier.css">

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
include 'header.php';
include 'sidebar.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier List</title>
    <link rel="stylesheet" href="supplier.css">
</head>
<body>
    <div class="container">
        <h1>Supplier List</h1>
        <a href="add_supplier.php" class="btn-add">+ Add New Supplier</a>
        <table class="supplier-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['NAME']); ?></td>
                    <td><?php echo htmlspecialchars($row['Contact']); ?></td>
                    <td><?php echo htmlspecialchars($row['Address']); ?></td>
                    <td class="actions">
                        <a href="edit_supplier.php?id=<?php echo $row['SupplierID']; ?>" class="btn-edit">Edit</a>
                        <a href="?delete=<?php echo $row['SupplierID']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this supplier?');">Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>



<?php 
include 'footer.php';