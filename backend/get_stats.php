<?php
header('Content-Type: application/json');
require_once 'config.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && defined('ALLOWED_ORIGIN') && strcasecmp(trim($origin), trim(ALLOWED_ORIGIN)) === 0) {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Get total completed cases
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'");
    $casesResolved = $stmt->fetch()['total'];

    // Get total verified advocates
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM advocate_applications WHERE status = 'approved'");
    $verifiedAdvocates = $stmt->fetch()['total'];

    // Get total unique clients (from bookings)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT phone) as total FROM bookings WHERE status IN ('completed', 'in_progress')");
    $totalClients = $stmt->fetch()['total'];

    // Calculate client satisfaction (for now, using a static 96% as it's hard to calculate from data)
    // In future, this could be based on ratings or feedback
    $clientSatisfaction = 96;

    // Calculate client hours saved (rough estimate: 2 hours per case)
    $clientHoursSaved = $casesResolved * 2;

    echo json_encode([
        'success' => true,
        'data' => [
            'cases_resolved' => (int)$casesResolved,
            'verified_advocates' => (int)$verifiedAdvocates,
            'total_clients' => (int)$totalClients,
            'client_hours_saved' => (int)$clientHoursSaved,
            'client_satisfaction' => (int)$clientSatisfaction
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>