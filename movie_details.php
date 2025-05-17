<?php
/*
 * movie_details.php
 * Affiche les informations détaillées d'un film.
 * Gère la récupération des données depuis TMDB et la base de données locale.
 * Inclut des fonctionnalités de monétisation simulées.
 */
require_once 'config.php'; // CRITICAL: Utiliser require_once pour config.php

// -----------------------------------------------------------------------------
// 1. INITIALISATION & VALIDATION DES PARAMÈTRES
// -----------------------------------------------------------------------------
$movieId = null;
$loggedInUserId = $_SESSION['user_id'] ?? null;
$pageError = null;
$pageTitle = SITE_NAME; // Titre par défaut

// Variables pour les données du film
$movieDetailsAPI = null;
$movieCreditsAPI = null;
$movieVideosAPI = null;
$movieWatchProvidersAPI = null;

// Variables pour les données locales
$isInWatchlist = false;
$userRating = null;
$userCommentText = '';
$publicComments = [];
$sceneAnnotationThreads = [];

// Vérification de la clé API TMDB (déplacée de config.php pour un contrôle plus direct ici si besoin)
if (empty(TMDB_API_KEY) || TMDB_API_KEY === 'YOUR_TMDB_API_KEY_HERE') {
    error_log("TMDB_API_KEY non définie ou placeholder dans config.php. Données des films indisponibles.");
    $pageError = "Erreur de configuration : La clé API pour le service de films est manquante. Certaines fonctionnalités du site peuvent être affectées.";
    $pageTitle = "Erreur de Configuration - " . SITE_NAME;
    // On ne quitte pas encore, on veut afficher l'erreur dans la page.
}

// Validation de l'ID du film
if (!$pageError) { // Continuer seulement si pas d'erreur de clé API
    if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
        $pageError = "ID de film invalide ou manquant.";
        // Pour ce type d'erreur (paramètre manquant/invalide), une redirection est appropriée.
        if (!headers_sent()) {
            $_SESSION['error'] = $pageError; // Message pour la page de redirection
            header("Location: index.php");
            exit;
        }
        // Si les en-têtes sont envoyés, on affichera l'erreur sur la page actuelle.
        $pageTitle = "Erreur - " . SITE_NAME;
    } else {
        $movieId = (int)$_GET['id'];
    }
}

// -----------------------------------------------------------------------------
// 2. RÉCUPÉRATION DES DONNÉES TMDB (si pas d'erreur initiale)
// -----------------------------------------------------------------------------
if (!$pageError && $movieId) {
    $tmdbApiKey = urlencode(TMDB_API_KEY);
    $detailsUrl = "https://api.themoviedb.org/3/movie/{$movieId}?api_key={$tmdbApiKey}&language=fr-FR&append_to_response=credits,videos,watch/providers";
    
    // Utilisation d'une fonction pour encapsuler l'appel API (peut être mise dans un fichier utils.php)
    function fetch_tmdb_data($url) {
        // Option: Ajouter un système de cache ici
        $responseJson = @file_get_contents($url);
        if ($responseJson === false) {
            return ['error' => "Impossible de contacter le service de films (TMDB).", 'data' => null];
        }
        $data = json_decode($responseJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
            $apiError = isset($data['status_code']) && $data['status_code'] == 34 ? "Film non trouvé." : "Erreur de format des données du service de films.";
            return ['error' => $apiError, 'data' => null];
        }
        return ['error' => null, 'data' => $data];
    }

    $tmdbResult = fetch_tmdb_data($detailsUrl);

    if ($tmdbResult['error']) {
        error_log("Erreur TMDB pour MovieID {$movieId}: " . $tmdbResult['error'] . " URL: " . $detailsUrl);
        $pageError = $tmdbResult['error'] . " (API Error M01/M02)";
    } else {
        $movieDetailsAPI = $tmdbResult['data'];
        $movieCreditsAPI = $movieDetailsAPI['credits'] ?? null;
        $movieVideosAPI = $movieDetailsAPI['videos']['results'] ?? null; // TMDB structure 'results'
        $movieWatchProvidersAPI = $movieDetailsAPI['watch/providers']['results'] ?? null; // TMDB structure 'results'
        
        // Nettoyer les données agrégées de $movieDetailsAPI pour ne garder que les détails du film
        unset($movieDetailsAPI['credits'], $movieDetailsAPI['videos'], $movieDetailsAPI['watch/providers']);
    }
}

