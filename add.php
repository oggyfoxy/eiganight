<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $movieId = (int)$_POST['movie_id'];
    $movieTitle = $conn->real_escape_string($_POST['movie_title']);
    $posterPath = $conn->real_escape_string($_POST['poster_path']);

    // Vérifier si le film est déjà dans la watchlist
    $checkQuery = "SELECT * FROM watchlist WHERE user_id = $userId AND movie_id = $movieId";
    $result = $conn->query($checkQuery);

    if ($result && $result->num_rows === 0) {
        $insertQuery = "INSERT INTO watchlist (user_id, movie_id, movie_title, poster_path) VALUES ($userId, $movieId, '$movieTitle', '$posterPath')";
        if ($conn->query($insertQuery)) {
            $_SESSION['message'] = "Film ajouté à votre watchlist.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du film.";
        }
    } else {
        $_SESSION['message'] = "Ce film est déjà dans votre watchlist.";
    }
} 

header('Location: index.php');
exit;
