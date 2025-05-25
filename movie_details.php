<?php
/*
 * movie_details.php
 * Affiche les informations détaillées d'un film.
 * Gère la récupération des données depuis TMDB et la base de données locale.
 * Inclut des fonctionnalités de monétisation simulées pour un projet scolaire.
 */
require_once 'config.php'; // Contient les constantes, la connexion DB
require_once 'includes/functions.php'; // Contient generate_simulated_ad_slot_content()

// -----------------------------------------------------------------------------
// 1. INITIALISATION & VALIDATION DES PARAMÈTRES
// -----------------------------------------------------------------------------
$movieId = null;
$loggedInUserId = $_SESSION['user_id'] ?? null;
$pageError = null;
$siteNameForTitle = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : "Eiganights";
$pageTitle = $siteNameForTitle; // Default page title

$movieDetailsAPI = null;
$movieCreditsAPI = null;
$movieVideosAPI = null;
$movieWatchProvidersAPI = null; // For streaming links

$isInWatchlist = false;
$userRating = null;
$userCommentText = '';
$publicComments = [];
$sceneAnnotationThreads = [];

// Critical API Key Check
if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY) || TMDB_API_KEY === 'YOUR_ACTUAL_TMDB_API_KEY' || TMDB_API_KEY === 'your_local_fallback_tmdb_key') {
    error_log("TMDB_API_KEY n'est pas configurée correctement dans config.php ou via les variables d'environnement.");
    $pageError = "Erreur de configuration critique du site. Veuillez contacter l'administrateur.";
    $pageTitle = "Erreur de Configuration - " . $siteNameForTitle;
}

// Validate Movie ID
if (!$pageError) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
        $pageError = "ID de film invalide ou manquant.";
        if ($loggedInUserId && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            // Admin might see error on page, others redirect for better UX
        } elseif (!headers_sent()) {
            $_SESSION['error'] = "Film non trouvé ou ID incorrect."; // More user-friendly
            header("Location: " . BASE_URL . "index.php");
            exit;
        }
        $pageTitle = "Erreur - Film Non Trouvé - " . $siteNameForTitle;
    } else {
        $movieId = (int)$_GET['id'];
    }
}

// -----------------------------------------------------------------------------
// 2. RÉCUPÉRATION DES DONNÉES TMDB
// -----------------------------------------------------------------------------
if (!$pageError && $movieId) {
    $tmdbApiKey = urlencode(TMDB_API_KEY);
    // Fetch details, credits, videos, and watch providers in one call
    $detailsUrl = "https://api.themoviedb.org/3/movie/{$movieId}?api_key={$tmdbApiKey}&language=fr-FR&append_to_response=credits,videos,watch/providers";

    // Re-define or ensure fetch_tmdb_data_from_url is available
    if (!function_exists('fetch_tmdb_data_from_url')) {
        function fetch_tmdb_data_from_url($url) {
            $context_options = [
                "http" => [
                    "method" => "GET",
                    "header" => "Accept: application/json\r\n",
                    "ignore_errors" => true // To get error response body
                ]
            ];
            $context = stream_context_create($context_options);
            $responseJson = @file_get_contents($url, false, $context);

            $http_code = null;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                        $http_code = (int)$matches[1];
                        break;
                    }
                }
            }

            if ($responseJson === false) {
                return ['error' => "Impossible de contacter le service de films (TMDB Network Error).", 'data' => null, 'http_code' => $http_code];
            }

            $data = json_decode($responseJson, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id']) || ($http_code && $http_code >= 400 && $http_code !== 404) ) {
                 // Allow 404 to be handled specifically
                $apiError = "Erreur lors de la récupération des informations du film depuis TMDB.";
                if (isset($data['status_message'])) {
                    $apiError = "TMDB: " . htmlspecialchars($data['status_message']);
                } elseif ($http_code === 401) {
                    $apiError = "Clé API TMDB invalide ou non autorisée. Vérifiez la configuration.";
                }
                return ['error' => $apiError, 'data' => $data, 'http_code' => $http_code];
            }
             if ($http_code === 404 || (isset($data['success']) && $data['success'] === false && isset($data['status_code']) && $data['status_code'] == 34) ){ // status_code 34 is "Resource not found"
                return ['error' => "Film non trouvé avec l'ID fourni.", 'data' => null, 'http_code' => 404];
            }
            return ['error' => null, 'data' => $data, 'http_code' => $http_code];
        }
    }

    $tmdbResult = fetch_tmdb_data_from_url($detailsUrl);

    if ($tmdbResult['error']) {
        error_log("Erreur TMDB pour MovieID {$movieId}: " . $tmdbResult['error'] . " (Code HTTP: " . ($tmdbResult['http_code'] ?? 'N/A') . ") URL: " . $detailsUrl);
        $pageError = $tmdbResult['error']; // Set pageError to the specific error from TMDB
        // If movie not found (404), we might still want to show a "not found" page rather than a generic error
        if ($tmdbResult['http_code'] === 404) {
            // $pageTitle = "Film Non Trouvé - " . $siteNameForTitle; // Title already handled if pageError is set
        }
    } else {
        $movieDetailsAPI = $tmdbResult['data'];
        $movieCreditsAPI = $movieDetailsAPI['credits'] ?? null;
        $movieVideosAPI = $movieDetailsAPI['videos']['results'] ?? null;
        $movieWatchProvidersAPI = $movieDetailsAPI['watch/providers']['results'] ?? null; // Get watch providers
        unset($movieDetailsAPI['credits'], $movieDetailsAPI['videos'], $movieDetailsAPI['watch/providers']); // Clean up main array
    }
}


