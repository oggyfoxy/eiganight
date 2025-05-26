<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$redirectUrl = 'index.php'; // Default redirect
if (isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])) {
    // Basic validation for local redirect
    if (parse_url($_POST['redirect_url'], PHP_URL_HOST) === null || parse_url($_POST['redirect_url'], PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        $redirectUrl = $_POST['redirect_url'];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $movieId = (int)$_POST['movie_id'];
    $movieTitle = $conn->real_escape_string($_POST['movie_title']);
    $posterPath = $conn->real_escape_string($_POST['poster_path']);

    // Vérifier si le film est déjà dans la watchlist
    $stmtCheck = $conn->prepare("SELECT id FROM watchlist WHERE user_id = ? AND movie_id = ?");
    $stmtCheck->bind_param("ii", $userId, $movieId);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows === 0) {
        $stmtInsert = $conn->prepare("INSERT INTO watchlist (user_id, movie_id, movie_title, poster_path) VALUES (?, ?, ?, ?)");
        $stmtInsert->bind_param("iiss", $userId, $movieId, $movieTitle, $posterPath);
        if ($stmtInsert->execute()) {
            $_SESSION['message'] = "Film ajouté à votre watchlist.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du film: " . $stmtInsert->error;
        }
        $stmtInsert->close();
    } else {
        $_SESSION['message'] = "Ce film est déjà dans votre watchlist.";
    }
    $stmtCheck->close();
} 

// If redirecting back to movie_details, use its session messages
if (strpos($redirectUrl, 'movie_details.php') !== false) {
    // Keep the generic session message, movie_details.php doesn't have its own for this action
}


header('Location: ' . $redirectUrl);
exit;