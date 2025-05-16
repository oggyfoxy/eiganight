<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer infos user
$stmt = $conn->prepare("SELECT username, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $bio);
$stmt->fetch();
$stmt->close();

// Modifier bio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio'])) {
    $new_bio = $_POST['bio'];
    $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $stmt->bind_param("si", $new_bio, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: profile.php");
    exit;
}

// Récupérer watchlist avec notes et commentaires
$sql = "
SELECT w.movie_id, w.movie_title, w.poster_path, r.rating, c.comment
FROM watchlist w
LEFT JOIN ratings r ON w.user_id = r.user_id AND w.movie_id = r.movie_id
LEFT JOIN comments c ON w.user_id = c.user_id AND w.movie_id = c.movie_id
WHERE w.user_id = ?
ORDER BY w.added_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profil de <?=htmlspecialchars($username)?> – Eiganights</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
<div class="container py-5">

    <h1>👤 Profil de <?=htmlspecialchars($username)?></h1>
    <a href="logout.php" class="btn btn-danger mb-4">Se déconnecter</a>

    <form method="post" action="">
        <label for="bio">Ma bio :</label><br>
        <textarea name="bio" id="bio" rows="3" class="form-control mb-3"><?=htmlspecialchars($bio)?></textarea><br>
        <button type="submit" class="btn btn-primary">Modifier ma bio</button>
    </form>

    <h2 class="mt-5">🎬 Ma Watchlist</h2>
    <div class="row">
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card bg-secondary text-white h-100">
                <img src="https://image.tmdb.org/t/p/w500<?=$row['poster_path']?>" class="card-img-top" alt="<?=htmlspecialchars($row['movie_title'])?>">
                <div class="card-body">
                    <h5 class="card-title"><?=htmlspecialchars($row['movie_title'])?></h5>

                    <form action="rate_comment.php" method="post" class="mb-2">
                        <input type="hidden" name="movie_id" value="<?=$row['movie_id']?>">
                        <label>Note (1 à 10) :</label>
                        <input type="number" name="rating" min="1" max="10" value="<?= $row['rating'] ?? '' ?>">
                        <br>
                        <label>Commentaire :</label><br>
                        <textarea name="comment" rows="2"><?= htmlspecialchars($row['comment'] ?? '') ?></textarea><br>
                        <button type="submit" class="btn btn-sm btn-light mt-2">Envoyer</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

</div>
</body>
</html>
