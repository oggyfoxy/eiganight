<?php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$defaultRedirectUrl = 'profile.php';
$redirectUrl = $defaultRedirectUrl;

if (isset($_POST['redirect_url']) && !empty(trim($_POST['redirect_url']))) {
    $postedRedirectUrl = trim($_POST['redirect_url']);
    $urlComponents = parse_url($postedRedirectUrl);
    if (empty($urlComponents['host']) || $urlComponents['host'] === $_SERVER['HTTP_HOST']) {
        $redirectUrl = $postedRedirectUrl;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Requête invalide pour cette action.";
    header('Location: ' . $redirectUrl);
    exit;
}

if (!isset($_POST['movie_id'])) {
    $_SESSION['error'] = "ID du film manquant pour la suppression.";
    header('Location: ' . $redirectUrl);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$movieId = (int)$_POST['movie_id'];

if ($movieId <= 0) {
    $_SESSION['error'] = "ID du film invalide fourni.";
    header('Location: ' . $redirectUrl);
    exit;
}

$sql = "DELETE FROM watchlist WHERE user_id = ? AND movie_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed for watchlist delete: (" . $conn->errno . ") " . $conn->error . " (Code: REM_PREP_DEL)");
    $_SESSION['error'] = "Une erreur système est survenue. Veuillez réessayer. (RW01)";
    header('Location: ' . $redirectUrl);
    exit;
}

$stmt->bind_param("ii", $userId, $movieId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Film retiré de votre watchlist.";
    } else {
        $_SESSION['warning'] = "Le film n'était pas dans votre watchlist ou l'ID était incorrect.";
    }
} else {
    error_log("Execute failed for watchlist delete: (" . $stmt->errno . ") " . $stmt->error . " (Code: REM_EXEC_DEL)");
    $_SESSION['error'] = "Erreur lors de la suppression du film de la watchlist. (RW02)";
}

$stmt->close();

header('Location: ' . $redirectUrl);
exit;
?>