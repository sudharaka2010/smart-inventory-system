<!-- CSS and JS Includes -->
<link rel="stylesheet" href="../assets/css/inventory.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    $ids = $_POST['delete_ids'];
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $stmt = $conn->prepare("DELETE FROM InventoryItem WHERE ItemID = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        header("Location: inventory.php?deleted=1");
        exit();
    }
}

// Fetch all inventory items
$result = $conn->query("SELECT * FROM InventoryItem");

include 'header.php';
include 'sidebar.php';
?>

<div class="main-content">
    <div class="card">
        <h1>Inventory Management</h1>
        <a href="add_inventory.php" class="btn btn-add">+ Add New Item</a>

        <!-- Success Message -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="message success">Selected item(s) deleted successfully!</div>
        <?php endif; ?>

        <!-- Filters -->
        <form class="filters" onsubmit="return false;">
            <input type="text" id="searchItemID" placeholder="Search by Item ID" aria-label="Search by Item ID">
            <input type="text" id="searchSupplierID" placeholder="Search by Supplier ID" aria-label="Search by Supplier ID">
            <input type="text" id="searchName" placeholder="Search by Name" aria-label="Search by Name">
            <input type="date" id="startDate" aria-label="Start Date">
            <input type="date" id="endDate" aria-label="End Date">
            <button id="filterBtn">Filter</button>
        </form>

        <!-- Inventory Table and Bulk Delete -->
        <form method="POST" action="inventory.php" onsubmit="return confirm('Are you sure you want to delete the selected item(s)?');">
            <table id="inventoryTable" class="inventory-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th scope="col">ItemID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Price (LKR)</th>
                        <th scope="col">SupplierID</th>
                        <th scope="col">Category</th>
                        <th scope="col">Receive Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" name="delete_ids[]" value="<?php echo $row['ItemID']; ?>"></td>
                        <td data-label="ItemID"><?php echo $row['ItemID']; ?></td>
                        <td data-label="Name"><?php echo htmlspecialchars($row['NAME']); ?></td>
                        <td data-label="Description"><?php echo htmlspecialchars($row['Description']); ?></td>
                        <td data-label="Quantity"><?php echo $row['Quantity']; ?></td>
                        <td data-label="Price"><?php echo number_format($row['Price'], 2); ?></td>
                        <td data-label="SupplierID"><?php echo htmlspecialchars($row['SupplierID']); ?></td>
                        <td data-label="Category"><?php echo htmlspecialchars($row['Category']); ?></td>
                        <td data-label="Receive Date"><?php echo htmlspecialchars($row['ReceiveDate']); ?></td>
                        <td data-label="Actions">
                            <a class="btn btn-edit" href="edit_inventory.php?id=<?php echo $row['ItemID']; ?>">Edit</a>
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

    // Search filters
    $('#searchItemID').on('keyup', function() {
        table.column(1).search(this.value).draw();
    });
    $('#searchName').on('keyup', function() {
        table.column(2).search(this.value).draw();
    });
    $('#searchSupplierID').on('keyup', function() {
        table.column(6).search(this.value).draw();
    });

    // Date range filter
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

    // Select/Deselect All
    $('#selectAll').click(function() {
        $('input[name="delete_ids[]"]').prop('checked', this.checked);
    });
});
</script>

<?php include 'footer.php'; ?>
