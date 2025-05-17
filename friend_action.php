<?php
/*
 * friend_action.php
 * Handles all friendship-related actions: send, cancel, accept, decline, unfriend.
 */
include_once 'config.php'; // Includes session_start(), db connection ($conn)

// Error reporting: should be set in config.php
// For development, ensure errors are displayed. For production, they should be logged.

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: login.php');
    exit;
}

// Validate request method and essential parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['action'], $_POST['profile_user_id']) ||
    empty(trim($_POST['action'])) || !is_numeric($_POST['profile_user_id'])) {
    
    $_SESSION['error'] = "Requête invalide ou données manquantes.";
    header('Location: index.php'); // Or a more appropriate default page
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$profileUserId = (int)$_POST['profile_user_id'];
$action = trim($_POST['action']);

// Users cannot perform friend actions on themselves
if ($loggedInUserId === $profileUserId) {
    $_SESSION['error'] = "Vous ne pouvez pas effectuer cette action avec vous-même.";
    header('Location: profile.php'); // Redirect to their own profile
    exit;
}

// For database consistency (user_one_id < user_two_id)
$user_one_id = min($loggedInUserId, $profileUserId);
$user_two_id = max($loggedInUserId, $profileUserId);

// Determine redirect URL with security check
$redirectUrl = 'view_profile.php?id=' . $profileUserId; // Default redirect
if (isset($_POST['redirect_url']) && !empty(trim($_POST['redirect_url']))) {
    $postedRedirectUrl = trim($_POST['redirect_url']);
    $urlComponents = parse_url($postedRedirectUrl);
    if (empty($urlComponents['host']) || $urlComponents['host'] === $_SERVER['HTTP_HOST']) {
        $redirectUrl = $postedRedirectUrl;
    }
    // If not a local path, $redirectUrl remains the default.
}


// --- Main switch logic for friendship actions ---
switch ($action) {
    case 'send_request':
        $stmt_check = $conn->prepare("SELECT status FROM friendships WHERE user_one_id = ? AND user_two_id = ?");
        if (!$stmt_check) {
            error_log("Prepare failed (FS_CHK_SR): " . $conn->error);
            $_SESSION['error'] = "Erreur système. Veuillez réessayer. (Code: FS01)";
            break;
        }
        $stmt_check->bind_param("ii", $user_one_id, $user_two_id);
        if (!$stmt_check->execute()) {
            error_log("Execute failed (FS_CHK_SR): " . $stmt_check->error);
            $_SESSION['error'] = "Erreur système. Veuillez réessayer. (Code: FS02)";
            $stmt_check->close();
            break;
        }
        $result_check = $stmt_check->get_result();

        if ($row = $result_check->fetch_assoc()) { // Existing relationship
            if ($row['status'] === 'accepted') {
                $_SESSION['message'] = "Vous êtes déjà amis.";
            } elseif ($row['status'] === 'pending') {
                $_SESSION['message'] = "Une demande d'ami est déjà en attente.";
            } elseif ($row['status'] === 'declined') {
                // Allow re-sending request if it was declined by the other user
                // The action_user_id for 'declined' would be the one who declined.
                // If $loggedInUserId is sending again, it's a new intent.
                $stmt_update = $conn->prepare("UPDATE friendships SET status = 'pending', action_user_id = ?, requested_at = NOW(), updated_at = NOW() WHERE user_one_id = ? AND user_two_id = ? AND status = 'declined'");
                if (!$stmt_update) { error_log("Prepare failed (FS_UPD_DEC): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS03)"; $stmt_check->close(); break; }
                $stmt_update->bind_param("iii", $loggedInUserId, $user_one_id, $user_two_id);
                $_SESSION['message'] = $stmt_update->execute() ? "Demande d'ami envoyée." : "Erreur lors de la mise à jour de la demande. (Code: FS04)";
                if (!$stmt_update->execute()) error_log("Execute failed (FS_UPD_DEC): " . $stmt_update->error);
                $stmt_update->close();
            } elseif ($row['status'] === 'blocked') {
                 $_SESSION['error'] = "Impossible d'envoyer une demande à cet utilisateur pour le moment.";
            }
        } else { // No existing relationship, insert new request
            $stmt_insert = $conn->prepare("INSERT INTO friendships (user_one_id, user_two_id, status, action_user_id) VALUES (?, ?, 'pending', ?)");
            if (!$stmt_insert) { error_log("Prepare failed (FS_INS_SR): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS05)"; $stmt_check->close(); break; }
            $stmt_insert->bind_param("iii", $user_one_id, $user_two_id, $loggedInUserId);
            if ($stmt_insert->execute()) {
                $_SESSION['message'] = "Demande d'ami envoyée.";
            } else {
                error_log("Execute failed (FS_INS_SR): " . $stmt_insert->error . " UserOne: $user_one_id, UserTwo: $user_two_id");
                // Check for specific errors like CHECK constraint violation FS06-C (errno 3819 for MySQL 8+)
                $_SESSION['error'] = "Erreur lors de l'envoi de la demande. (Code: FS06)";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
        break;

    case 'cancel_request':
        $stmt_delete = $conn->prepare("DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ? AND status = 'pending' AND action_user_id = ?");
        if (!$stmt_delete) { error_log("Prepare failed (FS_DEL_CR): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS07)"; break; }
        $stmt_delete->bind_param("iii", $user_one_id, $user_two_id, $loggedInUserId); // LoggedInUser must be the action_user_id
        if ($stmt_delete->execute()) {
            $_SESSION['message'] = ($stmt_delete->affected_rows > 0) ? "Demande d'ami annulée." : "Aucune demande à annuler trouvée ou action non autorisée.";
        } else {
            error_log("Execute failed (FS_DEL_CR): " . $stmt_delete->error);
            $_SESSION['error'] = "Erreur lors de l'annulation. (Code: FS08)";
        }
        $stmt_delete->close();
        break;

    case 'accept_request':
        // Request was sent by $profileUserId, so $profileUserId should be the action_user_id in DB for 'pending' status
        $stmt_update = $conn->prepare("UPDATE friendships SET status = 'accepted', action_user_id = ?, updated_at = NOW() WHERE user_one_id = ? AND user_two_id = ? AND status = 'pending' AND action_user_id = ?");
        if (!$stmt_update) { error_log("Prepare failed (FS_UPD_AR): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS09)"; break; }
        $stmt_update->bind_param("iiii", $loggedInUserId, $user_one_id, $user_two_id, $profileUserId);
        if ($stmt_update->execute()) {
            $_SESSION['message'] = ($stmt_update->affected_rows > 0) ? "Demande d'ami acceptée." : "Aucune demande à accepter ou action déjà effectuée/invalide.";
        } else {
            error_log("Execute failed (FS_UPD_AR): " . $stmt_update->error);
            $_SESSION['error'] = "Erreur lors de l'acceptation. (Code: FS10)";
        }
        $stmt_update->close();
        break;

    case 'decline_request':
        // Request was sent by $profileUserId
        $stmt_update = $conn->prepare("UPDATE friendships SET status = 'declined', action_user_id = ?, updated_at = NOW() WHERE user_one_id = ? AND user_two_id = ? AND status = 'pending' AND action_user_id = ?");
        if (!$stmt_update) { error_log("Prepare failed (FS_UPD_DR): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS11)"; break; }
        $stmt_update->bind_param("iiii", $loggedInUserId, $user_one_id, $user_two_id, $profileUserId);
        if ($stmt_update->execute()) {
            $_SESSION['message'] = ($stmt_update->affected_rows > 0) ? "Demande d'ami refusée." : "Aucune demande à refuser ou action déjà effectuée/invalide.";
        } else {
            error_log("Execute failed (FS_UPD_DR): " . $stmt_update->error);
            $_SESSION['error'] = "Erreur lors du refus. (Code: FS12)";
        }
        $stmt_update->close();
        break;

    case 'unfriend':
        $stmt_delete = $conn->prepare("DELETE FROM friendships WHERE user_one_id = ? AND user_two_id = ? AND status = 'accepted'");
        if (!$stmt_delete) { error_log("Prepare failed (FS_DEL_UF): " . $conn->error); $_SESSION['error'] = "Erreur système. (Code: FS13)"; break; }
        $stmt_delete->bind_param("ii", $user_one_id, $user_two_id);
        if ($stmt_delete->execute()) {
            $_SESSION['message'] = ($stmt_delete->affected_rows > 0) ? "Ami retiré." : "Relation d'amitié non trouvée.";
        } else {
            error_log("Execute failed (FS_DEL_UF): " . $stmt_delete->error);
            $_SESSION['error'] = "Erreur lors du retrait de l'ami. (Code: FS14)";
        }
        $stmt_delete->close();
        break;
    
    // case 'block_user':
    // case 'unblock_user':
        // Implement with similar error checking and logic if needed.
        // $_SESSION['error'] = "Cette fonctionnalité n'est pas encore disponible.";
        // break;

    default:
        $_SESSION['error'] = "Action non reconnue ou invalide.";
}

// $conn->close(); // Optional: close if this is the absolute end of DB interaction for this request.
header('Location: ' . $redirectUrl);
exit;
?>