<?php
include("config.php");
session_start();

$user_id = 1; // Simulé (tu ajouteras login plus tard)

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $movie_id = (int)$_POST["movie_id"];
    $title = $conn->real_escape_string($_POST["title"]);
    $poster = $conn->real_escape_string($_POST["poster_path"]);

    $conn->query("INSERT INTO watchlist (user_id, movie_id, movie_title, poster_path)
                  VALUES ($user_id, $movie_id, '$title', '$poster')");
}

header("Location: profile.php");
exit;