// -----------------------------------------------------------------------------
// 3. RÉCUPÉRATION DES DONNÉES LOCALES (Watchlist, Ratings, Comments, Annotations)
// -----------------------------------------------------------------------------
if (!$pageError && $movieDetailsAPI && $movieId) { // Only proceed if TMDB data was fetched successfully
    if ($loggedInUserId) {
        // Watchlist
        $stmt = $conn->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND movie_id = ?");
        if ($stmt) { $stmt->bind_param("ii", $loggedInUserId, $movieId); $stmt->execute(); $stmt->store_result(); $isInWatchlist = ($stmt->num_rows > 0); $stmt->close(); }
        else { error_log("DB Prepare Error (MD_WL_CHK): " . $conn->error); }

        // User Rating
        $stmt = $conn->prepare("SELECT rating FROM ratings WHERE user_id = ? AND movie_id = ?");
        if ($stmt) { $stmt->bind_param("ii", $loggedInUserId, $movieId); $stmt->execute(); $result = $stmt->get_result(); if ($row = $result->fetch_assoc()) $userRating = (int)$row['rating']; $stmt->close(); }
        else { error_log("DB Prepare Error (MD_URAT_SEL): " . $conn->error); }
        
        // User Comment
        $stmt = $conn->prepare("SELECT comment FROM comments WHERE user_id = ? AND movie_id = ? ORDER BY commented_at DESC LIMIT 1");
        if ($stmt) { $stmt->bind_param("ii", $loggedInUserId, $movieId); $stmt->execute(); $result = $stmt->get_result(); if ($row = $result->fetch_assoc()) $userCommentText = $row['comment']; $stmt->close(); }
        else { error_log("DB Prepare Error (MD_UCOM_SEL): " . $conn->error); }
    }

    // Public Comments
    $stmt = $conn->prepare("SELECT c.comment, c.commented_at, u.username, u.id as comment_user_id FROM comments c JOIN users u ON c.user_id = u.id WHERE c.movie_id = ? ORDER BY c.commented_at DESC");
    if ($stmt) { $stmt->bind_param("i", $movieId); $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $publicComments[] = $row; $stmt->close(); }
    else { error_log("DB Prepare Error (MD_PCOM_SEL): " . $conn->error); }

    // Scene Annotation Threads
    $stmt = $conn->prepare("SELECT ft.id, ft.title, ft.scene_start_time, ft.scene_description_short, u.username as author_username, u.id as author_id, ft.created_at FROM forum_threads ft JOIN users u ON ft.user_id = u.id WHERE ft.movie_id = ? AND (ft.scene_start_time IS NOT NULL OR ft.scene_description_short IS NOT NULL) ORDER BY ft.created_at DESC");
    if ($stmt) { $stmt->bind_param("i", $movieId); $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $sceneAnnotationThreads[] = $row; $stmt->close(); }
    else { error_log("DB Prepare Error (MD_SCENE_THREADS_SEL): " . $conn->error); }
}

