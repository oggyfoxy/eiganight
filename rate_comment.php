<?php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour noter ou commenter un film.";
    if (isset($_POST['redirect_url']) && !empty(trim($_POST['redirect_url']))) {
        $redirectUrl = trim($_POST['redirect_url']);
        $urlComponents = parse_url($redirectUrl);
        if (!empty($urlComponents['host']) && $urlComponents['host'] !== $_SERVER['HTTP_HOST']) {
            $redirectUrl = 'index.php';
        }
    } else {
        $redirectUrl = 'login.php';
    }
    header('Location: ' . $redirectUrl);
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

if (!isset($_POST['movie_id']) || !is_numeric($_POST['movie_id'])) {
    $_SESSION['rate_comment_error'] = "ID du film manquant ou invalide.";
    header('Location: ' . $redirectUrl);
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$movieId = (int)$_POST['movie_id'];

$ratingInput = $_POST['rating'] ?? null;
$rating = null;
if ($ratingInput !== null && $ratingInput !== '') {
    $rating = (int)$ratingInput;
    if ($rating < 1 || $rating > 10) {
        $_SESSION['rate_comment_error'] = "La note doit être entre 1 et 10.";
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$commentInput = trim($_POST['comment'] ?? '');

$actionTakenRating = false;
$actionTakenComment = false;

if ($ratingInput !== null) {
    if ($rating !== null) {
        $sqlCheckRating = "SELECT id FROM ratings WHERE user_id = ? AND movie_id = ?";
        $stmtCheckRating = $conn->prepare($sqlCheckRating);
        if (!$stmtCheckRating) {
            error_log("Prepare failed (RC_CHK_RAT): " . $conn->error);
            $_SESSION['rate_comment_error'] = "Erreur système (note). (RC01)";
        } else {
            $stmtCheckRating->bind_param("ii", $loggedInUserId, $movieId);
            if (!$stmtCheckRating->execute()) {
                error_log("Execute failed (RC_CHK_RAT): " . $stmtCheckRating->error);
                $_SESSION['rate_comment_error'] = "Erreur système (note). (RC02)";
            } else {
                $stmtCheckRating->store_result();
                if ($stmtCheckRating->num_rows > 0) {
                    $stmtActionRating = $conn->prepare("UPDATE ratings SET rating = ?, rated_at = NOW() WHERE user_id = ? AND movie_id = ?");
                    if (!$stmtActionRating) { error_log("Prepare failed (RC_UPD_RAT): " . $conn->error); $_SESSION['rate_comment_error'] = "Erreur système (note). (RC03)"; }
                    else { $stmtActionRating->bind_param("iii", $rating, $loggedInUserId, $movieId); }
                } else {
                    $stmtActionRating = $conn->prepare("INSERT INTO ratings (user_id, movie_id, rating) VALUES (?, ?, ?)");
                     if (!$stmtActionRating) { error_log("Prepare failed (RC_INS_RAT): " . $conn->error); $_SESSION['rate_comment_error'] = "Erreur système (note). (RC04)"; }
                    else { $stmtActionRating->bind_param("iii", $loggedInUserId, $movieId, $rating); }
                }

                if (isset($stmtActionRating) && $stmtActionRating->execute()) {
                    $_SESSION['rate_comment_message'] = "Note enregistrée.";
                    $actionTakenRating = true;
                } elseif(isset($stmtActionRating)) {
                    error_log("Execute failed (RC_ACTION_RAT): " . $stmtActionRating->error);
                    $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur enregistrement note. (RC05)";
                }
                if(isset($stmtActionRating)) $stmtActionRating->close();
            }
            $stmtCheckRating->close();
        }
    } elseif ($ratingInput === '') {
        $sqlDeleteRating = "DELETE FROM ratings WHERE user_id = ? AND movie_id = ?";
        $stmtDeleteRating = $conn->prepare($sqlDeleteRating);
        if (!$stmtDeleteRating) {
            error_log("Prepare failed (RC_DEL_RAT): " . $conn->error);
            $_SESSION['rate_comment_error'] = "Erreur système (note). (RC06)";
        } else {
            $stmtDeleteRating->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtDeleteRating->execute()) {
                if ($stmtDeleteRating->affected_rows > 0) {
                    $_SESSION['rate_comment_message'] = "Note supprimée.";
                    $actionTakenRating = true;
                }
            } else {
                error_log("Execute failed (RC_DEL_RAT): " . $stmtDeleteRating->error);
                $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur suppression note. (RC07)";
            }
            $stmtDeleteRating->close();
        }
    }
}

if (isset($_POST['comment'])) {
    if (!empty($commentInput)) {
        $sqlCheckComment = "SELECT id FROM comments WHERE user_id = ? AND movie_id = ?";
        $stmtCheckComment = $conn->prepare($sqlCheckComment);
        if (!$stmtCheckComment) {
            error_log("Prepare failed (RC_CHK_COM): " . $conn->error);
            $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur système (commentaire). (RC08)";
        } else {
            $stmtCheckComment->bind_param("ii", $loggedInUserId, $movieId);
            if (!$stmtCheckComment->execute()) {
                 error_log("Execute failed (RC_CHK_COM): " . $stmtCheckComment->error);
                $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur système (commentaire). (RC09)";
            } else {
                $stmtCheckComment->store_result();
                if ($stmtCheckComment->num_rows > 0) {
                    $stmtActionComment = $conn->prepare("UPDATE comments SET comment = ?, commented_at = NOW() WHERE user_id = ? AND movie_id = ?");
                    if (!$stmtActionComment) { error_log("Prepare failed (RC_UPD_COM): " . $conn->error); $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur système (commentaire). (RC10)";}
                    else { $stmtActionComment->bind_param("sii", $commentInput, $loggedInUserId, $movieId); }
                } else {
                    $stmtActionComment = $conn->prepare("INSERT INTO comments (user_id, movie_id, comment) VALUES (?, ?, ?)");
                    if (!$stmtActionComment) { error_log("Prepare failed (RC_INS_COM): " . $conn->error); $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur système (commentaire). (RC11)";}
                    else { $stmtActionComment->bind_param("iis", $loggedInUserId, $movieId, $commentInput); }
                }

                if (isset($stmtActionComment) && $stmtActionComment->execute()) {
                    $_SESSION['rate_comment_message'] = (isset($_SESSION['rate_comment_message']) ? trim($_SESSION['rate_comment_message'] . " ") : "") . "Commentaire enregistré.";
                    $actionTakenComment = true;
                } elseif (isset($stmtActionComment)) {
                    error_log("Execute failed (RC_ACTION_COM): " . $stmtActionComment->error);
                    $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur enregistrement commentaire. (RC12)";
                }
                 if(isset($stmtActionComment)) $stmtActionComment->close();
            }
            $stmtCheckComment->close();
        }
    } elseif (isset($_POST['comment']) && $commentInput === '') {
        $sqlDeleteComment = "DELETE FROM comments WHERE user_id = ? AND movie_id = ?";
        $stmtDeleteComment = $conn->prepare($sqlDeleteComment);
         if (!$stmtDeleteComment) {
            error_log("Prepare failed (RC_DEL_COM): " . $conn->error);
            $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur système (commentaire). (RC13)";
        } else {
            $stmtDeleteComment->bind_param("ii", $loggedInUserId, $movieId);
            if ($stmtDeleteComment->execute()) {
                if ($stmtDeleteComment->affected_rows > 0) {
                    $_SESSION['rate_comment_message'] = (isset($_SESSION['rate_comment_message']) ? trim($_SESSION['rate_comment_message'] . " ") : "") . "Commentaire supprimé.";
                    $actionTakenComment = true;
                }
            } else {
                error_log("Execute failed (RC_DEL_COM): " . $stmtDeleteComment->error);
                $_SESSION['rate_comment_error'] = (isset($_SESSION['rate_comment_error']) ? $_SESSION['rate_comment_error'] . " " : "") . "Erreur suppression commentaire. (RC14)";
            }
            $stmtDeleteComment->close();
        }
    }
}

if (!$actionTakenRating && !$actionTakenComment && 
    !isset($_SESSION['rate_comment_error']) && !isset($_SESSION['rate_comment_message'])) {
    $_SESSION['rate_comment_warning'] = "Aucune modification n'a été soumise ou détectée.";
}

// $conn->close(); // Optional
header("Location: " . $redirectUrl);
exit;
?>