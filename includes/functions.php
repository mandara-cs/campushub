<?php
// includes/functions.php

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Sanitize output (XSS prevention) ─────────────────────────
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ── Redirect helper ───────────────────────────────────────────
function redirect($url) {
    header("Location: $url");
    exit();
}

// ── Flash messages ────────────────────────────────────────────
// Set a flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Get and clear flash message (call once in header)
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Render flash message HTML
function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $type    = sanitize($flash['type']);    // success | error | warning | info
        $message = sanitize($flash['message']);
        echo "<div class=\"alert alert-{$type}\">{$message}</div>";
    }
}

// ── Auth guards ───────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access that page.');
        redirect('../index.php');
    }
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        setFlash('error', 'Access denied. Admins only.');
        redirect('../index.php');
    }
}

// ── Date formatting ───────────────────────────────────────────
function formatDate($datetime, $format = 'D, d M Y \a\t g:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

// ── File upload validation ────────────────────────────────────
// Returns ['valid' => bool, 'error' => string, 'ext' => string]
function validateFileUpload($file, $allowedTypes, $maxSizeBytes) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'error' => 'No file selected.', 'ext' => ''];
    }

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
        return ['valid' => false, 'error' => $msg, 'ext' => ''];
    }

    if ($file['size'] > $maxSizeBytes) {
        $mb = round($maxSizeBytes / 1048576, 1);
        return ['valid' => false, 'error' => "File too large. Max size: {$mb}MB.", 'ext' => ''];
    }

    // Verify MIME type using fileinfo (ignores spoofed extensions)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type.', 'ext' => ''];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    return ['valid' => true, 'error' => '', 'ext' => $ext];
}

// ── CSRF helpers ──────────────────────────────────────────────
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Render hidden CSRF input field
function csrfField() {
    $token = generateCsrfToken();
    echo "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}
?>
