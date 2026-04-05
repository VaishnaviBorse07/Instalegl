<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$diagKey = instaleglEnv(['INSTALEGL_DIAG_KEY', 'DIAG_KEY'], '');
$providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';
$isLocalRequest = in_array($remoteAddr, ['127.0.0.1', '::1'], true);

if (!$isLocalRequest) {
    if ($diagKey === '' || !hash_equals($diagKey, $providedKey)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit;
    }
}

echo "=== INSTALEGL DB DIAGNOSE ===\n\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "cURL available: " . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
echo "mail() available: " . (function_exists('mail') ? 'yes' : 'no') . "\n";
echo "Brevo API key: " . (BREVO_API_KEY === '' ? '[missing]' : '[configured]') . "\n";
echo "Brevo sender email: " . BREVO_SENDER_EMAIL . "\n";
echo "SMTP host: " . SMTP_HOST . "\n";
echo "SMTP port: " . SMTP_PORT . "\n";
echo "SMTP user: " . (SMTP_USER === '' ? '[missing]' : '[configured]') . "\n";
echo "SMTP pass: " . (SMTP_PASS === '' ? '[missing]' : '[configured]') . "\n\n";

echo "Host: " . DB_HOST . "\n";
echo "Port: " . DB_PORT . "\n";
echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n";
echo "Password: " . (DB_PASS === '' ? '[empty]' : '[configured]') . "\n\n";

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
if (DB_PORT !== '') {
    $dsn .= ';port=' . DB_PORT;
}

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    echo "Connection: OK\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo " - " . $table . "\n";
    }
} catch (Throwable $e) {
    echo "Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
