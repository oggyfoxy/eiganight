<?php
/*
 * contact.php
 * Handles user contact submissions.
 */
include_once 'config.php'; // Includes session_start(), $conn

$pageTitle = "Contactez-nous - Eiganights";
$message_sent = false;
$error_message = '';
$form_data = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['subject'] = trim($_POST['subject'] ?? '');
    $form_data['message'] = trim($_POST['message'] ?? '');

    if (empty($form_data['name']) || empty($form_data['email']) || empty($form_data['subject']) || empty($form_data['message'])) {
        $error_message = "Tous les champs sont requis.";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message = "Veuillez fournir une adresse e-mail valide.";
    } else {
        // For a minimal viable product, we'll simulate sending.
        // In a real application, you would use PHP's mail() function or a library.
        // $to = "admin@eiganights.com"; // Your admin email
        // $email_subject = "Contact Eiganights: " . htmlspecialchars($form_data['subject']);
        // $email_body = "Vous avez reçu un nouveau message de la part de " . htmlspecialchars($form_data['name']) . " (" . htmlspecialchars($form_data['email']) . ").\n\n" .
        //               "Message:\n" . htmlspecialchars($form_data['message']);
        // $headers = "From: noreply@eiganights.com\r\n"; // Or use the sender's email if your server allows
        // $headers .= "Reply-To: " . htmlspecialchars($form_data['email']) . "\r\n";

        // if (mail($to, $email_subject, $email_body, $headers)) {
        //     $message_sent = true;
        // } else {
        //     $error_message = "Désolé, une erreur est survenue lors de l'envoi de votre message. Veuillez réessayer plus tard.";
        //     error_log("Contact form mail() failed.");
        // }

        // --- MVP Simulation ---
        $message_sent = true; // Simulate successful sending
        // In a real app, you might save to DB or actually send email
        $_SESSION['contact_message_log'] = "Message de: {$form_data['name']} <{$form_data['email']}> Sujet: {$form_data['subject']} - {$form_data['message']}";
        // Clear form data on success
        $form_data = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    }
}

include_once 'includes/header.php';
?>

<main class="container auth-form-container"> <?php // Re-using auth form style for simplicity ?>
    <h1>Contactez-nous</h1>

    <?php if ($message_sent): ?>
        <div class="alert alert-success">
            Merci pour votre message ! Nous vous répondrons dès que possible.
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!$message_sent): ?>
    <form method="POST" action="contact.php" novalidate>
        <div class="form-group">
            <label for="name">Votre Nom:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Votre E-mail:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
        </div>
        <div class="form-group">
            <label for="subject">Sujet:</label>
            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($form_data['subject']); ?>" required>
        </div>
        <div class="form-group">
            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="6" required><?php echo htmlspecialchars($form_data['message']); ?></textarea>
        </div>
        <div class="form-group">
            <button type="submit" class="button-primary">Envoyer le Message</button>
        </div>
    </form>
    <?php endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>