<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

$success = "";

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $returnID = intval($_GET['delete']);
    $conn->query("DELETE FROM returnitem WHERE ReturnID = $returnID");
    $success = "Return record deleted successfully.";
}

// Fetch all return records with item and supplier info
$query = "
    SELECT r.ReturnID, r.InvoiceID, r.ReturnQuantity, r.ReturnReason, 
           r.ReturnDate, r.SupplierName, i.NAME AS ItemName 
    FROM returnitem r
    JOIN inventoryitem i ON r.ItemID = i.ItemID
    ORDER BY r.ReturnDate DESC
";
$result = $conn->query($query);
?>

<link rel="stylesheet" href="../assets/css/view_return.css">

<main class="return-list-wrapper">
    <div class="page-header">
        <h1>Returned Inventory Records</h1>
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table class="return-table">
            <thead>
                <tr>
                    <th>Return ID</th>
                    <th>Item Name</th>
                    <th>Invoice ID</th>
                    <th>Quantity</th>
                    <th>Reason</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['ReturnID'] ?></td>
                            <td><?= htmlspecialchars($row['ItemName']) ?></td>
                            <td><?= htmlspecialchars($row['InvoiceID']) ?></td>
                            <td><?= $row['ReturnQuantity'] ?></td>
                            <td><?= htmlspecialchars($row['ReturnReason']) ?></td>
                            <td><?= htmlspecialchars($row['SupplierName']) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['ReturnDate'])) ?></td>
                            <td>
                                <a href="?delete=<?= $row['ReturnID'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this return record?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No return records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
