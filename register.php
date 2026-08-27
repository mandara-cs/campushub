<?php
// register.php — Student Registration
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'student/dashboard.php');
}

$errors    = [];
$full_name = '';
$email     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $full_name       = trim($_POST['full_name']       ?? '');
        $email           = trim($_POST['email']           ?? '');
        $password        = $_POST['password']             ?? '';
        $confirm_password = $_POST['confirm_password']   ?? '';

        // ── Validation ─────────────────────────────────────────
        if (empty($full_name))
            $errors['full_name'] = 'Full name is required.';
        elseif (strlen($full_name) < 3)
            $errors['full_name'] = 'Full name must be at least 3 characters.';

        if (empty($email))
            $errors['email'] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Please enter a valid email address.';

        if (empty($password))
            $errors['password'] = 'Password is required.';
        elseif (strlen($password) < 8)
            $errors['password'] = 'Password must be at least 8 characters.';

        if ($password !== $confirm_password)
            $errors['confirm_password'] = 'Passwords do not match.';

        // Check email uniqueness
        if (empty($errors['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email is already registered.';
            }
        }

        // ── Insert ─────────────────────────────────────────────
        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare(
                "INSERT INTO users (role, full_name, email, password) VALUES ('student', ?, ?, ?)"
            );
            $stmt->execute([$full_name, $email, $hashed]);

            setFlash('success', 'Account created! You can now log in.');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your CampusHub student account.">
    <title>Register | CampusHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-logo">
            <span class="brand-icon">🎓</span>
            <h1>Create Account</h1>
            <p>Join CampusHub today</p>
        </div>

        <?php showFlash(); ?>

        <form id="register-form" method="POST" action="register.php" novalidate>
            <?php csrfField(); ?>

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= sanitize($full_name) ?>"
                    placeholder="Your full name"
                    required
                    autocomplete="name"
                    class="<?= isset($errors['full_name']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['full_name'])): ?>
                    <span class="field-error"><?= sanitize($errors['full_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= sanitize($email) ?>"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                    class="<?= isset($errors['email']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= sanitize($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="text-muted">(min. 8 characters)</span></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a strong password"
                    required
                    autocomplete="new-password"
                    class="<?= isset($errors['password']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?= sanitize($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Repeat your password"
                    required
                    autocomplete="new-password"
                    class="<?= isset($errors['confirm_password']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="field-error"><?= sanitize($errors['confirm_password']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" id="btn-register" class="btn btn-primary btn-full btn-lg">
                Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="index.php">Sign in</a>
        </div>

    </div>
</div>

</body>
</html>
