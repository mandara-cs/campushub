<?php
// admin/manage_registrations.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Manage Registrations';
$errors = [];

// ── Process Attendance Status Update ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request security token.';
    } else {
        $registrationId    = intval($_POST['registration_id'] ?? 0);
        $attendance_status = $_POST['attendance_status'] ?? 'registered';

        // Validate state values
        if (!in_array($attendance_status, ['registered', 'attended', 'absent'])) {
            $errors[] = 'Invalid status parameter value.';
        }

        if (empty($errors) && $registrationId > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE registrations SET attendance_status = ? WHERE id = ?");
                $stmt->execute([$attendance_status, $registrationId]);
                setFlash('success', 'Attendance status updated successfully!');
                redirect('manage_registrations.php');
            } catch (PDOException $e) {
                error_log("Failed to update registration status: " . $e->getMessage());
                $errors[] = 'Database error occurred while updating status.';
            }
        }
    }
}

// Fetch all registrations with student name and activity details
$registrations = [];
try {
    $registrations = $pdo->query("
        SELECT r.*, u.full_name, u.email, e.title AS event_title, e.event_date 
        FROM registrations r
        JOIN users u ON r.student_id = u.id 
        JOIN events e ON r.event_id = e.id 
        ORDER BY e.event_date DESC, r.registration_date DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch registrations: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Track Registrations & Attendance</h1>
        <p>Monitor student sign-ups for campus activities and update their final attendance status.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?= sanitize($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Email Address</th>
                <th>Activity Title</th>
                <th>Activity Date</th>
                <th>Registered On</th>
                <th>Attendance Status</th>
                <th class="text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registrations)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No registrations logged yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($registrations as $r): ?>
                    <tr>
                        <td><strong><?= sanitize($r['full_name']) ?></strong></td>
                        <td><?= sanitize($r['email']) ?></td>
                        <td><?= sanitize($r['event_title']) ?></td>
                        <td><?= formatDate($r['event_date'], 'd M Y') ?></td>
                        <td><?= formatDate($r['registration_date'], 'd M Y') ?></td>
                        <td>
                            <span class="badge badge-<?= sanitize($r['attendance_status']) ?>">
                                <?= sanitize($r['attendance_status']) ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <form method="POST" action="manage_registrations.php" style="display:inline-flex; align-items:center; gap:0.5rem;">
                                <?php csrfField(); ?>
                                <input type="hidden" name="registration_id" value="<?= $r['id'] ?>">
                                
                                <select name="attendance_status" style="padding: 0.35rem 0.5rem; font-size: 0.8rem; width: auto; min-width: 110px;">
                                    <option value="registered" <?= $r['attendance_status'] === 'registered' ? 'selected' : '' ?>>Registered</option>
                                    <option value="attended" <?= $r['attendance_status'] === 'attended' ? 'selected' : '' ?>>Attended</option>
                                    <option value="absent" <?= $r['attendance_status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                                </select>
                                
                                <button type="submit" class="btn btn-outline btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
