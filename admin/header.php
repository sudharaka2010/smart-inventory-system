<?php
// header.php — One-file header with inline CSS + JS (responsive, business style)
if (!headers_sent()) {
  header("X-Frame-Options: SAMEORIGIN");
  header("X-Content-Type-Options: nosniff");
  header("Referrer-Policy: strict-origin-when-cross-origin");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RB Stores Dashboard</title>

  <!-- Font and Icon Libraries -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">

  <style>
    /* ===== Responsive Modern Business Header - inline from header.css ===== */
    :root{
      --header-bg:#3b5683;
      --header-text:#f8fafc;
      --header-hover:#38bdf8;
      --header-active:#2563eb;
      --logout-color:#ef4444;
      --font-main:'Poppins',sans-serif;
      --transition-speed:.3s
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:var(--font-main);background-color:#f8fafc;color:#224081}

    /* ========== HEADER ========== */
    .header{
      background-color:var(--header-bg);
      color:var(--header-text);
      width:100%;
      padding:10px 20px;
      position:sticky;top:0;z-index:999;
      box-shadow:0 2px 4px rgba(0,0,0,.05)
    }

    /* Updated Header Container */
    .header__container{
      display:flex;justify-content:space-between;align-items:center;
      padding:0 30px;height:40px;position:relative
    }

    /* Logo */
    .header__logo{display:flex;align-items:center;gap:10px;cursor:pointer}
    .header__logo-img{width:42px;height:42px;object-fit:contain}
    .header__logo-text{font-size:1.4rem;font-weight:600;color:var(--header-text)}

    /* Nav */
    .header__nav{display:flex;align-items:center;gap:25px;transition:var(--transition-speed)}
    .header__nav a{
      text-decoration:none;color:var(--header-text);font-weight:500;padding:8px 0;position:relative;
      transition:color var(--transition-speed)
    }
    .header__nav a:hover,.header__nav a:focus{color:var(--header-hover)}
    .header__nav a.logout{color:var(--logout-color)}

    /* Toggle (mobile) */
    .header__toggle{display:none;background:none;border:none;font-size:1.6rem;color:var(--header-text);cursor:pointer}

    /* Tablets and below */
    @media (max-width:1024px){
      .header__nav{gap:18px}
    }

    /* Phones and small tablets */
    @media (max-width:768px){
      .header__toggle{display:block}
      .header__nav{
        position:absolute;top:60px;right:0;left:0;background-color:var(--header-bg);
        flex-direction:column;align-items:flex-start;gap:15px;padding:15px 25px;
        transform:translateY(-300%);opacity:0;visibility:hidden;
        transition:all var(--transition-speed) ease-in-out
      }
      .header__nav.header__nav--active{transform:translateY(0);opacity:1;visibility:visible}
      .header__logo{margin-left:12px}
    }
  </style>
</head>
<body>

<header class="header">
  <div class="header__container">
    <div class="header__logo" onclick="window.location='dashboard.php'">
      <img src="/rbstorsg/assets/images/rb.png" alt="RB Stores Logo" class="header__logo-img">
      <span class="header__logo-text">RB Stores</span>
    </div>

    <nav class="header__nav" id="navbar">
      <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
      <a href="support.php">Support <i class="fas fa-headset"></i></a>
      <a href="/rbstorsg/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <button class="header__toggle" id="menuToggle" aria-label="Toggle menu" aria-controls="navbar" aria-expanded="false">
      <i class="fas fa-bars"></i>
    </button>
  </div>
</header>

<script>
  (function(){
    const toggle = document.getElementById('menuToggle');
    const nav = document.getElementById('navbar');
    toggle.addEventListener('click', () => {
      const active = nav.classList.toggle('header__nav--active');
      toggle.setAttribute('aria-expanded', active ? 'true' : 'false');
    });

    // Close menu when clicking outside on mobile
    document.addEventListener('click', (e) => {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('header__nav--active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Close on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        nav.classList.remove('header__nav--active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  })();
</script>

</body>
</html>
