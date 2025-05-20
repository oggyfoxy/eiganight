<?php
/*
 * admin_panel.php
 * Admin dashboard for user management.
 */
include_once 'config.php'; // Includes session_start(), $conn

// --- Admin Access Control ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
    header('Location: login.php');
    exit;
}

$pageTitle = "Panneau d'Administration - Eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];
$users = [];

// --- Fetch all users (except the admin him/herself) ---
$sql = "SELECT id, username, role, is_banned, created_at FROM users WHERE id != ? ORDER BY username ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (ADMIN_PANEL_USERS_SEL): " . $conn->error);
    $_SESSION['admin_error'] = "Erreur lors du chargement des utilisateurs. (AP01)";
} else {
    $stmt->bind_param("i", $loggedInUserId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        error_log("Execute failed (ADMIN_PANEL_USERS_SEL): " . $stmt->error);
        $_SESSION['admin_error'] = "Erreur lors de la récupération des utilisateurs. (AP02)";
    }
    $stmt->close();
}

include_once 'includes/header.php';
?>

<main class="container admin-panel-page">
    <h1>Panneau d'Administration</h1>

    <?php if (!empty($_SESSION['admin_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['admin_message']); unset($_SESSION['admin_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_warning'])): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['admin_warning']); unset($_SESSION['admin_warning']); ?></div>
    <?php endif; ?>

    <section class="user-management-section card">
        <h2>Gestion des Utilisateurs</h2>
        <?php if (!empty($users)): ?>
            <table class="admin-users-table">
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Inscrit le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <a href="view_profile.php?id=<?php echo (int)$user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($user['is_banned']): ?>
                                    <span class="status-banned">Banni</span>
                                <?php else: ?>
                                    <span class="status-active">Actif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" action="admin_action.php" class="inline-form">
                                    <input type="hidden" name="user_id_to_manage" value="<?php echo (int)$user['id']; ?>">
                                    <?php if ($user['is_banned']): ?>
                                        <input type="hidden" name="action" value="unban_user">
                                        <button type="submit" class="button-success button-small">Débannir</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="ban_user">
                                        <button type="submit" class="button-danger button-small">Bannir</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun autre utilisateur à gérer.</p>
        <?php endif; ?>
    </section>
      <hr>

    <section class="faq-management-section card">
        <h2>Gestion des FAQs</h2>
        <p><a href="admin_edit_faq.php" class="button-primary">Gérer les FAQs</a></p>
        <?php
            // Optional: Display a quick list of FAQs here
            $stmtFaqList = $conn->query("SELECT id, question FROM faq_items ORDER BY sort_order ASC LIMIT 5");
            if ($stmtFaqList && $stmtFaqList->num_rows > 0) {
                echo "<ul>";
                while ($faq_row = $stmtFaqList->fetch_assoc()) {
                    echo "<li>" . htmlspecialchars($faq_row['question']) . 
                         " (<a href='admin_edit_faq.php?edit_id=" . $faq_row['id'] . "'>Modifier</a>)</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Aucune FAQ n'a encore été ajoutée.</p>";
            }
        ?>
    </section>

    <hr>

<section class="specific-content-management card">
    <h2>Gestion de Pages Spécifiques</h2>
    <ul>
        <li>
            <a href="admin_manage_terms.php" class="button button-secondary">Modifier les Conditions d'Utilisation (fichier)</a>
        </li>
        <?php // Vous pourriez ajouter ici des liens vers d'autres pages gérées par fichier si besoin ?>
        <?php /*
        <li>
            <a href="admin_manage_privacy.php" class="button button-secondary">Modifier la Politique de Confidentialité (fichier)</a>
        </li>
        */ ?>
    </ul>
</section>

<hr>

<section class="content-management-section card">
    <h2>Gestion du Contenu du Site (via Base de Données)</h2>
    <p>Pour les autres pages de contenu stockées en base de données (Privacy Policy, etc.) :</p>
    <p><a href="admin_edit_content.php" class="button-primary">Gérer le Contenu (BDD)</a></p>
     <?php
        $stmtContentList = $conn->query("SELECT slug, title FROM site_content ORDER BY title ASC LIMIT 5");
        if ($stmtContentList && $stmtContentList->num_rows > 0) {
            echo "<ul>";
            while ($content_row = $stmtContentList->fetch_assoc()){
                // Exclure 'terms-and-conditions' si vous le gérez par fichier maintenant
                if ($content_row['slug'] !== 'terms-and-conditions') {
                     echo "<li>" . htmlspecialchars($content_row['title']) . " (" . htmlspecialchars($content_row['slug']). ")" .
                     " (<a href='admin_edit_content.php?edit_slug=" . htmlspecialchars($content_row['slug']) . "'>Modifier</a>)</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p>Aucun autre contenu de site (BDD) n'a encore été ajouté.</p>";
        }
    ?>
</section>

    <section class="content-management-section card">
        <h2>Gestion du Contenu du Site (Ex: Conditions)</h2>
        <p><a href="admin_edit_content.php" class="button-primary">Gérer le Contenu</a></p>
         <?php
            $stmtContentList = $conn->query("SELECT id, slug, title FROM site_content ORDER BY title ASC LIMIT 5");
            if ($stmtContentList && $stmtContentList->num_rows > 0) {
                echo "<ul>";
                while ($content_row = $stmtContentList->fetch_assoc()) {
                    echo "<li>" . htmlspecialchars($content_row['title']) . " (" . htmlspecialchars($content_row['slug']). ")" .
                         " (<a href='admin_edit_content.php?edit_slug=" . htmlspecialchars($content_row['slug']) . "'>Modifier</a>)</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Aucun contenu de site n'a encore été ajouté.</p>";
            }
        ?>
    </section>
</main>

<?php
include_once 'includes/footer.php';
?>
