<?php
/*
 * includes/header.php
 * Common header for all pages.
 * Assumes config.php (which starts the session) has been included before this file.
 */

// --- DÉFINITION DE $logoutText ICI ---
$logoutText = "Déconnexion"; // Ou toute autre traduction que vous souhaitez
// ------------------------------------

// Page Title - should be set in the including page before this header is included.
// Default title if not set.
$currentPageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : (defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights');
$siteName = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights';

if (isset($pageTitle) && ($pageTitle === $siteName || strpos($pageTitle, $siteName) !== false)) {
    $fullTitle = $currentPageTitle;
} else {
    $fullTitle = $currentPageTitle . ' - ' . $siteName;
}

$headerSearchQuery = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8') : '';
$logoPath = BASE_URL . 'assets/images/eiganights_logov2.png'; // Ou le nom de votre fichier logo
$siteNameForDisplay = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Eiganights - Découvrez, notez et discutez de films. Créez votre watchlist et partagez avec vos amis.">
    <title><?php echo $fullTitle; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . 'assets/style.css', ENT_QUOTES, 'UTF-8'); ?>" />
    <!-- <link rel="icon" href="<?php echo htmlspecialchars(BASE_URL . 'assets/images/favicon.png', ENT_QUOTES, 'UTF-8'); ?>" type="image/png"> -->
</head>
<body>

<header class="site-header">
    <div class="header-container container">
        <div class="site-branding">
             <a href="<?php echo BASE_URL; ?>index.php" class="site-logo-link" aria-label="Page d'accueil <?php echo $siteNameForDisplay; ?>">
                <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo icône <?php echo $siteNameForDisplay; ?>" class="site-logo-image">
                <span class="site-title-header"><?php echo $siteNameForDisplay; ?></span>
             </a>
        </div>

        <nav class="main-navigation" aria-label="Navigation principale">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Accueil</a></li>
                <li><a href="<?php echo BASE_URL; ?>forum.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['forum.php', 'forum_view_thread.php', 'forum_create_thread.php']) ? 'active' : ''; ?>">Forum</a></li>
                <li><a href="<?php echo BASE_URL; ?>users_list.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : ''; ?>">Utilisateurs</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                        $messages_active_pages = ['messages.php', 'message_start_conversation.php', 'message_view_conversation.php'];
                        $is_messages_active = in_array(basename($_SERVER['PHP_SELF']), $messages_active_pages);
                    ?>
                    <a href="messages.php" class="nav-link <?php echo $is_messages_active ? 'active' : ''; ?>">Messages</a> <!-- NEW LINK -->
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin_panel.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_panel.php' ? 'active' : ''; ?>">Admin</a></li>
                    <?php endif; ?>
                    
                    <li><a href="<?php echo BASE_URL; ?>profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Mon Profil</a></li>
                    <?php // Ligne 48 (ou proche) - Utilisation de $logoutText ?>
                    <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link"><?php echo htmlspecialchars($logoutText, ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">Connexion</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-search-bar">
            <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="search-form-header" role="search">
                <label for="header-search-input" class="visually-hidden">Rechercher un film</label>
                <input type="text" id="header-search-input" name="search" placeholder="Rechercher un film..." value="<?php echo $headerSearchQuery; ?>" aria-label="Champ de recherche de film" />
                <button type="submit" class="search-button-header" aria-label="Lancer la recherche">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</header>

<div class="container page-content">
    <?php // Page content starts here ?>