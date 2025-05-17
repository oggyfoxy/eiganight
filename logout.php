<?php
/*
 * logout.php
 * Handles user logout by destroying the session.
 */

// It's good practice to include config for consistent session handling,
// even if this script's primary goal is to destroy the session.
// config.php now ensures session_start() is called.
include_once 'config.php';

// Unset all session variables.
// This clears the data from the $_SESSION superglobal array.
$_SESSION = array();

// If it's desired to kill the session on the client side as well,
// also delete the session cookie.
// Note: This will destroy the session cookie, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set to a time in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session on the server.
// This invalidates the session ID.
session_destroy();

// Optional: Set a message for the next page.
// To do this, a new session would need to be started after destroying the old one.
// For simplicity, we often rely on the UI change (e.g., nav bar) as logout confirmation.
/*
if (session_status() === PHP_SESSION_NONE) { // Start a new, clean session for the message
    session_start();
}
$_SESSION['message'] = "Vous avez été déconnecté avec succès.";
*/

// Redirect to the homepage or login page.
// Homepage is generally a good default after logout.
header('Location: index.php');
exit; // Crucial to prevent further script execution after redirection.
?>