<?php
session_start();
include('config.php');

$searchResults = [];
$searchQuery = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $apiKey = $TMDB_API_KEY;
    $searchUrl = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&language=fr-FR&query=" . urlencode($searchQuery);

    $response = @file_get_contents($searchUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (!empty($data['results'])) {
            $searchResults = $data['results'];
        }
    }
}
include('includes/header.php');
?>

<h1>Eiganights - Recherche de films</h1>

<form method="GET" action="" class="search-box">
    <input type="text" name="search" placeholder="Rechercher un film..." value="<?php echo htmlspecialchars($searchQuery); ?>" required />
    <input type="submit" value="Rechercher" />
</form>

<?php if (!empty($searchResults)): ?>
    <h2>Résultats pour "<?php echo htmlspecialchars($searchQuery); ?>" :</h2>
    <?php foreach ($searchResults as $movie): ?>
        <div class="movie">
            <?php if ($movie['poster_path']): ?>
                <img src="https://image.tmdb.org/t/p/w200<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" />
            <?php else: ?>
                <img src="https://via.placeholder.com/100x150?text=Pas+d'affiche" alt="Pas d'affiche" />
            <?php endif; ?>
            <div class="movie-info">
                <div class="movie-title"><?php echo htmlspecialchars($movie['title']); ?> (<?php echo substr($movie['release_date'] ?? '', 0, 4); ?>)</div>
                <div><?php echo nl2br(htmlspecialchars($movie['overview'] ?? 'Pas de description disponible.')); ?></div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="add.php" style="margin-top: 10px;">
                        <input type="hidden" name="movie_id" value="<?php echo $movie['id']; ?>">
                        <input type="hidden" name="movie_title" value="<?php echo htmlspecialchars($movie['title']); ?>">
                        <input type="hidden" name="poster_path" value="<?php echo $movie['poster_path']; ?>">
                        <input type="submit" value="Ajouter à ma watchlist" />
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Connectez-vous</a> pour ajouter ce film à votre watchlist.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php elseif (isset($_GET['search'])): ?>
    <p>Aucun résultat trouvé pour "<?php echo htmlspecialchars($searchQuery); ?>"</p>
<?php endif; ?>

<?php include('includes/footer.php'); ?>
