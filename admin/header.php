<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RB Stores Dashboard</title>
    
    <!-- Font and Icon Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">

    <!-- Header CSS -->
    <link rel="stylesheet" href="/rbstorsg/assets/css/header.css">
</head>
<body>

<!-- Top Navbar -->
<header class="top-header">
    <div class="logo" onclick="window.location='dashboard.php'">
        <img src="/rbstorsg/assets/images/rb.png" alt="RB Stores" class="logo-img">
        <h1>RB Stores</h1>
    </div>
    <nav class="top-nav">
        <a href="dashboard.php" class="dashboard-btn">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="support.php">Support <i class="fas fa-headset"></i></a>
        <a href="/rbstorsg/auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</header>
