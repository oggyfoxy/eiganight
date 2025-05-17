<?php
/*
 * faq.php
 * Displays Frequently Asked Questions.
 */
include_once 'config.php'; // Includes session_start(), $conn

$pageTitle = "FAQ - Eiganights";
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
    $stmt->close();
} else {
    error_log("Prepare failed (FAQ_SEL): " . $conn->error);
}

include_once 'includes/header.php';
?>

<main class="container static-page">
    <h1>Questions Fréquemment Posées (FAQ)</h1>

    <?php if (!empty($faqs)): ?>
        <dl class="faq-list">
            <?php foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <dt><?php echo nl2br(htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8')); ?></dt>
                    <dd><?php echo nl2br(htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8')); ?></dd>
                </div>
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
include_once 'includes/footer.php';
?>