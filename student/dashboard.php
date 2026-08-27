<?php
// student/dashboard.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle = 'Student Dashboard';
$studentId = $_SESSION['user_id'];

try {
    // 1. My Registrations Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $myRegCount = $stmt->fetchColumn();

    // 2. Total Upcoming Events Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= NOW()");
    $upcomingEventsCount = $stmt->fetchColumn();

    // 3. My Submissions Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_submissions WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $mySubmissionsCount = $stmt->fetchColumn();

    // 4. Fetch My Registered Events with event details
    $stmt = $pdo->prepare("
        SELECT r.*, e.title AS event_title, e.event_date, e.location, e.event_type 
        FROM registrations r 
        JOIN events e ON r.event_id = e.id 
        WHERE r.student_id = ? 
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$studentId]);
    $myRegistrations = $stmt->fetchAll();

    // 5. Fetch Top 3 Recent Announcements
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetchAll();

} catch (PDOException $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    setFlash('error', 'Error loading dashboard data.');
    $myRegCount = $upcomingEventsCount = $mySubmissionsCount = 0;
    $myRegistrations = $announcements = [];
}

include '../includes/header.php';
?>

<div class="dashboard-welcome">
    <h1>👋 Welcome back, <?= sanitize($_SESSION['full_name']) ?>!</h1>
    <p>Explore upcoming activities, track your event registrations, and post your personal campus activity updates.</p>
</div>

<!-- Stats Grid -->
<div class="grid-3 mb-3">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">📝</div>
        <div>
            <div class="stat-number"><?= $myRegCount ?></div>
            <div class="stat-label">My Registered Events</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">📅</div>
        <div>
            <div class="stat-number"><?= $upcomingEventsCount ?></div>
            <div class="stat-label">Upcoming Activities</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-cyan">📤</div>
        <div>
            <div class="stat-number"><?= $mySubmissionsCount ?></div>
            <div class="stat-label">My Activity Updates</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- My Registrations -->
    <div class="card">
        <div class="flex-between mb-2">
            <h3>My Event Registrations</h3>
            <a href="events.php" class="btn btn-outline btn-sm">Browse All Events</a>
        </div>

        <?php if (empty($myRegistrations)): ?>
            <p class="text-muted">You haven't registered for any events yet. Check out the <a href="events.php">Events section</a> to sign up!</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myRegistrations as $reg): ?>
                            <tr>
                                <td><strong><?= sanitize($reg['event_title']) ?></strong></td>
                                <td><span class="badge badge-<?= sanitize($reg['event_type']) ?>"><?= sanitize($reg['event_type']) ?></span></td>
                                <td><?= formatDate($reg['event_date'], 'd M Y') ?></td>
                                <td><span class="badge badge-<?= sanitize($reg['attendance_status']) ?>"><?= sanitize($reg['attendance_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Announcements -->
    <div class="card">
        <h3 class="mb-2">📢 Recent Campus Announcements</h3>
        <?php if (empty($announcements)): ?>
            <p class="text-muted">No announcements posted at this time.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($announcements as $ann): ?>
                    <div style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                        <h4 style="color: var(--primary-light); margin-bottom: 0.2rem;"><?= sanitize($ann['title']) ?></h4>
                        <span class="text-muted" style="font-size: 0.75rem; display: block; margin-bottom: 0.4rem;"><?= formatDate($ann['created_at']) ?></span>
                        <p style="font-size: 0.875rem; color: var(--text);"><?= nl2br(sanitize($ann['content'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
