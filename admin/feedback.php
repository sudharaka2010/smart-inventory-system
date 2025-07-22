<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="../assets/css/feedback.css">

<?php
include('../includes/auth.php');
include('../includes/db_connect.php');

// Fetch feedback records
$result = $conn->query("SELECT f.*, c.NAME as CustomerName 
                        FROM feedback f 
                        JOIN customer c ON f.CustomerID=c.CustomerID");
                        
                        
include 'header.php';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback</title>
</head>
<body>
    <h1>Customer Feedback</h1>
    <table border="1" cellpadding="10">
        <tr>
            <th>Customer</th>
            <th>Comments</th>
            <th>Rating</th>
            <th>Date Submitted</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['CustomerName']); ?></td>
            <td><?php echo htmlspecialchars($row['Comments']); ?></td>
            <td><?php echo $row['Rating']; ?> / 5</td>
            <td><?php echo $row['DateSubmitted']; ?></td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
