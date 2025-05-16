<?php
session_start();
include('config.php');

$searchResults = [];
$searchQuery = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $apiKey = 'cf536f66b460a5cf45e5e4bc648f5e81';  // ta clé TMDb
    $searchUrl = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&language=fr-FR&query=" . urlencode($searchQuery);

    $response = file_get_contents($searchUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (!empty($data['results'])) {
            $searchResults = $data['results'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<title>Eiganights - Recherche de films</title>
<style>
    body { font-family: Arial, sans-serif; background:#222; color:#eee; }
    .search-box { margin: 20px auto; max-width: 600px; }
    input[type="text"] { width: 80%; padding: 10px; font-size: 16px; }
    input[type="submit"] { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    .movie { display: flex; margin: 20px 0; border-bottom: 1px solid #444; padding-bottom: 15px; }
    .movie img { width: 100px; margin-right: 20px; }
    .movie-info { max-width: 500px; }
    .movie-title { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
</style>
</head>
<body>

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
            </div>
        </div>
    <?php endforeach; ?>
<?php elseif (isset($_GET['search'])): ?>
    <p>Aucun résultat trouvé pour "<?php echo htmlspecialchars($searchQuery); ?>"</p>
<?php endif; ?>

</body>
</html>
