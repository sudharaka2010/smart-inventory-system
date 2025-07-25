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

// Fetch suppliers with all columns
$result = $conn->query("SELECT * FROM supplier");
include 'header.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier List</title>
    
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
                    <th>Email</th>
                    <th>Company</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Payment Terms</th>
                    <th>GST No.</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['NAME']) ?></td>
                    <td><?= htmlspecialchars($row['Contact']) ?></td>
                    <td><?= htmlspecialchars($row['Address']) ?></td>
                    <td><?= htmlspecialchars($row['Email']) ?></td>
                    <td><?= htmlspecialchars($row['CompanyName']) ?></td>
                    <td><?= htmlspecialchars($row['Category']) ?></td>
                    <td><?= htmlspecialchars($row['Status']) ?></td>
                    <td><?= htmlspecialchars($row['CreatedAt']) ?></td>
                    <td><?= htmlspecialchars($row['UpdatedAt']) ?></td>
                    <td><?= htmlspecialchars($row['PaymentTerms']) ?></td>
                    <td><?= htmlspecialchars($row['GSTNumber']) ?></td>
                    <td><?= htmlspecialchars($row['Notes']) ?></td>
                    <td class="actions">
                        <a href="edit_supplier.php?id=<?= $row['SupplierID'] ?>" class="btn-edit">Edit</a>
                        <a href="?delete=<?= $row['SupplierID'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this supplier?');">Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php include 'footer.php'; ?>
