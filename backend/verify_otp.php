<?php
/**
 * Instalegl — OTP Verification
 * Verifies OTP and completes booking, contact, or advocate application.
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
$otp   = clean($body['otp']     ?? '');
$type  = clean($body['type']    ?? 'booking');  // booking, contact, advocate

// ── Validation ──────────────────────────────────────────────
if (empty($phone) || empty($otp)) {
    jsonResponse(false, 'Phone and OTP are required.');
}

if (!validPhone($phone)) {
    jsonResponse(false, 'Invalid phone number.');
}

if (strlen($otp) !== OTP_LENGTH) {
    jsonResponse(false, 'Invalid OTP format.');
}

// ── Verify OTP ───────────────────────────────────────────────
$verification = verifyOTP($phone, $otp);
if (!$verification['success']) {
    jsonResponse(false, $verification['message']);
}

// ── Update based on type ─────────────────────────────────────
try {
    $pdo = db();
    
    if ($type === 'booking') {
        // Find and mark booking as verified
        $stmt = $pdo->prepare(
            "SELECT `id` FROM `bookings` WHERE `phone` = ? AND `status` = 'otp_pending' ORDER BY `created_at` DESC LIMIT 1"
        );
        $stmt->execute([$phone]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            jsonResponse(false, 'No pending booking found.');
        }
        
        $id  = (int) $booking['id'];
        
        // Fetch booking details including email
        $stmt = $pdo->prepare("SELECT `full_name`, `email`, `phone`, `service` FROM `bookings` WHERE `id` = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch();
        
        $ref = generateRef('IGL', $id);
        $pdo->prepare("UPDATE `bookings` SET `ref_number` = :ref, `status` = 'new' WHERE `id` = :id")
            ->execute([':ref' => $ref, ':id' => $id]);

        // ── Email Notifications ───────────────────────────────────
        if ($details && !empty($details['email'])) {
            $userEmail = $details['email'];
            $userName  = $details['full_name'];
            $service   = $details['service'];
            
            // To User
            $userTitle   = "Booking Confirmed — $ref";
            $userMessage = "
                <p>Hello {$userName},</p>
                <p>Your booking for <strong>{$service}</strong> has been successfully confirmed. A verified advocate will contact you within the next 30 minutes to discuss your matter.</p>
                <div class='info-box'>
                    <p><strong>Reference Number:</strong> {$ref}</p>
                    <p><strong>Service:</strong> {$service}</p>
                </div>
                <p>Thank you for choosing Instalegl.</p>
            ";
            sendEmail($userEmail, $userTitle, getPremiumEmailTemplate($userTitle, $userMessage));

            // To Admin
            $adminTitle = "New Verified Booking — $ref";
            $adminContent = "
                <p>A new booking has been verified and confirmed.</p>
                <div class='info-box'>
                    <p><strong>Ref:</strong> {$ref}</p>
                    <p><strong>Client:</strong> {$userName}</p>
                    <p><strong>Phone:</strong> {$details['phone']}</p>
                    <p><strong>Email:</strong> {$userEmail}</p>
                    <p><strong>Service:</strong> {$service}</p>
                </div>
            ";
            sendEmail(ADMIN_EMAIL, $adminTitle, getPremiumEmailTemplate($adminTitle, $adminContent));
        }

        jsonResponse(true, 'Booking verified successfully.', [
            'ref'     => $ref,
            'message' => 'Your booking has been confirmed. Our advocate will contact you soon.',
        ]);
    }
    elseif ($type === 'contact') {
        // Find and mark contact message as verified
        $stmt = $pdo->prepare(
            "SELECT `id` FROM `contact_messages` WHERE `phone` = ? AND `status` = 'otp_pending' ORDER BY `created_at` DESC LIMIT 1"
        );
        $stmt->execute([$phone]);
        $contact = $stmt->fetch();
        
        if (!$contact) {
            jsonResponse(false, 'No pending message found.');
        }
        
        $id = (int) $contact['id'];
        
        $pdo->prepare("UPDATE `contact_messages` SET `status` = 'new' WHERE `id` = :id")
            ->execute([':id' => $id]);
        
        jsonResponse(true, 'Message verified successfully.', [
            'message' => 'Your message has been sent. We will respond within 4 business hours.',
        ]);
    }
    elseif ($type === 'advocate') {
        // New flow: OTP can be verified before the multipart application is uploaded.
        // Keep backward compatibility with any older pending advocate records.
        $stmt = $pdo->prepare(
            "SELECT `id` FROM `advocate_applications` WHERE `phone` = ? AND `status` = 'otp_pending' ORDER BY `created_at` DESC LIMIT 1"
        );
        $stmt->execute([$phone]);
        $advocate = $stmt->fetch();
        
        if (!$advocate) {
            jsonResponse(true, 'OTP verified successfully.', [
                'message' => 'OTP verified. You can now submit your application.',
            ]);
        }
        
        $id    = (int) $advocate['id'];
        $appId = generateRef('ADV', $id);
        
        $pdo->prepare("UPDATE `advocate_applications` SET `app_id` = :app_id, `status` = 'pending' WHERE `id` = :id")
            ->execute([':app_id' => $appId, ':id' => $id]);
        
        jsonResponse(true, 'Application verified successfully.', [
            'app_id'  => $appId,
            'message' => 'Your application has been received. Our team will review and contact you within 2-3 business days.',
        ]);
    }
    else {
        jsonResponse(false, 'Invalid type.');
    }

} catch (PDOException $e) {
    error_log("OTP Verification DB Error: " . $e->getMessage());
    jsonResponse(false, 'Verification failed. Please try again.', [], 500);
}
?>
