<?php
/*
 * index.php
 * Homepage: Displays trending movies and movie search functionality.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY

// Initialize variables
$searchResults = [];
$searchQueryDisplay = ''; // For displaying in the form, HTML escaped
$searchQueryParam = '';   // For using in API calls, raw
$trendingMovies = [];
$pageTitle = "Accueil - Eiganights"; // Default page title

// --- Fetch Trending Movies ---
$trendingUrl = "https://api.themoviedb.org/3/trending/movie/week?api_key=" . urlencode(TMDB_API_KEY) . "&language=fr-FR";
$trendingResponseJson = @file_get_contents($trendingUrl); // Suppress errors for network issues

if ($trendingResponseJson !== false) {
    $trendingData = json_decode($trendingResponseJson, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($trendingData['results'])) {
        $trendingMovies = array_slice($trendingData['results'], 0, 10); // Get top 10 trending
    } else {
        error_log("Failed to decode trending movies JSON or results empty. URL: $trendingUrl Error: " . json_last_error_msg());
    }
} else {
    error_log("Failed to fetch trending movies from TMDB. URL: $trendingUrl");
    // $_SESSION['warning'] = "Impossible de charger les films à la tendance pour le moment."; // Optional user message
}

// --- Handle Movie Search ---
if (isset($_GET['search'])) {
    $searchQueryParam = trim($_GET['search']);
    $searchQueryDisplay = htmlspecialchars($searchQueryParam, ENT_QUOTES, 'UTF-8');
    $pageTitle = "Recherche: " . $searchQueryDisplay . " - Eiganights";

    if (!empty($searchQueryParam)) {
        $searchUrl = "https://api.themoviedb.org/3/search/movie?api_key=" . urlencode(TMDB_API_KEY) . 
                     "&language=fr-FR&query=" . urlencode($searchQueryParam);
        
        $searchResponseJson = @file_get_contents($searchUrl);
        if ($searchResponseJson !== false) {
            $searchData = json_decode($searchResponseJson, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($searchData['results'])) {
                $searchResults = $searchData['results'];
            } else {
                error_log("Failed to decode search results JSON or 'results' key missing. Query: $searchQueryParam, URL: $searchUrl, Error: " . json_last_error_msg());
            }
        } else {
            error_log("Failed to fetch search results from TMDB. Query: $searchQueryParam, URL: $searchUrl");
            $_SESSION['error'] = "Erreur lors de la communication avec le service de films pour la recherche.";
        }
    }
    // If $searchQueryParam is empty after trim, no search is performed, shows trending by default.
}

include_once 'includes/header.php'; // Sets $pageTitle in <title>
?>
<?php
// echo '<main class="container">';
// echo '<h1>' . (defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Eiganights') . '</h1>';
// // Display session messages (ensure CSS for these classes exists)
?>


    
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning">
            <?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?>
        </div>
    <?php endif; ?>

    <section class="search-section" aria-labelledby="search-heading">
        <h2 id="search-heading" class="visually-hidden">Recherche de Films</h2> <?php // Hidden heading for accessibility ?>
        <form method="GET" action="index.php" class="search-box">
            <label for="search-input" class="visually-hidden">Rechercher un film</label> <?php // Hidden label for accessibility ?>
            <input type="text" id="search-input" name="search" placeholder="Ex: Inception, Star Wars..." value="<?php echo $searchQueryDisplay; ?>" />
            <input type="submit" value="Rechercher" />
        </form>
    </section>

    <?php if (!empty($searchResults)): ?>
        <section class="results-section" aria-labelledby="results-heading">
            <h2 id="results-heading">Résultats pour "<?php echo $searchQueryDisplay; ?>" :</h2>
            <div class="movies-grid">
                <?php foreach ($searchResults as $movie): ?>
                    <?php
                        $movie_id = $movie['id'] ?? null;
                        if (!$movie_id) continue; // Skip if essential data like ID is missing

                        $movie_title = $movie['title'] ?? 'Titre Inconnu';
                        $movie_poster_path = $movie['poster_path'] ?? null;
                        $movie_release_year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : 'N/A';
                        $movie_overview_full = $movie['overview'] ?? 'Pas de description disponible.';
                        // Ensure mb_strimwidth is available (usually is with mbstring extension)
                        $movie_overview_short = function_exists('mb_strimwidth') 
                                                ? mb_strimwidth($movie_overview_full, 0, 100, "...")
                                                : substr($movie_overview_full, 0, 97) . (strlen($movie_overview_full) > 100 ? "..." : "");
                    ?>
                    <article class="movie">
                        <a href="movie_details.php?id=<?php echo (int)$movie_id; ?>" aria-label="Détails pour <?php echo htmlspecialchars($movie_title); ?>">
                            <?php if ($movie_poster_path): ?>
                                <img src="https://image.tmdb.org/t/p/w300<?php echo htmlspecialchars($movie_poster_path); ?>" 
                                     alt="Affiche de <?php echo htmlspecialchars($movie_title); ?>" loading="lazy" />
                            <?php else: ?>
                                <img src="assets/images/no_poster_available.png" 
                                     alt="Pas d'affiche disponible pour <?php echo htmlspecialchars($movie_title); ?>" 
                                     class="movie-poster-placeholder" loading="lazy" />
                            <?php endif; ?>
                        </a>
                        <div class="movie-info">
                            <h3 class="movie-title">
                                <a href="movie_details.php?id=<?php echo (int)$movie_id; ?>"><?php echo htmlspecialchars($movie_title); ?> (<?php echo htmlspecialchars($movie_release_year); ?>)</a>
                            </h3>
                            <p class="movie-overview"><?php echo nl2br(htmlspecialchars($movie_overview_short)); ?></p>

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <form method="POST" action="add.php" class="add-watchlist-form">
                                    <input type="hidden" name="movie_id" value="<?php echo (int)$movie_id; ?>">
                                    <input type="hidden" name="movie_title" value="<?php echo htmlspecialchars($movie_title); ?>">
                                    <input type="hidden" name="poster_path" value="<?php echo htmlspecialchars($movie_poster_path ?? ''); ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(htmlspecialchars($_SERVER['REQUEST_URI'])); // Redirect back to current page with its params ?>">
                                    <input type="submit" value="Ajouter à ma watchlist" />
                                </form>
                            <?php else: ?>
                                <p class="login-prompt">
                                    <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour ajouter.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif (isset($_GET['search']) && $searchQueryParam !== '' && empty($searchResults)): // Executed a search but no results ?>
        <p>Aucun film trouvé pour "<?php echo $searchQueryDisplay; ?>". Veuillez essayer une autre recherche ou <a href="index.php">voir les tendances</a>.</p>
    <?php else: // No search performed or search query was empty, display trending movies ?>
        <?php if (!empty($trendingMovies)): ?>
            <section class="trending-section" aria-labelledby="trending-heading">
                <h2 id="trending-heading">Films à la Tendance cette semaine</h2>
                <div class="trending-movies-container">
                    <?php foreach ($trendingMovies as $movie): ?>
                        <?php
                            $movie_id = $movie['id'] ?? null;
                            if (!$movie_id) continue;

                            $movie_title = $movie['title'] ?? 'Titre Inconnu';
                            $movie_poster_path = $movie['poster_path'] ?? null;
                            $movie_release_year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '';
                            $link_title = htmlspecialchars($movie_title . ($movie_release_year ? " ($movie_release_year)" : ''));
                        ?>
                        <article class="trending-movie-item">
                            <a href="movie_details.php?id=<?php echo (int)$movie_id; ?>" title="<?php echo $link_title; ?>" aria-label="Détails pour <?php echo $link_title; ?>">
                                <?php if ($movie_poster_path): ?>
                                    <img src="https://image.tmdb.org/t/p/w300<?php echo htmlspecialchars($movie_poster_path); ?>" 
                                         alt="Affiche de <?php echo htmlspecialchars($movie_title); ?>" loading="lazy" />
                                <?php else: ?>
                                     <img src="assets/images/no_poster_available.png" 
                                          alt="Pas d'affiche disponible pour <?php echo htmlspecialchars($movie_title); ?>" 
                                          class="movie-poster-placeholder" loading="lazy" />
                                <?php endif; ?>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif (!isset($_GET['search'])): // Only show this if it's the initial page load and trending failed ?>
            <p>Impossible de charger les films à la tendance pour le moment. Veuillez <a href="index.php">réessayer</a>.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>