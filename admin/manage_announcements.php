<?php
// admin/manage_announcements.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Manage Announcements';
$errors = [];
$action = $_GET['action'] ?? 'list';
$announcementId = intval($_GET['id'] ?? 0);

// Initialize form variables
$title   = '';
$content = '';

// ── Process Delete Action ─────────────────────────────────────
if ($action === 'delete' && $announcementId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$announcementId]);
        setFlash('success', 'Announcement deleted successfully.');
    } catch (PDOException $e) {
        error_log("Failed to delete announcement: " . $e->getMessage());
        setFlash('error', 'Database error occurred while deleting announcement.');
    }
    redirect('manage_announcements.php');
}

// ── Process Form Submission (Create or Update) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid validation token.';
    } else {
        $announcementId = intval($_POST['id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $content        = trim($_POST['content'] ?? '');

        // Validation
        if (empty($title))   $errors[] = 'Announcement title is required.';
        if (empty($content)) $errors[] = 'Announcement content is required.';

        if (empty($errors)) {
            try {
                if ($announcementId > 0) {
                    // UPDATE
                    $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
                    $stmt->execute([$title, $content, $announcementId]);
                    setFlash('success', 'Announcement updated successfully.');
                } else {
                    // CREATE
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
                    $stmt->execute([$title, $content]);
                    setFlash('success', 'Announcement published successfully.');
                }
                redirect('manage_announcements.php');
            } catch (PDOException $e) {
                error_log("Failed to save announcement: " . $e->getMessage());
                $errors[] = 'Database error occurred while saving announcement.';
            }
        }
    }
    $action = ($announcementId > 0) ? 'edit' : 'add';
}

// ── Prepare Form Data for Edit Mode ───────────────────────────
if ($action === 'edit' && $announcementId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$announcementId]);
        $ann = $stmt->fetch();
        if ($ann) {
            $title   = $ann['title'];
            $content = $ann['content'];
        } else {
            setFlash('error', 'Announcement not found.');
            redirect('manage_announcements.php');
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch announcement details: " . $e->getMessage());
        redirect('manage_announcements.php');
    }
}

// Fetch all announcements for List view
$announcements = [];
if ($action === 'list') {
    try {
        $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch announcements: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Announcements</h1>
        <p>Publish global news, general deadlines, and updates for students.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="manage_announcements.php?action=add" class="btn btn-primary">➕ Publish News</a>
    <?php else: ?>
        <a href="manage_announcements.php" class="btn btn-outline">⬅️ Back to List</a>
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
                    <th>Title</th>
                    <th>Content Snippet</th>
                    <th>Published Date</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($announcements)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No announcements listed.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td><strong><?= sanitize($a['title']) ?></strong></td>
                            <td class="text-muted"><?= sanitize(substr($a['content'], 0, 80)) ?>...</td>
                            <td><?= formatDate($a['created_at'], 'd M Y') ?></td>
                            <td class="text-right">
                                <a href="manage_announcements.php?action=edit&id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <a href="manage_announcements.php?action=delete&id=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this announcement?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ── ADD / EDIT FORM VIEW ──────────────────────────────────── -->
<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h3 class="mb-2"><?= $action === 'edit' ? 'Edit Announcement' : 'Publish New Announcement' ?></h3>
        
        <form method="POST" action="manage_announcements.php">
            <?php csrfField(); ?>
            <input type="hidden" name="id" value="<?= $announcementId ?>">

            <div class="form-group">
                <label for="title">Title</label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    value="<?= sanitize($title) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="content">Announcement Details</label>
                <textarea 
                    id="content" 
                    name="content" 
                    rows="6" 
                    required
                ><?= sanitize($content) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary mt-1 w-full">
                <?= $action === 'edit' ? '💾 Update Announcement' : '📢 Publish' ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
