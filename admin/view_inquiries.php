<?php
// admin/view_inquiries.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'View Student Inquiries';
$errors = [];
$action = $_GET['action'] ?? 'list';
$inquiryId = intval($_GET['id'] ?? 0);

// ── Process Delete Action ─────────────────────────────────────
if ($action === 'delete' && $inquiryId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
        $stmt->execute([$inquiryId]);
        setFlash('success', 'Inquiry deleted successfully.');
    } catch (PDOException $e) {
        error_log("Failed to delete inquiry: " . $e->getMessage());
        setFlash('error', 'Database error occurred while deleting inquiry.');
    }
    redirect('view_inquiries.php');
}

// Fetch all inquiries for List view
$inquiries = [];
if ($action === 'list') {
    try {
        $inquiries = $pdo->query("
            SELECT i.*, u.full_name, u.email 
            FROM contact_inquiries i
            JOIN users u ON i.student_id = u.id
            ORDER BY i.submitted_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        // Suppress error if table doesn't exist yet, to not break page completely.
        if (strpos($e->getMessage(), 'Base table or view not found') === false) {
            error_log("Failed to fetch inquiries: " . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>View Student Inquiries</h1>
        <p>Read messages and feedback submitted by students through the Contact form.</p>
    </div>
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
                    <th>Student Name</th>
                    <th>Email Address</th>
                    <th>Subject</th>
                    <th>Submitted On</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inquiries)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No student inquiries found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inquiries as $i): ?>
                        <tr>
                            <td><strong><?= sanitize($i['full_name']) ?></strong></td>
                            <td><?= sanitize($i['email']) ?></td>
                            <td><?= sanitize($i['subject']) ?></td>
                            <td><?= formatDate($i['submitted_at'], 'd M Y') ?></td>
                            <td class="text-right">
                                <a href="view_inquiries.php?action=view&id=<?= $i['id'] ?>" class="btn btn-outline btn-sm">Read Message</a>
                                <a href="view_inquiries.php?action=delete&id=<?= $i['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this inquiry?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ── VIEW MESSAGE DETAILS ──────────────────────────────────── -->
<?php elseif ($action === 'view' && $inquiryId > 0): ?>
    <?php
        $stmt = $pdo->prepare("SELECT i.*, u.full_name, u.email FROM contact_inquiries i JOIN users u ON i.student_id = u.id WHERE i.id = ?");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch();
        
        if (!$inquiry) {
            echo '<div class="alert alert-error">Inquiry not found.</div>';
        } else {
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h3 class="mb-2">Message Details</h3>
        <p><strong>From:</strong> <?= sanitize($inquiry['full_name']) ?> (<?= sanitize($inquiry['email']) ?>)</p>
        <p><strong>Date:</strong> <?= formatDate($inquiry['submitted_at']) ?></p>
        <hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--border);">
        <h4 class="mb-1">Subject: <?= sanitize($inquiry['subject']) ?></h4>
        <div style="background: var(--bg); padding: 1rem; border-radius: var(--radius-sm); color: var(--text);">
            <?= nl2br(sanitize($inquiry['message'])) ?>
        </div>
        <div class="mt-2 text-right">
            <a href="mailto:<?= sanitize($inquiry['email']) ?>" class="btn btn-primary">✉️ Reply via Email</a>
            <a href="view_inquiries.php" class="btn btn-outline">⬅️ Back to List</a>
        </div>
    </div>
    <?php } ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
