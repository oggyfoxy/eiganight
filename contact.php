<?php
include_once 'config.php';

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
        $message_sent = true; 
        $_SESSION['contact_message_log'] = "Message de: {$form_data['name']} <{$form_data['email']}> Sujet: {$form_data['subject']} - {$form_data['message']}";
        $form_data = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    }
}

include_once 'includes/header.php';
?>

<main class="container auth-form-container"> <?php?>
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