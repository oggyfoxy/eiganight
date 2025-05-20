<?php
/*
 * includes/footer.php
 * Common footer for all pages.
 */
?>
</div> <!-- Closing .container .page-content (or just .container) from header.php -->

<footer class="site-footer-main">
    <div class="container footer-content">
        <p>© <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'EigaNights'; ?> - Tous droits réservés.</p>
        <nav class="footer-nav" aria-label="Navigation de pied de page">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>faq.php">FAQ</a></li>
                <li><a href="<?php echo BASE_URL; ?>terms.php">Conditions d'Utilisation</a></li>

                <?php
                // Vérifier si l'utilisateur est connecté pour afficher "Gérer les CGU" AVANT "Contactez-nous"
                if (isset($_SESSION['user_id'])) {
                    // Lien vers la gestion des CGU pour TOUS les utilisateurs connectés
                    // La page admin_manage_terms.php elle-même vérifiera les droits d'admin
                    echo '<li><span class="footer-nav-separator"></span> <a href="' . BASE_URL . 'admin_manage_terms.php" class="admin-link-footer">Gérer les CGU</a></li>';
                }
                ?>
                
                <li><span class="footer-nav-separator"></span> <a href="<?php echo BASE_URL; ?>contact.php">Contactez-nous</a></li>

                <?php
                // Si vous voulez toujours un lien vers le panneau admin uniquement pour les admins,
                // vous pouvez l'ajouter ici, après "Contactez-nous" ou où vous le souhaitez.
                if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    // echo '<li><span class="footer-nav-separator">|</span> <a href="' . BASE_URL . 'admin_panel.php" class="admin-link-footer">Panneau Admin</a></li>';
                }
                ?>
            </ul>
        </nav>
    </div>
</footer>

<?php
// Optionnel: Fermeture de la connexion DB
// if (isset($conn) && $conn instanceof mysqli) {
//     // $conn->close();
// }
?>
</body>
</html>