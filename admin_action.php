<?php
include_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['admin_error'] = "Action non autorisée. Droits admin requis.";
    header('Location: admin_panel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['action']) || !isset($_POST['user_id_to_manage']) ||
    !is_numeric($_POST['user_id_to_manage'])) {

    $_SESSION['admin_error'] = "Requête invalide ou données manquantes.";
    header('Location: admin_panel.php');
    exit;
}

$action = trim($_POST['action']);
$userIdToManage = (int)$_POST['user_id_to_manage'];
$loggedInAdminId = (int)$_SESSION['user_id'];

if ($userIdToManage === $loggedInAdminId) {
    $_SESSION['admin_error'] = "Vous ne pouvez pas vous bannir vous-même.";
    header('Location: admin_panel.php');
    exit;
}


switch ($action) {
    case 'ban_user':
        $sql = "UPDATE users SET is_banned = 1 WHERE id = ? AND role != 'admin'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (ADMIN_BAN): " . $conn->error);
            $_SESSION['admin_error'] = "Erreur système (ban). (AA01)";
        } else {
            $stmt->bind_param("i", $userIdToManage);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['admin_message'] = "Utilisateur banni avec succès.";
                } else {
                    $_SESSION['admin_warning'] = "L'utilisateur n'a pas pu être banni (déjà banni, admin, ou non trouvé).";
                }
            } else {
                error_log("Execute failed (ADMIN_BAN): " . $stmt->error);
                $_SESSION['admin_error'] = "Erreur lors du bannissement. (AA02)";
            }
            $stmt->close();
        }
        break;

    case 'unban_user':
        $sql = "UPDATE users SET is_banned = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
         if (!$stmt) {
            error_log("Prepare failed (ADMIN_UNBAN): " . $conn->error);
            $_SESSION['admin_error'] = "Erreur système (unban). (AA03)";
        } else {
            $stmt->bind_param("i", $userIdToManage);
            if ($stmt->execute()) {
                 if ($stmt->affected_rows > 0) {
                    $_SESSION['admin_message'] = "Utilisateur débanni avec succès.";
                } else {
                    $_SESSION['admin_warning'] = "L'utilisateur n'a pas pu être débanni (déjà actif ou non trouvé).";
                }
            } else {
                error_log("Execute failed (ADMIN_UNBAN): " . $stmt->error);
                $_SESSION['admin_error'] = "Erreur lors du débannissement. (AA04)";
            }
            $stmt->close();
        }
        break;

    default:
        $_SESSION['admin_error'] = "Action non reconnue.";
}

header('Location: admin_panel.php');
exit;
?>
