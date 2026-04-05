<?php
/**
 * Instalegl — Submit Contact Message
 * Handles POST from contact.html.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$name    = clean($body['name']    ?? '');
$email   = clean($body['email']   ?? '');
$phoneRaw = clean($body['phone'] ?? '');
$phone   = $phoneRaw !== '' ? normalizePhone($phoneRaw) : '';
$subject = clean($body['subject'] ?? '');
$message = clean($body['message'] ?? '');

// ── Validation ──────────────────────────────────────────────
$missing = validateRequired([
    'Full Name' => $name,
    'Email'     => $email,
    'Subject'   => $subject,
    'Message'   => $message,
]);
if ($missing) {
    jsonResponse(false, "{$missing} is required.");
}
if (!validEmail($email)) {
    jsonResponse(false, 'Please enter a valid email address.');
}
if ($phone !== '' && !validPhone($phone)) {
    jsonResponse(false, 'Please enter a valid mobile number.');
}
if (strlen($message) < 10) {
    jsonResponse(false, 'Message is too short. Please provide more detail.');
}

$result = createContactOtpRequest($name, $phone, $email, $subject, $message);
if (!$result['success']) {
    jsonResponse(false, $result['message'], [], (int) ($result['code'] ?? 500));
}

jsonResponse(true, $result['message'], [
    'email'   => $result['email'] ?? maskEmail($email),
    'message' => 'Enter the 6-digit code sent to your email address to verify your message.',
]);
