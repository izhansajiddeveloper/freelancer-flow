<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'helpers/auth_helper.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freelance Flow - CRM & Invoice System</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logo.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>

<?php if (!isset($hide_navbar) || !$hide_navbar): ?>
<nav class="navbar">
    <a href="<?php echo BASE_URL; ?>" class="logo">
        <i class="fas fa-rocket"></i>
        <span>FreelanceFlow</span>
    </a>

    <ul class="nav-links">
        <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
        <li><a href="<?php echo BASE_URL; ?>about.php">About</a></li>
        <li><a href="<?php echo BASE_URL; ?>features.php">Features</a></li>
        <li><a href="<?php echo BASE_URL; ?>contact.php">Contact</a></li>
    </ul>

    <div class="nav-auth">
        <?php if (isLoggedIn()): ?>
            <a href="<?php echo BASE_URL; ?>dashboard/index.php" class="btn btn-outline">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="btn btn-primary">Logout</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-outline">Login</a>
            <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn btn-primary">Register</a>
        <?php endif; ?>
    </div>

    <div class="menu-toggle" id="mobile-menu">
        <i class="fas fa-bars"></i>
    </div>
</nav>

<!-- Mobile Navigation Drawer -->
<div class="mobile-nav" id="mobile-nav">
    <div class="mobile-nav-header">
        <div class="logo">
            <i class="fas fa-rocket"></i>
            <span>FreelanceFlow</span>
        </div>
        <i class="fas fa-times" id="close-menu"></i>
    </div>
    <ul class="mobile-nav-links">
        <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
        <li><a href="<?php echo BASE_URL; ?>about.php">About</a></li>
        <li><a href="<?php echo BASE_URL; ?>features.php">Features</a></li>
        <li><a href="<?php echo BASE_URL; ?>contact.php">Contact</a></li>
        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 10px 0;">
        <?php if (isLoggedIn()): ?>
            <li><a href="<?php echo BASE_URL; ?>dashboard/index.php">Dashboard</a></li>
            <li><a href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="<?php echo BASE_URL; ?>auth/login.php">Login</a></li>
            <li><a href="<?php echo BASE_URL; ?>auth/register.php">Register</a></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<script>
    const menuToggle = document.getElementById('mobile-menu');
    const closeMenu = document.getElementById('close-menu');
    const mobileNav = document.getElementById('mobile-nav');

    menuToggle.addEventListener('click', () => {
        mobileNav.classList.add('active');
    });

    closeMenu.addEventListener('click', () => {
        mobileNav.classList.remove('active');
    });

    // Close menu when clicking on a link
    document.querySelectorAll('.mobile-nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            mobileNav.classList.remove('active');
        });
    });
</script>
