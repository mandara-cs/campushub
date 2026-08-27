<?php
// student/submit.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle = 'Upload Activity Update';
$errors = [];
$studentId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid validation token.';
    } else {
        $submission_title = trim($_POST['submission_title'] ?? '');
        $submission_text  = trim($_POST['submission_text'] ?? '');

        // Validation
        if (empty($submission_title)) $errors[] = 'Title is required.';
        if (empty($submission_text))  $errors[] = 'Activity text description is required.';

        // Handle Optional Photo / Media Upload
        $mediaUrl = null;
        if (isset($_FILES['submission_media']) && $_FILES['submission_media']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif',
                'video/mp4', 'video/webm'
            ];
            $maxSize = 15 * 1024 * 1024; // 15MB Max

            $uploadStatus = validateFileUpload($_FILES['submission_media'], $allowedTypes, $maxSize);

            if (!$uploadStatus['valid']) {
                $errors[] = $uploadStatus['error'];
            } else {
                $newFileName = 'sub_' . $studentId . '_' . time() . '.' . $uploadStatus['ext'];
                $uploadDir = '../assets/uploads/activity_posts/';

                if (move_uploaded_file($_FILES['submission_media']['tmp_name'], $uploadDir . $newFileName)) {
                    $mediaUrl = $newFileName;
                } else {
                    $errors[] = 'Failed to save media file.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO student_submissions (student_id, submission_title, submission_text, media_url) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$studentId, $submission_title, $submission_text, $mediaUrl]);
                setFlash('success', 'Your activity update was submitted successfully!');
                redirect('submit.php');
            } catch (PDOException $e) {
                error_log("Failed to submit student activity: " . $e->getMessage());
                $errors[] = 'Database error submitting activity.';
            }
        }
    }
}

// Handle submission deletion
$action = $_GET['action'] ?? '';
$submissionId = intval($_GET['id'] ?? 0);

if ($action === 'delete' && $submissionId > 0) {
    try {
        // Verify ownership before deleting
        $stmt = $pdo->prepare("SELECT media_url FROM student_submissions WHERE id = ? AND student_id = ?");
        $stmt->execute([$submissionId, $studentId]);
        $sub = $stmt->fetch();
        
        if ($sub) {
            // Delete file if it exists
            if (!empty($sub['media_url'])) {
                $filePath = '../assets/uploads/activity_posts/' . $sub['media_url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM student_submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            setFlash('success', 'Activity update deleted successfully.');
        } else {
            setFlash('error', 'Post not found or access denied.');
        }
    } catch (PDOException $e) {
        error_log("Failed to delete student activity: " . $e->getMessage());
        setFlash('error', 'Database error deleting activity.');
    }
    redirect('submit.php');
}

// Fetch Previous Submissions by logged-in student
$mySubmissions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_submissions WHERE student_id = ? ORDER BY submitted_at DESC");
    $stmt->execute([$studentId]);
    $mySubmissions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching submissions: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Upload Photographs & Activity Updates</h1>
        <p>Share your stories, project updates, and activity photos with the campus community.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <!-- Submit Form -->
    <div class="card">
        <h3 class="mb-2">New Activity Post</h3>
        <form method="POST" action="submit.php" enctype="multipart/form-data">
            <?php csrfField(); ?>

            <div class="form-group">
                <label for="submission_title">Update Title</label>
                <input 
                    type="text" 
                    id="submission_title" 
                    name="submission_title" 
                    placeholder="e.g. My Hackathon Preparation Story"
                    required
                >
            </div>

            <div class="form-group">
                <label for="submission_text">Activity Story / Details</label>
                <textarea 
                    id="submission_text" 
                    name="submission_text" 
                    rows="5" 
                    placeholder="Describe your project, workshop learning, or sports achievement..."
                    required
                ></textarea>
            </div>

            <div class="form-group">
                <label for="submission_media">
                    Attach Photograph / Media 
                    <span class="text-muted">(JPG, PNG, GIF, MP4 — Max 15MB)</span>
                </label>
                <input 
                    type="file" 
                    id="submission_media" 
                    name="submission_media"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                🚀 Publish Activity Update
            </button>
        </form>
    </div>

    <!-- Previous Submissions List -->
    <div class="card">
        <h3 class="mb-2">My Submissions Log</h3>
        <?php if (empty($mySubmissions)): ?>
            <p class="text-muted">You have not posted any updates yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($mySubmissions as $sub): ?>
                    <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                        <div class="flex-between mb-1">
                            <h4 style="color: var(--primary-light);"><?= sanitize($sub['submission_title']) ?></h4>
                            <span class="text-muted" style="font-size: 0.75rem;"><?= formatDate($sub['submitted_at']) ?></span>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text);" class="mb-1"><?= nl2br(sanitize($sub['submission_text'])) ?></p>
                        
                        <?php if (!empty($sub['media_url'])): ?>
                            <?php 
                                $ext = strtolower(pathinfo($sub['media_url'], PATHINFO_EXTENSION));
                                $imgPath = '../assets/uploads/activity_posts/' . $sub['media_url'];
                            ?>
                            <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && file_exists($imgPath)): ?>
                                <img src="<?= sanitize($imgPath) ?>" style="max-height: 180px; border-radius: var(--radius-sm); object-fit: cover;" alt="Submission media">
                            <?php elseif (in_array($ext, ['mp4', 'webm']) && file_exists($imgPath)): ?>
                                <video controls style="max-height: 180px;">
                                    <source src="<?= sanitize($imgPath) ?>" type="video/<?= $ext ?>">
                                </video>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="mt-1 text-right">
                            <a href="submit.php?action=delete&id=<?= $sub['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this activity update?');">🗑️ Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
