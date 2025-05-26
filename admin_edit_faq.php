<?php
include_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
    header('Location: login.php');
    exit;
}

$pageTitle = "Gérer les FAQs - Admin";
$edit_id = null;
$question = '';
$answer = '';
$sort_order = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $answer = trim($_POST['answer'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $faq_id = (int)($_POST['faq_id'] ?? 0);

    if (empty($question) || empty($answer)) {
        $_SESSION['admin_error'] = "La question et la réponse sont requises.";
    } else {
        if ($faq_id > 0) {
            $sql = "UPDATE faq_items SET question = ?, answer = ?, sort_order = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $question, $answer, $sort_order, $faq_id);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "FAQ mise à jour avec succès.";
            } else {
                $_SESSION['admin_error'] = "Erreur lors de la mise à jour de la FAQ: " . $stmt->error;
            }
        } else {
            $sql = "INSERT INTO faq_items (question, answer, sort_order) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $question, $answer, $sort_order);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "FAQ ajoutée avec succès.";
            } else {
                $_SESSION['admin_error'] = "Erreur lors de l'ajout de la FAQ: " . $stmt->error;
            }
        }
        if(isset($stmt)) $stmt->close();
        header("Location: admin_edit_faq.php"); // Refresh or redirect to list
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sql = "DELETE FROM faq_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['admin_message'] = "FAQ supprimée avec succès.";
    } else {
        $_SESSION['admin_error'] = "Erreur lors de la suppression: " . $stmt->error;
    }
    $stmt->close();
    header("Location: admin_edit_faq.php");
    exit;
}


// Load FAQ for editing (GET)
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $sql = "SELECT question, answer, sort_order FROM faq_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($faq_data = $result->fetch_assoc()) {
        $question = $faq_data['question'];
        $answer = $faq_data['answer'];
        $sort_order = $faq_data['sort_order'];
    }
    $stmt->close();
}

// Fetch all FAQs for listing
$faqs = [];
$result_list = $conn->query("SELECT id, question, sort_order FROM faq_items ORDER BY sort_order ASC, id ASC");
if ($result_list) {
    while($row = $result_list->fetch_assoc()) {
        $faqs[] = $row;
    }
}

include_once 'includes/header.php';
?>
<main class="container admin-panel-page">
    <h1>Gérer les FAQs</h1>
    <p><a href="admin_panel.php">« Retour au Panneau d'Administration</a></p>

    <?php if (!empty($_SESSION['admin_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['admin_message']); unset($_SESSION['admin_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
    <?php endif; ?>

    <section class="card">
        <h2><?php echo $edit_id ? 'Modifier la FAQ' : 'Ajouter une FAQ'; ?></h2>
        <form method="POST" action="admin_edit_faq.php">
            <input type="hidden" name="faq_id" value="<?php echo $edit_id ? (int)$edit_id : 0; ?>">
            <div class="form-group">
                <label for="question">Question:</label>
                <textarea name="question" id="question" rows="3" required><?php echo htmlspecialchars($question); ?></textarea>
            </div>
            <div class="form-group">
                <label for="answer">Réponse:</label>
                <textarea name="answer" id="answer" rows="6" required><?php echo htmlspecialchars($answer); ?></textarea>
            </div>
            <div class="form-group">
                <label for="sort_order">Ordre d'affichage (plus petit = plus haut):</label>
                <input type="number" name="sort_order" id="sort_order" value="<?php echo (int)$sort_order; ?>">
            </div>
            <button type="submit" class="button-primary"><?php echo $edit_id ? 'Mettre à jour' : 'Ajouter'; ?></button>
            <?php if ($edit_id): ?>
                <a href="admin_edit_faq.php" class="button button-secondary">Annuler l'édition</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h2>Liste des FAQs Actuelles</h2>
        <?php if (!empty($faqs)): ?>
        <table class="admin-users-table"> <!-- Re-use style for now -->
            <thead><tr><th>Question</th><th>Ordre</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($faqs as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars(mb_strimwidth($item['question'], 0, 70, "...")); ?></td>
                    <td><?php echo (int)$item['sort_order']; ?></td>
                    <td>
                        <a href="admin_edit_faq.php?edit_id=<?php echo (int)$item['id']; ?>" class="button-small button-secondary">Modifier</a>
                        <a href="admin_edit_faq.php?delete_id=<?php echo (int)$item['id']; ?>" class="button-small button-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette FAQ ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Aucune FAQ pour le moment.</p>
        <?php endif; ?>
    </section>
</main>
<?php include_once 'includes/footer.php'; ?>