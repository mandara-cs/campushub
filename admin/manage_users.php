<?php
// admin/manage_users.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Manage Users';
$errors = [];
$action = $_GET['action'] ?? 'list';
$userId = intval($_GET['id'] ?? 0);

// Initialize form variables
$full_name = '';
$email     = '';
$role      = 'student';

// ── Process Delete Action ─────────────────────────────────────
if ($action === 'delete' && $userId > 0) {
    // Prevent admin from deleting themselves
    if ($userId === intval($_SESSION['user_id'])) {
        setFlash('error', 'You cannot delete your own account.');
        redirect('manage_users.php');
    }

    try {
        // Fetch user first to delete profile image if it exists
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user) {
            if (!empty($user['profile_image'])) {
                $imgPath = '../assets/uploads/profiles/' . $user['profile_image'];
                if (file_exists($imgPath)) {
                    unlink($imgPath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            setFlash('success', 'User deleted successfully.');
        } else {
            setFlash('error', 'User not found.');
        }
    } catch (PDOException $e) {
        error_log("Failed to delete user: " . $e->getMessage());
        setFlash('error', 'Database error occurred while deleting user.');
    }
    redirect('manage_users.php');
}

// ── Process Form Submission (Create or Update) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form validation token.';
    } else {
        $userId    = intval($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = $_POST['role'] ?? 'student';
        $password  = $_POST['password'] ?? '';

        // Validation
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address format.';
        }

        // Email unique check
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered.';
            }
        }

        if ($userId === 0 && empty($password)) {
            $errors[] = 'Password is required for new users.';
        } elseif (!empty($password) && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (empty($errors)) {
            try {
                if ($userId > 0) {
                    // UPDATE
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$full_name, $email, $role, $hashed, $userId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$full_name, $email, $role, $userId]);
                    }
                    setFlash('success', 'User updated successfully.');
                } else {
                    // CREATE
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, role, password) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$full_name, $email, $role, $hashed]);
                    setFlash('success', 'User created successfully.');
                }
                redirect('manage_users.php');
            } catch (PDOException $e) {
                error_log("Failed to save user: " . $e->getMessage());
                $errors[] = 'Database error occurred while saving user details.';
            }
        }
    }
    // Re-set action to edit/add if submit failed
    $action = ($userId > 0) ? 'edit' : 'add';
}

// ── Prepare Form Data for Edit Mode ───────────────────────────
if ($action === 'edit' && $userId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $full_name = $user['full_name'];
            $email     = $user['email'];
            $role      = $user['role'];
        } else {
            setFlash('error', 'User not found.');
            redirect('manage_users.php');
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch user: " . $e->getMessage());
        redirect('manage_users.php');
    }
}

// Fetch all users for List view
$users = [];
if ($action === 'list') {
    try {
        $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch users list: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Users</h1>
        <p>View, add, edit, and delete CampusHub student & administrator accounts.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="manage_users.php?action=add" class="btn btn-primary">➕ Add User</a>
    <?php else: ?>
        <a href="manage_users.php" class="btn btn-outline">⬅️ Back to List</a>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ── LIST VIEW ─────────────────────────────────────────────── -->
<?php if ($action === 'list'): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Registered On</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?php if (!empty($u['profile_image'])): ?>
                                    <img src="../assets/uploads/profiles/<?= sanitize($u['profile_image']) ?>" class="avatar" alt="Avatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">👤</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= sanitize($u['full_name']) ?></strong></td>
                            <td><?= sanitize($u['email']) ?></td>
                            <td><span class="badge badge-<?= sanitize($u['role']) ?>"><?= sanitize($u['role']) ?></span></td>
                            <td><?= formatDate($u['created_at'], 'd M Y') ?></td>
                            <td class="text-right">
                                <a href="manage_users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <?php if ($u['id'] !== intval($_SESSION['user_id'])): ?>
                                    <a href="manage_users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ── ADD / EDIT FORM VIEW ──────────────────────────────────── -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width: 500px; margin: 0 auto;">
        <h3 class="mb-2"><?= $action === 'edit' ? 'Edit User Details' : 'Add New Account' ?></h3>
        
        <form method="POST" action="manage_users.php">
            <?php csrfField(); ?>
            <input type="hidden" name="id" value="<?= $userId ?>">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    value="<?= sanitize($full_name) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= sanitize($email) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">System Role</label>
                <select id="role" name="role">
                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">
                    Password 
                    <?php if ($action === 'edit'): ?>
                        <span class="text-muted">(Leave blank to keep current password)</span>
                    <?php endif; ?>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="<?= $action === 'edit' ? 'Optional password change' : 'Min. 8 characters' ?>"
                    <?= $action === 'add' ? 'required' : '' ?>
                >
            </div>

            <button type="submit" class="btn btn-primary mt-1 w-full">
                <?= $action === 'edit' ? '💾 Update Account' : '➕ Create User' ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
