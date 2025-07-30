<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/feedback.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch feedback records with customer names
$result = $conn->query("SELECT f.*, c.Name AS CustomerName 
                        FROM Feedback f 
                        JOIN Customer c ON f.CustomerID = c.CustomerID");

include 'header.php';
include 'sidebar.php';
?>

<div class="main-content">
    <div class="container">
        <h1 class="page-title">Customer Feedback</h1>

        <table class="feedback-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Comments</th>
                    <th>Rating</th>
                    <th>Date Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
                    <td><?php echo htmlspecialchars($row['Comments']); ?></td>
                    <td><?php echo intval($row['Rating']); ?> / 5</td>
                    <td><?php echo htmlspecialchars($row['DateSubmitted']); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
