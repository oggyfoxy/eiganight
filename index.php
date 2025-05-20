<?php
/*
 * index.php
 * Homepage: Displays various sections of movies inspired by Letterboxd.
 */
require_once 'config.php'; // Utiliser require_once pour config.php

// --- Fonctions d'aide pour récupérer les données de TMDB ---
// Idéalement, ces fonctions seraient dans includes/functions.php

if (!function_exists('fetch_tmdb_movies')) {
    function fetch_tmdb_movies($endpoint, $params = [], $max_results = 10) {
        if (empty(TMDB_API_KEY) || TMDB_API_KEY === 'YOUR_ACTUAL_TMDB_API_KEY') {
            error_log("TMDB_API_KEY non configurée pour fetch_tmdb_movies.");
            return []; // Retourner un tableau vide si la clé API n'est pas configurée
        }
        $base_api_url = "https://api.themoviedb.org/3/";
        $default_params = [
            'api_key' => TMDB_API_KEY,
            'language' => 'fr-FR',
            'page' => 1 // Par défaut, nous ne prenons que la première page
        ];
        $query_params = http_build_query(array_merge($default_params, $params));
        $url = $base_api_url . $endpoint . "?" . $query_params;

        $response_json = @file_get_contents($url);
        if ($response_json === false) {
            error_log("Erreur lors de la récupération des données TMDB pour l'endpoint: " . $endpoint);
            return [];
        }
        $data = json_decode($response_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['results'])) {
            error_log("Erreur de décodage JSON ou résultats manquants pour l'endpoint: " . $endpoint);
            return [];
        }
        return array_slice($data['results'], 0, $max_results);
    }
}

if (!function_exists('display_movie_grid_section')) {
    function display_movie_grid_section($title, $movies, $section_id = '') {
        if (empty($movies)) {
            // Optionnel: afficher un message si aucun film n'est trouvé pour cette section
            // echo "<p>Aucun film à afficher pour la section : " . htmlspecialchars($title) . "</p>";
            return; // Ne rien afficher si pas de films
        }
        $section_id_attr = $section_id ? 'id="' . htmlspecialchars($section_id) . '"' : '';
        echo '<section class="movie-list-section card" ' . $section_id_attr . ' aria-labelledby="' . htmlspecialchars(strtolower(str_replace(' ', '-', $title))) . '-heading">';
        echo '  <h2 id="' . htmlspecialchars(strtolower(str_replace(' ', '-', $title))) . '-heading">' . htmlspecialchars($title) . '</h2>';
        echo '  <div class="movies-grid homepage-grid">'; // Classe spécifique pour le stylage de la homepage

        foreach ($movies as $movie) {
            if (empty($movie['id']) || empty($movie['title'])) continue; // Sauter les films sans ID ou titre

            $movie_id = (int)$movie['id'];
            $movie_title = htmlspecialchars($movie['title']);
            $poster_path = $movie['poster_path'] ?? null;
            $release_year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '';
            $link_title = $movie_title . ($release_year ? " ({$release_year})" : '');

            $poster_url = $poster_path
                ? "https://image.tmdb.org/t/p/w300" . htmlspecialchars($poster_path)
                : BASE_URL . "assets/images/no_poster_available.png";
            $poster_alt = $poster_path ? "Affiche de " . $movie_title : "Pas d'affiche disponible";

            echo '<article class="movie-item">';
            echo '  <a href="' . BASE_URL . 'movie_details.php?id=' . $movie_id . '" title="' . htmlspecialchars($link_title) . '" aria-label="Détails pour ' . htmlspecialchars($link_title) . '" class="movie-poster-link">';
            echo '    <img src="' . $poster_url . '" alt="' . htmlspecialchars($poster_alt) . '" loading="lazy" class="movie-poster-grid"/>';
            echo '  </a>';
            echo '  <div class="movie-item-info">';
            echo '    <h3 class="movie-item-title"><a href="' . BASE_URL . 'movie_details.php?id=' . $movie_id . '">' . $movie_title . '</a></h3>';
            if ($release_year) {
                echo '    <p class="movie-item-year">' . $release_year . '</p>';
            }
            // Optionnel: Afficher la note moyenne TMDB
            // if (isset($movie['vote_average']) && $movie['vote_average'] > 0) {
            //     echo '    <p class="movie-item-rating">★ ' . number_format($movie['vote_average'], 1) . '</p>';
            // }
            echo '  </div>';
            echo '</article>';
        }
        echo '  </div>'; // Fin .movies-grid
        // Optionnel: Ajouter un lien "Voir plus" pour chaque section
        // echo '  <div class="section- देख_plus_link"><a href="#">Voir plus de ' . htmlspecialchars($title) . ' »</a></div>';
        echo '</section>';
    }
}

