<?php
/*
 * movie_details.php
 * Displays detailed information for a specific movie with improved structure and appeal.
 * Includes simulated monetization features (placeholder ads and direct streaming links) for a school project.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, and monetization constants

// --- Initialize Variables ---
$movieDetailsAPI = null;
$movieCreditsAPI = null;
$movieVideosAPI = null;
$movieWatchProvidersAPI = null;
$isInWatchlist = false;
$userRating = null;
$userCommentText = '';
$publicComments = [];
$sceneAnnotationThreads = [];
$pageError = null; // <<< *** INITIALIZE $pageError HERE ***

// --- CRITICAL: Check if TMDB_API_KEY is set ---
if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
    error_log("TMDB_API_KEY is not defined or is empty in config.php. Movie data cannot be fetched.");
    $pageError = "Configuration error: La clé API pour le service de films est manquante. Veuillez contacter l'administrateur.";
    // $pageTitle is set here so header.php can use it if we exit early
    $pageTitle = "Erreur de Configuration - Eiganights";
    // We will include header and footer and exit after this block if $pageError is set
}

// --- Validate Movie ID (only if no prior config error) ---
if (!$pageError) { // <<< ADDED CHECK
    if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
        // If ID is invalid, this becomes a page error.
        // We might want to set $pageTitle here too before including header.
        $pageError = "ID de film invalide ou manquant.";
        $_SESSION['error'] = $pageError; // Use session for redirect message
        header("Location: index.php"); // Redirect immediately for this type of error
        exit;
    }
    $movieId = (int)$_GET['id'];
}
$loggedInUserId = $_SESSION['user_id'] ?? null;


// --- Fetch Movie Details from TMDB API (including credits, videos, watch_providers) ---
if (!$pageError) { // Proceed only if no prior errors (config or invalid ID)
    $detailsUrl = sprintf(
        "https://api.themoviedb.org/3/movie/%s?api_key=%s&language=fr-FR&append_to_response=credits,videos,watch/providers",
        urlencode($movieId), // $movieId is now guaranteed to be set if we reach here
        urlencode(TMDB_API_KEY)
    );

    $apiResponseJson = @file_get_contents($detailsUrl);

    if ($apiResponseJson === false) {
        error_log("Failed to fetch movie details from TMDB. MovieID: $movieId, URL: $detailsUrl");
        $pageError = "Impossible de récupérer les détails du film pour le moment. (API Error M01)";
    } else {
        $apiData = json_decode($apiResponseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($apiData['id'])) {
            error_log("Failed to decode movie details JSON or invalid data. MovieID: $movieId, Error: " . json_last_error_msg());
            $pageError = (isset($apiData['status_code']) && $apiData['status_code'] == 34) ?
                "Film non trouvé. Il est possible que l'ID soit incorrect ou que le film ait été retiré." :
                "Erreur lors de la récupération des informations du film. (API Error M02)";
        } else {
            $movieDetailsAPI = $apiData;
            $movieCreditsAPI = $movieDetailsAPI['credits'] ?? null;
            $movieVideosAPI = $movieDetailsAPI['videos']['results'] ?? null;
            $movieWatchProvidersAPI = $movieDetailsAPI['watch/providers']['results'] ?? null;
       // // --- DÉBOGAGE ---

// echo "<pre>Movie Watch Providers API Response:\n";

// var_dump($movieWatchProvidersAPI);
 
// echo "</pre>";
// // --- FIN DÉBOGAGE ---
            unset($movieDetailsAPI['credits'], $movieDetailsAPI['videos'], $movieDetailsAPI['watch/providers']);
        }
    }
}

// --- If Movie Details Loaded Successfully, Fetch Local Data ---
// This block should only run if $pageError is still null AND $movieDetailsAPI is successfully populated.
if (!$pageError && $movieDetailsAPI) {
    if ($loggedInUserId) {
        // Check Watchlist
        $stmtWatchlist = $conn->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
        if ($stmtWatchlist) {
            $stmtWatchlist->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtWatchlist->execute()) {
                $stmtWatchlist->store_result();
                $isInWatchlist = ($stmtWatchlist->num_rows > 0);
            } else { error_log("Execute failed (MD_WL_CHK): " . $stmtWatchlist->error); }
            $stmtWatchlist->close();
        } else { error_log("Prepare failed (MD_WL_CHK): " . $conn->error); }

        // Fetch User Rating
        $stmtUserRating = $conn->prepare("SELECT rating FROM ratings WHERE user_id = ? AND movie_id = ?");
        if ($stmtUserRating) {
            $stmtUserRating->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtUserRating->execute()) {
                $resultUserRating = $stmtUserRating->get_result();
                if ($row = $resultUserRating->fetch_assoc()) $userRating = (int)$row['rating'];
            } else { error_log("Execute failed (MD_URAT_SEL): " . $stmtUserRating->error); }
            $stmtUserRating->close();
        } else { error_log("Prepare failed (MD_URAT_SEL): " . $conn->error); }

        // Fetch User Comment
        $stmtUserComment = $conn->prepare("SELECT comment FROM comments WHERE user_id = ? AND movie_id = ? ORDER BY commented_at DESC LIMIT 1");
        if ($stmtUserComment) {
            $stmtUserComment->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtUserComment->execute()) {
                $resultUserComment = $stmtUserComment->get_result();
                if ($row = $resultUserComment->fetch_assoc()) $userCommentText = $row['comment'];
            } else { error_log("Execute failed (MD_UCOM_SEL): " . $stmtUserComment->error); }
            $stmtUserComment->close();
        } else { error_log("Prepare failed (MD_UCOM_SEL): " . $conn->error); }
    }

    // Fetch Public Comments
    $stmtPublicComments = $conn->prepare("SELECT c.comment, c.commented_at, u.username, u.id as comment_user_id FROM comments c JOIN users u ON c.user_id = u.id WHERE c.movie_id = ? ORDER BY c.commented_at DESC");
    if ($stmtPublicComments) {
        $stmtPublicComments->bind_param("i", $movieId);
        if ($stmtPublicComments->execute()) {
            $resultPublicComments = $stmtPublicComments->get_result();
            while ($row = $resultPublicComments->fetch_assoc()) $publicComments[] = $row;
        } else { error_log("Execute failed (MD_PCOM_SEL): " . $stmtPublicComments->error); }
        $stmtPublicComments->close();
    } else { error_log("Prepare failed (MD_PCOM_SEL): " . $conn->error); }

    // Fetch Scene Annotation Threads for this movie
    $stmtSceneThreads = $conn->prepare("SELECT ft.id, ft.title, ft.scene_start_time, ft.scene_description_short, u.username as author_username, u.id as author_id, ft.created_at FROM forum_threads ft JOIN users u ON ft.user_id = u.id WHERE ft.movie_id = ? AND (ft.scene_start_time IS NOT NULL OR ft.scene_description_short IS NOT NULL) ORDER BY ft.created_at DESC");
    if ($stmtSceneThreads) {
        $stmtSceneThreads->bind_param("i", $movieId);
        if ($stmtSceneThreads->execute()) {
            $resultSceneThreads = $stmtSceneThreads->get_result();
            while ($row = $resultSceneThreads->fetch_assoc()) $sceneAnnotationThreads[] = $row;
        } else { error_log("Execute failed (MD_SCENE_THREADS_SEL): " . $stmtSceneThreads->error); }
        $stmtSceneThreads->close();
    } else { error_log("Prepare failed (MD_SCENE_THREADS_SEL): " . $conn->error); }
}


// --- Prepare Data for Display (from API results or defaults if error) ---
// Line 45 was the start of this block in your original numbering, now slightly shifted.
// The checks for !$pageError are crucial here.
$displayTitle    = "Détails du Film"; // Default title if API fails or $pageError is set
$posterPath      = (!$pageError && $movieDetailsAPI) ? ($movieDetailsAPI['poster_path'] ?? null) : null;
$posterAltText   = "Pas d'affiche disponible";
$posterUrl       = ($pageError || !$posterPath) ? BASE_URL . "assets/images/no_poster_available.png" : "https://image.tmdb.org/t/p/w500" . htmlspecialchars($posterPath);

if (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['title'])) {
    $displayTitle = htmlspecialchars($movieDetailsAPI['title']);
    $posterAltText = "Affiche de " . $displayTitle;
}
// Set $pageTitle for header.php. If $pageError was set during TMDB_API_KEY check, $pageTitle is already set.
if (!isset($pageTitle)) { // Only set if not already set by an earlier error.
    $pageTitle = ($pageError ? "Erreur" : $displayTitle) . " - Eiganights";
}


$releaseYear     = (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['release_date'])) ? substr($movieDetailsAPI['release_date'], 0, 4) : 'N/A';
$tagline         = (!$pageError && $movieDetailsAPI) ? htmlspecialchars($movieDetailsAPI['tagline'] ?? '') : '';
$genres          = (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['genres'])) ? htmlspecialchars(implode(', ', array_column($movieDetailsAPI['genres'], 'name'))) : 'N/A';
$runtime         = (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['runtime'])) ? (int)$movieDetailsAPI['runtime'] . ' minutes' : 'N/A';
$tmdbVoteAverage = (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['vote_average'])) ? number_format($movieDetailsAPI['vote_average'], 1) . '/10' : 'N/A';
$tmdbVoteCount   = (!$pageError && $movieDetailsAPI) ? (int)($movieDetailsAPI['vote_count'] ?? 0) : 0;
$overview        = (!$pageError && $movieDetailsAPI && !empty($movieDetailsAPI['overview'])) ? nl2br(htmlspecialchars($movieDetailsAPI['overview'])) : 'Synopsis non disponible.';

$trailerKey = null;
if (!$pageError && !empty($movieVideosAPI)) { // Check $movieVideosAPI which depends on $movieDetailsAPI
    foreach ($movieVideosAPI as $video) {
        if (isset($video['site'], $video['type']) && strtolower($video['site']) === 'youtube' && strtolower($video['type']) === 'trailer' && !empty($video['key'])) {
            $trailerKey = htmlspecialchars($video['key']);
            break;
        }
    }
}

$directors = [];
if (!$pageError && !empty($movieCreditsAPI['crew'])) { // Check $movieCreditsAPI
    foreach ($movieCreditsAPI['crew'] as $crewMember) {
        if (isset($crewMember['job']) && $crewMember['job'] === 'Director' && !empty($crewMember['name'])) {
            $directors[] = htmlspecialchars($crewMember['name']);
        }
    }
}

$cast = (!$pageError && !empty($movieCreditsAPI['cast'])) ? array_slice($movieCreditsAPI['cast'], 0, 10) : []; // Check $movieCreditsAPI


// If a page error occurred (like missing API key or failed API call), display error and exit cleanly.
// This needs to happen *before* the main HTML structure begins with include_once 'includes/header.php';
if ($pageError && !headers_sent()) { // Check if headers_sent is important if we decide to redirect for some errors
    // $pageTitle is already set if error occurred during TMDB_API_KEY check.
    // If it occurred later (e.g. API call failure), set it now.
    if(!isset($pageTitle)) $pageTitle = "Erreur - Eiganights";
    include_once 'includes/header.php';
    echo '<main class="container movie-detail-page" role="main"><div class="alert alert-danger" role="alert">' . htmlspecialchars($pageError) . '</div></main>';
    include_once 'includes/footer.php';
    exit;
}

include_once 'includes/header.php';
?>

<main class="container movie-detail-page" role="main">
    <?php // $pageError is now checked before header.php, so this first PHP block in main is only for session messages
          // or if $movieDetailsAPI is somehow null without $pageError being set (less likely now).
    ?>
    <?php if (!$pageError && !$movieDetailsAPI): // This is a fallback, $pageError should cover most critical issues ?>
        <div class="alert alert-warning" role="alert">Les détails de ce film ne sont pas disponibles actuellement.</div>
    <?php else: // Movie details loaded (or $pageError was handled above and exited), display page content ?>

        <?php // Session messages for various actions (watchlist, rating, comments) ?>
        <?php foreach (['message', 'error', 'warning', 'rate_comment_message', 'rate_comment_error', 'rate_comment_warning'] as $msgKey): ?>
            <?php if (!empty($_SESSION[$msgKey])): ?>
                <div class="alert <?php echo strpos($msgKey, 'error') !== false ? 'alert-danger' : (strpos($msgKey, 'warning') !== false ? 'alert-warning' : 'alert-success'); ?>" role="alert">
                    <?php echo htmlspecialchars($_SESSION[$msgKey]); unset($_SESSION[$msgKey]); ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <article class="movie-details-content card" aria-labelledby="movie-title-heading">
            <header class="movie-main-header">
                <div class="movie-poster">
                    <img src="<?php echo $posterUrl; ?>" alt="<?php echo $posterAltText; ?>" class="movie-detail-poster" loading="lazy">
                </div>
                <div class="movie-meta-info">
                    <h1 id="movie-title-heading"><?php echo $displayTitle; ?> <span class="release-year">(<?php echo $releaseYear; ?>)</span></h1>
                    <?php if ($tagline): ?><p class="tagline"><em><?php echo $tagline; ?></em></p><?php endif; ?>
                    
                    <div class="meta-grid">
                        <p><strong>Genres:</strong> <?php echo $genres; ?></p>
                        <p><strong>Durée:</strong> <?php echo $runtime; ?></p>
                        <p><strong>Note TMDB:</strong> <?php echo $tmdbVoteAverage; ?> (<?php echo number_format($tmdbVoteCount); ?> votes)</p>
                        <?php if (!empty($directors)): ?>
                            <p><strong>Réalisateur(s):</strong> <?php echo implode(', ', $directors); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php // Streaming Options - Direct Links for Simulation ?>
<?php
// Define allowed regions, if any, by constants
if (!defined('ALLOWED_API_REGIONS')) {
  define('ALLOWED_API_REGIONS', ['US', 'CA', 'FR', 'GB']); // Can add or remove the allowed regions here
}

// if (defined('DIRECT_STREAMING_LINKS_ENABLED') && DIRECT_STREAMING_LINKS_ENABLED && !empty($movieWatchProvidersAPI) && defined('STREAMING_PLATFORMS_OFFICIAL_LINKS'))
if (defined('DIRECT_STREAMING_LINKS_ENABLED') && DIRECT_STREAMING_LINKS_ENABLED && !empty($movieWatchProvidersAPI) && defined('STREAMING_PLATFORMS_OFFICIAL_LINKS')) {
    //Check a constance to allow all or a few specific region. Can work if no region is forced
    $availableRegions = array_keys($movieWatchProvidersAPI);

    // Filter the available regions with what the site admin has defined in constants ALLOWED_API_REGIONS if there is the information in array
    $targetRegions = !defined('ALLOWED_API_REGIONS') ? $availableRegions : array_intersect(constant('ALLOWED_API_REGIONS'), $availableRegions); // Regions we'll actually process from settings or constant
    // $providersForRegion = $movieWatchProvidersAPI['FR'] ?? ($movieWatchProvidersAPI[array_key_first($movieWatchProvidersAPI ?? [])] ?? null);  // This makes the code only support the french region, or the first on the top of it all. This must be adapted to what's provided
    $availableStreams = [];

    foreach ($targetRegions as $region) {

        if (isset($movieWatchProvidersAPI[$region]) && isset($movieWatchProvidersAPI[$region]['flatrate'])) {
            $providersForRegion = $movieWatchProvidersAPI[$region]['flatrate'];

            foreach ($providersForRegion as $provider) {
                if (isset(STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']])) {
                    $platformInfo = STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']];
                    $directSearchUrl = str_replace(
                        '{MOVIE_TITLE_URL_ENCODED}',
                        urlencode($displayTitle),
                        $platformInfo['search_url_pattern']
                    );

                    $availableStreams[$provider['provider_id'].'-'.$region] = [ // Unique key for the provider (can be the provider_id and region)
                        'name' => $platformInfo['name'],
                        'logo' => BASE_URL . $platformInfo['logo'], // Construct the URL and define in config
                        'url' => $directSearchUrl,
                        'region' => $region // To display the region
                    ];
                }
            }
        }
    }

    if (!empty($availableStreams)):
?>
<section class="movie-streaming-options" aria-labelledby="streaming-options-heading">
    <h3 id="streaming-options-heading">Regarder ce film (Liens directs) :</h3>
    <div class="streaming-providers-list">
        <?php foreach ($availableStreams as $streamInfo): ?>
            <a href="<?php echo htmlspecialchars($streamInfo['url']); ?>" target="_blank" rel="noopener noreferrer" class="streaming-provider-link" aria-label="Chercher <?php echo $displayTitle; ?> sur <?php echo htmlspecialchars($streamInfo['name']); ?> (<?php echo htmlspecialchars($streamInfo['region']); ?>)">
                <img src="<?php echo htmlspecialchars($streamInfo['logo']); ?>" alt="" aria-hidden="true">
                <span><?php echo htmlspecialchars($streamInfo['name']); ?> (<?php echo htmlspecialchars($streamInfo['region']); ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
    <small class="affiliate-disclosure">Note: Ces liens mènent aux pages de recherche des plateformes (simulation pour projet scolaire).</small>
</section>
<?php
    endif;
}
?>

                    <div class="movie-user-actions">
                        <?php if ($loggedInUserId): ?>
                            <form method="POST" action="<?php echo $isInWatchlist ? 'remove_from_watchlist.php' : 'add.php'; ?>" class="inline-form">
                                <input type="hidden" name="movie_id" value="<?php echo (int)$movieId; ?>">
                                <input type="hidden" name="movie_title" value="<?php echo $displayTitle; ?>">
                                <input type="hidden" name="poster_path" value="<?php echo htmlspecialchars($posterPath ?? ''); ?>">
                                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                <button type="submit" class="button <?php echo $isInWatchlist ? 'button-danger' : 'button-primary'; ?>" aria-label="<?php echo $isInWatchlist ? 'Retirer ' . $displayTitle . ' de la watchlist' : 'Ajouter ' . $displayTitle . ' à la watchlist'; ?>">
                                    <?php echo $isInWatchlist ? '➖ Retirer Watchlist' : '➕ Ajouter Watchlist'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="login-prompt-actions"><a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour ajouter à la watchlist ou noter.</p>
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
                            <?php foreach ($cast as $actor): ?>
                                <?php
                                    $actorName = htmlspecialchars($actor['name'] ?? 'Nom inconnu');
                                    $actorChar = htmlspecialchars($actor['character'] ?? 'Rôle inconnu');
                                    $actorPhotoUrl = !empty($actor['profile_path']) 
                                                ? 'https://image.tmdb.org/t/p/w185' . htmlspecialchars($actor['profile_path'])
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
                    
                    <?php // PLACEHOLDER ADVERTISEMENT SLOT 1 (Main Column) ?>
<?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED): ?>
    <aside class="advertisement-slot placeholder-ad card-section" role="complementary" aria-label="Espace publicitaire (simulation)">
        <div class="placeholder-ad-content">
            <p><strong>Espace Publicitaire (Simulation)</strong></p>
            <p>Contenu publicitaire simulé ici.</p>
        </div>
    </aside>
<?php endif; ?>
                </div>

                <aside class="sidebar-column">
                    <?php if ($loggedInUserId): ?>
                    <section class="user-interaction-section card-section card" aria-labelledby="user-rating-comment-heading">
                        <h2 id="user-rating-comment-heading">Votre Avis</h2>
                        <form method="POST" action="rate_comment.php" novalidate>
                            <input type="hidden" name="movie_id" value="<?php echo (int)$movieId; ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <div class="form-group">
                                <label for="rating">Votre Note (1-10):</label>
                                <select name="rating" id="rating" aria-describedby="rating-description">
                                    <option value="">-- Non Noté --</option>
                                    <?php for ($i = 10; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($userRating === $i) ? 'selected' : ''; ?>><?php echo $i; ?> ★</option>
                                    <?php endfor; ?>
                                </select>
                                <small id="rating-description" class="form-text">Donnez une note de 1 à 10 étoiles.</small>
                            </div>
                            <div class="form-group">
                                <label for="comment">Votre Commentaire:</label>
                                <textarea name="comment" id="comment" rows="5" placeholder="Laissez un commentaire..."><?php echo htmlspecialchars($userCommentText); ?></textarea>
                            </div>
                            <button type="submit" class="button button-primary">Soumettre l'Avis</button>
                        </form>
                    </section>
                    
                    <section class="annotate-scene-action card-section card" aria-labelledby="annotate-scene-heading">
                        <h2 id="annotate-scene-heading">Discuter une Scène</h2>
                        <p>Analysez une scène spécifique de "<?php echo $displayTitle; ?>" ou posez une question à la communauté.</p>
                        <a href="forum_create_thread.php?movie_id=<?php echo (int)$movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>" class="button button-secondary">
                            💬 Annoter & Démarrer une Discussion
                        </a>
                    </section>
                    <?php endif; ?>

                    <?php // PLACEHOLDER ADVERTISEMENT SLOT 2 (Sidebar) ?>
                    <?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED): ?>
                        <aside class="advertisement-slot placeholder-ad card-section" role="complementary" aria-label="Espace publicitaire (simulation)">
                            <div class="placeholder-ad-content">
                                <p><strong>Autre Espace Publicitaire (Simulation)</strong></p>
                            </div>
                        </aside>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if (!empty($sceneAnnotationThreads)): ?>
            <section class="scene-annotations-list-section card-section card" aria-labelledby="scene-annotations-heading">
                <h2 id="scene-annotations-heading">Annotations de Scènes & Discussions (<?php echo count($sceneAnnotationThreads); ?>)</h2>
                <ul class="annotations-list">
                    <?php foreach ($sceneAnnotationThreads as $saThread): ?>
                        <li class="annotation-item">
                            <a href="forum_view_thread.php?id=<?php echo (int)$saThread['id']; ?>" class="annotation-title" aria-label="Voir la discussion: <?php echo htmlspecialchars($saThread['title']); ?>">
                                <strong><?php echo htmlspecialchars($saThread['title']); ?></strong>
                            </a>
                            <?php if (!empty($saThread['scene_description_short'])): ?>
                                <p class="scene-desc-preview"><em>Scène : <?php echo htmlspecialchars($saThread['scene_description_short']); ?></em></p>
                            <?php endif; ?>
                            <?php if (!empty($saThread['scene_start_time'])): ?>
                                <p class="scene-time-preview">Temps : <?php echo htmlspecialchars($saThread['scene_start_time']); ?></p>
                            <?php endif; ?>
                            <p class="annotation-meta">
                                Par <a href="view_profile.php?id=<?php echo (int)$saThread['author_id']; ?>" aria-label="Voir le profil de <?php echo htmlspecialchars($saThread['author_username']); ?>"><?php echo htmlspecialchars($saThread['author_username']); ?></a>
                                le <?php echo date('d/m/Y', strtotime($saThread['created_at'])); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php elseif ($loggedInUserId && !$pageError && $movieDetailsAPI): // Only show prompt if movie details loaded and user is logged in ?>
                 <section class="scene-annotations-list-section card-section card text-center">
                     <p>Aucune annotation de scène pour ce film. <a href="forum_create_thread.php?movie_id=<?php echo (int)$movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>">Soyez le premier à en créer une !</a></p>
                 </section>
            <?php endif; ?>

            <section class="public-comments-section card-section card" aria-labelledby="public-comments-heading">
                <h2 id="public-comments-heading">Commentaires des Utilisateurs (<?php echo count($publicComments); ?>)</h2>
                <?php if (!empty($publicComments)): ?>
                    <div class="comments-list">
                        <?php foreach ($publicComments as $pComment): ?>
                            <div class="comment-item">
                                <p class="comment-meta">
                                    <strong>
                                        <a href="view_profile.php?id=<?php echo (int)$pComment['comment_user_id']; ?>" aria-label="Voir le profil de <?php echo htmlspecialchars($pComment['username']); ?>">
                                            <?php echo htmlspecialchars($pComment['username']); ?>
                                        </a>
                                    </strong>
                                    <time class="comment-date" datetime="<?php echo date('c', strtotime($pComment['commented_at'])); ?>">(Le <?php echo date('d/m/Y à H:i', strtotime($pComment['commented_at'])); ?>)</time>
                                </p>
                                <p class="comment-text"><?php echo nl2br(htmlspecialchars($pComment['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center">Aucun commentaire pour ce film. <?php if ($loggedInUserId) echo "Soyez le premier à commenter !"; else echo '<a href="login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '">Connectez-vous</a> pour commenter.'; ?></p>
                <?php endif; ?>
            </section>
        </article>
    <?php endif; // End of main content conditional display ?>
</main>

<?php
include_once 'includes/footer.php';
?>