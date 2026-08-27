<?php
// student/contact.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireLogin();

$pageTitle = 'Contact Us & Organizational Info';
$successMsg = '';

// Fetch organization details from database
try {
    $stmt = $pdo->query("SELECT * FROM organization_info ORDER BY id ASC LIMIT 1");
    $org = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Failed to fetch org info: " . $e->getMessage());
    $org = false;
}

// Process student online contact form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid validation token.');
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($subject) || empty($message)) {
            setFlash('error', 'Subject and message fields are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_inquiries (student_id, subject, message) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $subject, $message]);
                setFlash('success', 'Thank you for reaching out! Your message has been submitted to CampusHub administrators.');
            } catch (PDOException $e) {
                error_log("Failed to insert contact inquiry: " . $e->getMessage());
                setFlash('error', 'A database error occurred. Please try again.');
            }
            redirect('contact.php');
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Organizational Information & Contact</h1>
        <p>Learn more about CampusHub or reach out directly to the organizational committee.</p>
    </div>
</div>

<div class="grid-2">
    <!-- Organizational Info -->
    <div class="card">
        <h3 class="mb-1">🏛️ <?= sanitize($org['org_name'] ?? 'CampusHub Central') ?></h3>
        <p class="text-muted mb-2">Connecting Students, Empowering Communities</p>

        <div style="margin-bottom: 1.5rem;">
            <h4 style="color: var(--primary-light); margin-bottom: 0.4rem;">About CampusHub</h4>
            <p style="font-size: 0.9rem; color: var(--text); line-height: 1.6;">
                <?= nl2br(sanitize($org['about_text'] ?? 'CampusHub is a student services organisation coordinating student clubs, events, workshops, competitions, and sports activities across institutions.')) ?>
            </p>
        </div>

        <div>
            <h4 style="color: var(--primary-light); margin-bottom: 0.4rem;">Official Channels</h4>
            <p style="font-size: 0.9rem;">📧 Email: <strong><?= sanitize($org['contact_email'] ?? 'hello@campushub.com') ?></strong></p>
            <p style="font-size: 0.9rem;">📞 Phone: <strong>+94 11 234 5678</strong></p>
            <p style="font-size: 0.9rem;">📍 Location: <strong>42 University Avenue, Colombo 07, Sri Lanka</strong></p>
        </div>
    </div>

    <!-- Contact Form -->
    <div class="card">
        <h3 class="mb-2">✉️ Online Inquiry Form</h3>
        <form method="POST" action="contact.php">
            <?php csrfField(); ?>

            <div class="form-group">
                <label for="name">Your Name</label>
                <input 
                    type="text" 
                    id="name" 
                    value="<?= sanitize($_SESSION['full_name']) ?>" 
                    disabled
                >
            </div>

            <div class="form-group">
                <label for="email">Your Email</label>
                <input 
                    type="email" 
                    id="email" 
                    value="<?= sanitize($_SESSION['email'] ?? 'student@campushub.com') ?>" 
                    disabled
                >
            </div>

            <div class="form-group">
                <label for="subject">Subject / Inquiry Title</label>
                <input 
                    type="text" 
                    id="subject" 
                    name="subject" 
                    placeholder="e.g. Question regarding Hackathon venue"
                    required
                >
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea 
                    id="message" 
                    name="message" 
                    rows="5" 
                    placeholder="Type your message or feedback here..."
                    required
                ></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                📤 Submit Message
            </button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
