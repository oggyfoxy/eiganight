<?php
/*
 * includes/footer.php
 * Common footer for all pages.
 */
?>
</div> <!-- Closing .container .page-content (or just .container) from header.php -->

<footer class="site-footer-main">
    <div class="container footer-content"> <?php // Optional: another container if footer has a different width or background full-bleed ?>
        <p>© <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights'; ?> - Tous droits réservés.</p>
        <?php /* Add other footer links or information here if needed
        <p>
            <a href="about.php">À Propos</a> | 
            <a href="contact.php">Contact</a> | 
            <a href="privacy.php">Politique de Confidentialité</a>
        </p>
        */ ?>
    </div>
</footer>

<?php
// Optional: Close database connection if it was opened in config.php and not needed further.
// However, PHP automatically closes MySQLi connections at the end of script execution
// unless they are persistent connections. For most non-persistent connections, explicit close is good form but not strictly critical.
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Uncomment if you prefer explicit closing.
}
?>
</body>
</html>