// -----------------------------------------------------------------------------
// 3. RÉCUPÉRATION DES DONNÉES LOCALES (si détails TMDB chargés et pas d'erreur)
// -----------------------------------------------------------------------------
if (!$pageError && $movieDetailsAPI && $movieId) {
    // Check Watchlist (si utilisateur connecté)
    if ($loggedInUserId) {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND movie_id = ?");
            $stmt->bind_param("ii", $loggedInUserId, $movieId);
            $stmt->execute();
            $stmt->store_result();
            $isInWatchlist = ($stmt->num_rows > 0);
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log("Erreur DB (MD_WL_CHK): " . $e->getMessage());
            // Non bloquant, mais pourrait afficher un message d'avertissement.
        }

        // Fetch User Rating & Comment
        try {
            $stmt = $conn->prepare("SELECT rating FROM ratings WHERE user_id = ? AND movie_id = ?");
            $stmt->bind_param("ii", $loggedInUserId, $movieId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) $userRating = (int)$row['rating'];
            $stmt->close();

            $stmt = $conn->prepare("SELECT comment FROM comments WHERE user_id = ? AND movie_id = ? ORDER BY commented_at DESC LIMIT 1");
            $stmt->bind_param("ii", $loggedInUserId, $movieId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) $userCommentText = $row['comment'];
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log("Erreur DB (MD_URAT_UCOM_SEL): " . $e->getMessage());
        }
    }

    // Fetch Public Comments
    try {
        $stmt = $conn->prepare("SELECT c.comment, c.commented_at, u.username, u.id as comment_user_id FROM comments c JOIN users u ON c.user_id = u.id WHERE c.movie_id = ? ORDER BY c.commented_at DESC");
        $stmt->bind_param("i", $movieId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $publicComments[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Erreur DB (MD_PCOM_SEL): " . $e->getMessage());
    }

    // Fetch Scene Annotation Threads
    try {
        $stmt = $conn->prepare("SELECT ft.id, ft.title, ft.scene_start_time, ft.scene_description_short, u.username as author_username, u.id as author_id, ft.created_at FROM forum_threads ft JOIN users u ON ft.user_id = u.id WHERE ft.movie_id = ? AND (ft.scene_start_time IS NOT NULL OR ft.scene_description_short IS NOT NULL) ORDER BY ft.created_at DESC");
        $stmt->bind_param("i", $movieId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $sceneAnnotationThreads[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Erreur DB (MD_SCENE_THREADS_SEL): " . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 4. PRÉPARATION DES DONNÉES POUR L'AFFICHAGE
// -----------------------------------------------------------------------------
// Définir le titre de la page même en cas d'erreur, pour le header
if ($pageError) {
    // $pageTitle est déjà défini si l'erreur vient de la clé API ou d'une ID invalide (avant l'API TMDB)
    if (!isset($pageTitle) || $pageTitle === SITE_NAME) $pageTitle = "Erreur - " . SITE_NAME;
} elseif ($movieDetailsAPI && !empty($movieDetailsAPI['title'])) {
    $displayTitle = htmlspecialchars($movieDetailsAPI['title']);
    $pageTitle = $displayTitle . " - " . SITE_NAME;
    $posterAltText = "Affiche de " . $displayTitle;
} else { // Pas d'erreur critique mais film non trouvé ou détails vides
    $displayTitle = "Détails du Film Non Disponibles";
    $pageTitle = $displayTitle . " - " . SITE_NAME;
    $posterAltText = "Pas d'affiche disponible";
    // Si $movieDetailsAPI est null ici et qu'il n'y a pas de $pageError, on crée une erreur douce.
    if (!$pageError) $pageError = "Les informations de ce film ne sont pas disponibles actuellement.";
}

// Fallbacks pour les autres données si $movieDetailsAPI n'est pas chargé ou incomplet
$posterPath = $movieDetailsAPI['poster_path'] ?? null;
$posterUrl = ($pageError || !$posterPath) ? BASE_URL . "assets/images/no_poster_available.png" : "https://image.tmdb.org/t/p/w500" . htmlspecialchars($posterPath);

$releaseYear = !empty($movieDetailsAPI['release_date']) ? substr($movieDetailsAPI['release_date'], 0, 4) : 'N/A';
$tagline = !empty($movieDetailsAPI['tagline']) ? htmlspecialchars($movieDetailsAPI['tagline']) : '';
$genres = !empty($movieDetailsAPI['genres']) ? htmlspecialchars(implode(', ', array_column($movieDetailsAPI['genres'], 'name'))) : 'N/A';
$runtimeMinutes = $movieDetailsAPI['runtime'] ?? 0;
$runtime = $runtimeMinutes > 0 ? $runtimeMinutes . ' minutes' : 'N/A';
$tmdbVoteAverage = !empty($movieDetailsAPI['vote_average']) && $movieDetailsAPI['vote_average'] > 0 ? number_format($movieDetailsAPI['vote_average'], 1) . '/10' : 'N/A';
$tmdbVoteCount = (int)($movieDetailsAPI['vote_count'] ?? 0);
$overview = !empty($movieDetailsAPI['overview']) ? nl2br(htmlspecialchars($movieDetailsAPI['overview'])) : 'Synopsis non disponible.';

// Trailer (YouTube)
$trailerKey = null;
if (!empty($movieVideosAPI)) {
    foreach ($movieVideosAPI as $video) {
        if (isset($video['site'], $video['type']) && strtolower($video['site']) === 'youtube' && strtolower($video['type']) === 'trailer' && !empty($video['key'])) {
            $trailerKey = htmlspecialchars($video['key']);
            break;
        }
    }
}

// Réalisateurs
$directors = [];
if (!empty($movieCreditsAPI['crew'])) {
    foreach ($movieCreditsAPI['crew'] as $crewMember) {
        if (isset($crewMember['job'], $crewMember['name']) && $crewMember['job'] === 'Director') {
            $directors[] = htmlspecialchars($crewMember['name']);
        }
    }
}
$directorsFormatted = !empty($directors) ? implode(', ', $directors) : 'N/A';

// Acteurs principaux (10 premiers)
$cast = !empty($movieCreditsAPI['cast']) ? array_slice($movieCreditsAPI['cast'], 0, 10) : [];

// -----------------------------------------------------------------------------
// 5. GESTION DES ERREURS CRITIQUES (si elles n'ont pas déjà mené à une redirection)
// -----------------------------------------------------------------------------
if ($pageError && empty($movieDetailsAPI) && !headers_sent()) { // Erreur critique avant de pouvoir afficher quoi que ce soit d'utile
    include_once 'includes/header.php'; // $pageTitle est déjà défini
    echo '<main class="container movie-detail-page" role="main">';
    echo '  <div class="alert alert-danger" role="alert">' . htmlspecialchars($pageError) . '</div>';
    echo '</main>';
    include_once 'includes/footer.php';
    exit;
}

// =============================================================================
// DÉBUT DE L'AFFICHAGE HTML
// =============================================================================
include_once 'includes/header.php'; // $pageTitle est prêt
?>

<main class="container movie-detail-page" role="main">

    <?php // Affichage des messages de session globaux (peut être déplacé dans header.php si standardisé) ?>
    <?php foreach (['message', 'error', 'warning', 'rate_comment_message', 'rate_comment_error', 'rate_comment_warning'] as $msgKey): ?>
        <?php if (!empty($_SESSION[$msgKey])): ?>
            <div class="alert <?php echo strpos($msgKey, 'error') !== false ? 'alert-danger' : (strpos($msgKey, 'warning') !== false ? 'alert-warning' : 'alert-success'); ?>" role="alert">
                <?php echo htmlspecialchars($_SESSION[$msgKey]); unset($_SESSION[$msgKey]); ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php // Si une erreur est survenue MAIS on a quand même quelques détails (ex: film non trouvé mais titre connu via ID)
          // OU si $movieDetailsAPI est null pour une raison imprévue et $pageError est défini
          // On affiche l'erreur ici, avant de tenter d'afficher les détails.
    ?>
    <?php if ($pageError && !$movieDetailsAPI): // Erreur, mais pas d'exit plus haut ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($pageError); ?></div>
    <?php elseif ($pageError && $movieDetailsAPI) : // Erreur douce (ex: providers TMDB n'ont pas chargé, mais film OK)?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($pageError); ?></div>
    <?php endif; ?>


    <?php if ($movieDetailsAPI): // Afficher les détails seulement si $movieDetailsAPI est chargé ?>
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
                    <p><strong>Réalisateur(s):</strong> <?php echo $directorsFormatted; ?></p>
                </div>

                <?php // Section Liens de Streaming
                if (defined('DIRECT_STREAMING_LINKS_ENABLED') && DIRECT_STREAMING_LINKS_ENABLED && !empty($movieWatchProvidersAPI) && defined('STREAMING_PLATFORMS_OFFICIAL_LINKS')) {
                    $availableStreams = [];
                    $targetRegions = defined('ALLOWED_API_REGIONS') ? ALLOWED_API_REGIONS : array_keys($movieWatchProvidersAPI);

                    foreach ($targetRegions as $regionCode) {
                        if (isset($movieWatchProvidersAPI[$regionCode], $movieWatchProvidersAPI[$regionCode]['flatrate']) && is_array($movieWatchProvidersAPI[$regionCode]['flatrate'])) {
                            foreach ($movieWatchProvidersAPI[$regionCode]['flatrate'] as $provider) {
                                if (isset($provider['provider_id'], STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']])) {
                                    $platformInfo = STREAMING_PLATFORMS_OFFICIAL_LINKS[$provider['provider_id']];
                                    $searchUrl = str_replace('{MOVIE_TITLE_URL_ENCODED}', urlencode($displayTitle), $platformInfo['search_url_pattern']);
                                    $streamKey = $provider['provider_id'] . '-' . $regionCode;
                                    if (!isset($availableStreams[$streamKey])) {
                                        $availableStreams[$streamKey] = [
                                            'name' => $platformInfo['name'],
                                            'logo' => BASE_URL . $platformInfo['logo'],
                                            'url' => $searchUrl,
                                            'region' => $regionCode
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($availableStreams)): ?>
                    <section class="movie-streaming-options" aria-labelledby="streaming-options-heading">
                        <h3 id="streaming-options-heading">Regarder ce film (Liens simulés) :</h3>
                        <div class="streaming-providers-list">
                            <?php foreach ($availableStreams as $streamInfo): ?>
                                <a href="<?php echo htmlspecialchars($streamInfo['url']); ?>" target="_blank" rel="noopener noreferrer sponsored" class="streaming-provider-link" title="Chercher <?php echo $displayTitle; ?> sur <?php echo htmlspecialchars($streamInfo['name']); ?> (<?php echo htmlspecialchars($streamInfo['region']); ?>)">
                                    <img src="<?php echo htmlspecialchars($streamInfo['logo']); ?>" alt="Logo <?php echo htmlspecialchars($streamInfo['name']); ?>">
                                    <span><?php echo htmlspecialchars($streamInfo['name']); ?> (<?php echo htmlspecialchars($streamInfo['region']); ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <small class="affiliate-disclosure">Note: Ces liens sont une simulation pour un projet et peuvent ne pas refléter la disponibilité réelle.</small>
                    </section>
                    <?php endif;
                } // Fin DIRECT_STREAMING_LINKS_ENABLED
                ?>

                <div class="movie-user-actions">
                    <?php if ($loggedInUserId): ?>
                        <form method="POST" action="<?php echo $isInWatchlist ? 'remove_from_watchlist.php' : 'add.php'; ?>" class="inline-form">
                            <input type="hidden" name="movie_id" value="<?php echo $movieId; ?>">
                            <input type="hidden" name="movie_title" value="<?php echo $displayTitle; // On utilise $displayTitle déjà échappé ?>">
                            <input type="hidden" name="poster_path" value="<?php echo htmlspecialchars($posterPath ?? ''); ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <?php // Protection CSRF Token à ajouter ici si implémentée globalement ?>
                            <button type="submit" class="button <?php echo $isInWatchlist ? 'button-danger' : 'button-primary'; ?>">
                                <?php echo $isInWatchlist ? '➖ Retirer Watchlist' : '➕ Ajouter Watchlist'; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="login-prompt-actions"><a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour gérer votre watchlist ou noter.</p>
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
                
                <?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED): ?>
                <aside class="advertisement-slot card-section" role="complementary" aria-label="Espace publicitaire (simulation)">
                    <?php echo generate_simulated_ad_slot_content('main', ['movie_id' => $movieId, 'genres' => $movieDetailsAPI['genres'] ?? []]); ?>
                </aside>
                <?php endif; ?>
            </div>

            <aside class="sidebar-column">
                <?php if ($loggedInUserId): ?>
                <section class="user-interaction-section card-section card" aria-labelledby="user-rating-comment-heading">
                    <h2 id="user-rating-comment-heading">Votre Avis</h2>
                    <form method="POST" action="rate_comment.php" novalidate>
                        <?php // Protection CSRF Token à ajouter ici ?>
                        <input type="hidden" name="movie_id" value="<?php echo $movieId; ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
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
                            <textarea name="comment" id="comment" rows="5" placeholder="Laissez un commentaire..."><?php echo htmlspecialchars($userCommentText); ?></textarea>
                        </div>
                        <button type="submit" class="button button-primary">Soumettre l'Avis</button>
                    </form>
                </section>
                
                <section class="annotate-scene-action card-section card" aria-labelledby="annotate-scene-heading">
                    <h2 id="annotate-scene-heading">Discuter une Scène</h2>
                    <p>Analysez une scène de "<?php echo $displayTitle; ?>" ou posez une question.</p>
                    <a href="forum_create_thread.php?movie_id=<?php echo $movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>" class="button button-secondary">
                        💬 Annoter & Démarrer une Discussion
                    </a>
                </section>
                <?php endif; // Fin if $loggedInUserId pour interaction ?>

                <?php if (defined('PLACEHOLDER_ADS_ENABLED') && PLACEHOLDER_ADS_ENABLED): ?>
                <aside class="advertisement-slot card-section" role="complementary" aria-label="Espace publicitaire (simulation)">
                    <?php echo generate_simulated_ad_slot_content('sidebar', ['movie_id' => $movieId, 'genres' => $movieDetailsAPI['genres'] ?? []]); ?>
                </aside>
                <?php endif; ?>
            </aside>
        </div>

        <?php if (!empty($sceneAnnotationThreads)): ?>
        <section class="scene-annotations-list-section card-section card" aria-labelledby="scene-annotations-heading">
            <h2 id="scene-annotations-heading">Discussions de Scènes (<?php echo count($sceneAnnotationThreads); ?>)</h2>
            <ul class="annotations-list">
                <?php foreach ($sceneAnnotationThreads as $saThread): ?>
                    <li class="annotation-item">
                        <a href="forum_view_thread.php?id=<?php echo (int)$saThread['id']; ?>" class="annotation-title">
                            <strong><?php echo htmlspecialchars($saThread['title']); ?></strong>
                        </a>
                        <?php if (!empty($saThread['scene_description_short'])): ?>
                            <p class="scene-desc-preview"><em>Scène : <?php echo htmlspecialchars($saThread['scene_description_short']); ?></em></p>
                        <?php endif; ?>
                        <?php if (!empty($saThread['scene_start_time'])): ?>
                            <p class="scene-time-preview">Temps : <?php echo htmlspecialchars($saThread['scene_start_time']); ?></p>
                        <?php endif; ?>
                        <p class="annotation-meta">
                            Par <a href="view_profile.php?id=<?php echo (int)$saThread['author_id']; ?>"><?php echo htmlspecialchars($saThread['author_username']); ?></a>
                            le <?php echo date('d/m/Y', strtotime($saThread['created_at'])); ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php elseif ($loggedInUserId && !$pageError && $movieDetailsAPI): // Prompt pour créer annotation ?>
             <section class="scene-annotations-list-section card-section card text-center">
                 <p>Aucune discussion de scène pour ce film. <a href="forum_create_thread.php?movie_id=<?php echo $movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>">Soyez le premier !</a></p>
             </section>
        <?php endif; ?>

        <section class="public-comments-section card-section card" aria-labelledby="public-comments-heading">
            <h2 id="public-comments-heading">Commentaires (<?php echo count($publicComments); ?>)</h2>
            <?php if (!empty($publicComments)): ?>
                <div class="comments-list">
                    <?php foreach ($publicComments as $pComment): ?>
                        <div class="comment-item">
                            <p class="comment-meta">
                                <strong>
                                    <a href="view_profile.php?id=<?php echo (int)$pComment['comment_user_id']; ?>">
                                        <?php echo htmlspecialchars($pComment['username']); ?>
                                    </a>
                                </strong>
                                <time datetime="<?php echo date('c', strtotime($pComment['commented_at'])); ?>">(Le <?php echo date('d/m/Y à H:i', strtotime($pComment['commented_at'])); ?>)</time>
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
    <?php // Fin if $movieDetailsAPI (pour affichage principal)
    else: 
        // S'il y a eu une erreur et $movieDetailsAPI est null, $pageError devrait être défini
        // et aurait été affiché en haut. Mais par sécurité, si on arrive ici sans $movieDetailsAPI
        // et qu'aucune erreur majeure n'a causé un exit avant l'include du header :
        if (!$pageError) { // Cas très improbable où $pageError n'est pas défini mais $movieDetailsAPI l'est.
            echo '<div class="alert alert-warning" role="alert">Les détails de ce film ne sont pas disponibles actuellement.</div>';
        }
        // $pageError a déjà été affiché si c'était critique
    endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>