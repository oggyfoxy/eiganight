<?php
/*
 * faq.php
 * Affiche les Questions Fréquemment Posées.
 */
require_once 'config.php';

$pageTitle = "FAQ - " . (defined('SITE_NAME') ? SITE_NAME : "EigaNights");
$faqs = [];

$sql = "SELECT question, answer FROM faq_items ORDER BY sort_order ASC, id ASC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $faqs[] = $row;
        }
    } else {
        error_log("Execute failed (FAQ_SEL): " . $stmt->error);
        // Non-critical, page can still load with a message
    }
    $stmt->close(); // Close statement after use
} else {
    error_log("Prepare failed (FAQ_SEL): " . $conn->error);
}

include_once 'includes/header.php';
?>

<main class="container static-page faq-page">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php if ($fetch_error): ?>
        <div class="alert alert-danger" role="alert">
            <p>Nous rencontrons des difficultés pour charger les FAQs actuellement.</p>
            <?php if (ini_get('display_errors') && $fetch_error !== "Erreur de connexion à la base de données lors du chargement des FAQs."): // N'afficher que les erreurs de requête si display_errors est activé ?>
                <p><small>Détail pour l'administrateur : <?php echo htmlspecialchars($fetch_error); ?></small></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($faqs)): ?>
        <div class="faq-list-container">
            <?php foreach ($faqs as $index => $faq): ?>
                <details class="faq-item" <?php echo ($index === 0 && empty($fetch_error)) ? 'open' : ''; ?> >
                    <summary class="faq-question">
                        <?php echo nl2br(htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8')); ?>
                    </summary>
                    <div class="faq-answer">
                        <?php echo nl2br(htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </dl>
    <?php else: ?>
        <p>Aucune question fréquemment posée n'est disponible pour le moment. Revenez bientôt !</p>
        <?php if ($conn->error || (isset($stmt) && $stmt->error)): ?>
             <p class="alert alert-warning">Nous rencontrons des difficultés pour charger les FAQs actuellement.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
// $conn->close(); // Optional
include_once 'includes/footer.php';
?>