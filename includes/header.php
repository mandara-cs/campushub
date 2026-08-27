<?php
// includes/header.php
require_once __DIR__ . '/functions.php';

// $pageTitle should be set by each page before including this file
$pageTitle = $pageTitle ?? 'CampusHub';

// Determine base path for assets (works from root AND subdirectories)
$depth    = substr_count($_SERVER['PHP_SELF'], '/') - 1;
$basePath = str_repeat('../', max(0, $depth - 1));
if ($depth <= 1) $basePath = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CampusHub — Student event management portal for clubs, workshops, competitions and sports activities.">
    <title><?= sanitize($pageTitle) ?> | CampusHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body>

<?php if (isLoggedIn()): ?>
<!-- ── Authenticated Navigation ─────────────────────────────── -->
<nav class="navbar" id="main-nav">
    <div class="nav-container">
        <a href="<?= isAdmin() ? $basePath . 'admin/dashboard.php' : $basePath . 'student/dashboard.php' ?>" class="nav-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text">CampusHub</span>
        </a>

        <ul class="nav-links" id="nav-links">
            <?php if (isAdmin()): ?>
                <li><a href="<?= $basePath ?>admin/dashboard.php"         class="nav-link">Home</a></li>
                <li><a href="<?= $basePath ?>admin/manage_users.php"       class="nav-link">Users</a></li>
                <li><a href="<?= $basePath ?>admin/manage_events.php"      class="nav-link">Events</a></li>
                <li><a href="<?= $basePath ?>admin/manage_registrations.php" class="nav-link">Signups</a></li>
                <li><a href="<?= $basePath ?>admin/manage_submissions.php" class="nav-link">Uploads</a></li>
                <li><a href="<?= $basePath ?>admin/view_inquiries.php"     class="nav-link">Inquiries</a></li>
                <li><a href="<?= $basePath ?>admin/manage_announcements.php" class="nav-link">News</a></li>
                <li><a href="<?= $basePath ?>admin/export_xml.php"         class="nav-link">XML</a></li>
                <li><a href="<?= $basePath ?>admin/org_info.php"           class="nav-link">Settings</a></li>
            <?php else: ?>
                <li><a href="<?= $basePath ?>student/dashboard.php"   class="nav-link">Dashboard</a></li>
                <li><a href="<?= $basePath ?>student/events.php"       class="nav-link">Events</a></li>
                <li><a href="<?= $basePath ?>student/submit.php"       class="nav-link">Submit Activity</a></li>
                <li><a href="<?= $basePath ?>student/contact.php"      class="nav-link">Contact</a></li>
                <li><a href="<?= $basePath ?>student/profile.php"      class="nav-link">Profile</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-user">
            <span class="nav-user-name">👤 <?= sanitize($_SESSION['full_name'] ?? 'User') ?></span>
            <a href="<?= $basePath ?>logout.php" class="btn btn-outline btn-sm">Logout</a>
        </div>

        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">☰</button>
    </div>
</nav>
<?php else: ?>
<!-- ── Guest Navigation ──────────────────────────────────────── -->
<nav class="navbar navbar-guest" id="main-nav">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text">CampusHub</span>
        </a>
        <div class="nav-user">
            <a href="index.php"    class="btn btn-outline btn-sm">Login</a>
            <a href="register.php" class="btn btn-primary btn-sm">Register</a>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="main-content">
<?php showFlash(); ?>
