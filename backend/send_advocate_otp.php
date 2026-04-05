<?php
/**
 * Instalegl - Advocate OTP Sender
 * Sends an email OTP before final advocate application submission.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$name = clean($body['name'] ?? '');
$phone = normalizePhone(clean($body['phone'] ?? ''));
$email = clean($body['email'] ?? '');

$missing = validateRequired([
    'Full Name' => $name,
    'Mobile Phone' => $phone,
    'Email' => $email,
]);
if ($missing) {
    jsonResponse(false, "{$missing} is required.", [], 422);
}
if (!validPhone($phone)) {
    jsonResponse(false, 'Please enter a valid mobile number.', [], 422);
}
if (!validEmail($email)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

$result = storeAndSendOTP($phone, $email, $name);
if (!$result['success']) {
    jsonResponse(false, $result['message'], [], 500);
}

jsonResponse(true, 'OTP sent to your email. Please verify before submitting.', [
    'email' => $result['email'] ?? maskEmail($email),
    'message' => 'Enter the 6-digit code sent to your email address',
]);
