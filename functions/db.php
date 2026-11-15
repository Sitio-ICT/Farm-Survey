<?php
// Database connection. Credentials come from the git-ignored functions/config.php
// (copy functions/config.example.php), never from code.
require_once __DIR__ . '/app_config.php';

// Session cookie hardening. Applied before the session starts (login.php and the
// handlers include this file first), which is when the cookie is issued.
// SameSite=Lax stops other sites from POSTing to the handlers with the user's session.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
}

function dbFatal($message)
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo htmlspecialchars($message);
    exit;
}

if (!hasAppConfig()) {
    dbFatal("Farm Survey is not configured yet. Copy functions/config.example.php to functions/config.php and set the database settings.");
}

$dbConfig = appConfig()['db'] ?? [];
foreach (['host', 'username', 'password', 'name'] as $key) {
    if (!array_key_exists($key, $dbConfig)) {
        dbFatal("functions/config.php is missing the 'db' settings. See functions/config.example.php.");
    }
}

// Kept for older code that reads these constants.
define('DB_SERVER', $dbConfig['host']);
define('DB_USERNAME', $dbConfig['username']);
define('DB_PASSWORD', $dbConfig['password']);
define('DB_NAME', $dbConfig['name']);
define('DB_CHARSET', 'utf-8'); // unchanged legacy value; the connection charset is left at the server default

mysqli_report(MYSQLI_REPORT_OFF);
$connection = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, (int) ($dbConfig['port'] ?? 3306));
if (!$connection) {
    // The real reason goes to the server log only; it can reveal hosts and user names.
    error_log('Farm Survey database connection failed: ' . mysqli_connect_error());
    dbFatal("Database connection failed. Please try again later.");
}
// Restore PHP 8.1+'s default so query errors still throw, as the app expects.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
