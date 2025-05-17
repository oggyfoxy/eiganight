<?php
/*
 * movie_details.php
 * Displays detailed information for a specific movie.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY

// --- Validate Movie ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
    $_SESSION['error'] = "ID de film invalide ou manquant.";
    header("Location: index.php");
    exit;
}
$movieId = (int)$_GET['id'];
$loggedInUserId = $_SESSION['user_id'] ?? null; // Null if not logged in

// --- Initialize Variables ---
$movieDetailsAPI = null; // Data directly from TMDB API
$movieCreditsAPI = null; // Credits from TMDB API
$movieVideosAPI = null;  // Videos from TMDB API
$isInWatchlist = false;
$userRating = null;
$userCommentText = ''; // User's own comment text
$publicComments = [];
$pageError = null; // To store critical page loading errors

// --- Fetch Movie Details from TMDB API ---
$detailsUrl = "https://api.themoviedb.org/3/movie/" . urlencode($movieId) . 
              "?api_key=" . urlencode(TMDB_API_KEY) . 
              "&language=fr-FR&append_to_response=credits,videos";

$apiResponseJson = @file_get_contents($detailsUrl); // Suppress network errors

if ($apiResponseJson === false) {
    error_log("Failed to fetch movie details from TMDB. MovieID: $movieId, URL: $detailsUrl");
    $pageError = "Impossible de récupérer les détails du film pour le moment. (API Error M01)";
} else {
    $apiData = json_decode($apiResponseJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($apiData['id'])) { // Check for valid JSON and presence of 'id'
        error_log("Failed to decode movie details JSON or invalid data. MovieID: $movieId, Error: " . json_last_error_msg());
        if (isset($apiData['status_code']) && $apiData['status_code'] == 34) { // Resource not found
            $pageError = "Film non trouvé. Il est possible que l'ID soit incorrect ou que le film ait été retiré.";
        } else {
            $pageError = "Erreur lors de la récupération des informations du film. (API Error M02)";
        }
    } else {
        $movieDetailsAPI = $apiData; // Assign all data
        $movieCreditsAPI = $movieDetailsAPI['credits'] ?? null;
        $movieVideosAPI = $movieDetailsAPI['videos']['results'] ?? null;
        // Clean up main array after extracting appended responses
        unset($movieDetailsAPI['credits'], $movieDetailsAPI['videos']);
    }
}

// --- If Movie Details Loaded Successfully, Fetch Local Data ---
if ($movieDetailsAPI) {
    // Check if movie is in the logged-in user's watchlist
    if ($loggedInUserId) {
        $sqlWatchlist = "SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?";
        $stmtWatchlist = $conn->prepare($sqlWatchlist);
        if ($stmtWatchlist) {
            $stmtWatchlist->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtWatchlist->execute()) {
                $stmtWatchlist->store_result();
                $isInWatchlist = ($stmtWatchlist->num_rows > 0);
            } else {
                error_log("Execute failed (MD_WL_CHK): " . $stmtWatchlist->error);
                // Non-critical, page can still load
            }
            $stmtWatchlist->close();
        } else {
            error_log("Prepare failed (MD_WL_CHK): " . $conn->error);
        }
    }

    // Fetch existing rating and comment for the current logged-in user
    if ($loggedInUserId) {
        // Fetch rating
        $sqlUserRating = "SELECT rating FROM ratings WHERE user_id = ? AND movie_id = ?";
        $stmtUserRating = $conn->prepare($sqlUserRating);
        if ($stmtUserRating) {
            $stmtUserRating->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtUserRating->execute()) {
                $resultUserRating = $stmtUserRating->get_result();
                if ($row = $resultUserRating->fetch_assoc()) {
                    $userRating = (int)$row['rating'];
                }
            } else { error_log("Execute failed (MD_URAT_SEL): " . $stmtUserRating->error); }
            $stmtUserRating->close();
        } else { error_log("Prepare failed (MD_URAT_SEL): " . $conn->error); }

        // Fetch comment
        $sqlUserComment = "SELECT comment FROM comments WHERE user_id = ? AND movie_id = ? ORDER BY commented_at DESC LIMIT 1";
        $stmtUserComment = $conn->prepare($sqlUserComment);
        if ($stmtUserComment) {
            $stmtUserComment->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtUserComment->execute()) {
                $resultUserComment = $stmtUserComment->get_result();
                if ($row = $resultUserComment->fetch_assoc()) {
                    $userCommentText = $row['comment'];
                }
            } else { error_log("Execute failed (MD_UCOM_SEL): " . $stmtUserComment->error); }
            $stmtUserComment->close();
        } else { error_log("Prepare failed (MD_UCOM_SEL): " . $conn->error); }
    }

    // Fetch all public comments for this movie, joining with usernames
    $sqlPublicComments = "SELECT c.comment, c.commented_at, u.username, u.id as comment_user_id 
                          FROM comments c 
                          JOIN users u ON c.user_id = u.id 
                          WHERE c.movie_id = ? 
                          ORDER BY c.commented_at DESC";
    $stmtPublicComments = $conn->prepare($sqlPublicComments);
    if ($stmtPublicComments) {
        $stmtPublicComments->bind_param("i", $movieId);
        if ($stmtPublicComments->execute()) {
            $resultPublicComments = $stmtPublicComments->get_result();
            while ($row = $resultPublicComments->fetch_assoc()) {
                $publicComments[] = $row;
            }
        } else { error_log("Execute failed (MD_PCOM_SEL): " . $stmtPublicComments->error); }
        $stmtPublicComments->close();
    } else { error_log("Prepare failed (MD_PCOM_SEL): " . $conn->error); }
}


// --- Prepare Data for Display (from API results) ---
$displayTitle = "Détails du Film";
$posterUrl = "assets/images/no_poster_available.png"; // Default local placeholder
$releaseYear = 'N/A';
$tagline = '';
$genres = 'N/A';
$runtime = 'N/A';
$tmdbVoteAverage = 'N/A';
$tmdbVoteCount = 0;
$overview = 'Synopsis non disponible.';
$trailerKey = null;
$directors = [];
$cast = [];

if ($movieDetailsAPI) {
    $displayTitle = htmlspecialchars($movieDetailsAPI['title'] ?? 'Titre inconnu', ENT_QUOTES, 'UTF-8');
    $pageTitle = $displayTitle . " - Eiganights";
    if (!empty($movieDetailsAPI['poster_path'])) {
        $posterUrl = "https://image.tmdb.org/t/p/w500" . htmlspecialchars($movieDetailsAPI['poster_path'], ENT_QUOTES, 'UTF-8');
    }
    $releaseYear = !empty($movieDetailsAPI['release_date']) ? substr($movieDetailsAPI['release_date'], 0, 4) : 'N/A';
    $tagline = htmlspecialchars($movieDetailsAPI['tagline'] ?? '', ENT_QUOTES, 'UTF-8');
    
    if (!empty($movieDetailsAPI['genres'])) {
        $genreNames = array_column($movieDetailsAPI['genres'], 'name');
        $genres = htmlspecialchars(implode(', ', $genreNames), ENT_QUOTES, 'UTF-8');
    }
    $runtime = !empty($movieDetailsAPI['runtime']) ? (int)$movieDetailsAPI['runtime'] . ' minutes' : 'N/A';
    $tmdbVoteAverage = !empty($movieDetailsAPI['vote_average']) ? number_format($movieDetailsAPI['vote_average'], 1) . '/10' : 'N/A';
    $tmdbVoteCount = (int)($movieDetailsAPI['vote_count'] ?? 0);
    $overview = !empty($movieDetailsAPI['overview']) ? nl2br(htmlspecialchars($movieDetailsAPI['overview'], ENT_QUOTES, 'UTF-8')) : 'Synopsis non disponible.';

    if (!empty($movieVideosAPI)) {
        foreach ($movieVideosAPI as $video) {
            if (isset($video['site'], $video['type']) && strtolower($video['site']) === 'youtube' && strtolower($video['type']) === 'trailer' && !empty($video['key'])) {
                $trailerKey = htmlspecialchars($video['key'], ENT_QUOTES, 'UTF-8');
                break;
            }
        }
    }

    if (!empty($movieCreditsAPI['crew'])) {
        foreach ($movieCreditsAPI['crew'] as $crewMember) {
            if (isset($crewMember['job']) && $crewMember['job'] === 'Director' && !empty($crewMember['name'])) {
                $directors[] = htmlspecialchars($crewMember['name'], ENT_QUOTES, 'UTF-8');
            }
        }
    }
    if (!empty($movieCreditsAPI['cast'])) {
        $cast = array_slice($movieCreditsAPI['cast'], 0, 10); // Top 10 cast members
    }
}


$sceneAnnotationThreads = [];
if ($movieDetailsAPI) { // Only if movie details were loaded
    $sqlSceneThreads = "SELECT ft.id, ft.title, ft.scene_start_time, ft.scene_description_short, 
                               u.username as author_username, u.id as author_id, ft.created_at
                        FROM forum_threads ft
                        JOIN users u ON ft.user_id = u.id
                        WHERE ft.movie_id = ? AND (ft.scene_start_time IS NOT NULL OR ft.scene_description_short IS NOT NULL)
                        ORDER BY ft.created_at DESC"; // Or by scene_start_time
    $stmtSceneThreads = $conn->prepare($sqlSceneThreads);
    if ($stmtSceneThreads) {
        $stmtSceneThreads->bind_param("i", $movieId);
        if ($stmtSceneThreads->execute()) {
            $resultSceneThreads = $stmtSceneThreads->get_result();
            while ($row = $resultSceneThreads->fetch_assoc()) {
                $sceneAnnotationThreads[] = $row;
            }
        } else { error_log("Execute failed (MD_SCENE_THREADS_SEL): " . $stmtSceneThreads->error); }
        $stmtSceneThreads->close();
    } else { error_log("Prepare failed (MD_SCENE_THREADS_SEL): " . $conn->error); }
}

include_once 'includes/header.php';
?>

<main class="container movie-detail-page">
    <?php if ($pageError): // Display critical page error and stop ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (!$movieDetailsAPI): // Should be caught by $pageError, but as a fallback ?>
        <div class="alert alert-warning">Les détails de ce film ne sont pas disponibles actuellement.</div>
    <?php else: // Movie details loaded, display page content ?>
        
        <?php // Session messages for watchlist/rating/comment actions ?>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
         <?php if (!empty($_SESSION['rate_comment_message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['rate_comment_message']); unset($_SESSION['rate_comment_message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['rate_comment_error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['rate_comment_error']); unset($_SESSION['rate_comment_error']); ?></div>
        <?php endif; ?>
         <?php if (!empty($_SESSION['rate_comment_warning'])): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['rate_comment_warning']); unset($_SESSION['rate_comment_warning']); ?></div>
        <?php endif; ?>


        <article class="movie-detail-container card">
            <header class="movie-detail-header">
                <div class="poster-container">
                    <img src="<?php echo $posterUrl; ?>" alt="Affiche de <?php echo $displayTitle; ?>" class="movie-detail-poster" loading="lazy">
                </div>
                <div class="movie-detail-main-info">
                    <h1><?php echo $displayTitle; ?> <span class="release-year">(<?php echo $releaseYear; ?>)</span></h1>
                    <?php if ($tagline): ?><p class="tagline"><em><?php echo $tagline; ?></em></p><?php endif; ?>
                    <p><strong>Genres:</strong> <?php echo $genres; ?></p>
                    <p><strong>Durée:</strong> <?php echo $runtime; ?></p>
                    <p><strong>Note TMDB:</strong> <?php echo $tmdbVoteAverage; ?> (<?php echo number_format($tmdbVoteCount); ?> votes)</p>
                    
                    <?php if ($loggedInUserId): ?>
                        <div class="movie-actions">
                            <form method="POST" action="<?php echo $isInWatchlist ? 'remove_from_watchlist.php' : 'add.php'; ?>" class="inline-form">
                                <input type="hidden" name="movie_id" value="<?php echo (int)$movieId; ?>">
                                <input type="hidden" name="movie_title" value="<?php echo $displayTitle; ?>"> <?php // Already escaped ?>
                                <input type="hidden" name="poster_path" value="<?php echo htmlspecialchars($movieDetailsAPI['poster_path'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="<?php echo $isInWatchlist ? 'button-danger' : 'button-primary'; ?>">
                                    <?php echo $isInWatchlist ? 'Retirer de la Watchlist' : 'Ajouter à la Watchlist'; ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p><a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour gérer votre watchlist ou noter ce film.</p>
                    <?php endif; ?>
                </div>
            </header>

            <section class="movie-overview-section">
                <h2>Synopsis</h2>
                <p><?php echo $overview; /* Already nl2br and htmlspecialchars'd */ ?></p>
            </section>

            <?php if ($trailerKey): ?>
                <section class="trailer-section">
                    <h2>Bande-annonce</h2>
                    <div class="trailer-container">
                        <iframe src="https://www.youtube.com/embed/<?php echo $trailerKey; ?>" 
                                title="Bande-annonce YouTube pour <?php echo $displayTitle; ?>"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    </div>
                </section>
            <?php endif; ?>
            
            <?php if (!empty($cast)): ?>
                <section class="cast-section">
                    <h2>Acteurs Principaux</h2>
                    <div class="cast-list">
                        <?php foreach ($cast as $actor): ?>
                            <?php
                                $actorName = htmlspecialchars($actor['name'] ?? 'Nom inconnu', ENT_QUOTES, 'UTF-8');
                                $actorChar = htmlspecialchars($actor['character'] ?? 'Rôle inconnu', ENT_QUOTES, 'UTF-8');
                                $actorPhoto = !empty($actor['profile_path']) 
                                            ? 'https://image.tmdb.org/t/p/w185' . htmlspecialchars($actor['profile_path'], ENT_QUOTES, 'UTF-8')
                                            : 'assets/images/no_actor_photo.png'; // Create this placeholder
                            ?>
                            <div class="actor">
                                <img src="<?php echo $actorPhoto; ?>" alt="Photo de <?php echo $actorName; ?>" loading="lazy">
                                <p><strong><?php echo $actorName; ?></strong><br>en <?php echo $actorChar; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($directors)): ?>
                <section class="crew-section">
                    <h2>Réalisateur(s)</h2>
                    <p><?php echo implode(', ', $directors); /* Already escaped */ ?></p>
                </section>
            <?php endif; ?>

            <?php if ($loggedInUserId): ?>
                <section class="user-interaction-section card">
                    <h2>Votre Note et Commentaire</h2>
                    <form method="POST" action="rate_comment.php" novalidate>
                        <input type="hidden" name="movie_id" value="<?php echo (int)$movieId; ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <div class="form-group">
                            <label for="rating">Votre Note (1-10):</label>
                            <select name="rating" id="rating">
                                <option value="">-- Non Noté --</option>
                                <?php for ($i = 10; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($userRating === $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="comment">Votre Commentaire:</label>
                            <textarea name="comment" id="comment" rows="5" placeholder="Laissez un commentaire..."><?php echo htmlspecialchars($userCommentText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <button type="submit" class="button-primary">Soumettre</button>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($loggedInUserId): // Only show annotate button if logged in ?>
                <section class="annotate-scene-action card">
                    <h2>Annoter une Scène / Démarrer une Discussion</h2>
                    <p>Avez-vous une scène spécifique de "<?php echo $displayTitle; ?>" que vous souhaitez analyser ou discuter ?</p>
                    <a href="forum_create_thread.php?movie_id=<?php echo (int)$movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>" class="button-primary">
                        Annoter une Scène & Lancer la Discussion
                    </a>
                </section>
            <?php endif; ?>


            <section class="scene-annotations-list-section card">
                <h2>Annotations de Scènes & Discussions (<?php echo count($sceneAnnotationThreads); ?>)</h2>
                <?php if (!empty($sceneAnnotationThreads)): ?>
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
                <?php else: ?>
                    <p>Aucune annotation de scène pour ce film pour le moment.
                        <?php if ($loggedInUserId): ?>
                            Soyez le premier à en <a href="forum_create_thread.php?movie_id=<?php echo (int)$movieId; ?>&movie_title=<?php echo urlencode($displayTitle); ?>">créer une !</a>
                        <?php else: ?>
                            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour annoter.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>

            <section class="public-comments-section">
                <h2>Commentaires des Utilisateurs (<?php echo count($publicComments); ?>)</h2>
                <?php if (!empty($publicComments)): ?>
                    <div class="comments-list">
                        <?php foreach ($publicComments as $pComment): ?>
                            <div class="comment-item">
                                <p class="comment-meta">
                                    <strong>
                                        <a href="view_profile.php?id=<?php echo (int)$pComment['comment_user_id']; ?>">
                                            <?php echo htmlspecialchars($pComment['username'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </strong>
                                    <span class="comment-date">(Le <?php echo date('d/m/Y à H:i', strtotime($pComment['commented_at'])); ?>)</span>
                                </p>
                                <p class="comment-text"><?php echo nl2br(htmlspecialchars($pComment['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Aucun commentaire pour ce film pour le moment. Soyez le premier !</p>
                <?php endif; ?>
            </section>
        </article>
    <?php endif; // End of main content conditional display ?>
</main>

<?php
// $conn->close(); // Optional
include_once 'includes/footer.php';
?>