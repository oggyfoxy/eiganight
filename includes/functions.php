<?php
// eiganights/includes/functions.php

if (!defined('BASE_URL')) {
    // This is a fallback in case functions.php is included before config.php
    // or if BASE_URL isn't set, though config.php should always be first.
    // A more robust solution would be to ensure config.php is always included first.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] . '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($scriptDir === '/') $scriptDir = ''; // Avoid double slash if at root
    define('BASE_URL', $protocol . $domainName . ltrim($scriptDir, '/') . (substr($scriptDir, -1) === '/' ? '' : '/'));
    // This fallback BASE_URL might need adjustment if your project is in a subdirectory AND
    // you access files not from the project root. For simplicity, ensure config.php is always included first.
}


/**
 * Generates HTML for a simulated ad slot, picking a random ad.
 *
 * @param string $adType Type of ad, e.g., 'banner', 'sidebar', 'product_text'.
 *                       This is just for demonstration and can be expanded.
 * @return string HTML content for the ad.
 */
function generate_simulated_ad_slot_content($adType = 'random_gif') {
    // Define lists of your ad assets
    // Ensure these filenames exist in the specified paths!
    $imageAds = [
        // From assets/images/
        'appletv_logo.png',
        'disney_logo.png',
        'eiganights_logo.png', // Assuming you have one
        'hbomax_logo.png',
        'hulu_logo.png',
        'netflix_logo.png',
        'paramount_logo.png',
        'primevideo_logo.png',
        'youtubepremium_logo.png'
    ];

    $gifAds = [
        // From assets/videos/ (or a subfolder if you prefer, adjust path below)
        // Make sure these exact filenames are present and case-matches!
        'Apple Tv GIF by f...', // Full filename needed, e.g., 'Apple Tv GIF by fallontonight.gif'
        'comedy central.gif',
        'Digital Marketing...', // Full filename, e.g., 'Digital Marketing Ad.gif'
        'disaster_artist_ad.gif',
        'download (1).gif',
        'download (2).gif',
        'Fail Empire Strike...', // Full filename
        'filmdoo_ad.gif',
        'Food Porn GIF.gif',
        'Get A Job GIF.gif',
        'home rob.gif',
        'Hot Sauce Pizza G...', // Full filename
        'Hungry Beyond ...',   // Full filename
        'Hungry Hot Wing.gif',
        'Not Working Get .gif',// Full filename
        'Sale Working GIF.gif',
        'Social Media Mar.gif',// Full filename
        'south_park_ad.gif',
        'Unemployment N.gif'   // Full filename
    ];

    // --- IMPORTANT: Update the array above with your EXACT and FULL filenames ---
    // Example of corrected full filenames (you need to verify your actual files):
    /*
    $gifAds = [
        'Apple Tv GIF by fallontonight.gif',
        'comedy central.gif',
        'Digital Marketing Ad.gif',
        'disaster_artist_ad.gif',
        'download (1).gif',
        'download (2).gif',
        'Fail Empire Strikes Back.gif',
        'filmdoo_ad.gif',
        'Food Porn GIF.gif',
        'Get A Job GIF.gif',
        'home rob.gif',
        'Hot Sauce Pizza GIF.gif',
        'Hungry Beyond GIF.gif',
        'Hungry Hot Wing.gif',
        'Not Working Get GIF.gif',
        'Sale Working GIF.gif',
        'Social Media Marketing GIF.gif',
        'south_park_ad.gif',
        'Unemployment N GIF.gif'
    ];
    */


    $chosenAdFile = null;
    $adBasePath = '';
    $altText = "Publicité simulée";
    $isImage = false;

    // Simple logic to pick an ad type. Can be made more sophisticated.
    if ($adType === 'random_gif' && !empty($gifAds)) {
        $chosenAdFile = $gifAds[array_rand($gifAds)];
        $adBasePath = BASE_URL . 'assets/videos/'; // Assuming GIFs are in assets/videos/
        $isImage = true; // Treat GIF as an image for the <img> tag
    } elseif ($adType === 'random_image' && !empty($imageAds)) {
        $chosenAdFile = $imageAds[array_rand($imageAds)];
        $adBasePath = BASE_URL . 'assets/images/';
        $isImage = true;
    } else { // Fallback or if specific type not found, try a GIF then an image
        if (!empty($gifAds)) {
            $chosenAdFile = $gifAds[array_rand($gifAds)];
            $adBasePath = BASE_URL . 'assets/videos/';
            $isImage = true;
        } elseif (!empty($imageAds)) {
            $chosenAdFile = $imageAds[array_rand($imageAds)];
            $adBasePath = BASE_URL . 'assets/images/';
            $isImage = true;
        }
    }

    if ($chosenAdFile && $isImage) {
        $adUrl = $adBasePath . rawurlencode($chosenAdFile); // Use rawurlencode for filenames with spaces or special chars
        return '<div class="simulated-ad-content ad-banner">
                    <a href="#" onclick="return false;" title="Publicité simulée - Non cliquable">
                        <img src="' . htmlspecialchars($adUrl) . '" alt="' . htmlspecialchars($altText) . '" class="placeholder-ad-image-gif">
                        <span class="ad-sponsored-tag">Publicité</span>
                    </a>
                </div>';
    } elseif ($adType === 'text_placeholder') {
        return '<div class="placeholder-ad-content-textual">
                    <p><strong>Espace Publicitaire</strong></p>
                    <p>Contenu sponsorisé ici.</p>
                </div>';
    }

    return '<div class="placeholder-ad-content-textual"><p>Publicité</p></div>'; // Default fallback
}
?>