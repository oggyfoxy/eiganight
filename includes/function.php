<?php // eiganights/includes/functions.php

if (!function_exists('generate_simulated_ad_slot_content')) {
    /**
     * Génère le contenu HTML pour un emplacement publicitaire simulé,
     * en choisissant un GIF aléatoire dans le dossier configuré.
     *
     * @return string Le code HTML de la publicité simulée ou une chaîne vide.
     */
    function generate_simulated_ad_slot_content() {
        // Vérifier si les publicités simulées sont activées globalement
        if (!defined('PLACEHOLDER_ADS_ENABLED') || !PLACEHOLDER_ADS_ENABLED) {
            return '';
        }

        // Vérifier si le dossier des GIFs publicitaires est configuré
        if (!defined('RANDOM_GIF_ADS_DIRECTORY') || empty(RANDOM_GIF_ADS_DIRECTORY)) {
            error_log("CONFIG ERROR: La constante RANDOM_GIF_ADS_DIRECTORY n'est pas définie ou est vide dans config.php.");
            return '<div class="placeholder-ad-content-textual"><p><strong>Erreur config pub</strong></p><p>Dossier GIFs non spécifié.</p></div>';
        }

        $gif_directory_relative = RANDOM_GIF_ADS_DIRECTORY;
        // Construire le chemin absolu sur le serveur pour lire le dossier
        $gif_directory_server_path = realpath(__DIR__ . '/../' . rtrim($gif_directory_relative, '/'));

        if (!$gif_directory_server_path || !is_dir($gif_directory_server_path)) {
            error_log("CONFIG ERROR: Dossier GIFs introuvable. Relatif: '" . $gif_directory_relative . "' Résolu en: '" . $gif_directory_server_path . "'");
            return '<div class="placeholder-ad-content-textual"><p><strong>Erreur config pub</strong></p><p>Dossier GIFs introuvable: ' . htmlspecialchars($gif_directory_relative) . '</p></div>';
        }

        // Scanner le dossier pour les fichiers .gif
        $all_files = @scandir($gif_directory_server_path);
        $gif_files = [];

        if ($all_files) {
            foreach ($all_files as $file) {
                if (is_file($gif_directory_server_path . DIRECTORY_SEPARATOR . $file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'gif') {
                    $gif_files[] = $file;
                }
            }
        }

        if (empty($gif_files)) {
            error_log("INFO: Aucun fichier .gif trouvé dans le dossier: " . $gif_directory_relative);
            return '<div class="placeholder-ad-content-textual"><p><strong>Espace Publicitaire</strong></p><p>Aucune publicité GIF disponible dans le dossier configuré.</p></div>';
        }

        $random_gif_filename = $gif_files[array_rand($gif_files)];
        $ad_path_web = rtrim(BASE_URL, '/') . '/' . rtrim($gif_directory_relative, '/') . '/' . $random_gif_filename;
        $alt_text = defined('DEFAULT_AD_GIF_ALT_TEXT') ? DEFAULT_AD_GIF_ALT_TEXT : 'Publicité animée';
        $ad_link = defined('DEFAULT_AD_GIF_LINK') ? DEFAULT_AD_GIF_LINK : null;
        $output = '';

        if ($ad_link && filter_var($ad_link, FILTER_VALIDATE_URL)) {
             $output .= '<a href="' . htmlspecialchars($ad_link) . '" target="_blank" rel="noopener sponsored nofollow" class="simulated-ad-link">';
        }
        $output .= '<img src="' . htmlspecialchars($ad_path_web) . '" alt="' . htmlspecialchars($alt_text) . '" class="placeholder-ad-image-gif">';
        if ($ad_link && filter_var($ad_link, FILTER_VALIDATE_URL)) {
            $output .= '</a>';
        }
        return $output;
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generates a CSRF token and stores it in the session.
     * @return string The generated CSRF token.
     */
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    /**
     * Validates a submitted CSRF token against the one in the session.
     * @param string $submitted_token The token from the form.
     * @return bool True if valid, false otherwise.
     */
    function validate_csrf_token($submitted_token) {
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted_token)) {
            // Token is valid, consume it to prevent reuse (optional but good for some scenarios)
            // unset($_SESSION['csrf_token']); 
            return true;
        }
        return false;
    }
}



?>