// --- Récupération des différentes listes de films ---
$pageTitle = "Accueil - " . (defined('SITE_NAME') ? SITE_NAME : "EigaNights");
$number_of_movies_per_section = 12; // Nombre de films à afficher par section

// 1. Films à la Tendance (Trending)
$trendingMovies = fetch_tmdb_movies('trending/movie/week', [], $number_of_movies_per_section);

// 2. Films Populaires
$popularMovies = fetch_tmdb_movies('movie/popular', [], $number_of_movies_per_section);

// 3. Films les Mieux Notés
$topRatedMovies = fetch_tmdb_movies('movie/top_rated', [], $number_of_movies_per_section);

// 4. Prochaines Sorties
$upcomingMovies = fetch_tmdb_movies('movie/upcoming', ['region' => 'FR'], $number_of_movies_per_section); // Spécifier la région pour les sorties

// Recherche de films (reste de votre logique de recherche si besoin, sinon on se concentre sur les sections)
$searchResults = [];
$searchQueryDisplay = '';
if (isset($_GET['search'])) {
    $searchQueryParam = trim($_GET['search']);
    if (!empty($searchQueryParam)) {
        $searchQueryDisplay = htmlspecialchars($searchQueryParam, ENT_QUOTES, 'UTF-8');
        $pageTitle = "Recherche: " . $searchQueryDisplay . " - " . (defined('SITE_NAME') ? SITE_NAME : "EigaNights");
        $searchResults = fetch_tmdb_movies('search/movie', ['query' => $searchQueryParam], 20); // Afficher plus de résultats pour la recherche
    }
}

include_once 'includes/header.php';
?>

<main class="container homepage-content">

    <?php // Affichage des messages de session ?>
    <?php foreach (['message', 'error', 'warning'] as $msgKey): ?>
        <?php if (!empty($_SESSION[$msgKey])): ?>
            <div class="alert <?php echo $msgKey === 'error' ? 'alert-danger' : ($msgKey === 'warning' ? 'alert-warning' : 'alert-success'); ?>" role="alert">
                <?php echo htmlspecialchars($_SESSION[$msgKey]); unset($_SESSION[$msgKey]); ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php // La barre de recherche est maintenant dans le header, pas besoin de la dupliquer ici à moins d'un design spécifique ?>
    <?php /*
    <section class="search-section-homepage card" aria-labelledby="search-heading">
        <h2 id="search-heading" class="visually-hidden">Recherche de Films</h2>
        <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="search-box">
            <label for="search-input" class="visually-hidden">Rechercher un film</label>
            <input type="text" id="search-input" name="search" placeholder="Ex: Inception, Star Wars..." value="<?php echo $searchQueryDisplay; ?>" />
            <button type="submit" class="button-primary">Rechercher</button>
        </form>
    </section>
    */ ?>

    <?php if (!empty($searchResults)): // Si une recherche a été effectuée, afficher les résultats ?>
        <?php display_movie_grid_section('Résultats pour "' . $searchQueryDisplay . '"', $searchResults, 'search-results'); ?>
    <?php else: // Sinon, afficher les sections par défaut ?>

        <?php display_movie_grid_section('Films à la Tendance cette semaine', $trendingMovies, 'trending-movies'); ?>

        <?php display_movie_grid_section('Films Populaires du Moment', $popularMovies, 'popular-movies'); ?>

        <?php display_movie_grid_section('Films les Mieux Notés', $topRatedMovies, 'top-rated-movies'); ?>
        
        <?php display_movie_grid_section('Prochaines Sorties en France', $upcomingMovies, 'upcoming-movies'); ?>

    <?php endif; ?>

</main>

<?php
include_once 'includes/footer.php';
?>