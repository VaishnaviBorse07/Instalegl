<?php
/**
 * Instalegl - Configuration
 * Loads settings from local env files and common hosting environment keys.
 */

function instaleglLoadEnvFile(string $path): void {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (stripos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

function instaleglEnv(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $default;
}

foreach ([
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/.env.local',
    __DIR__ . '/.env',
] as $envPath) {
    instaleglLoadEnvFile($envPath);
}

$databaseUrl = instaleglEnv(['INSTALEGL_DATABASE_URL', 'DATABASE_URL', 'MYSQL_URL'], '');
$dbHost = instaleglEnv(['INSTALEGL_DB_HOST', 'DB_HOST', 'MYSQLHOST', 'MYSQL_HOST'], 'localhost');
$dbPort = instaleglEnv(['INSTALEGL_DB_PORT', 'DB_PORT', 'MYSQLPORT', 'MYSQL_PORT'], '3306');
$dbName = instaleglEnv(['INSTALEGL_DB_NAME', 'DB_NAME', 'DB_DATABASE', 'MYSQLDATABASE', 'MYSQL_DATABASE'], 'instalegl_db');
$dbUser = instaleglEnv(['INSTALEGL_DB_USER', 'DB_USER', 'DB_USERNAME', 'MYSQLUSER', 'MYSQL_USER'], '');
$dbPass = instaleglEnv(['INSTALEGL_DB_PASS', 'DB_PASS', 'DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_PASS'], '');

if ($databaseUrl !== '') {
    $parts = parse_url($databaseUrl);
    if (is_array($parts)) {
        if (!empty($parts['host'])) {
            $dbHost = (string) $parts['host'];
        }
        if (!empty($parts['port'])) {
            $dbPort = (string) $parts['port'];
        }
        if (!empty($parts['path'])) {
            $dbName = ltrim((string) $parts['path'], '/');
        }
        if (isset($parts['user']) && $parts['user'] !== '') {
            $dbUser = (string) $parts['user'];
        }
        if (isset($parts['pass'])) {
            $dbPass = (string) $parts['pass'];
        }
    }
}

if ($dbUser === '' || $dbPass === '') {
    $message = 'Critical configuration missing: database credentials are not configured. Please set INSTALEGL_DB_USER and INSTALEGL_DB_PASS in your environment.';
    error_log($message);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => $message]));
}

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', 'utf8mb4');

// Upload directory (absolute path) - must be writable
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/advocate-docs/');
define('UPLOAD_URL', '../uploads/advocate-docs/');

// Max file size in bytes (5 MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed MIME types for uploads
define('ALLOWED_MIME', ['image/jpeg', 'image/png', 'application/pdf']);
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'pdf']);

// Site
define('SITE_NAME', 'Instalegl');

// OTP and SMS configuration
define('SMS_SERVICE', instaleglEnv(['INSTALEGL_SMS_SERVICE', 'SMS_SERVICE'], 'brevo'));
define('SMS_FROM', instaleglEnv(['INSTALEGL_SMS_FROM', 'SMS_FROM'], 'Instalegl'));

// Brevo (Sendinblue) configuration - used for OTP SMS and transactional emails
define('BREVO_API_KEY', instaleglEnv(['INSTALEGL_BREVO_API_KEY', 'BREVO_API_KEY'], ''));
define('BREVO_SENDER_NAME', instaleglEnv(['INSTALEGL_BREVO_SENDER_NAME', 'BREVO_SENDER_NAME'], 'Instalegl'));
define('BREVO_SENDER_EMAIL', instaleglEnv(['INSTALEGL_BREVO_SENDER_EMAIL', 'BREVO_SENDER_EMAIL'], 'support@instalegl.in'));

// SMTP settings (standard relay)
define('SMTP_HOST', instaleglEnv(['INSTALEGL_SMTP_HOST', 'SMTP_HOST'], 'smtp-relay.brevo.com'));
define('SMTP_PORT', instaleglEnv(['INSTALEGL_SMTP_PORT', 'SMTP_PORT'], '587'));
define('SMTP_USER', instaleglEnv(['INSTALEGL_SMTP_USER', 'SMTP_USER'], ''));
define('SMTP_PASS', instaleglEnv(['INSTALEGL_SMTP_PASS', 'SMTP_PASS'], ''));

define('ADMIN_EMAIL', instaleglEnv(['INSTALEGL_ADMIN_EMAIL', 'ADMIN_EMAIL'], 'support@instalegl.in'));
define('ALLOWED_ORIGIN', instaleglEnv(['INSTALEGL_ALLOWED_ORIGIN'], 'https://instalegl.in'));
define('TRUSTED_PROXY_ADDRESSES', array_filter(array_map('trim', explode(',', instaleglEnv(['INSTALEGL_TRUSTED_PROXIES'], '')))));

define('INSTALEGL_DEBUG', instaleglEnv(['INSTALEGL_DEBUG', 'DEBUG'], '0') === '1');

// OTP settings
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS', 5);

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        if (DB_PORT !== '') {
            $dsn .= ';port=' . DB_PORT;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log(sprintf(
                'Instalegl DB connection failed [host=%s port=%s db=%s user=%s]: %s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_USER,
                $e->getMessage()
            ));
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }

    return $pdo;
}
