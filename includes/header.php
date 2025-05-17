<?php
/*
 * includes/header.php
 * Common header for all pages.
 * Assumes config.php (which starts the session) has been included before this file.
 */

// Determine the base path of the application for constructing asset URLs.
// This makes it easier to move the application to a subfolder or different domain.
// Note: $_SERVER['DOCUMENT_ROOT'] might not be set or appropriate in all environments.
// For simplicity in XAMPP, a relative path or a hardcoded base path might be easier if issues arise.
// $base_path = rtrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__DIR__)), '/'); // Dynamic base path
// If your project is at http://localhost/eiganights/, $base_path would be '/eiganights'
// Or, define BASE_URL in config.php and use it here:
// $base_url = defined('BASE_URL') ? BASE_URL : '/eiganights/'; // Fallback

// For simplicity with XAMPP, we'll assume assets are relative to the current file's directory's parent.
// This works if header.php is in 'eiganights/includes/' and style.css is in 'eiganights/assets/'.
$assets_path_prefix = '../assets/'; // Path from 'includes' folder to 'assets'
// If header.php is included from root files (e.g. index.php), the path is simpler:
// This dynamic check is a bit more robust if header.php location relative to root changes
// Check if the current script is in the root or a subdirectory like 'includes'
if (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === basename(dirname(__DIR__))) { // Current script is in root
    $assets_path_prefix = 'assets/';
} else { // Current script is likely in a subdirectory (like 'includes')
    $assets_path_prefix = '../assets/';
     // Or, for more robustness if you have deeper structures:
    // $assets_path_prefix = str_repeat('../', substr_count(dirname($_SERVER['SCRIPT_FILENAME']), DIRECTORY_SEPARATOR) - substr_count(dirname(__DIR__), DIRECTORY_SEPARATOR)) . 'assets/';
}
// A simpler hardcoded approach if always included from root or one level down:
// If included from root (index.php): $css_path = "assets/style.css";
// If included from includes/ (this file): $css_path = "../assets/style.css";
// Given most files are in root, let's assume 'assets/style.css' is the common case for files including this.
// For files within 'includes/' including other files, relative paths are tricky.
// Best is to define a BASE_URL in config.php and use absolute paths for assets.
// For now, using a simple relative path assuming header is included from root level files mostly.
$css_path = "assets/style.css"; // This will work if header.php is included from index.php, profile.php etc.

// Page Title - should be set in the including page before this header is included.
// Default title if not set.
$currentPageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'Eiganights';
$siteName = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights';
if (isset($pageTitle) && strpos($pageTitle, $siteName) === false) { // Avoid "Site - Site"
    $fullTitle = $currentPageTitle . ' - ' . $siteName;
} else {
    $fullTitle = $currentPageTitle;
}

// Character encoding fix for "Déconnexion" - ensure PHP files are saved as UTF-8
$logoutText = "Déconnexion";
// Test if mbstring is available for proper case conversion with UTF-8
if (function_exists('mb_convert_case')) {
    // Example: $logoutText = mb_convert_case($logoutText, MB_CASE_TITLE, "UTF-8");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Eiganights - Découvrez, notez et discutez de films. Créez votre watchlist et partagez avec vos amis.">
    <title><?php echo $fullTitle; ?></title>
    <?php /*
        A more robust way for asset paths, especially if your site structure is complex or might change:
        Define BASE_ASSET_URL in config.php: define('BASE_ASSET_URL', '/eiganights/assets/');
        Then use it: <link rel="stylesheet" href="<?php echo BASE_ASSET_URL; ?>style.css" />
        For now, assuming simple relative path from root files.
    */ ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css_path, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php // Add more meta tags, favicon links, etc. here if needed ?>
    <!-- <link rel="icon" href="<?php echo htmlspecialchars($assets_path_prefix, ENT_QUOTES, 'UTF-8'); ?>images/favicon.ico" type="image/x-icon"> -->
</head>
<body>

<header class="site-header">
    <nav class="main-navigation">
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Accueil</a>
        <a href="forum.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'forum.php' || basename($_SERVER['PHP_SELF']) == 'forum_view_thread.php' || basename($_SERVER['PHP_SELF']) == 'forum_create_thread.php' ? 'active' : ''; ?>">Forum</a> 
        <a href="users_list.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : ''; ?>">Utilisateurs</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_panel.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_panel.php' ? 'active' : ''; ?>">Admin Panel</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Mon Profil</a>
            <a href="logout.php" class="nav-link"><?php echo htmlspecialchars($logoutText, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php else: ?>
            <a href="login.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">Connexion</a>
            <a href="register.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">Inscription</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container page-content"> <?php // Renamed original .container to .page-content for clarity, keeping .container for overall width constraint.
                                     // Or, just use one .container directly under body and remove this one if header is not full-width.
                                     // Assuming the nav bar might be full width, and .container page-content is centered within.
?>
    <?php // A good place for site-wide persistent messages, if not handled per-page ?>
    <?php /*
    if (!empty($_SESSION['global_message'])) {
        echo '<div class="alert alert-info global-message">' . htmlspecialchars($_SESSION['global_message']) . '</div>';
        unset($_SESSION['global_message']);
    }
    */?>
