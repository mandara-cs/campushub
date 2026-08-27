<?php
// includes/footer.php
$depth    = substr_count($_SERVER['PHP_SELF'], '/') - 1;
$basePath = str_repeat('../', max(0, $depth - 1));
if ($depth <= 1) $basePath = '';
?>
</main><!-- /.main-content -->

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text">CampusHub</span>
            <p class="footer-tagline">Connecting Students, Empowering Communities</p>
        </div>

        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <?php if (isLoggedIn() && isAdmin()): ?>
                    <li><a href="<?= $basePath ?>admin/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= $basePath ?>admin/manage_events.php">Events</a></li>
                    <li><a href="<?= $basePath ?>admin/export_xml.php">Export XML</a></li>
                <?php elseif (isLoggedIn()): ?>
                    <li><a href="<?= $basePath ?>student/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= $basePath ?>student/events.php">Events</a></li>
                    <li><a href="<?= $basePath ?>student/profile.php">My Profile</a></li>
                <?php else: ?>
                    <li><a href="<?= $basePath ?>index.php">Login</a></li>
                    <li><a href="<?= $basePath ?>register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-contact">
            <h4>Contact</h4>
            <p>📧 info@campushub.com</p>
            <p>📞 +94 11 234 5678</p>
            <p>📍 42 University Avenue, Colombo 07</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> CampusHub. All rights reserved. | H7DT Web Programming I Project</p>
    </div>
</footer>

<script>
// Mobile nav toggle
document.getElementById('nav-toggle')?.addEventListener('click', function () {
    document.getElementById('nav-links').classList.toggle('nav-open');
});

// Highlight active nav link
document.querySelectorAll('.nav-link').forEach(function(link) {
    if (link.href === window.location.href) {
        link.classList.add('active');
    }
});
</script>
</body>
</html>
