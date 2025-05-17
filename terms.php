<?php
/*
 * terms.php
 * Displays Terms and Conditions.
 */
include_once 'config.php'; // Includes session_start(), $conn

$pageTitle = "Conditions Générales d'Utilisation - Eiganights";
$termsContent = null;
$termsTitle = "Conditions Générales d'Utilisation"; // Default title

$sql = "SELECT title, content FROM site_content WHERE slug = 'terms-and-conditions' LIMIT 1";
$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $termsTitle = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
            $termsContent = $row['content']; // HTML content, display as is (ensure it's sanitized on input from admin)
        }
    } else {
        error_log("Execute failed (TERMS_SEL): " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Prepare failed (TERMS_SEL): " . $conn->error);
}

include_once 'includes/header.php';
?>

<main class="container static-page">
    <h1><?php echo $termsTitle; ?></h1>

    <?php if ($termsContent): ?>
        <article class="terms-content">
            <?php echo $termsContent; // Outputting HTML directly from DB. Ensure admin input is sanitized. ?>
        </article>
    <?php else: ?>
        <p>Les conditions générales d'utilisation ne sont pas disponibles pour le moment.</p>
        <?php if ($conn->error || (isset($stmt) && $stmt->error)): ?>
             <p class="alert alert-warning">Nous rencontrons des difficultés pour charger ce contenu actuellement.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>