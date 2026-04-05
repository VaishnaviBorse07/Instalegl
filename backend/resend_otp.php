<?php
/**
 * Instalegl — Resend OTP
 * Looks up the email stored against this phone and resends the OTP via email.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$phone = normalizePhone(clean($body['phone'] ?? ''));
$name  = clean($body['name']  ?? 'there');

// ── Validation ──────────────────────────────────────────────
if (empty($phone)) {
    jsonResponse(false, 'Phone number is required.');
}

if (!validPhone($phone)) {
    jsonResponse(false, 'Invalid phone number.');
}

// ── Rate-limit: max 3 resends within the OTP window ──────────
try {
    $pdo = db();
    $resendWindowMinutes = (int) OTP_EXPIRY_MINUTES;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `otp_verifications`
         WHERE `phone` = ? AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$resendWindowMinutes} MINUTE)"
    );
    $stmt->execute([$phone]);
    $recentOtpCount = (int) $stmt->fetchColumn();

    if ($recentOtpCount >= 3) {
        jsonResponse(false, 'Too many resend attempts. Please wait before requesting again.');
    }

    // Look up the email linked to this phone
    $stmt = $pdo->prepare(
        "SELECT `email` FROM `otp_verifications`
         WHERE `phone` = ? AND `is_verified` = 0
         ORDER BY `created_at` DESC LIMIT 1"
    );
    $stmt->execute([$phone]);
    $row = $stmt->fetch();

    $email = $row['email'] ?? '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'No email address found for this number. Please restart the booking.');
    }

} catch (PDOException $e) {
    error_log("Resend OTP Check Error: " . $e->getMessage());
    jsonResponse(false, 'Error processing request.', [], 500);
}

// ── Generate and send new OTP ────────────────────────────────
$otpResult = storeAndSendOTP($phone, $email, $name);

if ($otpResult['success']) {
    jsonResponse(true, 'OTP resent to your email.', [
        'email'   => maskEmail($email),
        'message' => 'Enter the 6-digit code sent to your email address',
    ]);
} else {
    jsonResponse(false, $otpResult['message'], [], 500);
}
?>
