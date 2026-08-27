<?php
// admin/dashboard.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Admin Dashboard';

// Fetch stats
try {
    // Student Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $studentCount = $stmt->fetchColumn();

    // Event Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $eventCount = $stmt->fetchColumn();

    // Registration Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM registrations");
    $regCount = $stmt->fetchColumn();

    // Student Submissions Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM student_submissions");
    $submissionCount = $stmt->fetchColumn();

    // Fetch 5 Recent Student Submissions with Student Names
    $recentSubmissions = $pdo->query("
        SELECT s.*, u.full_name 
        FROM student_submissions s 
        JOIN users u ON s.student_id = u.id 
        ORDER BY s.submitted_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Fetch 5 Recent Registrations
    $recentRegistrations = $pdo->query("
        SELECT r.*, u.full_name, e.title AS event_title 
        FROM registrations r
        JOIN users u ON r.student_id = u.id 
        JOIN events e ON r.event_id = e.id 
        ORDER BY r.registration_date DESC 
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard query failed: " . $e->getMessage());
    setFlash('error', 'Error loading dashboard statistics.');
    $studentCount = $eventCount = $regCount = $submissionCount = 0;
    $recentSubmissions = $recentRegistrations = [];
}

include '../includes/header.php';
?>

<div class="dashboard-welcome">
    <h1>🎓 CampusHub Admin Panel</h1>
    <p>System Administrator Dashboard. Manage event activities, student updates, and site parameters.</p>
</div>

<!-- Stats Grid -->
<div class="grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">👥</div>
        <div>
            <div class="stat-number"><?= $studentCount ?></div>
            <div class="stat-label">Total Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">📅</div>
        <div>
            <div class="stat-number"><?= $eventCount ?></div>
            <div class="stat-label">Total Activities</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-yellow">📝</div>
        <div>
            <div class="stat-number"><?= $regCount ?></div>
            <div class="stat-label">Registrations</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-cyan">📤</div>
        <div>
            <div class="stat-number"><?= $submissionCount ?></div>
            <div class="stat-label">Submissions</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Recent Activity Submissions -->
    <div class="card">
        <h3 class="mb-2">Recent Student Submissions</h3>
        <?php if (empty($recentSubmissions)): ?>
            <p class="text-muted">No student submissions yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSubmissions as $sub): ?>
                            <tr>
                                <td><strong><?= sanitize($sub['full_name']) ?></strong></td>
                                <td><?= sanitize($sub['submission_title']) ?></td>
                                <td><?= formatDate($sub['submitted_at'], 'd M Y') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Registrations -->
    <div class="card">
        <h3 class="mb-2">Recent Event Registrations</h3>
        <?php if (empty($recentRegistrations)): ?>
            <p class="text-muted">No registrations logged yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRegistrations as $reg): ?>
                            <tr>
                                <td><strong><?= sanitize($reg['full_name']) ?></strong></td>
                                <td><?= sanitize($reg['event_title']) ?></td>
                                <td>
                                    <span class="badge badge-<?= sanitize($reg['attendance_status']) ?>">
                                        <?= sanitize($reg['attendance_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
