<?php
// eiganights/includes/functions.php

// Ensure session is started (config.php should do this, but good to double-check context)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback for BASE_URL if config.php wasn't included first (not ideal, but defensive)
if (!defined('BASE_URL')) {
    // ... (your existing BASE_URL fallback logic from functions.php) ...
}


// --- CSRF Token Functions ---

/**
 * Generates a CSRF token and stores it in the session.
 * If a token already exists in the session, it returns that one
 * to ensure the same token is used for all forms on a page load.
 *
 * @return string The CSRF token.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token against the one stored in the session.
 * To prevent timing attacks, use hash_equals.
 *
 * @param string $submitted_token The token submitted with the form.
 * @return bool True if the token is valid, false otherwise.
 */
function validate_csrf_token($submitted_token) {
    if (isset($_SESSION['csrf_token']) && !empty($submitted_token)) {
        if (hash_equals($_SESSION['csrf_token'], $submitted_token)) {
            // Token is valid, consume it by unsetting it for one-time use per request cycle (optional but good)
            // If you want tokens to be valid for multiple forms on one page load until next generation,
            // then don't unset it here, but rather upon successful processing of a specific form.
            // For simplicity of one-time use per form submission:
            // unset($_SESSION['csrf_token']); // See note below
            return true;
        }
    }
    return false;
}

// Note on unsetting CSRF token in validate_csrf_token():
// If you unset the token immediately after validation, and a form submission fails validation
// for other reasons (e.g., empty field), when the form is re-displayed, it will get a *new*
// CSRF token from generate_csrf_token(). This is generally fine.
// If you need a token to persist across multiple failed attempts of the *same* form instance
// without page reload, you'd handle unsetting it more carefully (e.g., only after successful action).
// For most POST-Redirect-Get patterns, unsetting after validation or upon successful action is okay.
// The current generate_csrf_token() ensures the same token is output for the entire page load.

// --- End CSRF Token Functions ---


/**
 * Generates HTML for a simulated ad slot, picking a random ad.
 * ... (your existing generate_simulated_ad_slot_content function) ...
 */
function generate_simulated_ad_slot_content($adType = 'random_gif') {
    // ... (your ad function code) ...
    // Make sure BASE_URL is defined or has a fallback if called before config.php
    $imageAds = [ /* ... */ ];
    $gifAds = [ /* ... */ ];

    $chosenAdFile = null;
    $adBasePath = '';
    $altText = "Publicité simulée";
    $isImage = false;

    if ($adType === 'random_gif' && !empty($gifAds)) {
        $chosenAdFile = $gifAds[array_rand($gifAds)];
        $adBasePath = (defined('BASE_URL') ? BASE_URL : '') . 'assets/videos/';
        $isImage = true;
    } elseif ($adType === 'random_image' && !empty($imageAds)) {
        $chosenAdFile = $imageAds[array_rand($imageAds)];
        $adBasePath = (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/';
        $isImage = true;
    } else {
        if (!empty($gifAds)) {
            $chosenAdFile = $gifAds[array_rand($gifAds)];
            $adBasePath = (defined('BASE_URL') ? BASE_URL : '') . 'assets/videos/';
            $isImage = true;
        } elseif (!empty($imageAds)) {
            $chosenAdFile = $imageAds[array_rand($imageAds)];
            $adBasePath = (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/';
            $isImage = true;
        }
    }

    if ($chosenAdFile && $isImage) {
        $adUrl = $adBasePath . rawurlencode($chosenAdFile);
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
    return '<div class="placeholder-ad-content-textual"><p>Publicité</p></div>';
}

?>