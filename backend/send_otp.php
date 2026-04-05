<?php
/**
 * Instalegl — OTP Sender Service
 * Generates a fresh random OTP on the backend for booking flows
 * and sends it to the client's email address.
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

$name    = clean($body['name'] ?? '');
$phone   = normalizePhone(clean($body['phone'] ?? ''));
$email   = clean($body['email'] ?? '');
$service = clean($body['service'] ?? '');

if (!rateLimit('booking_otp:' . getIP(), 10, 3600)) {
    jsonResponse(false, 'Too many OTP requests from this IP. Please try again in an hour.', [], 429);
}

$result = createBookingOtpRequest($name, $phone, $email, $service);

if (!$result['success']) {
    jsonResponse(false, $result['message'], [], (int) ($result['code'] ?? 500));
}

jsonResponse(true, $result['message'], [
    'email'   => $result['email'] ?? maskEmail($email),
    'message' => 'Enter the 6-digit code sent to your email address',
]);
?>
