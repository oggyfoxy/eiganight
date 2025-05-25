<?php
// config.php - eiganights Site Configuration
// Reads from Environment Variables for production and can use fallbacks for local development.

// 1) Error Reporting
$isProduction = (getenv('APP_ENV') === 'production');

if (!$isProduction) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', 1);
}

// 2) Session Setup
if (session_status() === PHP_SESSION_NONE) {
    $isHttpsOnServer = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isHttpsOnServer, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

// 3) Database settings
// For local development, you would typically have a SEPARATE, UNTRACKED (gitignored)
// config.local.php or set local environment variables, or adjust these fallbacks
// to match your local MAMP/XAMPP (e.g., user 'root', pass 'root' for MAMP).
// THE FALLBACKS HERE SHOULD NOT BE YOUR PRODUCTION CREDENTIALS.
define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('MYSQL_PORT') ?: 3306)); // Or your local MAMP port like 8889
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'eiganights'); // e.g., a local dev DB name
define('DB_USER', getenv('MYSQL_USER') ?: 'local_db_user');       // e.g., 'root' or 'eigaapp_dev'
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'local_db_password'); // e.g., 'root' or an empty string ''

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$conn = $mysqli;
if ($mysqli->connect_errno) {
    // Handle error (as previously defined)
    $db_error_message = "Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    error_log($db_error_message . " | Configured Host: " . DB_HOST . ", User: " . DB_USER . ", DB: " . DB_NAME);
    if (!$isProduction) {
        die($db_error_message . ". Please check your config.php fallbacks and ensure your local DB server is running.");
    } else {
        die("A critical database error occurred. Please try again later. (Error Ref: DB_CONN_PROD)");
    }
}
$mysqli->set_charset('utf8mb4');

// 4) TMDB API key
// For local development, you can use a real key as a fallback, but ideally,
// even this would be a local env var or in a gitignored local config.
// For this example, we keep your provided key as a fallback.
define('TMDB_API_KEY', getenv('TMDB_API_KEY') ?: 'ca76efbbd188354d4f49383014d8ed3b');

// 5) Base URL
$app_url_env = getenv('APP_URL');
if ($app_url_env) {
    define('BASE_URL', rtrim($app_url_env, '/') . '/');
} else {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $domain   = $_SERVER['HTTP_HOST'];
    $script   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($script === '/' || $script === '\\') $script = '';
    define('BASE_URL', $protocol . $domain . $script . '/');
}

// Optional: Define SITE_NAME
define('SITE_NAME', getenv('SITE_NAME') ?: 'eiganights (Dev)');


// --- SMTP Configuration for PHPMailer ---
// FALLBACKS HERE SHOULD BE PLACEHOLDERS OR DUMMY VALUES if this file is public.
// For actual local email sending, use a gitignored local config override or local env vars.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'your_local_smtp_host_or_placeholder'); // e.g., 'localhost' if using mailhog/mailtrap locally
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'local_user@example.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'local_password_placeholder');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 1025)); // e.g., MailHog port
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: ''); // e.g., '' or 'tls' if local mail server needs it
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'dev_noreply@eiganights.local');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'eiganights Dev Support');

?>