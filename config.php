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
//    Local fallbacks should match your MAMP setup.
define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');        // This is usually fine for MAMP
define('DB_PORT', (int)(getenv('MYSQL_PORT') ?: 3306));      // <<< CHANGE THIS if your MAMP MySQL is on port 8889 (MAMP default), or 3306 if you configured it to that.
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'eiganights');   // <<< Use 'eiganights' if that's your local DB name
define('DB_USER', getenv('MYSQL_USER') ?: 'root');            // <<< CHANGE THIS to 'root' for MAMP default
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: ""); 

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
define('SITE_NAME', getenv('SITE_NAME') ?: 'eiganights');

// --- Feature Flags ---
// Enable placeholder ads for demonstration (set to false to disable)
define('PLACEHOLDER_ADS_ENABLED', getenv('PLACEHOLDER_ADS_ENABLED') === 'true' ?: true); // Default to true for local dev

// Enable direct streaming links section (set to false to disable)
define('DIRECT_STREAMING_LINKS_ENABLED', getenv('DIRECT_STREAMING_LINKS_ENABLED') === 'true' ?: true); // Default to true
// 6) Monetization Settings (School Project Simulation - Simplified Random GIFs)
// ─────────────────────────────────────────────────────────────────────────────


// Chemin vers le dossier contenant VOS GIFs publicitaires (relatif à la racine du projet)
// ACTION : Assurez-vous que ce chemin est correct et que le dossier contient vos GIFs.
define('RANDOM_GIF_ADS_DIRECTORY', 'assets/videos/'); // Ou 'assets/videos/ads/' si vous avez un sous-dossier 'ads'

// Texte alternatif par défaut pour les GIFs publicitaires
define('DEFAULT_AD_GIF_ALT_TEXT', 'Publicité animée EigaNights');
;

if (!defined('ALLOWED_API_REGIONS')) {
  define('ALLOWED_API_REGIONS', ['FR', 'US']);
}
define('STREAMING_PLATFORMS_OFFICIAL_LINKS', [
    8 => [
        'name' => 'Netflix',
        'logo' => 'assets/images/netflix_logo.png',
        'search_url_pattern' => 'https://www.netflix.com/search?q={MOVIE_TITLE_URL_ENCODED}' // Usually works very well
    ],
    10 => [
        'name' => 'Amazon Prime Video',
        'logo' => 'assets/images/primevideo_logo.png', // Corrected path: assuming assets/images/primevideo_logo.png
        // Option 1: More universal Prime Video link (often redirects to local version)
        'search_url_pattern' => 'https://www.primevideo.com/search/?phrase={MOVIE_TITLE_URL_ENCODED}'
        // Option 2: Stick to French site if that's your primary target
        // 'search_url_pattern' => 'https://www.amazon.fr/s?k={MOVIE_TITLE_URL_ENCODED}&i=instant-video'
    ],
    337 => [
        'name' => 'Disney+',
        'logo' => 'assets/images/disney_logo.png',
        'search_url_pattern' => 'https://www.disneyplus.com/search?q={MOVIE_TITLE_URL_ENCODED}' // Standard search
    ],
    2 => [
        'name' => 'Apple TV',
        'logo' => 'assets/images/appletv_logo.png',
        // This pattern allows Apple to use the user's current store region or default.
        // For truly region-specific links, your PHP code would need to inject the region. See notes below.
        'search_url_pattern' => 'https://tv.apple.com/search?term={MOVIE_TITLE_URL_ENCODED}'
    ],
]);


// You can make this configurable via environment variable too if needed.
// If empty, it will try to use all regions returned by TMDB.


// 7) ReCAPTCHA & SMTP Settings (Prioritize Environment Variables)
// ─────────────────────────────────────────────────────────────────────────────
// --- Google reCAPTCHA v2 (Checkbox - typically used for registration) ---
// Get your keys from https://www.google.com/recaptcha/admin
// Set these as environment variables in Herogu: RECAPTCHA_V2_SITE_KEY and RECAPTCHA_V2_SECRET_KEY
define('RECAPTCHA_SITE_KEY_V2', getenv('RECAPTCHA_V2_SITE_KEY') ?: '6LdsyEgrAAAAAEdzcQGufogCHtE2Cx0uWN6XumUV'); // Replace placeholder
define('RECAPTCHA_SECRET_KEY_V2', getenv('RECAPTCHA_V2_SECRET_KEY') ?: 'Y6LdsyEgrAAAAAAFSQ2iSvPyPJJ7Wcz-aYfmgRHT'); // Replace placeholder

// --- Google reCAPTCHA v3 (Invisible - typically used for login, actions) ---
// Get your keys from https://www.google.com/recaptcha/admin
// Set these as environment variables in Herogu: RECAPTCHA_V3_SITE_KEY and RECAPTCHA_V3_SECRET_KEY
define('RECAPTCHA_SITE_KEY_V3', getenv('RECAPTCHA_V3_SITE_KEY') ?: '6Lfzx0grAAAAAFUvAV8GMVMXCY5kOL9CwZ_uD95z'); // Replace placeholder
define('RECAPTCHA_SECRET_KEY_V3', getenv('RECAPTCHA_V3_SECRET_KEY') ?: '6Lfzx0grAAAAAMQDdEQHpbE1YWlWCv3lXYrhAoLL'); // Replace placeholder

// --- SMTP Configuration for PHPMailer ---
// FALLBACKS HERE SHOULD BE PLACEHOLDERS OR DUMMY VALUES if this file is public.
// For actual local email sending, use a gitignored local config override or local env vars.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'quoilolaa@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'ezpcovunnnaumvlp');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'quoilolaa@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'eiganights Support');

?>