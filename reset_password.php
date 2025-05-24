<?php
include_once 'config.php';
$pageTitle = "Réinitialiser le Mot de Passe - eiganights";
$error = '';
$message = '';
$token_valid = false;
$token = null;
$user_id_to_reset = null;

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND is_used = 0");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($reset_request = $result->fetch_assoc()) {
            if (strtotime($reset_request['expires_at']) > time()) {
                $token_valid = true;
                $user_id_to_reset = $reset_request['user_id'];
            } else {
                $error = "Ce lien de réinitialisation a expiré. Veuillez en demander un nouveau.";
                // Optionally mark as used/expired here
                $conn->query("UPDATE password_resets SET is_used = 1 WHERE token = '" . $conn->real_escape_string($token) . "'");
            }
        } else {
            $error = "Lien de réinitialisation invalide ou déjà utilisé.";
        }
        $stmt->close();
    } else {
        $error = "Erreur système (RP01)."; error_log("Prepare failed (RP_VALIDATE_TOKEN): " . $conn->error);
    }
} else {
    $error = "Aucun token de réinitialisation fourni.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid && isset($_POST['password'], $_POST['password_confirm'])) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $submitted_token = trim($_POST['token'] ?? ''); // Ensure token from form matches URL token

    if ($submitted_token !== $token) {
        $error = "Incohérence du token. Veuillez utiliser le lien fourni.";
        $token_valid = false; // Invalidate further processing
    } elseif (empty($password) || empty($password_confirm)) {
        $error = "Veuillez entrer et confirmer votre nouveau mot de passe.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
            $error = "Erreur lors de la préparation de votre nouveau mot de passe. (RP02)";
            error_log("password_hash() failed in reset_password.");
        } else {
            $stmt_update_user = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_mark_used = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE token = ?");

            if ($stmt_update_user && $stmt_mark_used) {
                $conn->begin_transaction();
                try {
                    $stmt_update_user->bind_param("si", $hashed_password, $user_id_to_reset);
                    $stmt_update_user->execute();

                    $stmt_mark_used->bind_param("s", $token);
                    $stmt_mark_used->execute();

                    $conn->commit();
                    $message = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous <a href='login.php'>connecter</a>.";
                    $token_valid = false; // Prevent form resubmission
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $error = "Erreur lors de la mise à jour de votre mot de passe. (RP03)";
                    error_log("Password reset transaction failed: " . $exception->getMessage());
                }
                $stmt_update_user->close();
                $stmt_mark_used->close();
            } else {
                 $error = "Erreur système (RP04)."; error_log("Prepare failed for reset update: " . $conn->error);
            }
        }
    }
}

include_once 'includes/header.php';
?>
<main class="container auth-form-container">
    <h1>Réinitialiser Votre Mot de Passe</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; // Already contains HTML link ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($token_valid): ?>
    <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars(urlencode($token)); ?>" novalidate>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="form-group">
            <label for="password">Nouveau Mot de Passe:</label>
            <input type="password" id="password" name="password" required minlength="6">
            <small class="form-text">Au moins 6 caractères.</small>
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirmer le Nouveau Mot de Passe:</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <div class="form-group">
            <input type="submit" value="Réinitialiser le Mot de Passe" class="button-primary">
        </div>
    </form>
    <?php elseif (!$message && !$error): // Token was missing initially, or generic state before processing ?>
        <p>Si vous avez un lien de réinitialisation valide, veuillez l'utiliser. Sinon, <a href="forgot_password.php">demandez une nouvelle réinitialisation</a>.</p>
    <?php endif; ?>
     <?php if (!$token_valid && !$message && $error): // Only show if token invalid and no success message ?>
        <p><a href="forgot_password.php">Demander un nouveau lien de réinitialisation</a></p>
    <?php endif; ?>
</main>
<?php include_once 'includes/footer.php'; ?>