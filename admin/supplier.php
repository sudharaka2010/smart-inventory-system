<link rel="stylesheet" href="/rbstorsg/assets/css/supplier.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM supplier WHERE SupplierID = $id");
    header("Location: supplier.php");
    exit();
}

// Fetch suppliers
$result = $conn->query("SELECT * FROM supplier");
?>

<main class="main-content">
    <div class="page-header">
        <h1>Supplier List</h1>
        <div class="actions-bar">
            <a href="add_supplier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Supplier</a>
            <button class="btn btn-secondary" onclick="printTable()"><i class="fas fa-print"></i> Print</button>
            <button class="btn btn-success" onclick="exportToExcel('supplierTable')"><i class="fas fa-file-excel"></i> Export to Excel</button>
        </div>
    </div>

    <div class="table-container">
        <table id="supplierTable" class="supplier-table">
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
                        <a href="edit_supplier.php?id=<?= $row['SupplierID'] ?>" class="btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?= $row['SupplierID'] ?>" class="btn-action btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this supplier?');"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>

<!-- Export & Print Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function exportToExcel(tableID, filename = 'Suppliers_Export') {
    const table = document.getElementById(tableID);
    const workbook = XLSX.utils.table_to_book(table, {sheet: "Suppliers"});
    XLSX.writeFile(workbook, filename + ".xlsx");
}

function printTable() {
    const printContents = document.querySelector('.table-container').innerHTML;
    const win = window.open('', '', 'height=700,width=900');
    win.document.write('<html><head><title>Print Suppliers</title>');
    win.document.write('<style>table { width: 100%; border-collapse: collapse; } table, th, td { border: 1px solid #999; padding: 8px; } th { background-color: #eee; }</style>');
    win.document.write('</head><body>');
    win.document.write('<h2>Supplier List</h2>');
    win.document.write(printContents);
    win.document.write('</body></html>');
    win.document.close();
    win.print();
}
</script>
