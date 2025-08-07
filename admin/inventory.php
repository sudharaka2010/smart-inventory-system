<?php
// ✅ Enable PHP error reporting (only for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Auth and DB connection
include('../includes/auth.php');
include('../includes/db_connect.php');

// ✅ Bulk delete handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    $ids = $_POST['delete_ids'];
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $stmt = $conn->prepare("DELETE FROM inventoryitem WHERE ItemID = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        header("Location: inventory.php?deleted=1");
        exit();
    }
}

// ✅ Fetch inventory items (correct table name)
$result = $conn->query("SELECT * FROM inventoryitem");
if (!$result) {
    die("Database query error: " . $conn->error);
}

include 'header.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management - RB Stores</title>
    
    <!-- ✅ CSS -->
    <link rel="stylesheet" href="/assets/css/inventory.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- ✅ JS Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>

<div class="main-content">
    <div class="card">
        <h1>Inventory Management</h1>
        <a href="add_inventory.php" class="btn btn-add">+ Add New Item</a>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="message success">Selected item(s) deleted successfully!</div>
        <?php endif; ?>

        <!-- 🔍 Filters -->
        <form class="filters" onsubmit="return false;">
            <input type="text" id="searchItemID" placeholder="Search by Item ID" aria-label="Search by Item ID">
            <input type="text" id="searchSupplierID" placeholder="Search by Supplier ID" aria-label="Search by Supplier ID">
            <input type="text" id="searchName" placeholder="Search by Name" aria-label="Search by Name">
            <input type="date" id="startDate" aria-label="Start Date">
            <input type="date" id="endDate" aria-label="End Date">
            <button id="filterBtn">Filter</button>
        </form>

        <!-- 🗃 Inventory Table + Delete -->
        <form method="POST" action="inventory.php" onsubmit="return confirm('Are you sure you want to delete the selected item(s)?');">
            <table id="inventoryTable" class="inventory-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
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
                        <td><input type="checkbox" name="delete_ids[]" value="<?= $row['ItemID']; ?>"></td>
                        <td><?= $row['ItemID']; ?></td>
                        <td><?= htmlspecialchars($row['NAME']); ?></td>
                        <td><?= htmlspecialchars($row['Description']); ?></td>
                        <td><?= $row['Quantity']; ?></td>
                        <td><?= number_format($row['Price'], 2); ?></td>
                        <td><?= htmlspecialchars($row['SupplierID']); ?></td>
                        <td><?= htmlspecialchars($row['Category']); ?></td>
                        <td><?= htmlspecialchars($row['ReceiveDate']); ?></td>
                        <td>
                            <a class="btn btn-edit" href="edit_inventory.php?id=<?= $row['ItemID']; ?>">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-delete" style="margin-top: 10px;">Delete Selected</button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#inventoryTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'print'],
        columnDefs: [{ orderable: false, targets: 0 }]
    });

    // ✅ Filter columns
    $('#searchItemID').on('keyup', function() {
        table.column(1).search(this.value).draw();
    });
    $('#searchName').on('keyup', function() {
        table.column(2).search(this.value).draw();
    });
    $('#searchSupplierID').on('keyup', function() {
        table.column(6).search(this.value).draw();
    });

    // ✅ Filter by date range
    $('#filterBtn').click(function() {
        var start = new Date($('#startDate').val());
        var end = new Date($('#endDate').val());

        $.fn.dataTable.ext.search.push(function(settings, data) {
            var date = new Date(data[8]);
            return (!start || date >= start) && (!end || date <= end);
        });

        table.draw();
        $.fn.dataTable.ext.search.pop();
    });

    // ✅ Select/Deselect all
    $('#selectAll').click(function() {
        $('input[name="delete_ids[]"]').prop('checked', this.checked);
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>
