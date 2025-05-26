<?php
include_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
    header('Location: login.php');
    exit;
}

$pageTitle = "Gérer le Contenu du Site - Admin";
$edit_slug = null;
$content_title = '';
$content_body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_slug = trim($_POST['content_slug'] ?? '');
    $content_title = trim($_POST['content_title'] ?? '');
    $content_body = $_POST['content_body'] ?? '';

    if (empty($content_slug) || empty($content_title) || empty($content_body)) {
        $_SESSION['admin_error'] = "Le slug, le titre et le contenu sont requis.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM site_content WHERE slug = ?");
        $stmt_check->bind_param("s", $content_slug);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $sql = "UPDATE site_content SET title = ?, content = ? WHERE slug = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $content_title, $content_body, $content_slug);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "Contenu '" . htmlspecialchars($content_title) . "' mis à jour avec succès.";
            } else {
                $_SESSION['admin_error'] = "Erreur lors de la mise à jour: " . $stmt->error;
            }
             if(isset($stmt)) $stmt->close();
        } else {
             $_SESSION['admin_error'] = "Slug de contenu non trouvé pour la mise à jour. La création de nouveau contenu via ce formulaire n'est pas supportée.";
        }
        $stmt_check->close();
        header("Location: admin_edit_content.php?edit_slug=" . urlencode($content_slug)); // Refresh
        exit;
    }
}

// Load Content for editing (GET)
if (isset($_GET['edit_slug'])) {
    $edit_slug = trim($_GET['edit_slug']);
    $sql = "SELECT title, content FROM site_content WHERE slug = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $edit_slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($content_data = $result->fetch_assoc()) {
        $content_title = $content_data['title'];
        $content_body = $content_data['content'];
    } else {
        $_SESSION['admin_error'] = "Contenu avec le slug '" . htmlspecialchars($edit_slug) . "' non trouvé.";
        // Optionally redirect or just show empty form
    }
    $stmt->close();
}

// List all site content for navigation
$site_contents = [];
$result_list_content = $conn->query("SELECT slug, title FROM site_content ORDER BY title ASC");
if($result_list_content) {
    while($row = $result_list_content->fetch_assoc()){
        $site_contents[] = $row;
    }
}


include_once 'includes/header.php';
?>
<main class="container admin-panel-page">
    <h1>Gérer le Contenu du Site</h1>
    <p><a href="admin_panel.php">« Retour au Panneau d'Administration</a></p>

    <?php if (!empty($_SESSION['admin_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['admin_message']); unset($_SESSION['admin_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>

    <section class="card">
        <h2><?php echo $edit_slug ? 'Modifier: ' . htmlspecialchars($content_title) : 'Sélectionner un contenu à modifier'; ?></h2>

        <?php if (empty($edit_slug) && !empty($site_contents)): ?>
            <p>Veuillez sélectionner un élément à modifier :</p>
            <ul>
                <?php foreach($site_contents as $sc): ?>
                    <li><a href="admin_edit_content.php?edit_slug=<?php echo urlencode($sc['slug']); ?>"><?php echo htmlspecialchars($sc['title']); ?></a></li>
                <?php endforeach; ?>
            </ul>

        <?php elseif ($edit_slug): ?>
        <form method="POST" action="admin_edit_content.php?edit_slug=<?php echo urlencode($edit_slug);?>">
            <input type="hidden" name="content_slug" value="<?php echo htmlspecialchars($edit_slug); ?>">
            <div class="form-group">
                <label for="content_title">Titre:</label>
                <input type="text" name="content_title" id="content_title" value="<?php echo htmlspecialchars($content_title); ?>" required>
            </div>
            <div class="form-group">
                <label for="content_body">Contenu (HTML autorisé - Soyez prudent):</label>
                <textarea name="content_body" id="content_body" rows="15" required><?php echo htmlspecialchars($content_body);?></textarea>
                <small>Pour les sauts de ligne, utilisez <p>paragraphes</p> ou <br>. Pour les titres, <h2>Titre</h2>, etc.</small>
            </div>
            <button type="submit" class="button-primary">Mettre à jour le Contenu</button>
             <a href="admin_edit_content.php" class="button button-secondary">Choisir un autre contenu</a>
        </form>
        <?php elseif (empty($site_contents)): ?>
             <p>Aucun contenu de site n'a été défini.</p>
        <?php endif; ?>
    </section>
</main>
<?php include_once 'includes/footer.php'; ?>