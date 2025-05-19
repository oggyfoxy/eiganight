<?php
/*
 * terms.php
 * Affiche les Conditions Générales d'Utilisation depuis un fichier.
 */
require_once 'config.php';

$pageTitle = "Conditions Générales d'Utilisation - " . (defined('SITE_NAME') ? SITE_NAME : "Eiganights");
$termsContentFromFile = null;
$contentFilePath = __DIR__ . 'terms_content.html'; // Même chemin que dans admin_manage_terms.php
$fetch_error_file = null;

if (file_exists($contentFilePath)) {
    $termsContentFromFile = @file_get_contents($contentFilePath);
    if ($termsContentFromFile === false) {
        $fetch_error_file = "Impossible de lire le fichier des conditions actuellement.";
        error_log("Erreur file_get_contents pour {$contentFilePath} dans terms.php");
        $termsContentFromFile = '<p>Le contenu des conditions d\'utilisation n\'a pas pu être chargé.</p>'; // Contenu de secours
    }
} else {
    $fetch_error_file = "Le fichier des conditions d'utilisation est introuvable.";
    error_log("Fichier {$contentFilePath} non trouvé dans terms.php");
    $termsContentFromFile = '<p>Les conditions générales d\'utilisation ne sont pas encore disponibles.</p>'; // Contenu de secours
}

// Utiliser un titre par défaut si celui du fichier n'est pas géré ici (puisqu'on ne lit pas de titre du fichier)
$termsDisplayTitle = "Conditions Générales d'Utilisation";


include_once 'includes/header.php';
?>

<main class="container static-page terms-page">
    <h1><?php echo htmlspecialchars($termsDisplayTitle); ?></h1>

    <?php if ($fetch_error_file): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($fetch_error_file); ?></div>
    <?php endif; ?>

    <?php // Le contenu est déjà du HTML, donc on l'affiche directement.
          // La sécurité (contre XSS par exemple) doit être assurée par l'admin qui édite le contenu.
    ?>
    <article class="terms-content-container card">
        <?php echo $termsContentFromFile; ?>
    </article>
</main>

<?php
include_once 'includes/footer.php';
?>