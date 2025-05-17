<?php
/*
 * config.php
 * Site-wide configuration settings and database connection.
 */

// --- Error Reporting ---
// For development:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// For production (log errors to a file, don't display them):
// ini_set('display_errors', 0);
// ini_set('log_errors', 1); // Ensure this is On in php.ini for production
// ini_set('error_log', '/path/to/your/php-error.log'); // Set an actual, writable path
// error_reporting(E_ALL); // Log all types of errors

// --- Session Management ---
// Start the session if it hasn't been started already.
// This is convenient as most pages in this application use sessions.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Standard XAMPP default
define('DB_PASS', '');     // Standard XAMPP default (empty password)
define('DB_NAME', 'eiganights');
define('DB_CHARSET', 'utf8mb4'); // Recommended charset

// --- API Keys ---
// It's generally better to store API keys outside of version control (e.g., environment variables) for production.
// For this project, defining it here is acceptable.
define('TMDB_API_KEY', 'cf536f66b460a5cf45e5e4bc648f5e81');
// Make $TMDB_API_KEY variable available if some older parts of code might still expect it directly.
// However, consistently using the constant TMDB_API_KEY is preferred.
$TMDB_API_KEY = TMDB_API_KEY;

// --- Establish Database Connection (MySQLi) ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // For a public-facing site, avoid die() with detailed errors.
    // Log the error and show a generic error message/page.
    // For development, die() is acceptable for immediate feedback.
    error_log("Database Connection Failed: " . $conn->connect_error . " (Error Code: CFG_DB_CONN)"); // Log detailed error
    die("Une erreur de connexion à la base de données est survenue. Veuillez réessayer plus tard."); // User-friendly message
}

// Set the character set for the connection, crucial for handling various languages and emojis
if (!$conn->set_charset(DB_CHARSET)) {
    error_log("Error loading character set " . DB_CHARSET . ": " . $conn->error . " (Error Code: CFG_DB_CHARSET)");
    // If charset fails, it can lead to subtle data corruption or display issues.
    // Depending on severity, you might die or try to proceed with a warning.
    // For development, die() is okay.
    die("Erreur lors du chargement du jeu de caractères de la base de données.");
}

// --- Site Settings (OPTIONAL - Uncomment and configure if you want to use them) ---
/*
define('SITE_NAME', 'Eiganights');

// --- Base URL Configuration (IMPORTANT if you uncomment and use) ---
// Option 1: Dynamic (tries to guess, might need adjustment for sub-sub-folders)
// $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// $domainName = $_SERVER['HTTP_HOST'];
// $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // Path to the directory containing this config.php
// define('BASE_URL', $protocol . $domainName . $scriptPath . '/'); // Ensure trailing slash

// Option 2: Manual/Simpler for XAMPP (Recommended for your current setup if using subfolder)
// Replace '/eiganights' with your actual project subfolder name if different.
// If project is in web root (e.g., http://localhost/), use an empty string for subfolder.
$projectSubfolder = '/eiganights'; // **** ADJUST THIS FOR YOUR SETUP ****
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $projectSubfolder . '/');
define('BASE_ASSET_URL', BASE_URL . 'assets/');
*/

// Note: $conn and $TMDB_API_KEY are now globally available to any script that includes this config.php.
// If you define BASE_URL and BASE_ASSET_URL, they will be too.
?>