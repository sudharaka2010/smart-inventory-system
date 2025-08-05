<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/feedback.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');
include 'header.php';
include 'sidebar.php';

// Delete feedback with no rating
$conn->query("DELETE FROM Feedback WHERE Rating IS NULL OR Rating <= 0 OR Rating > 5");

// Fetch only valid feedback entries with rating
$query = "
    SELECT f.FeedbackID, f.Rating, f.DateSubmitted, c.Name AS CustomerName 
    FROM Feedback f
    JOIN Customer c ON f.CustomerID = c.CustomerID
    WHERE f.Rating BETWEEN 1 AND 5
    ORDER BY f.DateSubmitted DESC
";
$result = $conn->query($query);
?>

<div class="main-content">
    <div class="container">
        <h1 class="page-title">Customer Ratings</h1>

        <?php if ($result && $result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['CustomerName']) ?></td>
                        <td><?= intval($row['Rating']) ?> / 5</td>
                        <td><?= htmlspecialchars($row['DateSubmitted']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="no-data">No rating feedback available.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
