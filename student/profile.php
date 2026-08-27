<?php
// student/profile.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle = 'My Profile';
$errors = [];
$studentId = $_SESSION['user_id'];

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlash('error', 'User record not found.');
        redirect('../index.php');
    }
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    setFlash('error', 'Database error loading profile.');
    redirect('dashboard.php');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request security token.';
    } else {
        $full_name    = trim($_POST['full_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        // Validation
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        // Email uniqueness check (excluding current user)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $studentId]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already in use by another account.';
            }
        }

        if (!empty($new_password) && strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }

        // Handle Profile Image Upload
        $profileImage = $user['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB Max

            $uploadStatus = validateFileUpload($_FILES['profile_image'], $allowedTypes, $maxSize);

            if (!$uploadStatus['valid']) {
                $errors[] = $uploadStatus['error'];
            } else {
                $newFileName = 'profile_' . $studentId . '_' . time() . '.' . $uploadStatus['ext'];
                $uploadDir = '../assets/uploads/profiles/';

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $newFileName)) {
                    // Delete old profile picture if exists
                    if (!empty($user['profile_image']) && file_exists($uploadDir . $user['profile_image'])) {
                        unlink($uploadDir . $user['profile_image']);
                    }
                    $profileImage = $newFileName;
                } else {
                    $errors[] = 'Failed to upload profile picture to server.';
                }
            }
        }

        // Perform Database Update
        if (empty($errors)) {
            try {
                if (!empty($new_password)) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ?, password = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $profileImage, $hashed, $studentId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $profileImage, $studentId]);
                }

                // Update session variable name
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;

                setFlash('success', 'Profile updated successfully!');
                redirect('profile.php');
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $errors[] = 'Database error updating profile information.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p>View and edit your personal details, email, password, and avatar.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 550px; margin: 0 auto;">
    <div class="text-center mb-3">
        <?php if (!empty($user['profile_image'])): ?>
            <img src="../assets/uploads/profiles/<?= sanitize($user['profile_image']) ?>" class="avatar-lg" style="margin: 0 auto;" alt="Profile Picture">
        <?php else: ?>
            <div class="avatar-placeholder" style="width: 96px; height: 96px; font-size: 2.5rem; margin: 0 auto; border-radius: 50%;">👤</div>
        <?php endif; ?>
        <h2 class="mt-1"><?= sanitize($user['full_name']) ?></h2>
        <span class="badge badge-student"><?= sanitize($user['role']) ?></span>
    </div>

    <form method="POST" action="profile.php" enctype="multipart/form-data">
        <?php csrfField(); ?>

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input 
                type="text" 
                id="full_name" 
                name="full_name" 
                value="<?= sanitize($user['full_name']) ?>" 
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                value="<?= sanitize($user['email']) ?>" 
                required
            >
        </div>

        <div class="form-group">
            <label for="profile_image">
                Profile Image <span class="text-muted">(JPG, PNG, GIF — Max 5MB)</span>
            </label>
            <input 
                type="file" 
                id="profile_image" 
                name="profile_image" 
                accept="image/*"
            >
        </div>

        <div class="form-group">
            <label for="new_password">
                New Password <span class="text-muted">(Leave blank if keeping current password)</span>
            </label>
            <input 
                type="password" 
                id="new_password" 
                name="new_password" 
                placeholder="Enter new password (min. 8 characters)"
            >
        </div>

        <button type="submit" class="btn btn-primary mt-1 w-full">
            💾 Save Profile Changes
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
