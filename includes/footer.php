<?php
/*
 * includes/footer.php
 * Common footer for all pages.
 */
?>
</div> <!-- Closing .container .page-content (or just .container) from header.php -->

<footer class="site-footer-main">
    <div class="container footer-content">
        <p>© <?php echo date("Y"); ?> <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights'; ?> - Tous droits réservés.</p>
        <p>
            <a href="faq.php">FAQ</a> |
            <a href="terms.php">Conditions d'Utilisation</a> |
            <a href="contact.php">Contactez-nous</a>
        </p>
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