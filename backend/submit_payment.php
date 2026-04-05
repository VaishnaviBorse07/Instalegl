<?php
/**
 * Instalegl — Submit Payment Screenshot
 * Receives a QR payment screenshot from the client,
 * stores it, and marks the booking as payment_pending.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// ── Inputs ─────────────────────────────────────────────────
$ref     = clean($_POST['ref']     ?? '');
$amount  = (float) ($_POST['amount'] ?? 0);
$name    = clean($_POST['name']    ?? '');
$phone   = normalizePhone(clean($_POST['phone'] ?? ''));
$service = clean($_POST['service'] ?? '');

if (empty($ref)) {
    jsonResponse(false, 'Booking reference is required.');
}

// ── Upload screenshot ───────────────────────────────────────
if (empty($_FILES['screenshot']['tmp_name'])) {
    jsonResponse(false, 'Payment screenshot is required.');
}

$file    = $_FILES['screenshot'];
$maxSize = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxSize) {
    jsonResponse(false, 'Screenshot must be under 5 MB.');
}

$allowedExt  = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    jsonResponse(false, 'Invalid file type. Use JPG, PNG, WebP, or PDF.');
}

// MIME check
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) {
        jsonResponse(false, 'Invalid file type detected.');
    }
}

// Save to uploads/payment-screenshots/
$dir = dirname(__DIR__) . '/uploads/payment-screenshots/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    jsonResponse(false, 'Upload directory could not be created.', [], 500);
}

$filename  = 'pay_' . preg_replace('/[^a-zA-Z0-9\-]/', '', $ref) . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath  = $dir . $filename;
$relPath   = 'payment-screenshots/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(false, 'Failed to save screenshot. Please try again.', [], 500);
}

// Generate a transaction ID
$txnId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));

// ── Update booking in DB ────────────────────────────────────
try {
    $pdo = db();

    // Try to find existing booking by ref_number
    $stmt = $pdo->prepare(
        "SELECT `id`, `status` FROM `bookings` WHERE `ref_number` = ? LIMIT 1"
    );
    $stmt->execute([$ref]);
    $booking = $stmt->fetch();

    if ($booking) {
        // Update existing booking
        $pdo->prepare(
            "UPDATE `bookings`
             SET `payment_screenshot` = :sc,
                 `payment_txn_id`     = :txn,
                 `payment_amount`     = :amt,
                 `status`             = 'payment_pending'
             WHERE `id` = :id"
        )->execute([
            ':sc'  => $relPath,
            ':txn' => $txnId,
            ':amt' => $amount,
            ':id'  => $booking['id'],
        ]);
    } else {
        // No booking found with that ref — create a minimal record
        $pdo->prepare(
            "INSERT INTO `bookings`
             (`ref_number`, `full_name`, `phone`, `service`,
              `payment_screenshot`, `payment_txn_id`, `payment_amount`,
              `status`, `ip_address`)
             VALUES
             (:ref, :name, :phone, :service,
              :sc, :txn, :amt,
              'payment_pending', :ip)"
        )->execute([
            ':ref'     => $ref,
            ':name'    => $name,
            ':phone'   => $phone,
            ':service' => $service,
            ':sc'      => $relPath,
            ':txn'     => $txnId,
            ':amt'     => $amount,
            ':ip'      => getIP(),
        ]);
    }

    error_log("[Instalegl Payment] Ref: $ref | TXN: $txnId | Amount: $amount | Screenshot: $relPath");

    jsonResponse(true, 'Payment screenshot received. Booking is pending verification.', [
        'txn_id'  => $txnId,
        'ref'     => $ref,
        'message' => 'Our team will verify your payment within 30 minutes and confirm your booking.',
    ]);

} catch (PDOException $e) {
    error_log('Payment Submit DB Error: ' . $e->getMessage());
    jsonResponse(false, 'Error recording payment. Please contact support.', [], 500);
}
