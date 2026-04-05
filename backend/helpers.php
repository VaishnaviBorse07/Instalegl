<?php
/**
 * Instalegl — Shared Helper Functions
 */

/**
 * Send JSON response and exit.
 */
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Sanitize a string input.
 */
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize a phone number to E.164-like format.
 * Defaults bare 10-digit Indian numbers to +91.
 */
function normalizePhone(string $phone): string {
    $trimmed = trim($phone);
    $digits  = preg_replace('/\D+/', '', $trimmed);

    if ($digits === '') {
        return '';
    }

    if (strpos($trimmed, '+') === 0) {
        return '+' . $digits;
    }

    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }

    if (strlen($digits) === 11 && $digits[0] === '0') {
        return '+91' . substr($digits, 1);
    }

    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        return '+' . $digits;
    }

    return '+' . $digits;
}

/**
 * Mask a phone number for UI responses.
 */
function maskPhone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    $length = strlen($digits);

    if ($length <= 4) {
        return str_repeat('X', max(0, $length - 2)) . substr($digits, -2);
    }

    return substr($digits, 0, 2) . 'XXXXX' . substr($digits, -3);
}

/**
 * Generate a short unique placeholder ID for pending records.
 */
function generateTempIdentifier(string $prefix = 'TMP'): string {
    return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Store the last SMS error for downstream responses.
 */
function setSmsError(string $message): void {
    $GLOBALS['instalegl_sms_error'] = $message;
}

/**
 * Read the last SMS error.
 */
function getSmsError(): string {
    return $GLOBALS['instalegl_sms_error'] ?? 'Unable to send OTP right now.';
}

/**
 * Mask an email address for safe UI display.
 * e.g. john@example.com → jo**@example.com
 */
function maskEmail(string $email): string {
    if (!str_contains($email, '@')) return '***';
    [$local, $domain] = explode('@', $email, 2);
    $visible = min(2, strlen($local));
    return substr($local, 0, $visible) . str_repeat('*', max(2, strlen($local) - $visible)) . '@' . $domain;
}

/**
 * Generate a random OTP (6 digits by default).
 */
function generateOTP(int $length = OTP_LENGTH): string {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Send SMS via Brevo (only supported provider).
 */
function sendSMS(string $phone, string $message): bool {
    setSmsError('Unable to send OTP right now.');
    return sendSMSViaBrevo($phone, $message);
}

/**
 * Send OTP SMS via Brevo (Sendinblue) Transactional SMS API.
 */
function sendSMSViaBrevo(string $phone, string $message): bool {
    if (empty(BREVO_API_KEY) || BREVO_API_KEY === 'your_brevo_api_key') {
        setSmsError('Brevo API key is not configured on the server.');
        error_log("SMS not sent to $phone: Brevo API key missing.");
        return false;
    }

    if (!preg_match('/^\+\d{10,15}$/', $phone)) {
        setSmsError('Please enter a valid mobile number with country code.');
        return false;
    }

    if (!function_exists('curl_init')) {
        setSmsError('cURL is not enabled on the server, so OTP SMS cannot be sent.');
        error_log('Brevo SMS Error: cURL extension is not available.');
        return false;
    }

    $payload = json_encode([
        'sender'    => BREVO_SENDER_NAME,
        'recipient' => $phone,
        'content'   => $message,
        'type'      => 'transactional',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.brevo.com/v3/transactionalSMS/sms',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        setSmsError('Unable to reach the SMS gateway. Please try again.');
        error_log("Brevo SMS cURL Error: $curlError");
        return false;
    }

    $data = json_decode($response, true);

    // Brevo returns 201 on success
    if ($httpCode < 200 || $httpCode >= 300) {
        $errMsg = $data['message'] ?? 'Brevo rejected the OTP request.';
        setSmsError($errMsg);
        error_log("Brevo SMS Error [{$httpCode}]: $errMsg — Response: $response");
        return false;
    }

    return true;
}

/**
 * Send Transactional Email via Brevo API (v3).
 * Focuses on ease of use for the application.
 */
function sendEmail(string $to, string $subject, string $htmlContent, array $params = []): bool {
    return sendEmailViaBrevo($to, $subject, $htmlContent, $params);
}

/**
 * Core implementation for Brevo Transactional Emails.
 */
function sendEmailViaBrevo(string $to, string $subject, string $htmlContent, array $params = []): bool {
    if (BREVO_API_KEY === '') {
        error_log('Brevo API key is not configured. Falling back to PHP mail.');
        return sendEmailViaPhpMail($to, $subject, $htmlContent);
    }

    if (!function_exists('curl_init')) {
        error_log('cURL is not available. Falling back to PHP mail.');
        return sendEmailViaPhpMail($to, $subject, $htmlContent);
    }

    $payload = [
        'sender' => [
            'name' => BREVO_SENDER_NAME,
            'email' => BREVO_SENDER_EMAIL,
        ],
        'to' => [[ 'email' => $to ]],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
    ];

    if (!empty($params['replyTo']) && filter_var($params['replyTo'], FILTER_VALIDATE_EMAIL)) {
        $payload['replyTo'] = [
            'email' => $params['replyTo'],
        ];
        if (!empty($params['replyName'])) {
            $payload['replyTo']['name'] = $params['replyName'];
        }
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'api-key: ' . BREVO_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log('Brevo API request failed: ' . $curlErr);
        return sendEmailViaPhpMail($to, $subject, $htmlContent);
    }

    $decoded = json_decode($response, true);
    if ($status >= 200 && $status < 300 && is_array($decoded) && isset($decoded['messageId'])) {
        return true;
    }

    $message = '';
    if (is_array($decoded)) {
        if (!empty($decoded['message'])) {
            $message = $decoded['message'];
        } elseif (!empty($decoded['errors'])) {
            $message = json_encode($decoded['errors']);
        }
    }

    error_log(sprintf('Brevo API error (%s): %s | payload: %s', $status, $message ?: $response, json_encode($payload)));
    return sendEmailViaPhpMail($to, $subject, $htmlContent);
}

/**
 * Last-resort mail transport for hosts with built-in mail delivery.
 */
function sendEmailViaPhpMail(string $to, string $subject, string $htmlContent): bool {
    if (!function_exists('mail')) {
        error_log('PHP mail() is not available on this server.');
        return false;
    }

    $from = defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : 'support@instalegl.in';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: Instalegl <{$from}>",
        "Reply-To: {$from}",
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = @mail($to, $encodedSubject, $htmlContent, implode("\r\n", $headers));
    if (!$sent) {
        error_log("PHP mail() failed for {$to}");
    }

    return $sent;
}

/**
 * Returns a premium, branded HTML wrapper for transactional emails.
 */
function getPremiumEmailTemplate(string $title, string $content): string {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: sans-serif; line-height: 1.6; color: #1e293b; margin: 0; padding: 0; background: #f8fafc; }
            .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(10, 13, 24, 0.05); }
            .header { background: linear-gradient(135deg, #046C4E, #059669); padding: 32px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
            .content { padding: 40px 32px; }
            .content h2 { color: #0f172a; font-size: 18px; margin-top: 0; font-weight: 700; }
            .content p { margin-bottom: 16px; color: #475569; }
            .info-box { background: #f1f5f9; border-radius: 12px; padding: 20px; margin-top: 24px; border-left: 4px solid #10B981; }
            .footer { background: #0f172a; padding: 24px; text-align: center; color: #a0aec8; font-size: 11px; }
            .footer p { margin: 4px 0; }
            .footer a { color: #10B981; text-decoration: none; }
            .btn { display: inline-block; padding: 12px 24px; background: #10B981; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 16px; transition: all 0.2s ease; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Instalegl</h1>
            </div>
            <div class='content'>
                <h2>{$title}</h2>
                {$content}
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Instalegl · Tramarkify Business LLC</p>
                <p>Registered India · <a href='https://instalegl.in/terms.html'>Terms</a> · <a href='https://instalegl.in/privacy.html'>Privacy</a></p>
                <p>Support: <a href='mailto:support@instalegl.in'>support@instalegl.in</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}


/**
 * Send a branded OTP verification email via Brevo SMTP.
 */
function sendOTPEmail(string $toEmail, string $otp, string $name = 'there'): bool {
    $expiry  = OTP_EXPIRY_MINUTES;
    $subject = 'Your Instalegl Verification Code — ' . $otp;

    error_log("[Instalegl OTP] Sending OTP email to: {$toEmail} | OTP: {$otp}");

    $content = "
        <p>Hello {$name},</p>
        <p>Use the verification code below to complete your booking on Instalegl. This code is valid for <strong>{$expiry} minutes</strong>.</p>
        <div style='text-align:center;margin:32px 0;'>
            <div style='display:inline-block;background:#0f172a;border:2px solid #10B981;border-radius:14px;padding:20px 40px;'>
                <span style='font-size:36px;font-weight:800;letter-spacing:12px;color:#10B981;font-family:monospace;'>{$otp}</span>
            </div>
        </div>
        <p style='font-size:13px;color:#64748b;'>If you did not request this, please ignore this email. Do not share this code with anyone — Instalegl will never ask for it.</p>
        <div class='info-box'>
            <p><strong>Security tip:</strong> This code expires in {$expiry} minutes and can only be used once.</p>
        </div>
    ";

    $html = getPremiumEmailTemplate('Verify Your Identity', $content);
    return sendEmail($toEmail, $subject, $html);
}

/**
 * Ensure the OTP table exists and supports the current booking flow schema.
 */
function ensureOtpVerificationSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `otp_verifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(180) NULL,
            `otp_code` VARCHAR(6) NOT NULL,
            `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `used_at` DATETIME NULL,
            `attempts` INT NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_phone` (`phone`),
            INDEX `idx_email` (`email`),
            INDEX `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $requiredColumns = [
        'email' => "ALTER TABLE `otp_verifications` ADD COLUMN `email` VARCHAR(180) NULL AFTER `phone`",
        'otp_code' => "ALTER TABLE `otp_verifications` ADD COLUMN `otp_code` VARCHAR(6) NOT NULL AFTER `email`",
        'is_verified' => "ALTER TABLE `otp_verifications` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `otp_code`",
        'attempts' => "ALTER TABLE `otp_verifications` ADD COLUMN `attempts` INT NOT NULL DEFAULT 0 AFTER `is_verified`",
        'expires_at' => "ALTER TABLE `otp_verifications` ADD COLUMN `expires_at` DATETIME NOT NULL AFTER `attempts`",
        'created_at' => "ALTER TABLE `otp_verifications` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `expires_at`",
    ];

    foreach ($requiredColumns as $column => $statement) {
        if (!dbColumnExists($pdo, 'otp_verifications', $column)) {
            $pdo->exec($statement);
        }
    }

    if (!dbIndexExists($pdo, 'otp_verifications', 'idx_phone')) {
        $pdo->exec("ALTER TABLE `otp_verifications` ADD INDEX `idx_phone` (`phone`)");
    }
    if (!dbIndexExists($pdo, 'otp_verifications', 'idx_expires')) {
        $pdo->exec("ALTER TABLE `otp_verifications` ADD INDEX `idx_expires` (`expires_at`)");
    }
}

/**
 * Ensure bookings table supports the OTP-pending flow on older live databases.
 */
function ensureBookingsOtpSchema(PDO $pdo): void {
    if (!dbColumnExists($pdo, 'bookings', 'email')) {
        $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `email` VARCHAR(180) NULL AFTER `full_name`");
    }

    $pdo->exec(
        "ALTER TABLE `bookings`
         MODIFY COLUMN `status` ENUM('otp_pending','new','payment_pending','in_progress','completed','cancelled')
         NOT NULL DEFAULT 'otp_pending'"
    );
}

/**
 * Check whether a table column exists.
 */
function dbColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column
         LIMIT 1"
    );
    $stmt->execute([
        ':schema' => DB_NAME,
        ':table' => $table,
        ':column' => $column,
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Check whether a table index exists.
 */
function dbIndexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND INDEX_NAME = :index
         LIMIT 1"
    );
    $stmt->execute([
        ':schema' => DB_NAME,
        ':table' => $table,
        ':index' => $index,
    ]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Ensure the rate limit table exists.
 */
function ensureRateLimitSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rate_key` VARCHAR(120) NOT NULL,
            `count` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_rate_key` (`rate_key`),
            INDEX `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Track and enforce a simple per-IP rate limit.
 */
function rateLimit(string $key, int $limit, int $intervalSeconds): bool {
    try {
        $pdo = db();
        ensureRateLimitSchema($pdo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT `count`, `expires_at`
             FROM `rate_limits`
             WHERE `rate_key` = ?
             FOR UPDATE"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $now->modify("+{$intervalSeconds} seconds")->format('Y-m-d H:i:s');

        if (!$row || strtotime($row['expires_at']) < time()) {
            $stmt = $pdo->prepare(
                "REPLACE INTO `rate_limits` (`rate_key`, `count`, `expires_at`)
                 VALUES (:key, 1, :expires_at)"
            );
            $stmt->execute([':key' => $key, ':expires_at' => $expiresAt]);
            $pdo->commit();
            return true;
        }

        if ((int) $row['count'] >= $limit) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE `rate_limits`
             SET `count` = `count` + 1
             WHERE `rate_key` = :key"
        );
        $stmt->execute([':key' => $key]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Rate limit check failed: ' . $e->getMessage());
        return true; // Fail open on internal error
    }
}

/**
 * Store OTP in database and send via Email (Brevo SMTP).
 * Falls back to a descriptive error if no email address is available.
 */
function storeAndSendOTP(string $phone, ?string $email = null, string $name = 'there'): array {
    // Email is required for OTP delivery
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'A valid email address is required to receive your OTP.',
        ];
    }

    try {
        $pdo = db();
        ensureOtpVerificationSchema($pdo);
        $otp = generateOTP();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

        // Cleanup expired or stale OTP rows older than one day
        $pdo->prepare(
            "DELETE FROM `otp_verifications`
             WHERE `expires_at` < DATE_SUB(NOW(), INTERVAL 1 DAY)
                OR (`is_verified` = 1 AND `created_at` < DATE_SUB(NOW(), INTERVAL 1 DAY))"
        )->execute([]);

        // Delete any existing unverified OTP for this phone or email
        $pdo->prepare(
            "DELETE FROM `otp_verifications`
             WHERE `is_verified` = 0
               AND (`phone` = :phone OR `email` = :email)"
        )->execute([
            ':phone' => $phone,
            ':email' => $email,
        ]);

        // Always commit OTP to DB first — before attempting email
        $stmt = $pdo->prepare(
            "INSERT INTO `otp_verifications` (`phone`, `email`, `otp_code`, `is_verified`, `attempts`, `expires_at`)
             VALUES (:phone, :email, :otp, 0, 0, :expires_at)"
        );
        $stmt->execute([
            ':phone'      => $phone,
            ':email'      => $email,
            ':otp'        => $otp,
            ':expires_at' => $expiresAt,
        ]);

        // Log OTP for server-side debugging
        error_log("[Instalegl OTP] Phone: $phone | Email: $email | OTP: $otp | Expires: $expiresAt");

        $emailSent = sendOTPEmail($email, $otp, $name);

        if (!$emailSent) {
            error_log("[Instalegl OTP] Email failed for $email");
            return [
                'success' => false,
                'message' => 'OTP could not be sent. Please check your email address and try again.',
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent to your email.',
            'email'   => maskEmail($email),
        ];

    } catch (Throwable $e) {
        error_log("[Instalegl OTP] Exception: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to generate OTP. Please try again.',
        ];
    }
}

/**
 * Normalize a booking service slug/label into the stored service label.
 */
function mapBookingServiceLabel(string $service): string {
    $serviceLabels = [
        'court'        => 'Court Representation / Bail',
        'consultation' => 'Legal Consultation',
        'drafting'     => 'Document Drafting',
        'notice'       => 'Legal Notice',
        'notary'       => 'Affidavit / Notary',
        'contract'     => 'Contract Review',
        'trademark'    => 'Trademark Registration',
        'probate'      => 'Probate / Succession',
        'other'        => 'Other Legal Matter',
    ];

    return $serviceLabels[$service] ?? $service;
}

/**
 * Create a pending booking request and send a fresh OTP email.
 * This is the shared sender service used by the homepage and service pages.
 */
function createBookingOtpRequest(string $name, string $phone, string $email, string $service): array {
    $missing = validateRequired([
        'Full Name'    => $name,
        'Mobile Phone' => $phone,
        'Email'        => $email,
        'Service'      => $service,
    ]);
    if ($missing) {
        return ['success' => false, 'message' => "{$missing} is required.", 'code' => 422];
    }
    if (!validPhone($phone)) {
        return ['success' => false, 'message' => 'Please enter a valid mobile number.', 'code' => 422];
    }
    if (!validEmail($email)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.', 'code' => 422];
    }

    $serviceLabel = mapBookingServiceLabel($service);

    try {
        $pdo = db();
        ensureBookingsOtpSchema($pdo);
        ensureOtpVerificationSchema($pdo);
        $pdo->beginTransaction();

        // Keep only the latest pending flow for a phone number so verification
        // resolves against a single active booking request.
        $pdo->prepare("DELETE FROM `bookings` WHERE `phone` = :phone AND `status` = 'otp_pending'")
            ->execute([':phone' => $phone]);

        $tempRef = generateTempIdentifier('BKG');
        $stmt = $pdo->prepare(
            "INSERT INTO `bookings` (`ref_number`, `full_name`, `email`, `phone`, `service`, `status`, `ip_address`)
             VALUES (:ref, :name, :email, :phone, :service, 'otp_pending', :ip)"
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
        $otpResult = storeAndSendOTP($phone, $email, $name);
        if (!$otpResult['success']) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $otpResult['message'], 'code' => 500];
        }

        $pdo->commit();

        return [
            'success'    => true,
            'message'    => 'OTP sent to your email. Please check and verify.',
            'email'      => $otpResult['email'] ?? maskEmail($email),
            'booking_id' => $bookingId,
            'code'       => 200,
        ];
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Booking OTP Request Error: ' . $e->getMessage());
        $message = 'Error processing booking. Please try again.';
        if (defined('INSTALEGL_DEBUG') && INSTALEGL_DEBUG) {
            $message = 'Booking OTP Request failed: ' . $e->getMessage();
        }
        return ['success' => false, 'message' => $message, 'code' => 500];
    }
}

/**
 * Create a contact message request and send a verification OTP.
 */
function createContactOtpRequest(string $name, string $phone, string $email, string $subject, string $message): array {
    $missing = validateRequired([
        'Full Name' => $name,
        'Email'     => $email,
        'Subject'   => $subject,
        'Message'   => $message,
    ]);
    if ($missing) {
        return ['success' => false, 'message' => "{$missing} is required.", 'code' => 422];
    }
    if ($phone !== '' && !validPhone($phone)) {
        return ['success' => false, 'message' => 'Please enter a valid mobile number.', 'code' => 422];
    }
    if (!validEmail($email)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.', 'code' => 422];
    }

    try {
        $pdo = db();
        ensureOtpVerificationSchema($pdo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO `contact_messages`
             (`full_name`, `email`, `phone`, `subject`, `message`, `status`, `ip_address`)
             VALUES (:name, :email, :phone, :subject, :message, 'otp_pending', :ip)"
        );
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':phone'   => $phone,
            ':subject' => $subject,
            ':message' => $message,
            ':ip'      => getIP(),
        ]);

        $otpResult = storeAndSendOTP($phone, $email, $name);
        if (!$otpResult['success']) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $otpResult['message'], 'code' => 500];
        }

        $pdo->commit();
        return [
            'success' => true,
            'message' => 'Verification code sent. Please check your email and confirm your message.',
            'email'   => $otpResult['email'] ?? maskEmail($email),
            'code'    => 200,
        ];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Contact OTP Request Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process your message. Please try again.', 'code' => 500];
    }
}

/**
 * Verify OTP from database.
 */
function verifyOTP(string $identifier, string $otp): array {
    try {
        $pdo = db();
        ensureOtpVerificationSchema($pdo);

        $field = validEmail($identifier) ? 'email' : 'phone';
        $stmt = $pdo->prepare(
            "SELECT `id`, `otp_code`, `attempts`, `expires_at`, `is_verified`
             FROM `otp_verifications`
             WHERE `{$field}` = ?
             ORDER BY `created_at` DESC LIMIT 1"
        );
        $stmt->execute([$identifier]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'No OTP request found. Please request a new OTP.'];
        }

        if ($record['is_verified']) {
            return ['success' => false, 'message' => 'OTP already verified.'];
        }

        if ($record['attempts'] >= OTP_MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Too many attempts. Request a new OTP.'];
        }

        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP has expired.'];
        }

        if (!hash_equals((string) $record['otp_code'], $otp)) {
            incrementOTPAttempt($identifier);
            return ['success' => false, 'message' => 'Invalid OTP.'];
        }

        $pdo->prepare("UPDATE `otp_verifications` SET `is_verified` = 1, `used_at` = NOW() WHERE `id` = ?")
            ->execute([$record['id']]);

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    } catch (Exception $e) {
        error_log("OTP Verification Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Verification failed.'];
    }
}

/**
 * Confirm that a phone/email pair has a recently verified OTP.
 */
function hasRecentlyVerifiedOTP(string $phone, ?string $email = null, int $windowMinutes = 30): bool {
    try {
        $pdo = db();
        ensureOtpVerificationSchema($pdo);
        $windowMinutes = max(1, $windowMinutes);
        $sql = "
            SELECT `id`
            FROM `otp_verifications`
            WHERE `phone` = :phone
              AND `is_verified` = 1
              AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE)
        ";
        $params = [':phone' => $phone];

        if ($email !== null && $email !== '') {
            $sql .= " AND `email` = :email";
            $params[':email'] = $email;
        }

        $sql .= " ORDER BY `created_at` DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Verified OTP Check Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Increment OTP attempt counter.
 */
function incrementOTPAttempt(string $identifier): void {
    try {
        $pdo = db();
        ensureOtpVerificationSchema($pdo);
        $field = validEmail($identifier) ? 'email' : 'phone';
        $pdo->prepare(
            "UPDATE `otp_verifications` SET `attempts` = `attempts` + 1
             WHERE `id` = (
                 SELECT `id` FROM (
                     SELECT `id` FROM `otp_verifications`
                     WHERE `{$field}` = ? AND `is_verified` = 0 AND `expires_at` >= NOW()
                     ORDER BY `created_at` DESC
                     LIMIT 1
                 ) AS latest_otp
             )"
        )->execute([$identifier]);
    } catch (Exception $e) {
        error_log("Increment OTP Attempt Error: " . $e->getMessage());
    }
}

/**
 * Validate required fields. Returns first missing field label or null.
 */
function validateRequired(array $fields): ?string {
    foreach ($fields as $label => $value) {
        if ($value === '' || $value === null) {
            return $label;
        }
    }
    return null;
}

/**
 * Validate email format.
 */
function validEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone (Indian / international — 7–15 digits).
 */
function validPhone(string $phone): bool {
    return (bool) preg_match('/^\+\d{10,15}$/', $phone);
}

/**
 * Generate a sequential reference number.
 * e.g. IGL-2025-00042 or ADV-2025-00042
 */
function generateRef(string $prefix, int $id): string {
    return $prefix . '-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get visitor IP address.
 */
function getIP(): string {
    $remoteIp = !empty($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
    $trusted = defined('TRUSTED_PROXY_ADDRESSES') ? TRUSTED_PROXY_ADDRESSES : [];

    if ($remoteIp !== '' && is_array($trusted) && in_array($remoteIp, $trusted, true)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            $last = end($parts);
            if ($last !== false && $last !== '') {
                return $last;
            }
        }
    }

    return $remoteIp;
}

/**
 * Handle one file upload. Returns saved relative path or throws RuntimeException.
 *
 * @param  string $fieldName  $_FILES key
 * @param  string $subFolder  sub-directory inside UPLOAD_DIR
 * @param  string $prefix     filename prefix
 * @return string  relative path from UPLOAD_DIR
 */
function handleUpload(string $fieldName, string $subFolder, string $prefix): string {
    if (empty($_FILES[$fieldName]['tmp_name'])) {
        throw new RuntimeException("File '{$fieldName}' is missing.");
    }

    $file = $_FILES[$fieldName];

    // Size check
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException("File '{$fieldName}' exceeds 5 MB limit.");
    }

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException("File '{$fieldName}' has an invalid extension.");
    }

    // MIME check via finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new RuntimeException("File '{$fieldName}' has an invalid type.");
    }

    // Build destination
    $dir = UPLOAD_DIR . $subFolder . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Upload directory could not be created.");
    }

    $filename  = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath  = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException("Failed to save uploaded file.");
    }

    return $subFolder . '/' . $filename;
}

function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && defined('ALLOWED_ORIGIN') && strcasecmp(trim($origin), trim(ALLOWED_ORIGIN)) === 0) {
        header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit(0);
    }
}

setCorsHeaders();
