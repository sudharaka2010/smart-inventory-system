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
  <link rel="stylesheet" href="/assets/css/header.css">
</head>
<body>

<header class="header">
  <div class="header__container">
    <div class="header__logo" onclick="window.location='dashboard.php'">
      <img src="/assets/images/rb.png" alt="RB Stores Logo" class="header__logo-img">
      <span class="header__logo-text">RB Stores</span>
    </div>

    <nav class="header__nav" id="navbar">
      <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
      <a href="support.php">Support <i class="fas fa-headset"></i></a>
      <a href="/rbstorsg/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <button class="header__toggle" id="menuToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>
</header>

<script>
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('navbar');
  toggle.addEventListener('click', () => {
    nav.classList.toggle('header__nav--active');
  });
</script>

</body>
</html>
