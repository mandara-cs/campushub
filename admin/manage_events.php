<?php
// admin/manage_events.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Manage Events';
$errors = [];
$action = $_GET['action'] ?? 'list';
$eventId = intval($_GET['id'] ?? 0);

// Initialize form variables
$title          = '';
$event_type     = 'event';
$description    = '';
$event_date     = '';
$location       = '';
$multimedia_url = '';

// ── Process Delete Action ─────────────────────────────────────
if ($action === 'delete' && $eventId > 0) {
    try {
        // Fetch event first to clean up files
        $stmt = $pdo->prepare("SELECT multimedia_url FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if ($event) {
            if (!empty($event['multimedia_url'])) {
                $filePath = '../assets/uploads/events/' . $event['multimedia_url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            setFlash('success', 'Event deleted successfully.');
        } else {
            setFlash('error', 'Event not found.');
        }
    } catch (PDOException $e) {
        error_log("Failed to delete event: " . $e->getMessage());
        setFlash('error', 'Database error occurred while deleting event.');
    }
    redirect('manage_events.php');
}

// ── Process Form Submission (Create or Update) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid validation token.';
    } else {
        $eventId     = intval($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $event_type  = $_POST['event_type'] ?? 'event';
        $description = trim($_POST['description'] ?? '');
        $event_date  = $_POST['event_date'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        
        // Fetch existing file name if editing
        $existingMedia = '';
        if ($eventId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT multimedia_url FROM events WHERE id = ?");
                $stmt->execute([$eventId]);
                $existingMedia = $stmt->fetchColumn() ?: '';
            } catch (PDOException $e) {
                error_log("Failed to fetch existing media: " . $e->getMessage());
            }
        }

        // Validation
        if (empty($title))       $errors[] = 'Event title is required.';
        if (empty($description)) $errors[] = 'Event description is required.';
        if (empty($event_date))  $errors[] = 'Event date & time are required.';
        if (empty($location))    $errors[] = 'Event location is required.';

        // Handle File Upload if selected
        $uploadedFileName = $existingMedia;
        if (isset($_FILES['multimedia']) && $_FILES['multimedia']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Whitelist MIME types: video, audio, image
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif',
                'video/mp4', 'video/webm',
                'audio/mpeg', 'audio/wav', 'audio/mp3'
            ];
            $maxSize = 25 * 1024 * 1024; // 25MB Max
            
            $uploadStatus = validateFileUpload($_FILES['multimedia'], $allowedTypes, $maxSize);
            
            if (!$uploadStatus['valid']) {
                $errors[] = $uploadStatus['error'];
            } else {
                // Rename file safely to avoid collision
                $newFileName = 'event_' . time() . '_' . uniqid() . '.' . $uploadStatus['ext'];
                $uploadDir = '../assets/uploads/events/';
                
                if (move_uploaded_file($_FILES['multimedia']['tmp_name'], $uploadDir . $newFileName)) {
                    $uploadedFileName = $newFileName;
                    // Delete old file if updating
                    if (!empty($existingMedia) && file_exists($uploadDir . $existingMedia)) {
                        unlink($uploadDir . $existingMedia);
                    }
                } else {
                    $errors[] = 'Failed to save uploaded file on server.';
                }
            }
        }

        if (empty($errors)) {
            try {
                // Format datetime-local string to standard MySQL date format
                $formatted_date = date('Y-m-d H:i:s', strtotime($event_date));

                if ($eventId > 0) {
                    // UPDATE
                    $stmt = $pdo->prepare("UPDATE events SET title = ?, event_type = ?, description = ?, event_date = ?, location = ?, multimedia_url = ? WHERE id = ?");
                    $stmt->execute([$title, $event_type, $description, $formatted_date, $location, $uploadedFileName, $eventId]);
                    setFlash('success', 'Event updated successfully.');
                } else {
                    // CREATE
                    $stmt = $pdo->prepare("INSERT INTO events (title, event_type, description, event_date, location, multimedia_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $event_type, $description, $formatted_date, $location, $uploadedFileName, $_SESSION['user_id']]);
                    setFlash('success', 'Event created successfully.');
                }
                redirect('manage_events.php');
            } catch (PDOException $e) {
                error_log("Failed to save event: " . $e->getMessage());
                $errors[] = 'Database error occurred while saving event.';
            }
        }
    }
    $action = ($eventId > 0) ? 'edit' : 'add';
}

// ── Prepare Form Data for Edit Mode ───────────────────────────
if ($action === 'edit' && $eventId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if ($event) {
            $title          = $event['title'];
            $event_type     = $event['event_type'];
            $description    = $event['description'];
            $event_date     = date('Y-m-d\TH:i', strtotime($event['event_date']));
            $location       = $event['location'];
            $multimedia_url = $event['multimedia_url'];
        } else {
            setFlash('error', 'Event not found.');
            redirect('manage_events.php');
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch event details: " . $e->getMessage());
        redirect('manage_events.php');
    }
}

// Fetch all events for List view
$events = [];
if ($action === 'list') {
    try {
        $events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch events: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Events & Activities</h1>
        <p>Coordinate competitions, workshops, sports activities, and general student events.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="manage_events.php?action=add" class="btn btn-primary">➕ Add Activity</a>
    <?php else: ?>
        <a href="manage_events.php" class="btn btn-outline">⬅️ Back to List</a>
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
                    <th>Type</th>
                    <th>Activity Title</th>
                    <th>Date & Time</th>
                    <th>Location</th>
                    <th>Media Attached</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No events listed yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $e): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?= sanitize($e['event_type']) ?>">
                                    <?= sanitize($e['event_type']) ?>
                                </span>
                            </td>
                            <td><strong><?= sanitize($e['title']) ?></strong></td>
                            <td><?= formatDate($e['event_date']) ?></td>
                            <td><?= sanitize($e['location'] ?: 'Online') ?></td>
                            <td>
                                <?php if (!empty($e['multimedia_url'])): ?>
                                    📎 Yes (<?= strtoupper(pathinfo($e['multimedia_url'], PATHINFO_EXTENSION)) ?>)
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a href="manage_events.php?action=edit&id=<?= $e['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <a href="manage_events.php?action=delete&id=<?= $e['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this event? All student registrations will be removed.');">Delete</a>
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
        <h3 class="mb-2"><?= $action === 'edit' ? 'Edit Activity Details' : 'Add New Activity' ?></h3>
        
        <form method="POST" action="manage_events.php" enctype="multipart/form-data">
            <?php csrfField(); ?>
            <input type="hidden" name="id" value="<?= $eventId ?>">

            <div class="form-group">
                <label for="title">Activity Title</label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    value="<?= sanitize($title) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="event_type">Activity Category</label>
                <select id="event_type" name="event_type">
                    <option value="event" <?= $event_type === 'event' ? 'selected' : '' ?>>General Event</option>
                    <option value="workshop" <?= $event_type === 'workshop' ? 'selected' : '' ?>>Workshop</option>
                    <option value="competition" <?= $event_type === 'competition' ? 'selected' : '' ?>>Competition</option>
                    <option value="sports" <?= $event_type === 'sports' ? 'selected' : '' ?>>Sports Activity</option>
                </select>
            </div>

            <div class="form-group">
                <label for="event_date">Date & Time</label>
                <input 
                    type="datetime-local" 
                    id="event_date" 
                    name="event_date" 
                    value="<?= sanitize($event_date) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input 
                    type="text" 
                    id="location" 
                    name="location" 
                    value="<?= sanitize($location) ?>" 
                    placeholder="e.g. Block A Auditorium, Lab 2, Field"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Detailed Description</label>
                <textarea 
                    id="description" 
                    name="description" 
                    rows="5" 
                    required
                ><?= sanitize($description) ?></textarea>
            </div>

            <div class="form-group">
                <label for="multimedia">
                    Multimedia File 
                    <span class="text-muted">(Max size: 25MB. Whitelisted formats: JPG, PNG, GIF, MP4, MP3)</span>
                </label>
                <input 
                    type="file" 
                    id="multimedia" 
                    name="multimedia"
                >
                <?php if ($action === 'edit' && !empty($multimedia_url)): ?>
                    <p class="mt-1 text-muted" style="font-size: 0.8rem;">
                        Currently attached: <code><?= sanitize($multimedia_url) ?></code>
                    </p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary mt-1 w-full">
                <?= $action === 'edit' ? '💾 Update Activity' : '➕ Add Activity' ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
