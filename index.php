<?php
// index.php — Login Page
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'student/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Validation
        if (empty($email))    $errors[] = 'Email is required.';
        if (empty($password)) $errors[] = 'Password is required.';

        if (empty($errors)) {
            // Fetch user by email (prepared statement — SQL injection safe)
            $stmt = $pdo->prepare("SELECT id, role, full_name, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php');
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to CampusHub — Student event management portal.">
    <title>Login | CampusHub</title>
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
            <h1>CampusHub</h1>
            <p>Sign in to your account</p>
        </div>

        <?php showFlash(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= sanitize($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="login-form" method="POST" action="index.php" novalidate>
            <?php csrfField(); ?>

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
                    class="<?= (!empty($errors) && empty($email)) ? 'input-error' : '' ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" id="btn-login" class="btn btn-primary btn-full btn-lg">
                Sign In
            </button>
        </form>

        <div class="auth-divider">or</div>

        <div class="auth-footer">
            Don't have an account?
            <a href="register.php">Create one here</a>
        </div>

    </div>
</div>

</body>
</html>
