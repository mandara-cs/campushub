<?php
// admin/export_xml.php
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
requireAdmin();

$pageTitle = 'Export Data to XML';
$xmlStatus = '';
$xmlDownloadLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_xml'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid validation token.');
    } else {
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            $root = $dom->createElement('campushub');
            $root->setAttribute('exported_at', date('c'));
            $dom->appendChild($root);

            $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
            $events = $stmt->fetchAll();

            foreach ($events as $e) {
                $eventNode = $dom->createElement('event');
                $eventNode->setAttribute('id', $e['id']);

                $eventNode->appendChild($dom->createElement('title', htmlspecialchars($e['title'])));
                $eventNode->appendChild($dom->createElement('type', htmlspecialchars($e['event_type'])));
                $eventNode->appendChild($dom->createElement('description', htmlspecialchars($e['description'])));
                $eventNode->appendChild($dom->createElement('date', htmlspecialchars($e['event_date'])));
                $eventNode->appendChild($dom->createElement('location', htmlspecialchars($e['location'] ?: 'Online')));

                // Fetch registrations
                $regStmt = $pdo->prepare("
                    SELECT r.*, u.full_name, u.email 
                    FROM registrations r 
                    JOIN users u ON r.student_id = u.id 
                    WHERE r.event_id = ?
                ");
                $regStmt->execute([$e['id']]);
                $regs = $regStmt->fetchAll();

                $regsNode = $dom->createElement('registrations');
                $regsNode->setAttribute('count', count($regs));

                foreach ($regs as $r) {
                    $regNode = $dom->createElement('registration');
                    $regNode->appendChild($dom->createElement('student_name', htmlspecialchars($r['full_name'])));
                    $regNode->appendChild($dom->createElement('student_email', htmlspecialchars($r['email'])));
                    $regNode->appendChild($dom->createElement('status', htmlspecialchars($r['attendance_status'])));
                    $regsNode->appendChild($regNode);
                }

                $eventNode->appendChild($regsNode);
                $root->appendChild($eventNode);
            }

            $xmlFilePath = '../xml/latest_export.xml';
            $dom->save($xmlFilePath);

            // Validate against XSD
            if ($dom->schemaValidate('../xml/events.xsd')) {
                $xmlStatus = '<div class="alert alert-success">✅ XML generated and successfully validated against XSD (<code>xml/events.xsd</code>)!</div>';
                $xmlDownloadLink = $xmlFilePath;
            } else {
                $xmlStatus = '<div class="alert alert-error">❌ XML generated but failed XSD validation!</div>';
            }

        } catch (Exception $e) {
            error_log("XML Generation Error: " . $e->getMessage());
            setFlash('error', 'Failed to generate XML.');
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Export System Data to XML</h1>
        <p>Generate an XML feed of all events and their student registrations, automatically validated against the XSD schema.</p>
    </div>
</div>

<?php showFlash(); ?>

<div class="card" style="max-width: 600px;">
    <h3 class="mb-2">XML Generator</h3>
    <p class="text-muted mb-2">
        This tool will query the database, generate a structured XML file containing all events and associated registrations, and validate it using the defined XML Schema (XSD).
    </p>

    <?= $xmlStatus ?>

    <form method="POST" action="export_xml.php">
        <?php csrfField(); ?>
        <button type="submit" name="export_xml" class="btn btn-primary">
            ⚙️ Generate & Validate XML
        </button>
        
        <?php if (!empty($xmlDownloadLink)): ?>
            <a href="<?= $xmlDownloadLink ?>" target="_blank" class="btn btn-success" style="margin-left: 0.5rem;">
                📥 Download XML
            </a>
        <?php endif; ?>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
