<?php
include_once 'config.php';
include_once 'includes/functions.php';


if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
$loggedInUserId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['thread_id']) || !is_numeric($_POST['thread_id']) ||
    !isset($_POST['csrf_token'])) {

    $_SESSION['forum_error'] = "Requête invalide ou données manquantes pour la suppression.";
    $redirect_url = BASE_URL . 'forum.php';
    if (isset($_POST['thread_id']) && is_numeric($_POST['thread_id'])) {
        $redirect_url = BASE_URL . 'forum_view_thread.php?id=' . (int)$_POST['thread_id'];
    }
    header('Location: ' . $redirect_url);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['forum_error'] = "Erreur de sécurité (jeton invalide). Veuillez actualiser la page et réessayer.";
    unset($_SESSION['csrf_token']);
    $redirect_url = BASE_URL . 'forum.php';
    if (isset($_POST['thread_id']) && is_numeric($_POST['thread_id'])) {
        $redirect_url = BASE_URL . 'forum_view_thread.php?id=' . (int)$_POST['thread_id'];
    }
    header('Location: ' . $redirect_url);
    exit;
}
unset($_SESSION['csrf_token']);

$threadIdToDelete = (int)$_POST['thread_id'];

$threadAuthorId = null;
$stmt_check_author = $conn->prepare("SELECT user_id FROM forum_threads WHERE id = ?");
if (!$stmt_check_author) {
    error_log("Prepare failed (DEL_THREAD_AUTH_CHK): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système (FDT01).";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$stmt_check_author->bind_param("i", $threadIdToDelete);
if (!$stmt_check_author->execute()) {
    error_log("Execute failed (DEL_THREAD_AUTH_CHK): " . $stmt_check_author->error);
    $_SESSION['forum_error'] = "Erreur système (FDT02).";
    $stmt_check_author->close();
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$result_author = $stmt_check_author->get_result();
if ($thread_info = $result_author->fetch_assoc()) {
    $threadAuthorId = (int)$thread_info['user_id'];
}
$stmt_check_author->close();

if ($threadAuthorId === null) {
    $_SESSION['forum_warning'] = "Discussion non trouvée ou déjà supprimée.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}

$isAuthor = ($loggedInUserId === $threadAuthorId);
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$isAuthor && !$isAdmin) {
    $_SESSION['forum_error'] = "Action non autorisée.";
    header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $threadIdToDelete);
    exit;
}

$sql_delete_thread = "DELETE FROM forum_threads WHERE id = ?";
$stmt_delete_thread = $conn->prepare($sql_delete_thread);

if (!$stmt_delete_thread) {
    error_log("Prepare failed (DEL_THREAD): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système (FDT03).";
} else {
    $stmt_delete_thread->bind_param("i", $threadIdToDelete);
    if ($stmt_delete_thread->execute()) {
        if ($stmt_delete_thread->affected_rows > 0) {
            $_SESSION['forum_message'] = "Discussion supprimée avec succès.";
        } else {
            $_SESSION['forum_warning'] = "La discussion n'a pas pu être supprimée.";
        }
    } else {
        error_log("Execute failed (DEL_THREAD): " . $stmt_delete_thread->error);
        $_SESSION['forum_error'] = "Erreur lors de la suppression (FDT04).";
    }
    $stmt_delete_thread->close();
}

header('Location: ' . BASE_URL . 'forum.php');
exit;
?>