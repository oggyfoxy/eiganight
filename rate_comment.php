<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movie_id = (int)$_POST['movie_id'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $comment = trim($_POST['comment'] ?? '');

    // Enregistrer la note
    if ($rating !== null && $rating >= 1 && $rating <= 10) {
        // Vérifie si une note existe déjà
        $stmt = $conn->prepare("SELECT id FROM ratings WHERE user_id = ? AND movie_id = ?");
        $stmt->bind_param("ii", $user_id, $movie_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Mise à jour
            $stmt->close();
            $stmt = $conn->prepare("UPDATE ratings SET rating = ?, rated_at = NOW() WHERE user_id = ? AND movie_id = ?");
            $stmt->bind_param("iii", $rating, $user_id, $movie_id);
        } else {
            // Insertion
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO ratings (user_id, movie_id, rating) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $movie_id, $rating);
        }
        $stmt->execute();
        $stmt->close();
    }

    // Enregistrer le commentaire
    if (!empty($comment)) {
        // Vérifie si un commentaire existe déjà
        $stmt = $conn->prepare("SELECT id FROM comments WHERE user_id = ? AND movie_id = ?");
        $stmt->bind_param("ii", $user_id, $movie_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Mise à jour
            $stmt->close();
            $stmt = $conn->prepare("UPDATE comments SET comment = ?, commented_at = NOW() WHERE user_id = ? AND movie_id = ?");
            $stmt->bind_param("sii", $comment, $user_id, $movie_id);
        } else {
            // Insertion
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO comments (user_id, movie_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $movie_id, $comment);
        }
        $stmt->execute();
        $stmt->close();
    }
}

// Retour à la page profil
header("Location: profile.php");
exit;
?>
