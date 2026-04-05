<?php
/**
 * Instalegl — Submit Booking
 * Backward-compatible booking entrypoint.
 * OTP creation is now delegated to the shared sender service logic.
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

$name       = clean($body['name'] ?? '');
$phone      = normalizePhone(clean($body['phone'] ?? ''));
$email      = clean($body['email'] ?? '');
$service    = clean($body['service'] ?? '');
$requireOtp = true;

if (array_key_exists('require_otp', $body)) {
    $requireOtpRaw = $body['require_otp'];
    if (is_bool($requireOtpRaw)) {
        $requireOtp = $requireOtpRaw;
    } else {
        $requireOtp = !in_array(strtolower((string) $requireOtpRaw), ['0', 'false', 'no', 'off'], true);
    }
}

try {
    if ($requireOtp) {
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
    }

    $missing = validateRequired([
        'Full Name'    => $name,
        'Mobile Phone' => $phone,
        'Email'        => $email,
        'Service'      => $service,
    ]);
    if ($missing) {
        jsonResponse(false, "{$missing} is required.");
    }
    if (!validPhone($phone)) {
        jsonResponse(false, 'Please enter a valid mobile number.');
    }
    if (!validEmail($email)) {
        jsonResponse(false, 'Please enter a valid email address.');
    }

    $pdo = db();
    $tempRef = generateTempIdentifier('BKG');
    $serviceLabel = mapBookingServiceLabel($service);

    $stmt = $pdo->prepare(
        "INSERT INTO `bookings` (`ref_number`, `full_name`, `email`, `phone`, `service`, `status`, `ip_address`)
         VALUES (:ref, :name, :email, :phone, :service, 'new', :ip)"
    );
    $stmt->execute([
        ':ref'     => $tempRef,
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':service' => $serviceLabel,
        ':ip'      => getIP(),
    ]);

    $bookingId = (int) $pdo->lastInsertId();
    $ref = generateRef('IGL', $bookingId);

    $pdo->prepare("UPDATE `bookings` SET `ref_number` = :ref WHERE `id` = :id")
        ->execute([':ref' => $ref, ':id' => $bookingId]);

    jsonResponse(true, 'Booking received successfully.', [
        'ref'     => $ref,
        'message' => 'Your booking has been received. Our advocate will contact you soon.',
    ]);
} catch (Throwable $e) {
    error_log('Booking Submit Error: ' . $e->getMessage());
    if (defined('INSTALEGL_DEBUG') && INSTALEGL_DEBUG) {
        jsonResponse(false, 'Error processing booking. ' . $e->getMessage(), [], 500);
    }
    jsonResponse(false, 'Error processing booking. Please try again.', [], 500);
}
?>
