<?php
// config.php — EigaNights site config + DB connection

// ─────────────────────────────────────────────────────────────────────────────
// 1) DEV error reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ─────────────────────────────────────────────────────────────────────────────
// 2) Session setup
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) Database settings (TCP + non-root user)
define('DB_HOST', '127.0.0.1');      // force TCP
define('DB_PORT', 3306);             // default MySQL port
define('DB_NAME', 'eiganights');
define('DB_USER', 'eigaapp');        // see setup steps below
define('DB_PASS', '');               // blank password

$mysqli = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT
);

// (after your new mysqli(...) call)
$conn = $mysqli;

if ($mysqli->connect_errno) {
    // log real error, show friendly message
    error_log("MySQL connect failed ({$mysqli->connect_errno}): {$mysqli->connect_error}");
    die("Sorry—database temporarily unavailable.");
}

$mysqli->set_charset('utf8mb4');

// ─────────────────────────────────────────────────────────────────────────────
// 4) TMDB API key
define('TMDB_API_KEY', 'ca76efbbd188354d4f49383014d8ed3b');

// ─────────────────────────────────────────────────────────────────────────────
// 5) Base URL helper
$protocol = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443
) ? 'https://' : 'http://';
$domain   = $_SERVER['HTTP_HOST'];
$script   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . $domain . $script . '/');

