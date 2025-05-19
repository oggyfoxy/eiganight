<?php
/*
 * forum_delete_thread.php
 * Handles the deletion of a forum thread by its author or an admin.
 */
include_once 'config.php'; // Includes session_start(), db connection ($conn)

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Validate request method and essential parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['thread_id']) || !is_numeric($_POST['thread_id']) /*||
    !isset($_POST['csrf_token']) */ ) { // Validate CSRF token later
    
    $_SESSION['forum_error'] = "Requête invalide ou données manquantes pour la suppression.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}

// CSRF Token Validation - Placeholder - Implement this properly

if (!function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['forum_error'] = "Erreur de sécurité. Veuillez réessayer.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}


$loggedInUserId = (int)$_SESSION['user_id'];
$threadIdToDelete = (int)$_POST['thread_id'];

// --- Fetch thread author to verify permission ---
$threadAuthorId = null;
$stmt_check_author = $conn->prepare("SELECT user_id FROM forum_threads WHERE id = ?");
if (!$stmt_check_author) {
    error_log("Prepare failed (DEL_THREAD_AUTH_CHK): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système lors de la vérification des permissions. (FDT01)";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$stmt_check_author->bind_param("i", $threadIdToDelete);
if (!$stmt_check_author->execute()) {
    error_log("Execute failed (DEL_THREAD_AUTH_CHK): " . $stmt_check_author->error);
    $_SESSION['forum_error'] = "Erreur système lors de la vérification des permissions. (FDT02)";
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
    $_SESSION['forum_error'] = "Discussion non trouvée ou déjà supprimée.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}

// --- Permission Check: User must be author OR admin ---
$isAuthor = ($loggedInUserId === $threadAuthorId);
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$isAuthor && !$isAdmin) {
    $_SESSION['forum_error'] = "Action non autorisée. Vous ne pouvez supprimer que vos propres discussions.";
    // Redirect back to the thread they tried to delete, or forum index
    header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $threadIdToDelete);
    exit;
}

// --- Proceed with Deletion ---
// Deleting a thread will also delete its posts due to ON DELETE CASCADE in DB schema (forum_posts.thread_id)
$sql_delete_thread = "DELETE FROM forum_threads WHERE id = ?";
$stmt_delete_thread = $conn->prepare($sql_delete_thread);

if (!$stmt_delete_thread) {
    error_log("Prepare failed (DEL_THREAD): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système lors de la suppression de la discussion. (FDT03)";
} else {
    $stmt_delete_thread->bind_param("i", $threadIdToDelete);
    if ($stmt_delete_thread->execute()) {
        if ($stmt_delete_thread->affected_rows > 0) {
            $_SESSION['forum_message'] = "Discussion supprimée avec succès.";
        } else {
            // Should not happen if author check passed and thread existed.
            $_SESSION['forum_warning'] = "La discussion n'a pas pu être supprimée (peut-être déjà supprimée).";
        }
    } else {
        error_log("Execute failed (DEL_THREAD): " . $stmt_delete_thread->error);
        $_SESSION['forum_error'] = "Erreur lors de la suppression de la discussion. (FDT04)";
    }
    $stmt_delete_thread->close();
}

// $conn->close(); // Optional
header('Location: ' . BASE_URL . 'forum.php'); // Redirect to forum index after deletion
exit;
?>