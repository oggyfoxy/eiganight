<?php
include_once 'config.php'; // Defines BASE_URL and potentially SMTP constants
require 'vendor/autoload.php'; // PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$pageTitle = "Mot de Passe Oublié - eiganights";
$message = '';
$error = '';
$email_value = ''; // To repopulate email field on error

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $email_value = htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); // Repopulate

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez fournir une adresse e-mail valide.";
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND is_banned = 0");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                $user_id = $user['id'];
                $token = bin2hex(random_bytes(32));
                $expires_in_seconds = 3600; // Token expires in 1 hour
                $expires_at = date('Y-m-d H:i:s', time() + $expires_in_seconds);

                // Invalidate previous active tokens for this user
                $stmt_invalidate = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ? AND is_used = 0 AND expires_at > NOW()");
                if ($stmt_invalidate) {
                    $stmt_invalidate->bind_param("i", $user_id);
                    $stmt_invalidate->execute();
                    $stmt_invalidate->close();
                } else {
                    error_log("Prepare failed to invalidate old tokens: " . $conn->error);
                    // Non-fatal, proceed with creating new token
                }

                $stmt_insert = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("iss", $user_id, $token, $expires_at);
                    if ($stmt_insert->execute()) {
                        $reset_link = BASE_URL . "reset_password.php?token=" . urlencode($token);

                        // --- SEND EMAIL WITH PHPMAILER ---
                        $mail = new PHPMailer(true);

                        try {
                            // Server settings from config.php (ensure these are defined there!)
                            if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') ||
                                !defined('SMTP_PORT') || !defined('SMTP_FROM_EMAIL') || !defined('SMTP_FROM_NAME')) {
                                throw new Exception("Les paramètres SMTP ne sont pas configurés dans config.php.");
                            }

                            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for testing
                            $mail->isSMTP();
                            $mail->Host       = SMTP_HOST;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = SMTP_USERNAME;
                            $mail->Password   = SMTP_PASSWORD;
                            if (defined('SMTP_SECURE') && !empty(SMTP_SECURE)) {
                                if (strtoupper(SMTP_SECURE) === 'TLS') {
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                } elseif (strtoupper(SMTP_SECURE) === 'SSL') {
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                }
                            }
                            $mail->Port       = (int)SMTP_PORT;

                            //Recipients
                            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                            $mail->addAddress($email, htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'));
                            // $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME); // Optional

                            // Content
                            $mail->isHTML(false); // Set to true if you want to send HTML email
                            $mail->CharSet = 'UTF-8';
                            $mail->Subject = 'Réinitialisation de votre mot de passe eiganights';
                            
                            $email_body_text = "Bonjour " . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . ",\n\n";
                            $email_body_text .= "Pour réinitialiser votre mot de passe sur eiganights, veuillez cliquer sur le lien suivant ou le copier dans votre navigateur:\n";
                            $email_body_text .= $reset_link . "\n\n";
                            $email_body_text .= "Ce lien est valable pendant 1 heure.\n\n";
                            $email_body_text .= "Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet e-mail.\n\n";
                            $email_body_text .= "Cordialement,\nL'équipe eiganights";
                            $mail->Body = $email_body_text;
                            // If isHTML(true):
                            // $mail->Body    = "<html><body><p>" . nl2br($email_body_text) . "</p></body></html>";
                            // $mail->AltBody = $email_body_text; // For non-HTML mail clients

                            $mail->send();
                            $message = "Si un compte avec cet e-mail existe, un lien de réinitialisation vous a été envoyé. Veuillez vérifier votre boîte de réception (et votre dossier spam).";
                        } catch (Exception $e) {
                            $error = "L'e-mail n'a pas pu être envoyé. Veuillez réessayer plus tard ou contacter le support.";
                            // $error = "L'e-mail n'a pas pu être envoyé. Erreur: {$mail->ErrorInfo}. Veuillez réessayer plus tard ou contacter le support."; // More detailed error for dev
                            error_log("PHPMailer Error for {$email}: {$mail->ErrorInfo}");
                        }
                        // --- END OF PHPMAILER SENDING ---

                    } else {
                        $error = "Erreur lors de la création de la demande de réinitialisation. (FP01)";
                        error_log("Failed to insert password reset token: " . $stmt_insert->error);
                    }
                    $stmt_insert->close();
                } else {
                     $error = "Erreur système (FP02)."; error_log("Prepare failed (FP_INS_TOKEN): " . $conn->error);
                }
            } else {
                // User not found or banned, show generic message for security to prevent email enumeration
                $message = "Si un compte avec cet e-mail existe et est actif, un lien de réinitialisation vous a été envoyé. Veuillez vérifier votre boîte de réception (et votre dossier spam).";
            }
            $stmt->close();
        } else {
             $error = "Erreur système (FP03)."; error_log("Prepare failed (FP_SEL_USER): " . $conn->error);
        }
    }
}

include_once 'includes/header.php';
?>
<main class="container auth-form-container">
    <h1>Mot de Passe Oublié</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$message): // Show form only if no success message has been set (e.g. initial load, or error before trying to send mail) ?>
    <form method="POST" action="forgot_password.php" novalidate>
        <p>Veuillez entrer votre adresse e-mail. Nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
        <div class="form-group">
            <label for="email">Adresse E-mail:</label>
            <input type="email" id="email" name="email" value="<?php echo $email_value; ?>" required autofocus>
        </div>
        <div class="form-group">
            <input type="submit" value="Envoyer le lien de réinitialisation" class="button-primary">
        </div>
    </form>
    <?php endif; ?>
    <p class="auth-links"><a href="login.php">Retour à la connexion</a></p>
</main>
<?php include_once 'includes/footer.php'; ?>