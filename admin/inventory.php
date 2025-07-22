<link rel="stylesheet" href="../assets/css/inventory.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle Delete securely
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM InventoryItem WHERE ItemID = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: inventory.php?deleted=1");
        exit();
    } else {
        header("Location: inventory.php?error=delete_failed");
        exit();
    }
}

// Fetch all inventory items
$result = $conn->query("SELECT * FROM InventoryItem");
?>

<div class="main-content">
    <div class="card">
        <h1>Inventory Management</h1>
        <a href="add_inventory.php" class="btn btn-add">+ Add New Item</a>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="message success">Item deleted successfully!</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="message error">Failed to delete the item.</div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <input type="text" id="searchItemID" placeholder="Search by Item ID">
            <input type="text" id="searchSupplierID" placeholder="Search by Supplier ID">
            <input type="text" id="searchName" placeholder="Search by Name">
            <input type="date" id="startDate" placeholder="Start Date">
            <input type="date" id="endDate" placeholder="End Date">
            <button id="filterBtn">Filter</button>
        </div>

        <!-- Inventory Table -->
        <table id="inventoryTable" class="inventory-table">
            <thead>
                <tr>
                    <th>ItemID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Price (LKR)</th>
                    <th>SupplierID</th>
                    <th>Category</th>
                    <th>Receive Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['ItemID']; ?></td>
                    <td><?php echo htmlspecialchars($row['NAME']); ?></td>
                    <td><?php echo htmlspecialchars($row['Description']); ?></td>
                    <td><?php echo $row['Quantity']; ?></td>
                    <td><?php echo number_format($row['Price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['SupplierID']); ?></td>
                    <td><?php echo htmlspecialchars($row['Category']); ?></td>
                    <td><?php echo htmlspecialchars($row['ReceiveDate']); ?></td>
                    <td>
                        <a class="btn btn-edit" href="edit_inventory.php?id=<?php echo $row['ItemID']; ?>">Edit</a>
                        <a class="btn btn-delete" href="?delete=<?php echo $row['ItemID']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#inventoryTable').DataTable();

    // Search filters
    $('#searchItemID').on('keyup', function() {
        table.column(0).search(this.value).draw();
    });
    $('#searchName').on('keyup', function() {
        table.column(1).search(this.value).draw();
    });
    $('#searchSupplierID').on('keyup', function() {
        table.column(5).search(this.value).draw();
    });

    // Date range filter
    $('#filterBtn').click(function() {
        var start = $('#startDate').val();
        var end = $('#endDate').val();
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            var date = data[7]; // Receive Date column index
            if ((start === "" || date >= start) && (end === "" || date <= end)) {
                return true;
            }
            return false;
        });
        table.draw();
        $.fn.dataTable.ext.search.pop();
    });
});
</script>

<?php include 'footer.php'; ?>
