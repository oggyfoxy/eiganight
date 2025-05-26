<?php

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

if (session_status() === PHP_SESSION_NONE) {
    $isHttpsOnServer = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isHttpsOnServer, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('MYSQL_PORT') ?: 3306));
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'eiganights');
define('DB_USER', getenv('MYSQL_USER') ?: 'root');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: ""); 

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$conn = $mysqli;
if ($mysqli->connect_errno) {
    $db_error_message = "Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    error_log($db_error_message . " | Configured Host: " . DB_HOST . ", User: " . DB_USER . ", DB: " . DB_NAME);
    if (!$isProduction) {
        die($db_error_message . ". Please check your config.php fallbacks and ensure your local DB server is running.");
    } else {
        die("A critical database error occurred. Please try again later. (Error Ref: DB_CONN_PROD)");
    }
}
$mysqli->set_charset('utf8mb4');

define('TMDB_API_KEY', getenv('TMDB_API_KEY') ?: 'ca76efbbd188354d4f49383014d8ed3b');

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

define('SITE_NAME', getenv('SITE_NAME') ?: 'eiganights');

define('PLACEHOLDER_ADS_ENABLED', getenv('PLACEHOLDER_ADS_ENABLED') === 'true' ?: true);

define('DIRECT_STREAMING_LINKS_ENABLED', getenv('DIRECT_STREAMING_LINKS_ENABLED') === 'true' ?: true);


define('RANDOM_GIF_ADS_DIRECTORY', 'assets/videos/');

define('DEFAULT_AD_GIF_ALT_TEXT', 'Publicité animée EigaNights');
;

if (!defined('ALLOWED_API_REGIONS')) {
  define('ALLOWED_API_REGIONS', ['FR', 'US']);
}
define('STREAMING_PLATFORMS_OFFICIAL_LINKS', [
    8 => [
        'name' => 'Netflix',
        'logo' => 'assets/images/netflix_logo.png',
        'search_url_pattern' => 'https://www.netflix.com/search?q={MOVIE_TITLE_URL_ENCODED}'
    ],
    10 => [
        'name' => 'Amazon Prime Video',
        'logo' => 'assets/images/primevideo_logo.png',
        'search_url_pattern' => 'https://www.primevideo.com/search/?phrase={MOVIE_TITLE_URL_ENCODED}'
    ],
    337 => [
        'name' => 'Disney+',
        'logo' => 'assets/images/disney_logo.png',
        'search_url_pattern' => 'https://www.disneyplus.com/search?q={MOVIE_TITLE_URL_ENCODED}'
    ],
    2 => [
        'name' => 'Apple TV',
        'logo' => 'assets/images/appletv_logo.png',
        'search_url_pattern' => 'https://tv.apple.com/search?term={MOVIE_TITLE_URL_ENCODED}'
    ],
]);


define('RECAPTCHA_SITE_KEY_V3', getenv('RECAPTCHA_V3_SITE_KEY') ?: '6Lfzx0grAAAAAFUvAV8GMVMXCY5kOL9CwZ_uD95z');
define('RECAPTCHA_SECRET_KEY_V3', getenv('RECAPTCHA_V3_SECRET_KEY') ?: '6Lfzx0grAAAAAMQDdEQHpbE1YWlWCv3lXYrhAoLL');

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'quoilolaa@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'ezpcovunnnaumvlp');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'quoilolaa@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'eiganights Support');

?>