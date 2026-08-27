<?php
// admin/manage_submissions.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Manage Student Submissions';
$errors = [];
$action = $_GET['action'] ?? 'list';
$submissionId = intval($_GET['id'] ?? 0);

// ── Process Delete Action ─────────────────────────────────────
if ($action === 'delete' && $submissionId > 0) {
    try {
        // Fetch submission first to clean up media file
        $stmt = $pdo->prepare("SELECT media_url FROM student_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();

        if ($sub) {
            if (!empty($sub['media_url'])) {
                $filePath = '../assets/uploads/activity_posts/' . $sub['media_url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM student_submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            setFlash('success', 'Student submission deleted successfully.');
        } else {
            setFlash('error', 'Submission not found.');
        }
    } catch (PDOException $e) {
        error_log("Failed to delete submission: " . $e->getMessage());
        setFlash('error', 'Database error occurred while deleting submission.');
    }
    redirect('manage_submissions.php');
}

// Fetch all submissions for List view
$submissions = [];
if ($action === 'list') {
    try {
        $submissions = $pdo->query("
            SELECT s.*, u.full_name, u.email 
            FROM student_submissions s
            JOIN users u ON s.student_id = u.id
            ORDER BY s.submitted_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch submissions: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Student Submissions</h1>
        <p>Moderate student activity updates, text stories, and uploaded media files.</p>
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
                    <th>Activity Title</th>
                    <th>Media Attached</th>
                    <th>Submitted On</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No student submissions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $s): ?>
                        <tr>
                            <td><strong><?= sanitize($s['full_name']) ?></strong><br><small class="text-muted"><?= sanitize($s['email']) ?></small></td>
                            <td><?= sanitize($s['submission_title']) ?></td>
                            <td>
                                <?php if (!empty($s['media_url'])): ?>
                                    📎 Yes (<?= strtoupper(pathinfo($s['media_url'], PATHINFO_EXTENSION)) ?>)
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($s['submitted_at'], 'd M Y') ?></td>
                            <td class="text-right">
                                <a href="manage_submissions.php?action=view&id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">View Post</a>
                                <a href="manage_submissions.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this student submission permanently?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ── VIEW POST DETAILS ──────────────────────────────────── -->
<?php elseif ($action === 'view' && $submissionId > 0): ?>
    <?php
        $stmt = $pdo->prepare("SELECT s.*, u.full_name, u.email FROM student_submissions s JOIN users u ON s.student_id = u.id WHERE s.id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();
        
        if (!$sub) {
            echo '<div class="alert alert-error">Submission not found.</div>';
        } else {
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h3 class="mb-2">Student Post Details</h3>
        <p><strong>From:</strong> <?= sanitize($sub['full_name']) ?> (<?= sanitize($sub['email']) ?>)</p>
        <p><strong>Date:</strong> <?= formatDate($sub['submitted_at']) ?></p>
        <hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--border);">
        <h4 class="mb-1">Title: <?= sanitize($sub['submission_title']) ?></h4>
        <div style="background: var(--bg); padding: 1rem; border-radius: var(--radius-sm); color: var(--text); margin-bottom: 1.5rem;">
            <?= nl2br(sanitize($sub['submission_text'])) ?>
        </div>
        
        <?php if (!empty($sub['media_url'])): ?>
            <h4 class="mb-1">Attached Media</h4>
            <div style="text-align: center; background: var(--bg); padding: 1rem; border-radius: var(--radius-sm);">
                <?php 
                    $ext = strtolower(pathinfo($sub['media_url'], PATHINFO_EXTENSION));
                    $imgPath = '../assets/uploads/activity_posts/' . $sub['media_url'];
                ?>
                <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && file_exists($imgPath)): ?>
                    <img src="<?= sanitize($imgPath) ?>" style="max-width: 100%; border-radius: var(--radius-sm);" alt="Submission media">
                <?php elseif (in_array($ext, ['mp4', 'webm']) && file_exists($imgPath)): ?>
                    <video controls style="max-width: 100%;">
                        <source src="<?= sanitize($imgPath) ?>" type="video/<?= $ext ?>">
                    </video>
                <?php else: ?>
                    <a href="<?= sanitize($imgPath) ?>" target="_blank" class="btn btn-outline">View Attached File</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-2 text-right">
            <a href="manage_submissions.php?action=delete&id=<?= $sub['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this post?');">🗑️ Delete Post</a>
            <a href="manage_submissions.php" class="btn btn-outline">⬅️ Back to List</a>
        </div>
    </div>
    <?php } ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
