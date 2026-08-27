<?php
// student/events.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle = 'Explore Activities & Events';
$errors = [];
$studentId = $_SESSION['user_id'];
$selectedCategory = $_GET['category'] ?? 'all';

// Handle Event Registration / Cancellation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid validation token.';
    } else {
        $eventId = intval($_POST['event_id'] ?? 0);
        $action  = $_POST['action'] ?? '';

        if ($eventId > 0) {
            try {
                if ($action === 'register') {
                    // Prevent duplicate registration
                    $stmt = $pdo->prepare("SELECT id FROM registrations WHERE student_id = ? AND event_id = ?");
                    $stmt->execute([$studentId, $eventId]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO registrations (student_id, event_id, attendance_status) VALUES (?, ?, 'registered')");
                        $stmt->execute([$studentId, $eventId]);
                        setFlash('success', 'You have registered for this activity!');
                    } else {
                        setFlash('warning', 'You are already registered for this activity.');
                    }
                } elseif ($action === 'cancel') {
                    $stmt = $pdo->prepare("DELETE FROM registrations WHERE student_id = ? AND event_id = ?");
                    $stmt->execute([$studentId, $eventId]);
                    setFlash('info', 'Your registration has been cancelled.');
                }
                redirect('events.php' . ($selectedCategory !== 'all' ? '?category=' . urlencode($selectedCategory) : ''));
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = 'A database error occurred.';
            }
        }
    }
}

// Fetch Events with category filter
$events = [];
try {
    if (in_array($selectedCategory, ['event', 'workshop', 'competition', 'sports'])) {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_type = ? ORDER BY event_date ASC");
        $stmt->execute([$selectedCategory]);
        $events = $stmt->fetchAll();
    } else {
        $events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();
    }

    // Fetch array of event IDs registered by this student
    $stmt = $pdo->prepare("SELECT event_id FROM registrations WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $myRegisteredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Explore Campus Activities</h1>
        <p>Browse events, workshops, competitions, and sports activities. Sign up online!</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Category Filter Buttons -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; flex-wrap: wrap;">
    <a href="events.php?category=all" class="btn <?= $selectedCategory === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All Categories</a>
    <a href="events.php?category=event" class="btn <?= $selectedCategory === 'event' ? 'btn-primary' : 'btn-outline' ?> btn-sm">General Events</a>
    <a href="events.php?category=workshop" class="btn <?= $selectedCategory === 'workshop' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Workshops</a>
    <a href="events.php?category=competition" class="btn <?= $selectedCategory === 'competition' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Competitions</a>
    <a href="events.php?category=sports" class="btn <?= $selectedCategory === 'sports' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Sports Activities</a>
</div>

<!-- Events Grid -->
<?php if (empty($events)): ?>
    <div class="card text-center">
        <p class="text-muted">No activities found in this category.</p>
    </div>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($events as $e): ?>
            <?php 
                $isRegistered = in_array($e['id'], $myRegisteredIds);
                $mediaUrl = $e['multimedia_url'];
                $mediaExt = !empty($mediaUrl) ? strtolower(pathinfo($mediaUrl, PATHINFO_EXTENSION)) : '';
                $mediaPath = !empty($mediaUrl) ? (str_starts_with($mediaUrl, 'media/') ? '../assets/' . $mediaUrl : '../assets/uploads/events/' . $mediaUrl) : '';
            ?>
            <div class="event-card">
                <!-- Multimedia Display Block -->
                <?php if (!empty($mediaUrl) && file_exists($mediaPath)): ?>
                    <div class="event-card-media">
                        <?php if (in_array($mediaExt, ['mp4', 'webm'])): ?>
                            <video controls preload="metadata">
                                <source src="<?= sanitize($mediaPath) ?>" type="video/<?= $mediaExt ?>">
                                Your browser does not support the video tag.
                            </video>
                        <?php elseif (in_array($mediaExt, ['mp3', 'wav', 'mpeg'])): ?>
                            <div style="padding: 1rem; background: var(--bg-card);">
                                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.2rem;">AUDIO PROMO:</label>
                                <audio controls preload="metadata" style="width:100%;">
                                    <source src="<?= sanitize($mediaPath) ?>" type="audio/<?= $mediaExt ?>">
                                    Your browser does not support the audio element.
                                </audio>
                            </div>
                        <?php elseif (in_array($mediaExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <img src="<?= sanitize($mediaPath) ?>" alt="<?= sanitize($e['title']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="event-card-body">
                    <div class="flex-between mb-1">
                        <span class="badge badge-<?= sanitize($e['event_type']) ?>"><?= sanitize($e['event_type']) ?></span>
                        <span class="card-meta">📍 <?= sanitize($e['location'] ?: 'Campus') ?></span>
                    </div>

                    <h3 class="card-title"><?= sanitize($e['title']) ?></h3>
                    <p class="card-meta">📅 <?= formatDate($e['event_date']) ?></p>
                    <p class="card-body mb-2"><?= nl2br(sanitize($e['description'])) ?></p>
                </div>

                <div class="event-card-footer">
                    <form method="POST" action="events.php<?= $selectedCategory !== 'all' ? '?category=' . urlencode($selectedCategory) : '' ?>">
                        <?php csrfField(); ?>
                        <input type="hidden" name="event_id" value="<?= $e['id'] ?>">

                        <?php if ($isRegistered): ?>
                            <div class="flex-between">
                                <span class="badge badge-attended">Registered ✅</span>
                                <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm" onclick="return confirm('Cancel registration for this event?');">
                                    Cancel Registration
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="action" value="register" class="btn btn-primary btn-full">
                                📝 Register Now
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