// -----------------------------------------------------------------------------
// 4. PRÉPARATION DES DONNÉES POUR L'AFFICHAGE (Titles, URLs, Formatted Text)
// -----------------------------------------------------------------------------
if ($pageError && !$movieDetailsAPI) { // Critical error, TMDB data totally missing
    $displayTitle = "Erreur de Chargement";
    $pageTitle = "Erreur - " . $siteNameForTitle; // pageTitle might have been set by ID validation
    $posterAltText = "Erreur";
} elseif ($movieDetailsAPI && !empty($movieDetailsAPI['title'])) {
    $displayTitle = htmlspecialchars($movieDetailsAPI['title'], ENT_QUOTES, 'UTF-8');
    $pageTitle = $displayTitle . " - " . $siteNameForTitle;
    $posterAltText = "Affiche de " . $displayTitle;
} else { // No API data, but no critical $pageError was set (e.g. movie ID valid but TMDB call failed silently before)
    $displayTitle = "Détails du Film Indisponibles";
    $pageTitle = $displayTitle . " - " . $siteNameForTitle;
    $posterAltText = "Pas d'affiche disponible";
    if (!$pageError) $pageError = "Les informations complètes de ce film n'ont pas pu être chargées actuellement."; // Soft error
}

$posterPath = $movieDetailsAPI['poster_path'] ?? null;
$posterUrl = $posterPath ? "https://image.tmdb.org/t/p/w500" . htmlspecialchars($posterPath, ENT_QUOTES, 'UTF-8') : BASE_URL . "assets/images/no_poster_available.png";

$releaseYear = !empty($movieDetailsAPI['release_date']) ? substr($movieDetailsAPI['release_date'], 0, 4) : 'N/A';
$tagline = !empty($movieDetailsAPI['tagline']) ? htmlspecialchars($movieDetailsAPI['tagline'], ENT_QUOTES, 'UTF-8') : '';
$genres = !empty($movieDetailsAPI['genres']) ? htmlspecialchars(implode(', ', array_column($movieDetailsAPI['genres'], 'name')), ENT_QUOTES, 'UTF-8') : 'N/A';
$runtimeMinutes = $movieDetailsAPI['runtime'] ?? 0;
$runtime = $runtimeMinutes > 0 ? $runtimeMinutes . ' minutes' : 'N/A';
$tmdbVoteAverage = !empty($movieDetailsAPI['vote_average']) && $movieDetailsAPI['vote_average'] > 0 ? number_format($movieDetailsAPI['vote_average'], 1) . '/10' : 'N/A';
$tmdbVoteCount = (int)($movieDetailsAPI['vote_count'] ?? 0);
$overview = !empty($movieDetailsAPI['overview']) ? nl2br(htmlspecialchars($movieDetailsAPI['overview'], ENT_QUOTES, 'UTF-8')) : 'Synopsis non disponible.';

$trailerKey = null;
if (!empty($movieVideosAPI)) {
    foreach ($movieVideosAPI as $video) {
        if (isset($video['site'], $video['type'], $video['key']) && strtolower($video['site']) === 'youtube' && strtolower($video['type']) === 'trailer' && !empty($video['key'])) {
            $trailerKey = htmlspecialchars($video['key'], ENT_QUOTES, 'UTF-8');
            break;
        }
    }
}

