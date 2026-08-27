<?php
// admin/org_info.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Organization Info';
$errors = [];

// Fetch current organizational info
try {
    $stmt = $pdo->query("SELECT * FROM organization_info ORDER BY id ASC LIMIT 1");
    $org = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Failed to fetch organization details: " . $e->getMessage());
    $org = false;
}

// Process update form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $org_name      = trim($_POST['org_name']      ?? '');
        $about_text    = trim($_POST['about_text']    ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');

        // Validation
        if (empty($org_name)) {
            $errors[] = 'Organization name is required.';
        }
        if (empty($about_text)) {
            $errors[] = 'About text is required.';
        }
        if (empty($contact_email)) {
            $errors[] = 'Contact email is required.';
        } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            try {
                if ($org) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE organization_info SET org_name = ?, about_text = ?, contact_email = ? WHERE id = ?");
                    $stmt->execute([$org_name, $about_text, $contact_email, $org['id']]);
                } else {
                    // Insert if none exists
                    $stmt = $pdo->prepare("INSERT INTO organization_info (org_name, about_text, contact_email) VALUES (?, ?, ?)");
                    $stmt->execute([$org_name, $about_text, $contact_email]);
                }

                setFlash('success', 'Organization settings updated successfully!');
                redirect('org_info.php');
            } catch (PDOException $e) {
                error_log("Failed to update organization details: " . $e->getMessage());
                $errors[] = 'A database error occurred. Please try again.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Maintain Organizational Information</h1>
        <p>Update general info displayed in student headers, footers, and contact sections.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST" action="org_info.php">
        <?php csrfField(); ?>

        <div class="form-group">
            <label for="org_name">Organization Name</label>
            <input 
                type="text" 
                id="org_name" 
                name="org_name" 
                value="<?= sanitize($org['org_name'] ?? '') ?>" 
                required
            >
        </div>

        <div class="form-group">
            <label for="contact_email">Contact Email</label>
            <input 
                type="email" 
                id="contact_email" 
                name="contact_email" 
                value="<?= sanitize($org['contact_email'] ?? '') ?>" 
                required
            >
        </div>

        <div class="form-group">
            <label for="about_text">About Us / Overview</label>
            <textarea 
                id="about_text" 
                name="about_text" 
                rows="6" 
                required
            ><?= sanitize($org['about_text'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary mt-1">
            💾 Save Settings
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
