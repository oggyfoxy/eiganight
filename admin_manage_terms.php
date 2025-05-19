<?php
/*
 * admin_manage_terms.php
 * Gère l'édition du contenu des Conditions Générales d'Utilisation stockées dans un fichier.
 */
require_once 'config.php'; // Pour session, droits admin, BASE_URL

// --- Contrôle d'accès Admin ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé. Droits admin requis.";
    header('Location: login.php');
    exit;
}

$pageTitle = "Gérer les Conditions d'Utilisation - Admin";
$contentFilePath = __DIR__ . '/content/terms_content.html'; // Chemin vers le fichier de contenu

// --- Gestion de la soumission du formulaire (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['terms_content'])) {
        $newContent = $_POST['terms_content']; // Pas de htmlspecialchars ici, on stocke du HTML
                                              // La sécurité se fait à l'affichage si nécessaire,
                                              // ou on assume que l'admin sait ce qu'il fait.
                                              // Pour un WYSIWYG, il faudrait une librairie de sanitization.

        // Écrire le nouveau contenu dans le fichier
        if (@file_put_contents($contentFilePath, $newContent) !== false) {
            $_SESSION['admin_message'] = "Conditions d'Utilisation mises à jour avec succès.";
        } else {
            // Vérifier les permissions si file_put_contents échoue
            $error_details = error_get_last();
            $permission_error_msg = isset($error_details['message']) ? $error_details['message'] : 'Vérifiez les permissions du fichier/dossier.';
            $_SESSION['admin_error'] = "Erreur lors de l'écriture du fichier des conditions. " . $permission_error_msg;
            error_log("Erreur file_put_contents pour {$contentFilePath}: " . $permission_error_msg);
        }
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: admin_manage_terms.php");
        exit;
    }
}

// --- Chargement du contenu actuel depuis le fichier ---
$currentContent = '';
if (file_exists($contentFilePath)) {
    $currentContent = @file_get_contents($contentFilePath);
    if ($currentContent === false) {
        $_SESSION['admin_warning'] = "Impossible de lire le fichier des conditions. Le fichier est peut-être inaccessible.";
        error_log("Erreur file_get_contents pour {$contentFilePath}");
        $currentContent = ''; // S'assurer que c'est une chaîne vide
    }
} else {
    $_SESSION['admin_warning'] = "Le fichier des conditions ('content/terms_content.html') n'existe pas. Il sera créé lors de la première sauvegarde.";
    // On pourrait essayer de le créer ici avec des permissions par défaut, mais file_put_contents le fera.
}

include_once 'includes/header.php';
?>

<main class="container admin-panel-page">
    <h1>Gérer les Conditions Générales d'Utilisation</h1>
    <p><a href="admin_panel.php" class="button button-secondary">« Retour au Panneau d'Administration</a></p>

    <?php if (!empty($_SESSION['admin_message'])): ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($_SESSION['admin_message']); unset($_SESSION['admin_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_warning'])): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($_SESSION['admin_warning']); unset($_SESSION['admin_warning']); ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Modifier le contenu des CGU</h2>
        <form method="POST" action="admin_manage_terms.php">
            <div class="form-group">
                <label for="terms_content_editor">Contenu (HTML autorisé - Soyez prudent !) :</label>
                <textarea name="terms_content" id="terms_content_editor" rows="20" class="terms-editor-textarea" required><?php echo htmlspecialchars($currentContent); // Échapper pour l'affichage dans le textarea ?></textarea>
                <small>Utilisez des balises HTML pour la mise en forme (par exemple, `<p>`, `<h2>`, `<ul>`, `<li>`, `<strong>`, `<br>`).</small>
            </div>
            <div class="form-group">
                <button type="submit" class="button button-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </section>
</main>

<?php include_once 'includes/footer.php'; ?>