$directors = [];
if (!empty($movieCreditsAPI['crew'])) {
    foreach ($movieCreditsAPI['crew'] as $crewMember) {
        if (isset($crewMember['job'], $crewMember['name']) && $crewMember['job'] === 'Director' && !empty($crewMember['name'])) {
            $directors[] = htmlspecialchars($crewMember['name'], ENT_QUOTES, 'UTF-8');
        }
    }
}
$directorsFormatted = !empty($directors) ? implode(', ', $directors) : 'N/A';
$cast = !empty($movieCreditsAPI['cast']) ? array_slice($movieCreditsAPI['cast'], 0, 10) : []; // Top 10 cast

// -----------------------------------------------------------------------------
// DÉBUT DE L'AFFICHAGE HTML
// -----------------------------------------------------------------------------
include_once 'includes/header.php'; // $pageTitle is now set
?>

<main class="container movie-detail-page" role="main">

    <?php /* Session Message Display */ ?>
    <?php foreach (['message', 'error', 'warning', 'rate_comment_message', 'rate_comment_error', 'rate_comment_warning'] as $msgKey): ?>
        <?php if (!empty($_SESSION[$msgKey])): ?>
            <div class="alert <?php echo strpos($msgKey, 'error') !== false ? 'alert-danger' : (strpos($msgKey, 'warning') !== false ? 'alert-warning' : 'alert-success'); ?>" role="alert">
                <?php echo htmlspecialchars($_SESSION[$msgKey]); unset($_SESSION[$msgKey]); ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php /* Page Level Error Display (if not a full stop error) */ ?>
    <?php if ($pageError && !$movieDetailsAPI): // Critical error, no TMDB data at all ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($pageError); ?></div>
        <p><a href="<?php echo BASE_URL; ?>index.php">Retour à l'accueil</a></p>
    <?php else: // We have at least some $movieDetailsAPI or a non-critical $pageError ?>

        <?php if ($pageError && $movieDetailsAPI): // TMDB data loaded, but there was some other error (e.g. API sub-request failed) ?>
             <div class="alert alert-warning" role="alert">
                <?php echo htmlspecialchars($pageError); ?>
                <br><small>Certaines informations pourraient être incomplètes, mais les détails de base du film sont affichés ci-dessous.</small>
            </div>
        <?php endif; ?>

        <?php if ($movieDetailsAPI): // Proceed to display movie details if we have them ?>
        <article class="movie-details-content card" aria-labelledby="movie-title-heading">
            <header class="movie-main-header">
                <div class="movie-poster">
                    <img src="<?php echo $posterUrl; ?>" alt="<?php echo htmlspecialchars($posterAltText, ENT_QUOTES, 'UTF-8'); ?>" class="movie-detail-poster" loading="lazy">
                </div>
                <div class="movie-meta-info">
                    <h1 id="movie-title-heading"><?php echo $displayTitle; ?> <span class="release-year">(<?php echo $releaseYear; ?>)</span></h1>
                    <?php if ($tagline): ?><p class="tagline"><em><?php echo $tagline; ?></em></p><?php endif; ?>
                    
                    <div class="meta-grid">
                        <p><strong>Genres:</strong> <?php echo $genres; ?></p>
                        <p><strong>Durée:</strong> <?php echo $runtime; ?></p>
                        <p><strong>Note TMDB:</strong> <?php echo $tmdbVoteAverage; ?> (<?php echo number_format($tmdbVoteCount); ?> votes)</p>
                        <p><strong>Réalisateur(s):</strong> <?php echo $directorsFormatted; ?></p>
                    </div>

                    <?php // Section Liens de Streaming (Watch Providers)
                    if (defined('DIRECT_STREAMING_LINKS_ENABLED') && DIRECT_STREAMING_LINKS_ENABLED && !empty($movieWatchProvidersAPI) && defined('STREAMING_PLATFORMS_OFFICIAL_LINKS') && is_array(STREAMING_PLATFORMS_OFFICIAL_LINKS)) {
                        $availableStreams = [];
                        // Use regions defined in config, or all available if not defined or empty
                        $targetRegions = (defined('ALLOWED_API_REGIONS') && is_array(ALLOWED_API_REGIONS) && !empty(ALLOWED_API_REGIONS))
                                         ? ALLOWED_API_REGIONS
                                         : array_keys($movieWatchProvidersAPI);

                        foreach ($targetRegions as $regionCode) {
                            if (isset($movieWatchProvidersAPI[strtoupper($regionCode)])) { // TMDB regions are uppercase
                                $regionData = $movieWatchProvidersAPI[strtoupper($regionCode)];
                                $providerTypesToShow = ['flatrate', 'ads', 'rent', 'buy']; // Order of preference or types to show

                                foreach ($providerTypesToShow as $type) {
                                    if (isset($regionData[$type]) && is_array($regionData[$type])) {
                                        foreach ($regionData[$type] as $provider) {
                                            if (isset($provider['provider_id'], STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']])) {
                                                $platformInfo = STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']];
                                                // Construct a search URL, not a direct play link
                                                $searchUrl = str_replace('{MOVIE_TITLE_URL_ENCODED}', urlencode($movieDetailsAPI['title']), $platformInfo['search_url_pattern']);
                                                
                                                $streamKey = $provider['provider_id'] . '-' . strtoupper($regionCode) . '-' . $type;
                                                if (!isset($availableStreams[$streamKey])) { // Avoid duplicates if a provider offers multiple types
                                                    $availableStreams[$streamKey] = [
                                                        'name' => htmlspecialchars($platformInfo['name'], ENT_QUOTES, 'UTF-8'),
                                                        'logo' => BASE_URL . htmlspecialchars($platformInfo['logo'], ENT_QUOTES, 'UTF-8'),
                                                        'url' => htmlspecialchars($searchUrl, ENT_QUOTES, 'UTF-8'),
                                                        'region' => htmlspecialchars(strtoupper($regionCode), ENT_QUOTES, 'UTF-8'),
                                                        'type' => htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8') // e.g., Flatrate, Rent
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($availableStreams)): ?>
                        <section class="movie-streaming-options" aria-labelledby="streaming-options-heading">
                            <h3 id="streaming-options-heading">Où regarder (Liens de recherche) :</h3>
                            <div class="streaming-providers-list">
                                <?php foreach ($availableStreams as $streamInfo): ?>
                                    <a href="<?php echo $streamInfo['url']; ?>" target="_blank" rel="noopener noreferrer nofollow sponsored" class="streaming-provider-link" title="Rechercher <?php echo $displayTitle; ?> sur <?php echo $streamInfo['name']; ?> (<?php echo $streamInfo['region']; ?> - <?php echo $streamInfo['type']; ?>)">
                                        <img src="<?php echo $streamInfo['logo']; ?>" alt="Logo <?php echo $streamInfo['name']; ?>">
                                        <span><?php echo $streamInfo['name']; ?> <small>(<?php echo $streamInfo['region']; ?> - <?php echo $streamInfo['type']; ?>)</small></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <small class="affiliate-disclosure">Note: Ces liens effectuent une recherche sur les plateformes. La disponibilité et les conditions peuvent varier. Simulation pour projet scolaire.</small>
                        </section>
                        <?php elseif (defined('DIRECT_STREAMING_LINKS_ENABLED') && DIRECT_STREAMING_LINKS_ENABLED): ?>
                        <section class="movie-streaming-options">
                            <p><small>Aucune option de visionnage direct trouvée via TMDB pour ce film dans les régions configurées.</small></p>
                        </section>
                        <?php endif;
                    } // End of DIRECT_STREAMING_LINKS_ENABLED check
                    ?>

                    <div class="movie-user-actions">
                        <?php if ($loggedInUserId): ?>
                            <form method="POST" action="<?php echo $isInWatchlist ? (BASE_URL.'remove_from_watchlist.php') : (BASE_URL.'add.php'); ?>" class="inline-form">
                                <input type="hidden" name="movie_id" value="<?php echo $movieId; ?>">
                                <input type="hidden" name="movie_title" value="<?php echo htmlspecialchars($movieDetailsAPI['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="poster_path" value="<?php echo htmlspecialchars($posterPath ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="button <?php echo $isInWatchlist ? 'button-danger' : 'button-primary'; ?>">
                                    <?php echo $isInWatchlist ? '➖ Retirer Watchlist' : '➕ Ajouter Watchlist'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="login-prompt-actions"><a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour gérer votre watchlist ou noter.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="movie-content-columns">
                <div class="main-column">
                    <?php if ($overview !== 'Synopsis non disponible.'): ?>
                    <section class="movie-synopsis card-section" aria-labelledby="synopsis-heading">
                        <h2 id="synopsis-heading">Synopsis</h2>
                        <p><?php echo $overview; ?></p>
                    </section>
                    <?php endif; ?>

                    <?php if ($trailerKey): ?>
                    <section class="movie-trailer card-section" aria-labelledby="trailer-heading">
                        <h2 id="trailer-heading">Bande-annonce</h2>
                        <div class="trailer-container">
                            <iframe src="https://www.youtube.com/embed/<?php echo $trailerKey; ?>" 
                                    title="Bande-annonce YouTube pour <?php echo $displayTitle; ?>"
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen loading="lazy"></iframe>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($cast)): ?>
                    <section class="movie-cast card-section" aria-labelledby="cast-heading">
                        <h2 id="cast-heading">Acteurs Principaux</h2>
                        <div class="cast-list">
                            <?php foreach ($cast as $actor):
                                $actorName = htmlspecialchars($actor['name'] ?? 'Nom inconnu', ENT_QUOTES, 'UTF-8');
                                $actorChar = htmlspecialchars($actor['character'] ?? 'Rôle inconnu', ENT_QUOTES, 'UTF-8');
                                $actorPhotoUrl = !empty($actor['profile_path']) 
                                            ? 'https://image.tmdb.org/t/p/w185' . htmlspecialchars($actor['profile_path'], ENT_QUOTES, 'UTF-8')
                                            : BASE_URL . 'assets/images/no_actor_photo.png';
                            ?>
                            <figure class="actor">
                                <img src="<?php echo $actorPhotoUrl; ?>" alt="Photo de <?php echo $actorName; ?>" loading="lazy">
                                <figcaption>
                                    <strong><?php echo $actorName; ?></strong><br>
                                    <small>en <?php echo $actorChar; ?></small>
                                </figcaption>
                            </figure>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <aside class="sidebar-column">
                    <?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED && function_exists('generate_simulated_ad_slot_content')): ?>
                    <section class="advertisement-slot card-section sticky-sidebar-ad" role="complementary" aria-label="Espace publicitaire sidebar (simulation)">
                        <?php echo generate_simulated_ad_slot_content('random_gif'); // Explicitly request a GIF or image
                        ?>
                    </section>
                    <?php endif; ?>

                    <?php if ($loggedInUserId): ?>
                    <section class="user-interaction-section card-section card" aria-labelledby="user-rating-comment-heading">
                        <h2 id="user-rating-comment-heading">Votre Avis</h2>
                        <form method="POST" action="<?php echo BASE_URL; ?>rate_comment.php" novalidate>
                            <input type="hidden" name="movie_id" value="<?php echo $movieId; ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <label for="rating">Votre Note (1-10):</label>
                                <select name="rating" id="rating">
                                    <option value="">-- Non Noté --</option>
                                    <?php for ($i = 10; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($userRating === $i) ? 'selected' : ''; ?>><?php echo $i; ?> ★</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="comment">Votre Commentaire:</label>
                                <textarea name="comment" id="comment" rows="5" placeholder="Laissez un commentaire..."><?php echo htmlspecialchars($userCommentText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="submit" class="button button-primary">Soumettre l'Avis</button>
                        </form>
                    </section>
                    
                    <section class="annotate-scene-action card-section card" aria-labelledby="annotate-scene-heading">
                        <h2 id="annotate-scene-heading">Discuter une Scène</h2>
                        <p>Analysez une scène de "<?php echo $displayTitle; ?>" ou posez une question.</p>
                        <a href="<?php echo BASE_URL; ?>forum_create_thread.php?movie_id=<?php echo $movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>" class="button button-secondary">
                            💬 Annoter & Démarrer une Discussion
                        </a>
                    </section>
                    <?php endif; ?>
                </aside>
            </div> <?php // Fin .movie-content-columns ?>

            <?php if (!empty($sceneAnnotationThreads)): ?>
            <section class="scene-annotations-list-section card-section card" aria-labelledby="scene-annotations-heading">
                <h2 id="scene-annotations-heading">Discussions de Scènes (<?php echo count($sceneAnnotationThreads); ?>)</h2>
                <ul class="annotations-list">
                    <?php foreach ($sceneAnnotationThreads as $saThread): ?>
                        <li class="annotation-item">
                            <a href="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo (int)$saThread['id']; ?>" class="annotation-title">
                                <strong><?php echo htmlspecialchars($saThread['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </a>
                            <?php if (!empty($saThread['scene_description_short'])): ?>
                                <p class="scene-desc-preview"><em>Scène : <?php echo htmlspecialchars($saThread['scene_description_short'], ENT_QUOTES, 'UTF-8'); ?></em></p>
                            <?php endif; ?>
                            <?php if (!empty($saThread['scene_start_time'])): ?>
                                <p class="scene-time-preview">Temps : <?php echo htmlspecialchars($saThread['scene_start_time'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="annotation-meta">
                                Par <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$saThread['author_id']; ?>"><?php echo htmlspecialchars($saThread['author_username'], ENT_QUOTES, 'UTF-8'); ?></a>
                                le <?php echo date('d/m/Y', strtotime($saThread['created_at'])); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php elseif ($loggedInUserId && $movieDetailsAPI && !$pageError): ?>
                 <section class="scene-annotations-list-section card-section card text-center">
                     <p>Aucune discussion de scène pour ce film. <a href="<?php echo BASE_URL; ?>forum_create_thread.php?movie_id=<?php echo $movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>">Soyez le premier !</a></p>
                 </section>
            <?php endif; ?>

            <section class="public-comments-section card-section card" aria-labelledby="public-comments-heading">
                <h2 id="public-comments-heading">Commentaires Généraux (<?php echo count($publicComments); ?>)</h2>
                <?php if (!empty($publicComments)): ?>
                    <div class="comments-list">
                        <?php foreach ($publicComments as $pComment): ?>
                            <div class="comment-item">
                                <p class="comment-meta">
                                    <strong>
                                        <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$pComment['comment_user_id']; ?>">
                                            <?php echo htmlspecialchars($pComment['username'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </strong>
                                    <time datetime="<?php echo date('c', strtotime($pComment['commented_at'])); ?>">(Le <?php echo date('d/m/Y à H:i', strtotime($pComment['commented_at'])); ?>)</time>
                                </p>
                                <p class="comment-text"><?php echo nl2br(htmlspecialchars($pComment['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center">Aucun commentaire général pour ce film. <?php if ($loggedInUserId) echo "Soyez le premier à commenter !"; else echo '<a href="'.BASE_URL.'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '">Connectez-vous</a> pour commenter.'; ?></p>
                <?php endif; ?>
            </section>

            <?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED && function_exists('generate_simulated_ad_slot_content')): ?>
            <aside class="advertisement-slot bottom-ad-slot" role="complementary" aria-label="Espace publicitaire en bas de page (simulation)">
                <?php echo generate_simulated_ad_slot_content('random_gif'); // You can pick a different type if defined ?>
            </aside>
            <?php endif; ?>

        </article> <?php // Fin .movie-details-content ?>
        <?php endif; // Fin de if ($movieDetailsAPI) ?>

    <?php endif; // Fin de else pour if ($pageError && !$movieDetailsAPI) ?>
</main>

<?php
include_once 'includes/footer.php';
?>