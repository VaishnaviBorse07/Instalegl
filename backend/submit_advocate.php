<?php
/**
 * Instalegl — Submit Advocate Application
 * Handles multipart POST from join-advocate.html (4-step wizard).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// ── Personal Info ────────────────────────────────────────────
$name    = clean($_POST['name']    ?? '');
$dob     = clean($_POST['dob']     ?? '');
$email   = clean($_POST['email']   ?? '');
$phone   = normalizePhone(clean($_POST['phone'] ?? ''));
$city    = clean($_POST['city']    ?? '');
$address = clean($_POST['address'] ?? '');

// ── Practice Info ────────────────────────────────────────────
$council       = clean($_POST['council']       ?? '');
$enrollNo      = clean($_POST['enrollment_no'] ?? '');
$enrollDate    = clean($_POST['enrollment_date'] ?? '');
$years         = clean($_POST['years']         ?? '');
$practiceAreas = clean($_POST['practice_areas'] ?? '');
$courts        = clean($_POST['courts']        ?? '');
$languages     = clean($_POST['languages']     ?? '');
$bio           = clean($_POST['bio']           ?? '');
$govIdType     = clean($_POST['gov_id_type']   ?? 'Aadhaar');

// ── Validation ───────────────────────────────────────────────
$missing = validateRequired([
    'Full Name'       => $name,
    'Email'           => $email,
    'Phone'           => $phone,
    'City'            => $city,
    'Bar Council'     => $council,
    'Enrollment No.'  => $enrollNo,
    'Years of Practice' => $years,
    'Practice Areas'  => $practiceAreas,
]);
if ($missing) {
    jsonResponse(false, "{$missing} is required.");
}
if (!validEmail($email)) {
    jsonResponse(false, 'Please enter a valid email address.');
}
if (!validPhone($phone)) {
    jsonResponse(false, 'Please enter a valid mobile number.');
}

$otpWindowMinutes = max(OTP_EXPIRY_MINUTES, 30);
if (!hasRecentlyVerifiedOTP($phone, $email, $otpWindowMinutes)) {
    jsonResponse(false, 'Please verify OTP first.');
}

// ── File Uploads ─────────────────────────────────────────────
$folder = date('Ym') . '/' . preg_replace('/[^a-z0-9_]/', '', strtolower($enrollNo));
$docs   = [];
$required_files = [
    'bc_front' => 'Bar Council ID Front',
    'bc_back'  => 'Bar Council ID Back',
    'cert'     => 'Enrollment Certificate',
    'govid'    => 'Government ID',
    'photo'    => 'Profile Photo',
    'qr_code'  => 'Payment QR Code',
];

foreach ($required_files as $field => $label) {
    if (empty($_FILES[$field]['tmp_name'])) {
        jsonResponse(false, "Please upload your {$label}.");
    }
    try {
        $docs[$field] = handleUpload($field, $folder, $field);
    } catch (RuntimeException $e) {
        jsonResponse(false, $e->getMessage());
    }
}

try {
    $pdo  = db();
    $tempAppId = generateTempIdentifier('ADV');
    $stmt = $pdo->prepare("
        INSERT INTO `advocate_applications`
          (`app_id`, `full_name`, `dob`, `email`, `phone`, `city`, `address`,
           `bar_council`, `enrollment_no`, `enrollment_date`, `years_practice`,
           `practice_areas`, `courts`, `languages`, `bio`,
           `doc_bc_front`, `doc_bc_back`, `doc_cert`, `doc_govid`, `gov_id_type`, `status`,
           `doc_photo`, `doc_qr_code`, `ip_address`)
        VALUES
          (:app_id, :name, :dob, :email, :phone, :city, :address,
           :council, :enroll_no, :enroll_date, :years,
           :areas, :courts, :langs, :bio,
           :bcf, :bcb, :cert, :govid, :git, 'pending',
           :photo, :qr_code, :ip)
    ");
    $stmt->execute([
        ':app_id'     => $tempAppId,
        ':name'       => $name,
        ':dob'        => $dob ?: null,
        ':email'      => $email,
        ':phone'      => $phone,
        ':city'       => $city,
        ':address'    => $address,
        ':council'    => $council,
        ':enroll_no'  => $enrollNo,
        ':enroll_date'=> $enrollDate ?: null,
        ':years'      => $years,
        ':areas'      => $practiceAreas,
        ':courts'     => $courts,
        ':langs'      => $languages,
        ':bio'        => $bio,
        ':bcf'        => $docs['bc_front'],
        ':bcb'        => $docs['bc_back'],
        ':cert'       => $docs['cert'],
        ':govid'      => $docs['govid'],
        ':git'        => $govIdType,
        ':photo'      => $docs['photo'],
        ':qr_code'    => $docs['qr_code'],
        ':ip'         => getIP(),
    ]);
    $applicationId = (int) $pdo->lastInsertId();

    $appId = generateRef('ADV', $applicationId);

    $pdo->prepare("UPDATE `advocate_applications` SET `app_id` = :app_id WHERE `id` = :id")
        ->execute([
            ':app_id' => $appId,
            ':id'     => $applicationId,
        ]);

    // ── Email Notifications ───────────────────────────────────

    // To Advocate
    $advocateTitle   = "Application Received — $appId";
    $advocateMessage = "
        <p>Dear {$name},</p>
        <p>Thank you for applying to join the Instalegl advocate network. Your application has been successfully received and is currently under review by our compliance team.</p>
        <div class='info-box'>
            <p><strong>Application ID:</strong> {$appId}</p>
            <p><strong>Status:</strong> Pending Review</p>
        </div>
        <p>Our team will verify your Bar Council credentials and documents. You will receive an update via email or phone within 48 business hours.</p>
        <p>If you have any questions, please reply to this email.</p>
    ";
    sendEmail($email, $advocateTitle, getPremiumEmailTemplate($advocateTitle, $advocateMessage));

    // To Admin
    $adminTitle   = "New Advocate Application — $appId";
    $adminMessage = "
        <p>A new advocate has applied to join the network.</p>
        <div class='info-box'>
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Phone:</strong> {$phone}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>City:</strong> {$city}</p>
            <p><strong>Council:</strong> {$council}</p>
            <p><strong>Enrollment:</strong> {$enrollNo}</p>
            <p><strong>Experience:</strong> {$years} years</p>
            <p><strong>Areas:</strong> {$practiceAreas}</p>
        </div>
        <p>View this application in the admin panel to verify documents.</p>
    ";
    sendEmail(ADMIN_EMAIL, $adminTitle, getPremiumEmailTemplate($adminTitle, $adminMessage));

    jsonResponse(true, 'Your application has been submitted successfully.', [
        'app_id'  => $appId,
        'message' => 'Our team will review your documents and contact you within 48 hours.',
    ]);

} catch (PDOException $e) {
    foreach ($docs as $path) {
        $fullPath = UPLOAD_DIR . ltrim($path, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
    error_log('Advocate Submit Error: ' . $e->getMessage());
    jsonResponse(false, 'Database error. Please try again later.', [], 500);
}
