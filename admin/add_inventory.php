
<link rel="stylesheet" href="../assets/css/add_inventory.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $qty = intval($_POST['quantity']);
    $price = floatval($_POST['price']);

    $conn->query("INSERT INTO inventoryitem (NAME, Description, Quantity, Price) 
                  VALUES ('$name', '$desc', $qty, $price)");
    header("Location: inventory.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Inventory | RB Stores</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



</head>
<body>
    <!-- Top Navbar -->
    <header class="top-header">
        <div class="logo" onclick="window.location='dashboard.php'">
            <img src="/rbstorsg/assets/images/rb.png" alt="RB Stores" class="logo-img">
            <h1>RB Stores</h1>
        </div>
        <nav class="top-nav">
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="support.php">Support <i class="fas fa-headset"></i></a>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <ul>
              <li>
    <div class="menu-item">
        <a href="dashboard.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</li>
            <li class="active">
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-boxes"></i> Stock <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu open">
                    <li><a href="add_inventory.php" class="active"><i class="fas fa-plus"></i> Add Inventory</a></li>
                    <li><a href="edit_inventory.php"><i class="fas fa-edit"></i> Edit Inventory</a></li>
                    <li><a href="inventory.php"><i class="fas fa-list"></i> View Inventory</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-truck"></i> Supplier <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_supplier.php"><i class="fas fa-plus"></i> Add Supplier</a></li>
                    <li><a href="edit_supplier.php"><i class="fas fa-edit"></i> Edit Supplier</a></li>
                    <li><a href="supplier.php"><i class="fas fa-list"></i> View Suppliers</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-user-tie"></i> Employee <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_employee.php"><i class="fas fa-plus"></i> Add Employee</a></li>
                    <li><a href="edit_employee.php"><i class="fas fa-edit"></i> Edit Employee</a></li>
                    <li><a href="employee.php"><i class="fas fa-list"></i> View Employees</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-shipping-fast"></i> Transport <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_transport.php"><i class="fas fa-plus"></i> Add Transport</a></li>
                    <li><a href="edit_transport.php"><i class="fas fa-edit"></i> Edit Transport</a></li>
                    <li><a href="transport.php"><i class="fas fa-list"></i> View Transport</a></li>
                </ul>
            </li>
            <li>
                <div class="menu-item" onclick="toggleDropdown(this)">
                    <i class="fas fa-users"></i> Customer <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="add_customer.php"><i class="fas fa-plus"></i> Add Customer</a></li>
                    <li><a href="edit_customer.php"><i class="fas fa-edit"></i> Edit Customer</a></li>
                    <li><a href="customer.php"><i class="fas fa-list"></i> View Customers</a></li>
                </ul>
            </li>
            <li>
    <div class="menu-item">
        <a href="feedback.php">
            <i class="fas fa-comments"></i> Feedback
        </a>
    </div>
</li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <h2>Add New Inventory Item</h2>
        <div class="card">
            <form method="POST" class="add-inventory-form">
                <div class="form-group">
                    <label for="name">Item Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter item name" required />
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter description"></textarea>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" placeholder="Enter quantity" required />
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="text" id="price" name="price" placeholder="Enter price" required />
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-plus-circle"></i> Add Item</button>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 Code Counters - Group 15 | RB Stores</p>
    </footer>

    <!-- Script -->
    <script>
        function toggleDropdown(element) {
            element.parentElement.classList.toggle('open');
        }
    </script>
</body>
</html>

