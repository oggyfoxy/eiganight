<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Récupérer les infos utilisateur
$userQuery = "SELECT username, bio FROM users WHERE id = $userId";
$userResult = $conn->query($userQuery);
$user = $userResult->fetch_assoc();

// Gestion update bio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio'])) {
    $newBio = $conn->real_escape_string($_POST['bio']);
    $updateQuery = "UPDATE users SET bio = '$newBio' WHERE id = $userId";
    if ($conn->query($updateQuery)) {
        $_SESSION['message'] = "Bio mise à jour.";
        header('Location: profile.php');
        exit;
    } else {
        $_SESSION['error'] = "Erreur lors de la mise à jour.";
    }
}

// Récupérer watchlist
$watchlistQuery = "SELECT * FROM watchlist WHERE user_id = $userId";
$watchlistResult = $conn->query($watchlistQuery);

include('includes/header.php');
?>

<h1>Profil de <?php echo htmlspecialchars($user['username']); ?></h1>

<?php if (!empty($_SESSION['message'])): ?>
    <p style="color:green;"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></p>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <p style="color:red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
<?php endif; ?>

<h2>Bio</h2>
<form method="POST" action="">
    <textarea name="bio" rows="4" cols="50" placeholder="Votre bio..."><?php echo htmlspecialchars($user['bio']); ?></textarea><br/>
    <input type="submit" value="Mettre à jour" />
</form>

<h2>Ma Watchlist</h2>
<?php if ($watchlistResult->num_rows > 0): ?>
    <?php while ($movie = $watchlistResult->fetch_assoc()): ?>
        <div class="movie">
            <?php if ($movie['poster_path']): ?>
                <img src="https://image.tmdb.org/t/p/w200<?php echo $movie['poster_path']; ?>" alt="<?php echo htmlspecialchars($movie['movie_title']); ?>" />
            <?php else: ?>
                <img src="https://via.placeholder.com/100x150?text=Pas+d'affiche" alt="Pas d'affiche" />
            <?php endif; ?>
            <div class="movie-info">
                <div class="movie-title"><?php echo htmlspecialchars($movie['movie_title']); ?></div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>Votre watchlist est vide.</p>
<?php endif; ?>

<?php include('includes/footer.php'); ?>
