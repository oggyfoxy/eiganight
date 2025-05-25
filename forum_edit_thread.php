<?php
// forum_edit_thread.php
include_once 'config.php';
include_once 'includes/functions.php'; // For CSRF

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour modifier une discussion.";
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
$loggedInUserId = (int)$_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['forum_error'] = "ID de discussion invalide pour l'édition.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$thread_id = (int)$_GET['id'];

// Fetch thread details to edit, and verify ownership or admin role
$thread_data = null;
$sql_fetch = "SELECT user_id, title, initial_post_content, movie_id, movie_title, scene_start_time, scene_end_time, scene_description_short 
              FROM forum_threads 
              WHERE id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
if (!$stmt_fetch) {
    error_log("Prepare Error (EDIT_THREAD_FETCH): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système (ET01).";
    header('Location: ' . BASE_URL . 'forum.php'); exit;
}
$stmt_fetch->bind_param("i", $thread_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
$thread_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();

if (!$thread_data) {
    $_SESSION['forum_error'] = "Discussion non trouvée pour l'édition.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}

// Authorization check: Only author or admin can edit
$is_author = ($loggedInUserId === (int)$thread_data['user_id']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$is_author && !$is_admin) {
    $_SESSION['forum_error'] = "Vous n'êtes pas autorisé à modifier cette discussion.";
    header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $thread_id);
    exit;
}

$pageTitle = "Modifier la Discussion: " . htmlspecialchars($thread_data['title']) . " - Eiganights";

// Initialize form data with existing thread data
$form_data = [
    'thread_title' => $thread_data['title'],
    'initial_post' => $thread_data['initial_post_content'],
    'scene_start_time' => $thread_data['scene_start_time'] ?? '',
    'scene_end_time' => $thread_data['scene_end_time'] ?? '',
    'scene_description_short' => $thread_data['scene_description_short'] ?? ''
    // Movie ID and title are not editable here, just shown for context
];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = "Erreur de sécurité (jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } else {
        $threadTitle = trim($_POST['thread_title'] ?? '');
        $initialPost = trim($_POST['initial_post'] ?? '');
        $sceneStartTime = trim($_POST['scene_start_time'] ?? '');
        $sceneEndTime = trim($_POST['scene_end_time'] ?? '');
        $sceneDescriptionShort = trim($_POST['scene_description_short'] ?? '');

        // Update form_data for sticky form
        $form_data['thread_title'] = $threadTitle;
        $form_data['initial_post'] = $initialPost;
        $form_data['scene_start_time'] = $sceneStartTime;
        $form_data['scene_end_time'] = $sceneEndTime;
        $form_data['scene_description_short'] = $sceneDescriptionShort;

        if (empty($threadTitle)) {
            $error_message = "Le titre de la discussion est requis.";
        } elseif (mb_strlen($threadTitle) > 255) {
            $error_message = "Le titre ne doit pas dépasser 255 caractères.";
        } elseif (empty($initialPost)) {
            $error_message = "Le contenu initial de la discussion est requis.";
        }

        if (empty($error_message)) {
            $sql_update = "UPDATE forum_threads SET 
                                title = ?, 
                                initial_post_content = ?,
                                scene_start_time = ?,
                                scene_end_time = ?,
                                scene_description_short = ?,
                                updated_at = NOW() 
                           WHERE id = ? AND (user_id = ? OR ? = 'admin')"; // Double check auth
            
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                error_log("Prepare Error (EDIT_THREAD_UPDATE): " . $conn->error);
                $error_message = "Erreur système (ET02).";
            } else {
                $sceneStartTimeDb = !empty($sceneStartTime) ? $sceneStartTime : null;
                $sceneEndTimeDb = !empty($sceneEndTime) ? $sceneEndTime : null;
                $sceneDescriptionShortDb = !empty($sceneDescriptionShort) ? $sceneDescriptionShort : null;
                $current_user_role_for_query = $_SESSION['role'] ?? 'user';

                $stmt_update->bind_param("sssssisi", 
                    $threadTitle, $initialPost,
                    $sceneStartTimeDb, $sceneEndTimeDb, $sceneDescriptionShortDb,
                    $thread_id, $loggedInUserId, $current_user_role_for_query
                );

                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        $_SESSION['forum_message'] = "Discussion mise à jour avec succès.";
                    } else {
                        // This can happen if nothing actually changed or auth failed at DB level (though PHP check should prevent it)
                        $_SESSION['forum_warning'] = "Aucune modification détectée ou autorisation refusée au niveau de la base de données.";
                    }
                    unset($_SESSION['csrf_token']);
                    header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $thread_id);
                    exit;
                } else {
                    error_log("Execute Error (EDIT_THREAD_UPDATE): " . $stmt_update->error);
                    $error_message = "Erreur lors de la mise à jour de la discussion (ET03).";
                }
                $stmt_update->close();
            }
        }
    }
    if(!empty($error_message)) {
        unset($_SESSION['csrf_token']); // Regenerate token on error
    }
}


include_once 'includes/header.php';
?>
<main class="container create-thread-page"> <!-- Re-use class for similar form styling -->
    <h1>Modifier la Discussion</h1>
    <p><a href="<?php echo BASE_URL . 'forum_view_thread.php?id=' . $thread_id; ?>">« Retour à la discussion</a></p>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <div class="card" style="margin-bottom: 20px; padding: 15px; background-color: #2a2a2a;">
        <p><strong>Film Associé:</strong> <?php echo htmlspecialchars($thread_data['movie_title'], ENT_QUOTES, 'UTF-8'); ?>
           (<a href="<?php echo BASE_URL . 'movie_details.php?id=' . (int)$thread_data['movie_id']; ?>" target="_blank">Voir détails</a>)
        </p>
        <small>Le film associé ne peut pas être modifié.</small>
    </div>


    <form method="POST" action="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo $thread_id; ?>" class="card" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

        <fieldset class="scene-details-fieldset card">
            <legend>Détails de la Scène (Optionnel)</legend>
            <div class="form-group">
                <label for="scene_start_time">Début de la scène (ex: 00:45:12 ou 2712s):</label>
                <input type="text" name="scene_start_time" id="scene_start_time" value="<?php echo htmlspecialchars($form_data['scene_start_time'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes">
            </div>
            <div class="form-group">
                <label for="scene_end_time">Fin de la scène (ex: 00:46:00 ou 2760s):</label>
                <input type="text" name="scene_end_time" id="scene_end_time" value="<?php echo htmlspecialchars($form_data['scene_end_time'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes (optionnel)">
            </div>
            <div class="form-group">
                <label for="scene_description_short">Brève description de la scène:</label>
                <input type="text" name="scene_description_short" id="scene_description_short" value="<?php echo htmlspecialchars($form_data['scene_description_short'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="200" placeholder="Ex: Confrontation dans l'entrepôt">
                <small class="form-text">Max 200 caractères.</small>
            </div>
        </fieldset>

        <div class="form-group">
            <label for="thread_title">Titre de votre Discussion/Annotation:</label>
            <input type="text" name="thread_title" id="thread_title" value="<?php echo htmlspecialchars($form_data['thread_title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </div>

        <div class="form-group">
            <label for="initial_post">Message principal / Annotation :</label>
            <textarea name="initial_post" id="initial_post" rows="10" required><?php echo htmlspecialchars($form_data['initial_post'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Mettre à jour la Discussion</button>
    </form>
</main>

<?php include_once 'includes/footer.php'; ?>